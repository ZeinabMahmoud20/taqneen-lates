<?php

namespace Tests\Feature;

use App\Models\Center;
use App\Models\Citizen;
use App\Models\Contract;
use App\Models\Installment;
use App\Models\Payment;
use App\Models\PaymentPlan;
use App\Services\Excel\ImportFormatException;
use App\Services\Excel\OpenSpoutTempFolder;
use App\Services\InstallmentAdditionImporter;
use App\Services\InstallmentAdditionResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class InstallmentAdditionImportTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $files = [];

    private const HEADERS = [
        'م',
        'رقم الطلب',
        'الاسم',
        'التليفون',
        'الرقم القومي',
        'تاريخ الإصدار',
        'المبلغ',
        'تاريخ الأذن',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        PaymentPlan::create([
            'name' => 'ثلاث سنوات',
            'installments_count' => 3,
            'is_cash' => false,
        ]);
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    private function citizen(string $name = 'أحمد علي', string $phone = '01000000000', ?string $nationalId = null): Citizen
    {
        $citizen = Citizen::create([
            'name' => $name,
            'national_id' => $nationalId,
            'center_id' => Center::firstOrCreate(['name' => 'بلطيم'])->id,
        ]);

        if ($phone !== '') {
            $citizen->phones()->create(['phone' => $phone, 'is_primary' => true]);
        }

        return $citizen;
    }

    /**
     * @param  list<array{no?: int, due?: ?Carbon, amount?: float, status?: string, paid_at?: ?Carbon}>  $installments
     */
    private function contract(Citizen $citizen, string $code, array $installments): Contract
    {
        $plan = PaymentPlan::query()->firstOrFail();
        $total = array_sum(array_map(fn (array $row): float => (float) ($row['amount'] ?? 100), $installments));

        $contract = Contract::create([
            'code' => $code,
            'citizen_id' => $citizen->id,
            'payment_plan_id' => $plan->id,
            'total_price' => $total,
        ]);

        foreach ($installments as $index => $row) {
            $contract->installments()->create([
                'installment_no' => $row['no'] ?? ($index + 1),
                'due_date' => $row['due'] ?? null,
                'amount' => $row['amount'] ?? 100,
                'paid_amount' => ($row['status'] ?? Installment::STATUS_PENDING) === Installment::STATUS_PAID
                    ? ($row['amount'] ?? 100)
                    : 0,
                'status' => $row['status'] ?? Installment::STATUS_PENDING,
                'paid_at' => $row['paid_at'] ?? null,
            ]);
        }

        return $contract->refresh();
    }

    /**
     * بيبني ملف Excel: صف 1 عنوان، صفوف 2-8 فاضية، صف 9 عناوين الأعمدة، والبيانات من صف 10.
     *
     * @param  list<array<string,mixed>>  $rows
     */
    private function xlsx(array $rows): string
    {
        $path = OpenSpoutTempFolder::file('installment-addition-test-');
        $this->files[] = $path;

        $writer = new Writer;
        $writer->openToFile($path);

        $writer->addRow(Row::fromValues(['تقرير الرسوم الحكومية']));

        for ($i = 2; $i < 9; $i++) {
            $writer->addRow(Row::fromValues(array_fill(0, count(self::HEADERS), '')));
        }

        $writer->addRow(Row::fromValues(self::HEADERS));

        foreach ($rows as $values) {
            $cells = array_fill(0, count(self::HEADERS), '');

            foreach ($values as $key => $value) {
                $index = array_search($key, self::HEADERS, true);
                $this->assertNotFalse($index, "عنوان غير موجود في الملف: {$key}");
                $cells[$index] = $value;
            }

            $writer->addRow(Row::fromValues($cells));
        }

        $writer->close();

        return $path;
    }

    /** @param list<array<string,mixed>> $rows */
    private function import(array $rows, bool $dryRun = false): InstallmentAdditionResult
    {
        return app(InstallmentAdditionImporter::class)->import($this->xlsx($rows), $dryRun);
    }

    /** @return array<string,mixed> */
    private function row(array $override = []): array
    {
        return $override + [
            'رقم الطلب' => '201148/32',
            'الاسم' => 'أحمد علي',
            'التليفون' => '01000000000',
        ];
    }

    public function test_issue_date_records_a_full_payment_and_removes_the_installment_from_overdue(): void
    {
        $contract = $this->contract($this->citizen(), '201148', [
            ['no' => 1, 'due' => today()->subMonth(), 'amount' => 100],
            ['no' => 2, 'due' => today()->addYear(), 'amount' => 100],
        ]);

        $issue = today()->subWeek()->toDateString();
        $permit = today()->toDateString();

        $result = $this->import([
            $this->row([
                'المبلغ' => 100,
                'تاريخ الإصدار' => $issue,
                'تاريخ الأذن' => $permit,
            ]),
        ]);

        $this->assertSame([], $result->errors);
        $this->assertSame([], $result->warnings);
        $this->assertSame(1, $result->total);
        $this->assertSame(1, $result->applied);
        $this->assertSame(1, $result->paidInstallments);
        $this->assertSame(1, $result->permitsSaved);
        $this->assertSame(100.0, $result->paidTotal);

        $paid = $contract->installments()->where('installment_no', 1)->firstOrFail();

        $this->assertSame(Installment::STATUS_PAID, $paid->status);
        $this->assertSame('100.00', $paid->amount);
        $this->assertSame('100.00', $paid->paid_amount);
        $this->assertSame($issue, $paid->paid_at->toDateString());
        $this->assertSame($permit, $paid->permit_date->toDateString());

        $payment = Payment::query()->where('installment_id', $paid->id)->sole();

        $this->assertSame(Payment::TYPE_INSTALLMENT, $payment->type);
        $this->assertSame('100.00', $payment->amount);
        $this->assertSame($issue, $payment->paid_at->toDateString());

        $this->assertSame('200.00', $contract->refresh()->total_price);
        $this->assertFalse(Installment::query()->overdue()->whereKey($paid->id)->exists());
    }

    public function test_the_report_amount_replaces_the_installment_amount_and_adjusts_the_contract_total(): void
    {
        $contract = $this->contract($this->citizen(), '226659', [
            ['no' => 1, 'due' => today()->subMonth(), 'amount' => 236408],
        ]);

        $result = $this->import([
            $this->row([
                'رقم الطلب' => '226659',
                'المبلغ' => 61852,
                'تاريخ الإصدار' => today()->toDateString(),
            ]),
        ]);

        $this->assertSame([], $result->errors);
        $this->assertSame(1, $result->applied);
        $this->assertSame(61852.0, $result->paidTotal);
        $this->assertStringContainsString('قيمة القسط اتعدلت', $result->warnings[0]['message']);

        $paid = $contract->installments()->firstOrFail();

        $this->assertSame('61852.00', $paid->amount);
        $this->assertSame('61852.00', $paid->paid_amount);
        $this->assertSame(Installment::STATUS_PAID, $paid->status);
        $this->assertSame('61852.00', $contract->refresh()->total_price);
    }

    public function test_future_issue_date_rejects_the_row_without_saving_anything(): void
    {
        $contract = $this->contract($this->citizen(), '201148', [
            ['due' => today()->subMonth()],
        ]);

        $result = $this->import([
            $this->row([
                'المبلغ' => 50,
                'تاريخ الإصدار' => today()->addDay()->toDateString(),
            ]),
        ]);

        $this->assertCount(1, $result->errors);
        $this->assertStringContainsString('تاريخ الإصدار', $result->errors[0]['message']);
        $this->assertStringContainsString('في المستقبل', $result->errors[0]['message']);
        $this->assertSame(0, $result->applied);
        $this->assertSame(0, Payment::query()->count());

        $target = $contract->installments()->firstOrFail();
        $this->assertSame('100.00', $target->amount);
        $this->assertSame(Installment::STATUS_PENDING, $target->status);
        $this->assertNull($target->paid_at);
        $this->assertSame('100.00', $contract->refresh()->total_price);
    }

    public function test_permit_date_before_the_installment_due_date_is_a_warning_only(): void
    {
        $due = today()->addMonths(2)->startOfDay();
        $contract = $this->contract($this->citizen(), '201148', [
            ['due' => $due],
        ]);

        $result = $this->import([
            $this->row([
                'المبلغ' => 100,
                'تاريخ الإصدار' => today()->toDateString(),
                'تاريخ الأذن' => today()->subWeek()->toDateString(),
            ]),
        ]);

        $this->assertSame([], $result->errors);
        $this->assertCount(1, $result->warnings);
        $this->assertStringContainsString('قبل تاريخ استحقاق القسط', $result->warnings[0]['message']);
        $this->assertSame(1, $result->applied);

        $target = $contract->installments()->firstOrFail();
        $this->assertSame(Installment::STATUS_PAID, $target->status);
    }

    public function test_permit_only_row_stores_the_permit_date_without_recording_a_payment(): void
    {
        $contract = $this->contract($this->citizen(), '201148', [
            ['no' => 1, 'due' => today()->subMonths(3), 'status' => Installment::STATUS_PAID, 'paid_at' => today()->subMonths(2)],
            ['no' => 2, 'due' => today()->subMonth()],
            ['no' => 3, 'due' => today()->addYear()],
        ]);

        $permit = today()->subWeek()->toDateString();

        $result = $this->import([
            $this->row([
                'تاريخ الأذن' => $permit,
            ]),
        ]);

        $this->assertSame([], $result->errors);
        $this->assertSame(1, $result->applied);
        $this->assertSame(0, $result->paidInstallments);
        $this->assertSame(1, $result->permitsSaved);
        $this->assertSame(0, Payment::query()->count());

        $overdue = $contract->installments()->where('installment_no', 2)->firstOrFail();

        $this->assertSame($permit, $overdue->permit_date->toDateString());
        $this->assertSame(Installment::STATUS_PENDING, $overdue->status);
        $this->assertSame('100.00', $overdue->amount);

        $alreadyPaid = $contract->installments()->where('installment_no', 1)->firstOrFail();
        $this->assertNull($alreadyPaid->permit_date);
    }

    public function test_amount_without_issue_date_is_ignored_and_the_permit_date_is_still_saved(): void
    {
        $contract = $this->contract($this->citizen(), '201148', [
            ['due' => today()->subMonth()],
        ]);

        $result = $this->import([
            $this->row([
                'المبلغ' => 50,
                'تاريخ الأذن' => today()->toDateString(),
            ]),
        ]);

        $this->assertSame([], $result->errors);
        $this->assertSame(1, $result->applied);
        $this->assertSame(0, $result->paidInstallments);
        $this->assertSame(1, $result->permitsSaved);
        $this->assertStringContainsString('مفيهوش «تاريخ الإصدار»', $result->warnings[0]['message']);

        $target = $contract->installments()->firstOrFail();

        $this->assertSame('100.00', $target->amount);
        $this->assertSame(Installment::STATUS_PENDING, $target->status);
        $this->assertSame(today()->toDateString(), $target->permit_date->toDateString());
    }

    public function test_amount_without_issue_date_or_permit_date_is_skipped_with_a_warning(): void
    {
        $contract = $this->contract($this->citizen(), '201148', [
            ['due' => today()->subMonth()],
        ]);

        $result = $this->import([
            $this->row([
                'المبلغ' => 50,
            ]),
        ]);

        $this->assertSame([], $result->errors);
        $this->assertSame(0, $result->applied);
        $this->assertSame(1, $result->skipped);
        $this->assertStringContainsString('مينفعش نسجل سداد', $result->warnings[0]['message']);

        $target = $contract->installments()->firstOrFail();
        $this->assertSame('100.00', $target->amount);
        $this->assertSame(Installment::STATUS_PENDING, $target->status);
    }

    public function test_row_without_amount_issue_date_or_permit_date_is_ignored_with_a_warning(): void
    {
        $this->contract($this->citizen(), '201148', [
            ['due' => today()->subMonth()],
        ]);

        $result = $this->import([
            $this->row(['م' => 1]),
        ]);

        $this->assertSame([], $result->errors);
        $this->assertSame(0, $result->applied);
        $this->assertSame(1, $result->skipped);
        $this->assertStringContainsString('مفيهوش مبلغ ولا تاريخ إصدار ولا تاريخ أذن', $result->warnings[0]['message']);
    }

    public function test_invalid_amount_rejects_the_row(): void
    {
        $contract = $this->contract($this->citizen(), '201148', [
            ['due' => today()->subMonth()],
        ]);

        $result = $this->import([
            $this->row([
                'المبلغ' => 'مش رقم',
                'تاريخ الإصدار' => today()->toDateString(),
            ]),
        ]);

        $this->assertCount(1, $result->errors);
        $this->assertStringContainsString('قيمة غير رقمية في «المبلغ»', $result->errors[0]['message']);
        $this->assertSame(0, $result->applied);
        $this->assertSame('100.00', $contract->installments()->firstOrFail()->amount);
    }

    public function test_matched_paid_installment_is_skipped_with_a_warning(): void
    {
        $due = today()->subMonth()->startOfDay();
        $contract = $this->contract($this->citizen(), '201148', [
            ['due' => $due, 'status' => Installment::STATUS_PAID, 'paid_at' => today()->subWeek()],
        ]);

        $result = $this->import([
            $this->row([
                'المبلغ' => 50,
                'تاريخ الإصدار' => today()->toDateString(),
            ]),
        ]);

        $this->assertSame([], $result->errors);
        $this->assertSame(0, $result->applied);
        $this->assertSame(1, $result->skipped);
        $this->assertStringContainsString('مسدد بالفعل', $result->warnings[0]['message']);
        $this->assertSame(0, Payment::query()->count());

        $target = $contract->installments()->firstOrFail();
        $this->assertSame('100.00', $target->amount);
        $this->assertSame('100.00', $contract->refresh()->total_price);
    }

    public function test_rerunning_the_same_file_skips_the_now_paid_installment(): void
    {
        $contract = $this->contract($this->citizen(), '201148', [
            ['due' => today()->subMonth()],
        ]);

        $row = $this->row([
            'المبلغ' => 100,
            'تاريخ الإصدار' => today()->toDateString(),
        ]);

        $first = app(InstallmentAdditionImporter::class)->import($this->xlsx([$row]));
        $second = app(InstallmentAdditionImporter::class)->import($this->xlsx([$row]));

        $this->assertSame(1, $first->applied);
        $this->assertSame(0, $second->applied);
        $this->assertSame(1, $second->skipped);
        $this->assertStringContainsString('مسدد بالفعل', $second->warnings[0]['message']);

        $this->assertSame(1, Payment::query()->count());
        $this->assertSame('100.00', $contract->installments()->firstOrFail()->amount);
        $this->assertSame('100.00', $contract->refresh()->total_price);
    }

    public function test_same_contract_multiple_rows_are_processed_in_order(): void
    {
        $contract = $this->contract($this->citizen(), '201148', [
            ['no' => 1, 'due' => today()->subYears(2), 'amount' => 100],
            ['no' => 2, 'due' => today()->subYear(), 'amount' => 100],
        ]);

        $result = $this->import([
            $this->row([
                'المبلغ' => 100,
                'تاريخ الإصدار' => today()->subYears(2)->addDays(5)->toDateString(),
            ]),
            $this->row([
                'المبلغ' => 100,
                'تاريخ الإصدار' => today()->subYear()->addDays(5)->toDateString(),
            ]),
        ]);

        $this->assertSame([], $result->errors);
        $this->assertSame(2, $result->applied);
        $this->assertSame(2, $result->paidInstallments);
        $this->assertSame(200.0, $result->paidTotal);
        $this->assertSame(2, Payment::query()->count());

        $this->assertSame(
            Installment::STATUS_PAID,
            $contract->installments()->where('installment_no', 1)->firstOrFail()->status,
        );
        $this->assertSame(
            Installment::STATUS_PAID,
            $contract->installments()->where('installment_no', 2)->firstOrFail()->status,
        );
    }

    public function test_dry_run_reports_the_same_result_without_saving_anything(): void
    {
        $contract = $this->contract($this->citizen(), '201148', [
            ['due' => today()->subMonth()],
        ]);

        $result = $this->import([
            $this->row([
                'المبلغ' => 100,
                'تاريخ الإصدار' => today()->toDateString(),
            ]),
        ], dryRun: true);

        $this->assertTrue($result->dryRun);
        $this->assertSame([], $result->errors);
        $this->assertSame(1, $result->applied);
        $this->assertSame(1, $result->paidInstallments);
        $this->assertSame(100.0, $result->paidTotal);

        $target = $contract->installments()->firstOrFail();
        $this->assertSame('100.00', $target->amount);
        $this->assertSame(Installment::STATUS_PENDING, $target->status);
        $this->assertNull($target->paid_at);
        $this->assertSame('100.00', $contract->refresh()->total_price);
        $this->assertSame(0, Payment::query()->count());
        $this->assertSame(
            0,
            Activity::query()->where('log_name', InstallmentAdditionImporter::LOG_NAME)->count(),
        );
    }

    public function test_falls_back_to_the_citizen_name_and_phone_when_the_contract_code_is_missing(): void
    {
        $citizen = $this->citizen('أحمد علي', '01000000000');
        $contract = $this->contract($citizen, 'REAL-1', [
            ['due' => today()->subMonth()],
        ]);

        $result = $this->import([[
            'رقم الطلب' => 'MISSING/99',
            'الاسم' => 'أحمد علي',
            'التليفون' => '01000000000',
            'المبلغ' => 100,
            'تاريخ الإصدار' => today()->toDateString(),
        ]]);

        $this->assertSame([], $result->errors);
        $this->assertSame(1, $result->applied);
        $this->assertStringContainsString('بالاسم والتليفون', $result->warnings[0]['message']);

        $this->assertSame(Installment::STATUS_PAID, $contract->installments()->firstOrFail()->status);
    }

    public function test_name_only_fallback_does_not_match_a_citizen(): void
    {
        $citizen = $this->citizen('أحمد علي', '01000000000');
        $contract = $this->contract($citizen, 'REAL-1', [
            ['due' => today()->subMonth()],
        ]);

        $result = $this->import([[
            'رقم الطلب' => 'MISSING/99',
            'الاسم' => 'أحمد علي',
            'المبلغ' => 100,
            'تاريخ الإصدار' => today()->toDateString(),
        ]]);

        $this->assertCount(1, $result->errors);
        $this->assertStringContainsString('مفيش عقد مطابق', $result->errors[0]['message']);
        $this->assertSame(0, $result->applied);
        $this->assertSame(Installment::STATUS_PENDING, $contract->installments()->firstOrFail()->status);
    }

    public function test_national_id_fallback_picks_the_contract_with_an_overdue_installment(): void
    {
        $citizen = $this->citizen('أحمد علي', '01000000000', '12345678901234');

        $future = $this->contract($citizen, 'A-1', [
            ['due' => today()->addYear()],
        ]);
        $overdue = $this->contract($citizen, 'B-1', [
            ['due' => today()->subMonth()],
        ]);

        $result = $this->import([[
            'رقم الطلب' => 'UNKNOWN/1',
            'الرقم القومي' => '12345678901234',
            'المبلغ' => 100,
            'تاريخ الإصدار' => today()->toDateString(),
        ]]);

        $this->assertSame([], $result->errors);
        $this->assertSame(1, $result->applied);
        $this->assertStringContainsString('بالرقم القومي', $result->warnings[0]['message']);

        $this->assertSame(Installment::STATUS_PENDING, $future->installments()->firstOrFail()->status);
        $this->assertSame(Installment::STATUS_PAID, $overdue->installments()->firstOrFail()->status);
    }

    public function test_password_protected_file_gives_a_clear_error(): void
    {
        $path = OpenSpoutTempFolder::file('encrypted-test-');
        $this->files[] = $path;

        // ملف OLE2 مشفّر: التوقيع + اسم stream التشفير
        file_put_contents(
            $path,
            "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1".str_repeat("\0", 512).'EncryptedPackage',
        );

        try {
            app(InstallmentAdditionImporter::class)->import($path);
            $this->fail('المفروض الملف المشفّر يترفض برسالة واضحة.');
        } catch (ImportFormatException $e) {
            $this->assertStringContainsString('محمي بكلمة مرور', $e->getMessage());
        }
    }

    public function test_corrupt_file_gives_a_clear_error_instead_of_a_zip_library_error(): void
    {
        $path = OpenSpoutTempFolder::file('corrupt-test-');
        $this->files[] = $path;
        file_put_contents($path, 'not really an xlsx file');

        try {
            app(InstallmentAdditionImporter::class)->import($path);
            $this->fail('المفروض الملف التالف يترفض برسالة واضحة.');
        } catch (ImportFormatException $e) {
            $this->assertStringContainsString('تالف أو مش xlsx', $e->getMessage());
            $this->assertStringNotContainsString('Not a zip archive', $e->getMessage());
        }
    }
}
