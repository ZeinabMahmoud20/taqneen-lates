<?php

namespace App\Services;

use App\Models\Center;
use App\Models\Citizen;
use App\Models\Contract;
use App\Models\Installment;
use App\Models\InstallmentNote;
use App\Models\PaymentPlan;
use App\Services\Excel\ArrearsSheetParser;
use App\Services\Excel\ImportFormatException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * استيراد العقود والأقساط من إكسيل (قالب النظام).
 * - كل صف في transaction لوحده: الصف الغلط مايوقفش الباقي ومايسيبش بيانات نصف مكتملة.
 * - وضع التحقق (dryRun): بينفّذ كل حاجة جوه transaction كبيرة وبيرجّعها في الآخر.
 */
class ArrearsImporter
{
    public function __construct(
        private ArrearsSheetParser $parser,
        private ContractService $contracts,
        private InstallmentPaymentService $payments,
    ) {
    }

    /** @throws ImportFormatException */
    public function import(string $path, bool $dryRun = false, ?int $userId = null): ImportResult
    {
        $rows = $this->parser->parse($path);
        $result = new ImportResult($dryRun);

        if ($dryRun) {
            DB::beginTransaction();
        }

        try {
            foreach ($rows as $row) {
                $result->total++;

                if ($row['errors'] !== []) {
                    $result->addError($row['row'], $row['code'], implode('، ', $row['errors']));
                    continue;
                }

                try {
                    $stats = DB::transaction(fn () => $this->importRow($row, $userId));
                } catch (ValidationException $e) {
                    $result->addError($row['row'], $row['code'], collect($e->errors())->flatten()->implode('، '));
                    continue;
                } catch (Throwable $e) {
                    report($e);
                    $result->addError($row['row'], $row['code'], 'خطأ غير متوقع: ' . $e->getMessage());
                    continue;
                }

                $result->contracts++;
                $result->paidInstallments += $stats['paid'];
                $stats['citizen_created'] ? $result->citizensCreated++ : $result->citizensReused++;

                foreach ($stats['warnings'] as $warning) {
                    $result->addWarning($row['row'], $row['code'], $warning);
                }
            }
        } finally {
            if ($dryRun) {
                DB::rollBack();
            }
        }

        return $result;
    }

    /** @return array{citizen_created: bool, paid: int, warnings: list<string>} */
    private function importRow(array $row, ?int $userId): array
    {
        $stats = ['citizen_created' => false, 'paid' => 0, 'warnings' => $row['warnings']];

        if (Contract::query()->where('code', $row['code'])->exists()) {
            $this->fail('code', 'الكود موجود بالفعل في النظام.');
        }

        $byNo = collect($row['installments'])->keyBy('no');
        $amounts = [];
        $dueDates = null;

        if ($row['plan_kind'] === 'cash') {
            // «سداد 100%»: دفعة واحدة، والمقدم = الإجمالي
            $plan = PaymentPlan::query()->where('is_cash', true)->first();
            if (! $plan) {
                $this->fail('type', 'نوع السداد كاش غير موجود في النظام (شغّل PaymentPlanSeeder).');
            }
            if ($byNo->isNotEmpty()) {
                $this->fail('installments', 'العقد مكتوب سداد 100% لكن الصف فيه أقساط.');
            }
            if ($row['down_amount'] <= 0) {
                $this->fail('down_amount', 'مبلغ الدفعة (المبلغ المدفوع كاش) مطلوب.');
            }
            if ($row['down_date'] === null) {
                $stats['warnings'][] = 'تاريخ الدفع فاضي، العقد اتسجل من غير سجل دفع.';
            }

            $computedTotal = $row['down_amount'];
            $data = [
                'down_payment_amount' => 0,
                'down_payment_date' => null,
                'total_price' => $this->money($computedTotal),
                'cash_paid_at' => $row['down_date']?->format('Y-m-d'),
            ];
        } else {
            // «تم سداد كامل ثمن الأرض»: نوع السداد بيتحدد من عدد الأقساط المكتوبة
            if ($row['plan_kind'] === 'completed') {
                $written = $byNo->filter(fn ($i) => $i['amount'] !== null)->count();
                $plan = PaymentPlan::query()->where('is_cash', false)->where('installments_count', $written)->first();
                if (! $plan) {
                    $this->fail('installments', "عدد الأقساط ({$written}) مش بيطابق أي نوع سداد (3 أو 5 أو 7 سنوات).");
                }
            } else {
                $plan = PaymentPlan::query()->where('is_cash', false)->where('installments_count', $row['plan_years'])->first();
                if (! $plan) {
                    $this->fail('type', 'نوع السداد غير موجود في النظام (شغّل PaymentPlanSeeder).');
                }
            }

            $count = $plan->installments_count;

            foreach ($byNo as $no => $installment) {
                if ($no > $count) {
                    $this->fail('installments', "الصف فيه القسط رقم {$no} لكن نوع السداد ({$plan->name}) فيه {$count} أقساط فقط.");
                }
            }

            // مبلغ القسط لازم يكون موجود: مبلغ مجهول مينفعش نخمنه
            for ($no = 1; $no <= $count; $no++) {
                $amount = $byNo->get($no)['amount'] ?? null;
                if ($amount === null || $amount <= 0) {
                    $this->fail('installments', "قيمة القسط رقم {$no} ناقصة أو غير صحيحة.");
                }
                $amounts[] = $amount;
            }

            // البيانات الناقصة بتتسجل بتنبيه، مش رفض
            $unpaid = [];    // لسه ماتدفعتش
            $undated = [];   // «تم سداد كامل» لكن من غير تاريخ سداد
            for ($no = 1; $no <= $count; $no++) {
                if (($byNo->get($no)['paid_at'] ?? null) === null) {
                    if ($row['plan_kind'] === 'completed') {
                        $undated[] = $no;
                    } else {
                        $unpaid[] = $no;
                    }
                }
            }

            if ($undated !== []) {
                $stats['warnings'][] = 'العقد مكتوب «تم سداد كامل» لكن مفيش تاريخ سداد للأقساط رقم ('
                    . implode('، ', $undated) . '). اتسجلت مسددة من غير تاريخ.';
            }

            if ($row['down_amount'] <= 0) {
                $stats['warnings'][] = 'مفيش مبلغ مقدم في الصف.';
            }

            if ($row['down_date'] === null) {
                // من غير تاريخ المقدم مفيش حساب للاستحقاق: الأقساط بتتسجل من غير موعد ويتضاف يدوي
                $dueDates = array_fill(0, $count, null);

                if ($row['down_amount'] > 0) {
                    $stats['warnings'][] = 'تاريخ المقدم فاضي: المقدم اتسجل في العقد من غير سجل دفع.';
                }

                $stats['warnings'][] = $unpaid !== []
                    ? 'تاريخ المقدم فاضي والأقساط رقم (' . implode('، ', $unpaid)
                        . ') لسه ماتدفعتش: اتسجلت من غير موعد استحقاق ومش هتظهر في المتأخرات لحد ما يتضاف الموعد يدوي.'
                    : 'تاريخ المقدم فاضي: مواعيد الاستحقاق اتسجلت فاضية.';
            }

            $computedTotal = $row['down_amount'] + array_sum($amounts);
            $data = [
                'down_payment_amount' => $this->money($row['down_amount']),
                'down_payment_date' => $row['down_date']?->format('Y-m-d'),
                'total_price' => $this->money($computedTotal),
            ];
        }

        if ($row['total'] !== null && abs($row['total'] - $computedTotal) > 0.5) {
            $stats['warnings'][] = 'إجمالي الإكسيل (' . number_format($row['total'], 2, '.', '')
                . ') مختلف عن المحسوب (' . number_format($computedTotal, 2, '.', '') . ') = المقدم + الأقساط. اتسجل المحسوب.';
        }

        $citizen = $this->resolveCitizen($row, $userId, $stats);

        $contract = $this->contracts->create(
            $citizen,
            $plan,
            $data + [
                'code' => $row['code'],
                'serial_no' => $row['serial'],
                'interest_amount' => $this->money($row['interest']),
                'created_by' => $userId,
            ],
            $amounts,
            $dueDates,
        );

        // الأقساط اللي ليها تاريخ سداد = اتدفعت. «تم سداد كامل» من غير تاريخ = مسدد وتاريخه مجهول
        foreach ($contract->installments as $model) {
            $paidAt = $byNo->get($model->installment_no)['paid_at'] ?? null;

            if ($paidAt !== null) {
                $this->payments->pay($model, Carbon::instance($paidAt), null, $userId);
                $stats['paid']++;
            } elseif ($row['plan_kind'] === 'completed') {
                $model->update([
                    'paid_amount' => $model->amount,
                    'status' => Installment::STATUS_PAID,
                    'updated_by' => $userId,
                ]);
                $stats['paid']++;
            }
        }

        // ملاحظة الصف تتسجل على أول قسط لسه ماتسددش (أو آخر قسط)
        if ($row['notes'] !== '') {
            $installments = $contract->installments()->get();
            $target = $installments->firstWhere('status', 'pending') ?? $installments->last();

            if ($target) {
                InstallmentNote::create(['installment_id' => $target->id, 'user_id' => $userId, 'note' => $row['notes']]);
            } else {
                $stats['warnings'][] = 'الملاحظة ماتسجلتش لأن عقد الكاش مفيهوش أقساط.';
            }
        }

        return $stats;
    }

    private function resolveCitizen(array $row, ?int $userId, array &$stats): Citizen
    {
        $center = $row['center'] !== '' ? Center::firstOrCreate(['name' => $row['center']]) : null;

        if ($row['national_id'] !== '') {
            $citizen = Citizen::query()->where('national_id', $row['national_id'])->first();
        } else {
            // نفس المواطن ممكن يظهر بأكواد مختلفة: بنطابق بالاسم + المركز
            $matches = Citizen::query()
                ->where('name', $row['name'])
                ->when(
                    $center,
                    fn ($q) => $q->where('center_id', $center->id),
                    fn ($q) => $q->whereNull('center_id'),
                )
                ->get();

            if ($matches->count() > 1) {
                $stats['warnings'][] = 'فيه أكتر من مواطن بنفس الاسم والمركز، فاتسجل كمواطن جديد. راجع التكرار أو اكتب الرقم القومي.';
                $citizen = null;
            } else {
                $citizen = $matches->first();
            }
        }

        if (! $citizen) {
            $citizen = Citizen::create([
                'name' => $row['name'],
                'national_id' => $row['national_id'] !== '' ? $row['national_id'] : null,
                'center_id' => $center?->id,
                'created_by' => $userId,
            ]);
            $stats['citizen_created'] = true;
        }

        // تليفونات المواطن
        $existing = $citizen->phones()->pluck('phone')->all();
        foreach ($row['citizen_phones'] as $phone) {
            if (in_array($phone, $existing, true)) {
                continue;
            }
            $citizen->phones()->create(['phone' => $phone, 'is_primary' => $existing === []]);
            $existing[] = $phone;
        }

        // النائب
        if ($row['rep_name'] !== '') {
            $current = $citizen->representatives()->whereNull('ended_at')->latest('id')->first();

            if ($current && $current->name === $row['rep_name']) {
                $rep = $current;
                if ($rep->relationship === null && $row['rep_relationship'] !== '') {
                    $rep->update(['relationship' => $row['rep_relationship']]);
                }
            } else {
                if ($current) {
                    $current->update(['ended_at' => today()]);
                    $stats['warnings'][] = "النائب اتغيّر من ({$current->name}) إلى ({$row['rep_name']}).";
                }

                $rep = $citizen->representatives()->create([
                    'name' => $row['rep_name'],
                    'relationship' => $row['rep_relationship'] !== '' ? $row['rep_relationship'] : null,
                    'started_at' => today(),
                ]);
            }

            $repPhones = $rep->phones()->pluck('phone')->all();
            foreach ($row['rep_phones'] as $phone) {
                if (in_array($phone, $repPhones, true)) {
                    continue;
                }
                $rep->phones()->create(['phone' => $phone, 'is_primary' => $repPhones === []]);
                $repPhones[] = $phone;
            }
        }

        return $citizen;
    }

    private function money(float|int $value): string
    {
        return number_format(round((float) $value, 2), 2, '.', '');
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
