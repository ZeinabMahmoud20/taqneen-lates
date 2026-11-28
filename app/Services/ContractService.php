<?php

namespace App\Services;

use App\Models\Citizen;
use App\Models\Contract;
use App\Models\Installment;
use App\Models\Payment;
use App\Models\PaymentPlan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ContractService
{
    /**
     * إنشاء عقد مع جدول أقساطه.
     *
     * تاريخ استحقاق القسط رقم N = تاريخ المقدم + N سنة (القسط 1 = المقدم + سنة).
     * لو تاريخ المقدم فاضي: الأقساط بتتسجل من غير موعد استحقاق (والمتأخرات مش بتشوفها لحد ما يتضاف الموعد).
     *
     * @param array{
     *     code: string,
     *     serial_no?: int|null,
     *     down_payment_amount?: float|int|string,
     *     down_payment_date?: string|null,
     *     interest_amount?: float|int|string,
     *     total_price: float|int|string,
     *     cash_paid_at?: string|null,
     *     created_by?: int|null,
     * } $data
     * @param  array<int, float|int|string>  $installmentAmounts  قيمة كل قسط بالترتيب (بيدخلها الموظف من الإكسيل)
     * @param  list<?string>|null  $dueDates  مواعيد استحقاق صريحة (استثناء). لو null بتتحسب من تاريخ المقدم،
     *                                     ولو تاريخ المقدم فاضي بتتسجل فاضية (ويتضاف الموعد يدوي بعدين).
     */
    public function create(
        Citizen $citizen,
        PaymentPlan $plan,
        array $data,
        array $installmentAmounts = [],
        ?array $dueDates = null,
    ): Contract {
        $amounts = array_values($installmentAmounts);
        $this->validate($plan, $data, $amounts, $dueDates);

        $dueDates = $plan->is_cash ? [] : array_values($dueDates ?? $this->scheduleFromDownPayment($data, count($amounts)));

        return DB::transaction(function () use ($citizen, $plan, $data, $amounts, $dueDates) {
            $userId = $data['created_by'] ?? null;

            $contract = Contract::create([
                'code' => trim((string) $data['code']),
                'citizen_id' => $citizen->id,
                'payment_plan_id' => $plan->id,
                'serial_no' => $data['serial_no'] ?? null,
                'down_payment_amount' => $data['down_payment_amount'] ?? 0,
                'down_payment_date' => $data['down_payment_date'] ?? null,
                'interest_amount' => $data['interest_amount'] ?? 0,
                'total_price' => $data['total_price'],
                'created_by' => $userId,
            ]);

            // سجل المقدم
            if ((float) ($data['down_payment_amount'] ?? 0) > 0 && ! empty($data['down_payment_date'])) {
                Payment::create([
                    'contract_id' => $contract->id,
                    'type' => Payment::TYPE_DOWN_PAYMENT,
                    'amount' => $data['down_payment_amount'],
                    'paid_at' => $data['down_payment_date'],
                    'created_by' => $userId,
                ]);
            }

            // الكاش (سداد 100%): دفعة واحدة، مفيش أقساط
            if ($plan->is_cash) {
                if (! empty($data['cash_paid_at'])) {
                    Payment::create([
                        'contract_id' => $contract->id,
                        'type' => Payment::TYPE_CASH,
                        'amount' => $data['total_price'],
                        'paid_at' => $data['cash_paid_at'],
                        'created_by' => $userId,
                    ]);
                }

                return $contract;
            }

            foreach ($amounts as $index => $amount) {
                $contract->installments()->create([
                    'installment_no' => $index + 1,
                    'due_date' => $dueDates[$index] === null ? null : Carbon::parse($dueDates[$index])->toDateString(),
                    'amount' => $amount,
                    'status' => 'pending',
                    'created_by' => $userId,
                ]);
            }

            return $contract->load('installments');
        });
    }

    /**
     * بعد ما المستخدم يضيف تاريخ المقدم يدوي: بنحسب مواعيد الاستحقاق للأقساط اللي موعدها فاضي
     * (المقدم + N سنة). الأقساط اللي ليها موعد بالفعل مابتتلمسش.
     *
     * @return int عدد الأقساط اللي اتعبّى موعدها
     */
    public function fillMissingDueDates(Contract $contract): int
    {
        if ($contract->down_payment_date === null) {
            return 0;
        }

        $base = Carbon::parse($contract->down_payment_date)->startOfDay();
        $filled = 0;

        foreach ($contract->installments()->whereNull('due_date')->get() as $installment) {
            $installment->update([
                'due_date' => $base->copy()->addYearsNoOverflow($installment->installment_no)->toDateString(),
            ]);
            $filled++;
        }

        return $filled;
    }

    /**
     * بيخلّي سجل دفع المقدم يطابق بيانات العقد (المبلغ والتاريخ).
     * بيتنادى بعد تعديل العقد: لو المستخدم ضاف تاريخ المقدم بعد الاستيراد، سجل الدفع بيتعمل،
     * ولو عدّل المبلغ أو التاريخ، نفس السجل بيتحدّث (مش بيتعمل سجل تاني).
     * من غير مبلغ أو من غير تاريخ: مفيش حاجة تتسجل.
     */
    public function syncDownPayment(Contract $contract, ?int $userId = null): void
    {
        if ((float) $contract->down_payment_amount <= 0 || $contract->down_payment_date === null) {
            // العقد بقى كاش أو اتحذف المقدم: مفيش سجل مقدم في الحالتين
            Payment::query()
                ->where('contract_id', $contract->id)
                ->where('type', Payment::TYPE_DOWN_PAYMENT)
                ->delete();

            return;
        }

        $attributes = [
            'amount' => $contract->down_payment_amount,
            'paid_at' => Carbon::parse($contract->down_payment_date)->toDateString(),
        ];

        $payment = Payment::query()
            ->where('contract_id', $contract->id)
            ->where('type', Payment::TYPE_DOWN_PAYMENT)
            ->first();

        if ($payment) {
            $payment->update($attributes);

            return;
        }

        Payment::create($attributes + [
            'contract_id' => $contract->id,
            'type' => Payment::TYPE_DOWN_PAYMENT,
            'created_by' => $userId,
        ]);
    }

    /**
     * تحقق من تغيير نوع السداد في شاشة التعديل وحساب إجمالي الثمن الجديد.
     * الأقساط المسددة بتفضل زي ما هي ومش بتدخل في الخانات اللي بيكتبها المستخدم.
     *
     * @param  array<int, array<string, mixed>>  $rows  الأقساط غير المسددة اللي المستخدم كتب قيمتها
     * @return string إجمالي الثمن الجديد
     */
    public function prepareEditTotal(Contract $contract, PaymentPlan $plan, array $data): string
    {
        $paid = $contract->installments()->where('status', Installment::STATUS_PAID)->get();
        $rows = array_values($data['installments'] ?? []);

        if ($plan->is_cash) {
            if ($paid->isNotEmpty()) {
                $this->fail('payment_plan_id', 'لا يمكن تحويل العقد إلى كاش وفيه أقساط مسددة. ألغِ السداد أولاً.');
            }

            return number_format($this->cents($data['cash_amount'] ?? 0) / 100, 2, '.', '');
        }

        // نوع «تم سداد كامل» عدد أقساطه مش ثابت: اللي في الصف/النموذج هو المطلوب
        if (! $plan->is_completed) {
            $pendingCount = $plan->installments_count - $paid->count();

            if ($pendingCount < 0 || count($rows) !== $pendingCount) {
                $this->fail('installments', "نوع السداد ({$plan->name}) يتطلب {$plan->installments_count} قسطاً، منها {$paid->count()} مسدد. أدخل ".max(0, $pendingCount)." قسطاً غير مسدد.");
            }
        }

        foreach ($rows as $row) {
            $amount = $this->rowAmount($row);
            if ($amount === null || $amount <= 0) {
                $this->fail('installments', 'قيمة كل قسط لازم تكون رقم أكبر من صفر.');
            }
        }

        $totalCents = $this->cents($data['down_payment_amount'] ?? 0)
            + $paid->sum(fn (Installment $i): int => $this->cents($i->amount))
            + array_sum(array_map(fn (array $row): int => $this->cents($this->rowAmount($row) ?? 0), $rows));

        return number_format($totalCents / 100, 2, '.', '');
    }

    /**
     * مزامنة جدول الأقساط بعد تغيير نوع السداد:
     *  - الأقساط المسددة بتتساب تماماً (رقمها، مبلغها وموعدها).
     *  - الأقساط غير المسددة القديمة بيتحدّث مبلغها، والجديدة بيتسجل موعدها من تاريخ المقدم.
     *  - الزيادة عن العدد المطلوب بتتحذف (غير المسددة فقط).
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return int عدد الأقساط الجديدة اللي اتضافت
     */
    public function syncInstallmentsForEdit(Contract $contract, PaymentPlan $plan, array $rows, ?int $userId = null): int
    {
        $rows = $plan->is_cash ? [] : array_values($rows);

        return DB::transaction(function () use ($contract, $plan, $rows, $userId): int {
            $all = $contract->installments()->orderBy('installment_no')->get();
            $pending = $all->where('status', Installment::STATUS_PENDING)->values();

            if ($plan->is_cash) {
                foreach ($pending as $installment) {
                    $installment->delete();
                }

                return 0;
            }

            $kept = $pending->take(count($rows))->values();
            $toDelete = $pending->slice(count($rows))->values();
            $usedNumbers = $all->pluck('installment_no')->map(fn ($no): int => (int) $no)->all();
            $usedNumbers = array_diff($usedNumbers, $toDelete->pluck('installment_no')->map(fn ($no): int => (int) $no)->all());

            foreach ($kept as $index => $installment) {
                $installment->update([
                    'amount' => $this->rowAmount($rows[$index]),
                    'updated_by' => $userId,
                ]);
            }

            $created = 0;
            // في نوع «تم سداد كامل» العدد مش ثابت، فالحد الأقصى = الموجود + اللي المستخدم أضافه
            $maxNo = $plan->is_completed ? $all->count() + count($rows) : $plan->installments_count;
            for ($i = $kept->count(); $i < count($rows); $i++) {
                $number = 1;
                while ($number <= $maxNo && in_array($number, $usedNumbers, true)) {
                    $number++;
                }

                if ($number > $maxNo) {
                    $this->fail('installments', 'عدد الأقساط المطلوب أكبر من عدد الأقساط المتاح في نوع السداد.');
                }

                $contract->installments()->create([
                    'installment_no' => $number,
                    'amount' => $this->rowAmount($rows[$i]),
                    'due_date' => $this->dueDateForNumber($contract, $number),
                    'status' => Installment::STATUS_PENDING,
                    'created_by' => $userId,
                ]);

                $usedNumbers[] = $number;
                $created++;
            }

            foreach ($toDelete as $installment) {
                $installment->delete();
            }

            return $created;
        });
    }

    /** سجل دفع الكاش يطابق بيانات العقد بعد التعديل (ويتحذف لو العقد بقى مقسّط). */
    public function syncCashPayment(Contract $contract, ?string $paidAt, ?int $userId = null): void
    {
        $payment = Payment::query()
            ->where('contract_id', $contract->id)
            ->where('type', Payment::TYPE_CASH)
            ->first();

        $isCash = (bool) PaymentPlan::query()->whereKey($contract->payment_plan_id)->value('is_cash');

        if (! $isCash) {
            $payment?->delete();

            return;
        }

        if ((float) $contract->total_price <= 0 || blank($paidAt)) {
            return;
        }

        $attributes = [
            'amount' => $contract->total_price,
            'paid_at' => Carbon::parse($paidAt)->toDateString(),
        ];

        if ($payment) {
            $payment->update($attributes);

            return;
        }

        Payment::create($attributes + [
            'contract_id' => $contract->id,
            'type' => Payment::TYPE_CASH,
            'created_by' => $userId,
        ]);
    }

    private function dueDateForNumber(Contract $contract, int $number): ?string
    {
        if ($contract->down_payment_date === null) {
            return null;
        }

        return Carbon::parse($contract->down_payment_date)->startOfDay()
            ->addYearsNoOverflow($number)
            ->toDateString();
    }

    private function rowAmount(mixed $row): ?float
    {
        $amount = is_array($row) ? ($row['amount'] ?? null) : $row;

        return is_numeric($amount) ? (float) $amount : null;
    }

    /** @return list<?string> */
    private function scheduleFromDownPayment(array $data, int $count): array
    {
        // من غير تاريخ مقدم مفيش حساب للاستحقاق: بنسيبه فاضي (مش خطأ)
        if (empty($data['down_payment_date'])) {
            return array_fill(0, $count, null);
        }

        $base = Carbon::parse($data['down_payment_date'])->startOfDay();

        // addYearsNoOverflow من التاريخ الأصلي كل مرة: 29 فبراير مايتحولش لمارس
        return array_map(
            fn (int $n): string => $base->copy()->addYearsNoOverflow($n)->toDateString(),
            range(1, $count),
        );
    }

    private function validate(PaymentPlan $plan, array $data, array $amounts, ?array $dueDates): void
    {
        $code = trim((string) ($data['code'] ?? ''));
        if ($code === '') {
            $this->fail('code', 'كود العقد مطلوب.');
        }
        if (Contract::query()->where('code', $code)->exists()) {
            $this->fail('code', 'كود العقد مستخدم بالفعل.');
        }

        if ($plan->is_cash) {
            if ($amounts !== []) {
                $this->fail('installments', 'عقد الكاش لا يحتوي على أقساط.');
            }

            return;
        }

        if ($plan->is_completed && $amounts === []) {
            $this->fail('installments', 'العقد «تم سداد كامل ثمن الأرض» لازم فيه قسط واحد على الأقل.');
        }

        if (! $plan->is_completed && count($amounts) !== $plan->installments_count) {
            $this->fail('installments', "نوع السداد ({$plan->name}) يتطلب {$plan->installments_count} أقساط بالظبط.");
        }

        foreach ($amounts as $amount) {
            if (! is_numeric($amount) || (float) $amount <= 0) {
                $this->fail('installments', 'قيمة كل قسط لازم تكون رقم أكبر من صفر.');
            }
        }

        if ($dueDates !== null && count($dueDates) !== count($amounts)) {
            $this->fail('due_dates', 'عدد مواعيد الاستحقاق لازم يساوي عدد الأقساط.');
        }

        // إجمالي الثمن = المقدم + مجموع الأقساط (نفس معادلة الإكسيل)
        $expectedCents = $this->cents($data['down_payment_amount'] ?? 0)
            + array_sum(array_map(fn ($a) => $this->cents($a), $amounts));

        if ($expectedCents !== $this->cents($data['total_price'] ?? 0)) {
            $expected = number_format($expectedCents / 100, 2, '.', '');
            $this->fail('total_price', "إجمالي الثمن لا يساوي المقدم + مجموع الأقساط ({$expected}).");
        }
    }

    private function cents(float|int|string $value): int
    {
        return (int) round(((float) $value) * 100);
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
