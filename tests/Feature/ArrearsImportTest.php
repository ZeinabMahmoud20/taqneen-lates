<?php

namespace Tests\Feature;

use App\Models\Center;
use App\Models\Citizen;
use App\Models\Contract;
use App\Models\InstallmentNote;
use App\Models\OverdueInstallment;
use App\Models\Payment;
use App\Models\PaymentPlan;
use App\Services\ArrearsImporter;
use App\Services\Excel\ArrearsTemplateWriter;
use App\Services\Excel\ImportFormatException;
use App\Services\ImportResult;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class ArrearsImportTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $files = [];

    private const ORDINAL_HEADERS = [
        1 => 'القسط الاول', 2 => 'القسط الثاني', 3 => 'القسط الثالث', 4 => 'القسط الرابع',
        5 => 'القسط الخامس', 6 => 'القسط السادس', 7 => 'القسط السابع',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        PaymentPlan::create(['name' => 'كاش', 'installments_count' => 0, 'is_cash' => true]);
        PaymentPlan::create(['name' => 'ثلاث سنوات', 'installments_count' => 3, 'is_cash' => false]);
        PaymentPlan::create(['name' => 'خمس سنوات', 'installments_count' => 5, 'is_cash' => false]);
        PaymentPlan::create(['name' => 'سبع سنوات', 'installments_count' => 7, 'is_cash' => false]);
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    private function d(string $date): DateTimeImmutable
    {
        return new DateTimeImmutable($date);
    }

    /**
     * بيبني ملف xlsx من صفوف. المفاتيح = عناوين القالب،
     * و«سداد N» = عمود تاريخ السداد الخاص بالقسط N.
     *
     * @param list<array<string,mixed>> $rows
     */
    private function xlsx(array $rows): string
    {
        $headers = ArrearsTemplateWriter::HEADERS;
        $path = sys_get_temp_dir() . '/arrears_' . uniqid() . '.xlsx';
        $this->files[] = $path;

        $writer = new Writer();
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues($headers));

        foreach ($rows as $values) {
            $cells = array_fill(0, count($headers), '');

            foreach ($values as $key => $value) {
                if (preg_match('/^سداد (\d)$/u', (string) $key, $m)) {
                    $index = array_search(self::ORDINAL_HEADERS[(int) $m[1]], $headers, true) + 1;
                } else {
                    $index = array_search($key, $headers, true);
                }

                $this->assertNotFalse($index, "عنوان غير موجود في القالب: {$key}");
                $cells[$index] = $value;
            }

            $writer->addRow(Row::fromValues($cells));
        }

        $writer->close();

        return $path;
    }

    private function import(array $rows, bool $dryRun = false): ImportResult
    {
        return app(ArrearsImporter::class)->import($this->xlsx($rows), $dryRun);
    }

    /** أول صف في الإكسيل الأصلي */
    private function sampleRow(array $override = []): array
    {
        return $override + [
            'نوع القسط' => 'ثلاث سنوات', 'م' => 1, 'كود' => 253190, 'الاسم' => 'صبري ', 'المركز' => 'بلطيم',
            'مبلغ الدفعة المقدمة' => 37170, 'اجمالى الثمن بالفايدة' => 277290, 'تاريخ المقدم' => $this->d('2024-01-10'),
            'القسط الاول' => 84955, 'سداد 1' => $this->d('2025-01-28'),
            'القسط الثاني' => 80040, 'سداد 2' => $this->d('2026-01-11'),
            'القسط الثالث' => 75125,
        ];
    }

    public function test_imports_the_first_original_row_with_due_dates_from_the_down_payment(): void
    {
        $result = $this->import([$this->sampleRow()]);

        $this->assertSame([], $result->errors);
        $this->assertSame([], $result->warnings);
        $this->assertSame(1, $result->contracts);
        $this->assertSame(2, $result->paidInstallments);

        $contract = Contract::where('code', '253190')->firstOrFail();
        $this->assertSame('277290.00', $contract->total_price);   // المقدم + الأقساط
        $this->assertSame('صبري', $contract->citizen->name);
        $this->assertSame('بلطيم', $contract->citizen->center->name);

        $installments = $contract->installments;
        $this->assertSame(['paid', 'paid', 'pending'], $installments->pluck('status')->all());

        // الاستحقاق = تاريخ المقدم (2024-01-10) + N سنة
        $this->assertSame(
            ['2025-01-10', '2026-01-10', '2027-01-10'],
            $installments->map(fn ($i) => $i->due_date->toDateString())->all()
        );
        // السداد الفعلي منفصل عن الاستحقاق
        $this->assertSame(
            ['2025-01-28', '2026-01-11', null],
            $installments->map(fn ($i) => $i->paid_at?->toDateString())->all()
        );

        // المقدم + قسطين مسددين
        $this->assertSame(3, Payment::count());
    }

    public function test_cash_row_100_percent_records_one_cash_payment(): void
    {
        $result = $this->import([[
            'نوع القسط' => 'سداد 100%', 'م' => 940, 'كود' => 226315, 'الاسم' => 'إبراهيم ', 'المركز' => 'بلطيم',
            'مبلغ الدفعة المقدمة' => 105004, 'اجمالى الثمن بالفايدة' => 105004, 'تاريخ المقدم' => '2026/02/26',
        ]]);

        $this->assertSame([], $result->errors);
        $contract = Contract::where('code', '226315')->firstOrFail();
        $this->assertTrue($contract->paymentPlan->is_cash);
        $this->assertSame('105004.00', $contract->total_price);
        $this->assertSame(0, $contract->installments()->count());

        $payment = Payment::where('type', Payment::TYPE_CASH)->firstOrFail();
        $this->assertSame('105004.00', $payment->amount);
        $this->assertSame('2026-02-26', $payment->paid_at->toDateString());
    }

    public function test_fully_paid_row_infers_the_plan_from_the_installment_count(): void
    {
        // صف «منتصر» من ملف المستخدم (فيه تاريخ 30/02/2020 غير موجود في التقويم)
        $result = $this->import([[
            'نوع القسط' => 'تم سداد كامل ثمن الأرض ', 'م' => 942, 'كود' => 30247, 'الاسم' => 'منتصر  ', 'المركز' => 'الحامول',
            'مبلغ الدفعة المقدمة' => 175045, 'اجمالى الثمن بالفايدة' => 383716, 'تاريخ المقدم' => '2019/02/04',
            'القسط الاول' => 73828, 'سداد 1' => '30/02/2020',
            'القسط الثاني' => 69557, 'سداد 2' => '2021/02/08',
            'القسط الثالث' => 65286, 'سداد 3' => '2022/01/31',
        ]]);

        $this->assertSame([], $result->errors);
        $this->assertSame(3, $result->paidInstallments);
        $this->assertCount(1, $result->warnings);                       // 30/02 اتحسب آخر الشهر
        $this->assertStringContainsString('2020-02-29', $result->warnings[0]['message']);

        $contract = Contract::where('code', '30247')->firstOrFail();
        $this->assertSame('ثلاث سنوات', $contract->paymentPlan->name);   // من عدد الأقساط
        $this->assertSame('383716.00', $contract->total_price);
        $this->assertSame(['paid', 'paid', 'paid'], $contract->installments->pluck('status')->all());
        $this->assertSame(
            ['2020-02-04', '2021-02-04', '2022-02-04'],                  // المقدم + N سنة
            $contract->installments->map(fn ($i) => $i->due_date->toDateString())->all()
        );
        $this->assertSame('2020-02-29', $contract->installments[0]->paid_at->toDateString());
        $this->assertSame('الحامول', $contract->citizen->center->name);
    }

    public function test_fully_paid_row_with_a_missing_payment_date_is_imported_with_a_warning(): void
    {
        $result = $this->import([[
            'نوع القسط' => 'تم سداد كامل ثمن الأرض', 'كود' => 'F-1', 'الاسم' => 'ناقص',
            'مبلغ الدفعة المقدمة' => 1000, 'تاريخ المقدم' => '2019/02/04',
            'القسط الاول' => 100, 'سداد 1' => '2020/02/04',
            'القسط الثاني' => 100, 'القسط الثالث' => 100, 'سداد 3' => '2022/02/04',
        ]]);

        $this->assertSame([], $result->errors);
        $this->assertSame(1, $result->contracts);
        $this->assertSame(3, $result->paidInstallments);
        $this->assertCount(1, $result->warnings);
        $this->assertStringContainsString('(2)', $result->warnings[0]['message']);

        $contract = Contract::where('code', 'F-1')->firstOrFail();
        $second = $contract->installments[1];
        $this->assertSame('paid', $second->status);          // العقد «تم سداد كامل» => مسدد
        $this->assertNull($second->paid_at);                 // وتاريخه مجهول
        $this->assertSame('100.00', $second->paid_amount);
        $this->assertSame(2, Payment::where('type', Payment::TYPE_INSTALLMENT)->count());   // سجل دفع للتاريخين المعروفين بس
        $this->assertSame(0, OverdueInstallment::query()->count());                          // مش متأخر
    }

    public function test_installment_count_that_matches_no_plan_is_rejected(): void
    {
        $result = $this->import([[
            'نوع القسط' => 'تم سداد كامل ثمن الأرض', 'كود' => 'F-2', 'الاسم' => 'اتنين',
            'مبلغ الدفعة المقدمة' => 1000, 'تاريخ المقدم' => '2019/02/04',
            'القسط الاول' => 100, 'سداد 1' => '2020/02/04', 'القسط الثاني' => 100, 'سداد 2' => '2021/02/04',
        ]]);

        $this->assertSame(0, $result->contracts);
        $this->assertStringContainsString('عدد الأقساط (2)', $result->errors[0]['message']);
    }

    public function test_unpaid_installments_are_due_down_payment_plus_n_years_and_show_in_overdue(): void
    {
        // المقدم من سنتين ويوم => الاستحقاقات: من سنة ويوم، ومن يوم، وبعد سنة
        $result = $this->import([$this->sampleRow([
            'كود' => 'U-1', 'سداد 1' => '', 'سداد 2' => '', 'تاريخ المقدم' => $this->d('-2 years -1 day'),
            'اجمالى الثمن بالفايدة' => '',
        ])]);

        $this->assertSame([], $result->errors);
        $this->assertSame(2, OverdueInstallment::query()->count());   // أول اتنين متأخرين دايماً
    }

    public function test_unpaid_installments_without_a_down_payment_date_are_imported_with_a_warning(): void
    {
        $result = $this->import([$this->sampleRow(['كود' => 'U-2', 'تاريخ المقدم' => '', 'سداد 2' => ''])]);

        $this->assertSame([], $result->errors);
        $this->assertSame(1, $result->contracts);

        $messages = implode(' | ', array_column($result->warnings, 'message'));
        $this->assertStringContainsString('تاريخ المقدم فاضي', $messages);
        $this->assertStringContainsString('مش هتظهر في المتأخرات', $messages);

        $contract = Contract::where('code', 'U-2')->firstOrFail();
        $this->assertSame([null, null, null], $contract->installments->map(fn ($i) => $i->due_date)->all());
        $this->assertSame(['paid', 'pending', 'pending'], $contract->installments->pluck('status')->all());

        // من غير موعد استحقاق => مش ظاهر في المتأخرات، لكن ظاهر كبيانات ناقصة
        $this->assertSame(0, OverdueInstallment::query()->count());
        $this->assertSame(2, \App\Models\Installment::missingDueDate()->count());
    }

    public function test_missing_down_payment_date_with_every_installment_paid_gives_a_milder_warning(): void
    {
        // سداد 3 في المستقبل ما زال خطأ: تاريخ دفع مستحيل
        $result = $this->import([$this->sampleRow(['كود' => 'U-3', 'تاريخ المقدم' => '', 'سداد 3' => $this->d('+1 year')])]);
        $this->assertSame(0, $result->contracts);

        $result = $this->import([$this->sampleRow(['كود' => 'U-4', 'تاريخ المقدم' => '', 'سداد 3' => $this->d('2026-02-01')])]);

        $this->assertSame([], $result->errors);
        $this->assertSame(3, $result->paidInstallments);
        $messages = implode(' | ', array_column($result->warnings, 'message'));
        $this->assertStringContainsString('مواعيد الاستحقاق اتسجلت فاضية', $messages);
        $this->assertStringNotContainsString('مش هتظهر في المتأخرات', $messages);   // مفيش أقساط لسه ماتدفعتش
    }

    public function test_row_without_a_down_payment_amount_gives_a_warning(): void
    {
        $result = $this->import([$this->sampleRow(['كود' => 'N-1', 'مبلغ الدفعة المقدمة' => 0, 'اجمالى الثمن بالفايدة' => ''])]);

        $this->assertSame([], $result->errors);
        $this->assertSame(1, $result->contracts);
        $this->assertStringContainsString('مفيش مبلغ مقدم', implode(' | ', array_column($result->warnings, 'message')));
    }

    public function test_bad_phone_and_national_id_are_ignored_with_warnings_not_rejected(): void
    {
        $result = $this->import([$this->sampleRow([
            'هاتف المواطن' => '123 / 01000000000', 'الرقم القومي' => '12345',
        ])]);

        $this->assertSame([], $result->errors);
        $this->assertSame(1, $result->contracts);
        $this->assertCount(2, $result->warnings);

        $citizen = Citizen::firstOrFail();
        $this->assertNull($citizen->national_id);
        $this->assertSame(['01000000000'], $citizen->phones()->pluck('phone')->all());
    }

    public function test_ambiguous_citizen_name_creates_a_new_citizen_with_a_warning(): void
    {
        $center = Center::create(['name' => 'بلطيم']);
        Citizen::create(['name' => 'صبري', 'center_id' => $center->id]);
        Citizen::create(['name' => 'صبري', 'center_id' => $center->id]);

        $result = $this->import([$this->sampleRow()]);

        $this->assertSame([], $result->errors);
        $this->assertSame(1, $result->contracts);
        $this->assertSame(3, Citizen::count());
        $this->assertSame(1, $result->citizensCreated);
        $this->assertStringContainsString('فيه أكتر من مواطن', implode(' | ', array_column($result->warnings, 'message')));
    }

    public function test_excel_total_that_differs_from_the_computed_total_gives_a_warning(): void
    {
        $result = $this->import([$this->sampleRow(['اجمالى الثمن بالفايدة' => 999])]);

        $this->assertSame([], $result->errors);
        $this->assertCount(1, $result->warnings);
        $this->assertStringContainsString('999.00', $result->warnings[0]['message']);       // اللي في الإكسيل
        $this->assertStringContainsString('277290.00', $result->warnings[0]['message']);    // المحسوب
        $this->assertSame('277290.00', Contract::firstOrFail()->total_price);   // المحسوب
    }

    public function test_same_citizen_with_different_codes_is_matched_by_name_and_center(): void
    {
        $result = $this->import([
            $this->sampleRow(['كود' => 1001]),
            $this->sampleRow(['كود' => 1002]),
        ]);

        $this->assertSame([], $result->errors);
        $this->assertSame(1, Citizen::count());
        $this->assertSame(2, Contract::count());
        $this->assertSame(1, $result->citizensCreated);
        $this->assertSame(1, $result->citizensReused);
    }

    public function test_dry_run_reports_but_saves_nothing(): void
    {
        $result = $this->import([$this->sampleRow()], dryRun: true);

        $this->assertTrue($result->dryRun);
        $this->assertSame(1, $result->contracts);
        $this->assertSame(0, Contract::count());
        $this->assertSame(0, Citizen::count());
        $this->assertSame(0, Center::count());
        $this->assertSame(0, Payment::count());
    }

    public function test_duplicate_code_is_reported_and_the_other_rows_still_import(): void
    {
        $result = $this->import([
            $this->sampleRow(['كود' => 'X-1', 'الاسم' => 'أول']),
            $this->sampleRow(['كود' => 'X-1', 'الاسم' => 'مكرر']),
            $this->sampleRow(['كود' => 'X-2', 'الاسم' => 'تالت']),
        ]);

        $this->assertSame(2, $result->contracts);
        $this->assertCount(1, $result->errors);
        $this->assertSame(3, $result->errors[0]['row']);        // الصف التاني في البيانات = صف 3 في الشيت
        $this->assertStringContainsString('الكود موجود بالفعل', $result->errors[0]['message']);
        $this->assertSame(0, Citizen::where('name', 'مكرر')->count());
    }

    public function test_wrong_installment_count_fails_only_that_row_and_leaves_nothing_behind(): void
    {
        $result = $this->import([
            $this->sampleRow(['كود' => 'BAD-1', 'الاسم' => 'ناقص', 'القسط الثالث' => '', 'سداد 3' => '']),
        ]);

        $this->assertSame(0, $result->contracts);
        $this->assertCount(1, $result->errors);
        $this->assertStringContainsString('القسط رقم 3', $result->errors[0]['message']);
        $this->assertSame(0, Contract::count());
        $this->assertSame(0, Citizen::count());
    }

    public function test_phones_and_representative_are_imported(): void
    {
        $this->import([$this->sampleRow([
            'هاتف المواطن' => '01000000000 / 1012345678',
            'اسم النائب' => 'أحمد', 'صلة القرابة' => 'ابن', 'هاتف النائب' => '٠١١١١١١١١١١، 01222222222',
        ])]);

        $citizen = Citizen::firstOrFail();
        $this->assertSame(['01000000000', '01012345678'], $citizen->phones()->orderBy('id')->pluck('phone')->all());

        $rep = $citizen->currentRepresentative;
        $this->assertSame('أحمد', $rep->name);
        $this->assertSame('ابن', $rep->relationship);
        $this->assertSame(['01111111111', '01222222222'], $rep->phones()->orderBy('id')->pluck('phone')->all());
    }

    public function test_a_different_representative_replaces_the_current_one_with_a_warning(): void
    {
        $result = $this->import([
            $this->sampleRow(['كود' => 'R-1', 'اسم النائب' => 'أحمد']),
            $this->sampleRow(['كود' => 'R-2', 'اسم النائب' => 'محمود']),
        ]);

        $citizen = Citizen::firstOrFail();
        $this->assertSame(2, $citizen->representatives()->count());
        $this->assertSame('محمود', $citizen->currentRepresentative->name);
        $this->assertNotNull($citizen->representatives()->where('name', 'أحمد')->first()->ended_at);
        $this->assertCount(1, $result->warnings);
    }

    public function test_row_notes_are_saved_on_the_first_pending_installment(): void
    {
        $this->import([$this->sampleRow(['ملاحظات' => 'عميل متعاون'])]);

        $note = InstallmentNote::firstOrFail();
        $this->assertSame('عميل متعاون', $note->note);
        $this->assertSame(3, $note->installment->installment_no);   // أول قسط لسه pending
    }

    public function test_file_without_required_columns_is_rejected(): void
    {
        $path = sys_get_temp_dir() . '/arrears_bad_' . uniqid() . '.xlsx';
        $this->files[] = $path;
        $writer = new Writer();
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(['الاسم', 'المركز']));
        $writer->close();

        $this->expectException(ImportFormatException::class);
        app(ArrearsImporter::class)->import($path);
    }
}
