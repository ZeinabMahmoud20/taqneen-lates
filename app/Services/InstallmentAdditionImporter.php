<?php

namespace App\Services;

use App\Models\Citizen;
use App\Models\Contract;
use App\Models\Installment;
use App\Services\Excel\ImportFormatException;
use App\Services\Excel\InstallmentAdditionSheetParser;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * تسجيل سداد الأقساط وتاريخ الأذن من ملف Excel على عقود موجودة.
 *
 * - «تاريخ الإصدار» = تاريخ السداد الفعلي، و«المبلغ» = قيمة القسط المدفوعة.
 * - مبلغ التقرير هو المرجع: لو مختلف عن المسجل، بيتعدل القسط وإجمالي العقد بالفرق.
 * - القسط بيتحول لمسدد (Payment + paid_at + status=paid) ويختفي من المتأخرات.
 * - كل صف في transaction لوحده: الصف الغلط مايوقفش الباقي ومايسيبش بيانات نصف مكتملة.
 * - وضع التحقق (dryRun): بينفّذ كل حاجة جوه transaction كبيرة وبيرجّعها في الآخر.
 */
class InstallmentAdditionImporter
{
    public const LOG_NAME = 'installment_additions';

    public function __construct(
        private InstallmentAdditionSheetParser $parser,
        private InstallmentPaymentService $payments,
    ) {}

    /** @throws ImportFormatException */
    public function import(string $path, bool $dryRun = false, ?int $userId = null): InstallmentAdditionResult
    {
        $rows = $this->parser->parse($path);
        $result = new InstallmentAdditionResult($dryRun);

        if ($dryRun) {
            DB::beginTransaction();
        }

        try {
            foreach ($rows as $row) {
                $result->total++;
                $code = $this->rowCode($row);

                if ($row['errors'] !== []) {
                    $result->addError($row['row'], $code, implode('، ', $row['errors']));

                    continue;
                }

                if ($row['amount'] === null && $row['permit_date'] === null && $row['issue_date'] === null) {
                    $result->skipped++;
                    $this->warnings($result, $row, $code, [
                        'الصف مفيهوش مبلغ ولا تاريخ إصدار ولا تاريخ أذن — اتجاهل.',
                    ]);

                    continue;
                }

                try {
                    $stats = DB::transaction(fn () => $this->importRow($row, $userId));
                } catch (ValidationException $e) {
                    $result->addError($row['row'], $code, collect($e->errors())->flatten()->implode('، '));

                    continue;
                } catch (Throwable $e) {
                    report($e);
                    $result->addError($row['row'], $code, 'خطأ غير متوقع: '.$e->getMessage());

                    continue;
                }

                $this->warnings($result, $row, $code, $stats['warnings']);

                if (! $stats['applied']) {
                    $result->skipped++;

                    continue;
                }

                $result->applied++;
                $result->paidInstallments += $stats['paid'] ? 1 : 0;
                $result->paidTotal += $stats['amount_paid'] ?? 0.0;
                $result->permitsSaved += $stats['permit'] ? 1 : 0;
            }
        } finally {
            if ($dryRun) {
                DB::rollBack();
            }
        }

        return $result;
    }

    /**
     * @return array{applied: bool, paid: bool, amount_paid: ?float, permit: bool, warnings: list<string>}
     */
    private function importRow(array $row, ?int $userId): array
    {
        $warnings = [];

        $contract = $this->findContract($row, $warnings);
        $this->assertContractHasInstallments($contract);

        $target = $this->findTargetInstallment($contract, $row, $warnings);

        if ($target === null) {
            return ['applied' => false, 'paid' => false, 'amount_paid' => null, 'permit' => false, 'warnings' => $warnings];
        }

        $issueDate = $row['issue_date'];
        $permitDate = $row['permit_date'];
        $amount = $row['amount'];

        // مفيش تاريخ إصدار = مفيش سداد. بنسجل تاريخ الأذن بس لو موجود.
        if ($issueDate === null) {
            if ($permitDate === null) {
                $warnings[] = 'الصف فيه مبلغ لكن مفيهوش «تاريخ الإصدار» — مينفعش نسجل سداد، والمبلغ اتجاهل.';

                return ['applied' => false, 'paid' => false, 'amount_paid' => null, 'permit' => false, 'warnings' => $warnings];
            }

            if ($amount !== null) {
                $warnings[] = 'الصف فيه مبلغ لكن مفيهوش «تاريخ الإصدار» — المبلغ اتجاهل، واتسجل تاريخ الأذن فقط.';
            }

            $target->permit_date = $permitDate->toDateString();
            $target->updated_by = $userId;
            $target->save();

            $this->log($target, $contract, $row, 'installment_permit', [
                'permit_date' => $permitDate->toDateString(),
            ], $userId, 'تسجيل تاريخ أذن للقسط من ملف Excel');

            return ['applied' => true, 'paid' => false, 'amount_paid' => null, 'permit' => true, 'warnings' => $warnings];
        }

        if ($permitDate !== null && $target->due_date !== null && $permitDate->lt($target->due_date)) {
            $warnings[] = 'تاريخ الأذن ('.$permitDate->format('Y-m-d')
                .') قبل تاريخ استحقاق القسط ('.$target->due_date->format('Y-m-d')
                .') — تم تسجيل السداد مع التنبيه.';
        }

        $oldAmount = (float) $target->amount;
        $newAmount = $amount ?? $oldAmount;
        $delta = round($newAmount - $oldAmount, 2);

        if ($amount === null) {
            $warnings[] = 'مفيش مبلغ في التقرير — اتسدد القسط بقيمته المسجلة ('.number_format($oldAmount, 2, '.', '').').';
        } elseif ($delta !== 0.0) {
            $warnings[] = 'قيمة القسط اتعدلت من '.number_format($oldAmount, 2, '.', '')
                .' إلى '.number_format($newAmount, 2, '.', '')
                .' حسب مبلغ التقرير، واتعدل إجمالي العقد بالفرق.';
        }

        if ($amount !== null) {
            $target->amount = $newAmount;
        }

        if ($permitDate !== null) {
            $target->permit_date = $permitDate->toDateString();
        }

        $target->updated_by = $userId;
        $target->save();

        if ($delta !== 0.0) {
            $contract->total_price = round((float) $contract->total_price + $delta, 2);
            $contract->updated_by = $userId;
            $contract->save();
        }

        // السداد بيتم بقيمة القسط بعد التعديل، وبيحول القسط لمسدد ويخرجه من المتأخرات.
        $payment = $this->payments->pay($target, $issueDate, null, $userId);

        $this->log($target, $contract, $row, 'installment_payment', [
            'paid_at' => $issueDate->toDateString(),
            'amount_paid' => (float) $payment->amount,
            'old_amount' => $oldAmount,
            'amount_changed' => $delta,
            'permit_date' => $permitDate?->toDateString(),
        ], $userId, 'تسجيل سداد قسط من ملف Excel');

        return [
            'applied' => true,
            'paid' => true,
            'amount_paid' => (float) $payment->amount,
            'permit' => $permitDate !== null,
            'warnings' => $warnings,
        ];
    }

    /**
     * @param  array<string,mixed>  $row
     * @param  array<string,mixed>  $properties
     */
    private function log(
        Installment $target,
        Contract $contract,
        array $row,
        string $event,
        array $properties,
        ?int $userId,
        string $message,
    ): void {
        $logger = activity(self::LOG_NAME)
            ->performedOn($target)
            ->event($event)
            ->withProperties([
                'row' => $row['row'],
                'request_no' => $row['request_no'],
                'contract_id' => $contract->id,
                'installment_id' => $target->id,
                'installment_no' => $target->installment_no,
                ...$properties,
            ]);

        if ($userId !== null) {
            $logger->causedBy($userId);
        }

        $logger->log($message);
    }

    /**
     * @param  list<string>  $messages
     */
    private function warnings(InstallmentAdditionResult $result, array $row, string $code, array $messages): void
    {
        foreach ([...$row['warnings'], ...$messages] as $message) {
            $result->addWarning($row['row'], $code, $message);
        }
    }

    private function rowCode(array $row): string
    {
        if ($row['request_no'] !== '') {
            return $row['request_no'];
        }

        if ($row['name'] !== '') {
            return $row['name'];
        }

        return $row['serial'] !== null ? (string) $row['serial'] : '—';
    }

    /** @param list<string> $warnings */
    private function findContract(array $row, array &$warnings): Contract
    {
        if ($row['contract_code'] !== '') {
            $contract = Contract::query()->where('code', $row['contract_code'])->first();

            if ($contract) {
                return $contract;
            }
        }

        if ($row['national_id'] !== '') {
            $citizen = Citizen::query()->where('national_id', $row['national_id'])->first();

            if ($citizen) {
                $contract = $this->pickContract($citizen->contracts()->get(), $warnings);

                if ($contract) {
                    $warnings[] = 'تمت مطابقة العقد بالرقم القومي.';

                    return $contract;
                }
            }
        }

        $citizens = $this->findCitizensByNameAndPhone($row['name'], $row['phone']);

        if ($citizens->isNotEmpty()) {
            $contracts = Contract::query()
                ->whereIn('citizen_id', $citizens->pluck('id'))
                ->get();

            $contract = $this->pickContract($contracts, $warnings);

            if ($contract) {
                $warnings[] = 'تمت مطابقة العقد بالاسم والتليفون.';

                return $contract;
            }
        }

        throw ValidationException::withMessages([
            'contract' => 'مفيش عقد مطابق لرقم الطلب «'.($row['request_no'] !== '' ? $row['request_no'] : '—')
                .'» ولا بالاسم والتليفون. راجع رقم الطلب أو بيانات المواطن.',
        ]);
    }

    private function assertContractHasInstallments(Contract $contract): void
    {
        if (! $contract->installments()->exists()) {
            throw ValidationException::withMessages([
                'installments' => "العقد «{$contract->code}» مفيهوش أقساط (عقد كاش)؛ مينفعش نضيف عليه قسط.",
            ]);
        }
    }

    /**
     * @param  Collection<int,Contract>  $contracts
     * @param  list<string>  $warnings
     */
    private function pickContract(Collection $contracts, array &$warnings): ?Contract
    {
        if ($contracts->isEmpty()) {
            return null;
        }

        if ($contracts->count() === 1) {
            return $contracts->first();
        }

        $withOverdue = $contracts->first(
            fn (Contract $contract): bool => $contract->installments()->overdue()->exists(),
        );

        if ($withOverdue) {
            return $withOverdue;
        }

        $warnings[] = 'فيه أكثر من عقد مطابق ومفيش واحد فيهم قسط متأخر — استخدمنا أول عقد. راجع رقم الطلب.';

        return $contracts->sortBy('id')->first();
    }

    /** @return Collection<int,Citizen> */
    private function findCitizensByNameAndPhone(string $name, string $phone): Collection
    {
        $name = trim($name);

        // المطابقة الاحتياطية لازم تكون بالاسم والتليفون مع بعض، مش بالاسم لوحده.
        if ($name === '' || $phone === '') {
            return new Collection;
        }

        $query = Citizen::query()
            ->where('name', $name)
            ->whereHas('phones', fn ($q) => $q->where('phone', $phone));

        $citizens = $query->get();

        if ($citizens->isNotEmpty()) {
            return $citizens;
        }

        // اختلاف بسيط في المسافات أو اسم زيادة: بحث أوسع، لكن بنفس التليفون
        $like = '%'.addcslashes($name, '%_\\').'%';

        return Citizen::query()
            ->where('name', 'like', $like)
            ->whereHas('phones', fn ($q) => $q->where('phone', $phone))
            ->get();
    }

    /** @param list<string> $warnings */
    private function findTargetInstallment(Contract $contract, array $row, array &$warnings): ?Installment
    {
        $installments = $contract->installments()->get();

        if ($row['issue_date'] !== null) {
            // تاريخ السداد بيتمطابق مع أقرب تاريخ استحقاق
            $dated = $installments->filter(fn (Installment $installment): bool => $installment->due_date !== null);

            if ($dated->isNotEmpty()) {
                $target = $dated
                    ->sortBy(fn (Installment $installment): float => abs($installment->due_date->diffInDays($row['issue_date'])))
                    ->first();
            } else {
                $warnings[] = 'مفيش أقساط ليها تاريخ استحقاق؛ اتختار أول قسط.';
                $target = $installments->first();
            }
        } else {
            $target = $installments->first(
                fn (Installment $installment): bool => ! $installment->isPaid()
                    && $installment->due_date !== null
                    && $installment->due_date->lt(today()),
            ) ?? $installments->first(fn (Installment $installment): bool => ! $installment->isPaid());
        }

        if ($target === null) {
            $warnings[] = 'كل أقساط العقد مسددة — اتجاهل الصف.';

            return null;
        }

        if ($target->isPaid()) {
            $warnings[] = "القسط رقم {$target->installment_no} المطابق مسدد بالفعل — اتجاهل الصف.";

            return null;
        }

        return $target;
    }
}
