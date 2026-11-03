<?php

namespace Tests\Feature;

use App\Models\Citizen;
use App\Models\Installment;
use App\Models\OverdueInstallment;
use App\Models\Payment;
use App\Models\PaymentPlan;
use App\Services\ContractService;
use App\Services\InstallmentPaymentService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ContractAndPaymentTest extends TestCase
{
    use RefreshDatabase;

    private PaymentPlan $threeYears;
    private PaymentPlan $cash;
    private ContractService $contracts;
    private InstallmentPaymentService $payments;
    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->threeYears = PaymentPlan::create(['name' => 'ثلاث سنوات', 'installments_count' => 3, 'is_cash' => false]);
        $this->cash = PaymentPlan::create(['name' => 'كاش', 'installments_count' => 0, 'is_cash' => true]);

        $this->contracts = new ContractService();
        $this->payments = new InstallmentPaymentService();
    }

    private function citizen(string $label = '1001'): Citizen
    {
        return Citizen::create(['name' => 'مواطن ' . $label]);
    }

    /**
     * عقد 3 سنوات: مقدم 1000 + أقساط 100 و200 و300 = 1600.
     * الاستحقاق = تاريخ المقدم + N سنة.
     */
    private function threeYearContract(Citizen $citizen, string $downDate = '2024-01-10')
    {
        return $this->contracts->create(
            $citizen,
            $this->threeYears,
            [
                'code' => 'C-' . (++$this->seq),
                'down_payment_amount' => 1000,
                'down_payment_date' => $downDate,
                'total_price' => 1600,
            ],
            [100, 200, 300],
        );
    }

    /** تاريخ مقدم يخلّي القسط الأول يستحق في التاريخ المطلوب */
    private function downDateForFirstDue(Carbon $firstDue): string
    {
        return $firstDue->copy()->subYear()->toDateString();
    }

    public function test_due_dates_are_down_payment_date_plus_n_years(): void
    {
        $contract = $this->threeYearContract($this->citizen(), '2024-01-10');

        $this->assertCount(3, $contract->installments);
        $this->assertSame(
            ['2025-01-10', '2026-01-10', '2027-01-10'],
            $contract->installments->map(fn ($i) => $i->due_date->toDateString())->all()
        );
        $this->assertSame(['100.00', '200.00', '300.00'], $contract->installments->pluck('amount')->all());

        // المقدم اتسجل كدفعة
        $this->assertSame(1, Payment::where('type', Payment::TYPE_DOWN_PAYMENT)->count());
    }

    public function test_leap_day_down_payment_does_not_overflow_into_march(): void
    {
        $contract = $this->threeYearContract($this->citizen(), '2024-02-29');

        $this->assertSame(
            ['2025-02-28', '2026-02-28', '2027-02-28'],
            $contract->installments->map(fn ($i) => $i->due_date->toDateString())->all()
        );
    }

    public function test_missing_down_payment_date_leaves_due_dates_empty_instead_of_failing(): void
    {
        $contract = $this->contracts->create(
            $this->citizen(),
            $this->threeYears,
            ['code' => 'ND-1', 'down_payment_amount' => 1000, 'total_price' => 1600],
            [100, 200, 300],
        );

        $this->assertCount(3, $contract->installments);
        $this->assertSame([null, null, null], $contract->installments->map(fn ($i) => $i->due_date)->all());

        // من غير موعد استحقاق: مش ظاهر في المتأخرات، لكن ظاهر كبيانات ناقصة
        $this->assertSame(0, OverdueInstallment::query()->count());
        $this->assertSame(3, Installment::missingDueDate()->count());

        // والمستخدم يقدر يضيف الموعد يدوي، فيبدأ يتحسب في المتأخرات
        $contract->installments->first()->update(['due_date' => Carbon::yesterday()->toDateString()]);
        $this->assertSame(1, OverdueInstallment::query()->count());
        $this->assertSame(2, Installment::missingDueDate()->count());
    }

    public function test_explicit_due_dates_can_be_supplied_instead(): void
    {
        $contract = $this->contracts->create(
            $this->citizen(),
            $this->threeYears,
            ['code' => 'ED-1', 'down_payment_amount' => 1000, 'total_price' => 1600],
            [100, 200, 300],
            ['2020-02-04', '2021-02-04', '2022-02-04'],
        );

        $this->assertSame('2021-02-04', $contract->installments[1]->due_date->toDateString());
    }

    public function test_rejects_wrong_installment_count(): void
    {
        $this->expectException(ValidationException::class);

        $this->contracts->create(
            $this->citizen(),
            $this->threeYears,
            ['code' => 'W-1', 'down_payment_amount' => 1000, 'down_payment_date' => '2024-01-10', 'total_price' => 1300],
            [100, 200],
        );
    }

    public function test_rejects_total_that_does_not_match_down_payment_plus_installments(): void
    {
        $this->expectException(ValidationException::class);

        $this->contracts->create(
            $this->citizen(),
            $this->threeYears,
            ['code' => 'W-2', 'down_payment_amount' => 1000, 'down_payment_date' => '2024-01-10', 'total_price' => 9999],
            [100, 200, 300],
        );
    }

    public function test_cash_contract_has_no_installments_and_records_one_payment(): void
    {
        $contract = $this->contracts->create(
            $this->citizen(),
            $this->cash,
            ['code' => 'CASH-1', 'total_price' => 5000, 'cash_paid_at' => '2025-03-01'],
        );

        $this->assertSame(0, $contract->installments()->count());
        $this->assertSame(1, Payment::where('type', Payment::TYPE_CASH)->count());
    }

    public function test_contract_code_must_be_unique_and_citizen_can_have_several_contracts(): void
    {
        $citizen = $this->citizen();
        $first = $this->contracts->create($citizen, $this->cash, ['code' => 'DUP-1', 'total_price' => 100]);
        $second = $this->contracts->create($citizen, $this->cash, ['code' => 'DUP-2', 'total_price' => 200]);

        $this->assertSame(2, $citizen->contracts()->count());
        $this->assertNotSame($first->id, $second->id);

        $this->expectException(ValidationException::class);
        $this->contracts->create($this->citizen('other'), $this->cash, ['code' => 'DUP-1', 'total_price' => 300]);
    }

    public function test_pay_marks_installment_paid_with_the_exact_amount(): void
    {
        $contract = $this->threeYearContract($this->citizen());
        $first = $contract->installments->first();

        $payment = $this->payments->pay($first, '2025-01-20', 'R-1');

        $this->assertSame('100.00', $payment->amount);
        $first->refresh();
        $this->assertTrue($first->isPaid());
        $this->assertSame('100.00', $first->paid_amount);
        $this->assertSame('2025-01-20', $first->paid_at->toDateString());
        $this->assertSame('0.00', $first->remaining_amount);
    }

    public function test_cannot_pay_the_same_installment_twice(): void
    {
        $first = $this->threeYearContract($this->citizen())->installments->first();
        $this->payments->pay($first, '2025-01-20');

        $this->expectException(ValidationException::class);
        $this->payments->pay($first, '2025-01-21');
    }

    public function test_cannot_pay_with_a_future_date(): void
    {
        $first = $this->threeYearContract($this->citizen())->installments->first();

        $this->expectException(ValidationException::class);
        $this->payments->pay($first, Carbon::tomorrow());
    }

    public function test_pending_installment_amount_can_be_edited(): void
    {
        $first = $this->threeYearContract($this->citizen())->installments->first();

        $first->update(['amount' => 150]);

        $this->assertSame('150.00', $first->fresh()->amount);
    }

    public function test_paid_installment_amount_cannot_be_edited(): void
    {
        $first = $this->threeYearContract($this->citizen())->installments->first();
        $this->payments->pay($first, '2025-01-20');

        $this->expectException(DomainException::class);
        $first->refresh()->update(['amount' => 150]);
    }

    public function test_void_returns_installment_to_pending(): void
    {
        $first = $this->threeYearContract($this->citizen())->installments->first();
        $this->payments->pay($first, '2025-01-20');

        $this->payments->void($first);

        $first->refresh();
        $this->assertFalse($first->isPaid());
        $this->assertNull($first->paid_at);
        $this->assertSame(0, Payment::where('installment_id', $first->id)->count());

        // وممكن يتسدد تاني بعد التصحيح
        $this->payments->pay($first, '2025-01-22');
        $this->assertTrue($first->fresh()->isPaid());
    }

    public function test_installment_is_overdue_from_one_day_after_due_date_with_no_grace(): void
    {
        // قسط أول استحقاقه امبارح => متأخر
        $late = $this->threeYearContract($this->citizen('2001'), $this->downDateForFirstDue(Carbon::yesterday()));
        // قسط أول استحقاقه النهاردة => لسه مش متأخر
        $today = $this->threeYearContract($this->citizen('2002'), $this->downDateForFirstDue(Carbon::today()));

        $overdue = OverdueInstallment::query()->get();

        $this->assertCount(1, $overdue);
        $row = $overdue->first();
        $this->assertSame($late->installments->first()->id, $row->installment_id);
        $this->assertSame(1, $row->days_overdue);
        $this->assertSame('100.00', $row->remaining_amount);
        $this->assertSame('1', (string) $row->installment_no);
        $this->assertNotNull($today);
    }

    public function test_paid_installment_leaves_the_overdue_list(): void
    {
        $contract = $this->threeYearContract($this->citizen(), $this->downDateForFirstDue(Carbon::yesterday()));
        $this->assertCount(1, OverdueInstallment::query()->get());

        $this->payments->pay($contract->installments->first(), Carbon::today());

        $this->assertCount(0, OverdueInstallment::query()->get());
        $this->assertSame(0, Installment::overdue()->count());
    }
}
