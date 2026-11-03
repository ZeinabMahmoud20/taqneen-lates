<?php

namespace Tests\Feature;

use App\Models\Citizen;
use App\Models\Payment;
use App\Models\PaymentPlan;
use App\Models\Representative;
use App\Services\ContractService;
use App\Services\InstallmentPaymentService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** القواعد اللي شاشات الإدخال بتعتمد عليها. */
class ContractScreensSupportTest extends TestCase
{
    use RefreshDatabase;

    private PaymentPlan $threeYears;
    private ContractService $contracts;

    protected function setUp(): void
    {
        parent::setUp();

        $this->threeYears = PaymentPlan::create(['name' => 'ثلاث سنوات', 'installments_count' => 3, 'is_cash' => false]);
        $this->contracts = new ContractService();
    }

    private function contractWithoutDownDate(string $code = 'S-1')
    {
        return $this->contracts->create(
            Citizen::create(['name' => 'مواطن ' . $code]),
            $this->threeYears,
            ['code' => $code, 'down_payment_amount' => 1000, 'total_price' => 1600],
            [100, 200, 300],
        );
    }

    public function test_adding_the_down_payment_date_later_fills_the_missing_due_dates(): void
    {
        $contract = $this->contractWithoutDownDate();
        $this->assertSame([null, null, null], $contract->installments->map(fn ($i) => $i->due_date)->all());

        // لسه مفيش تاريخ مقدم: مفيش حاجة تتعبّى
        $this->assertSame(0, $this->contracts->fillMissingDueDates($contract));

        $contract->update(['down_payment_date' => '2024-01-10']);

        $this->assertSame(3, $this->contracts->fillMissingDueDates($contract->fresh()));
        $this->assertSame(
            ['2025-01-10', '2026-01-10', '2027-01-10'],
            $contract->installments()->get()->map(fn ($i) => $i->due_date->toDateString())->all()
        );

        // تاني مرة: مفيش حاجة فاضية
        $this->assertSame(0, $this->contracts->fillMissingDueDates($contract->fresh()));
    }

    public function test_existing_due_dates_are_never_overwritten_by_the_fill(): void
    {
        $contract = $this->contractWithoutDownDate();
        $first = $contract->installments->first();
        $first->update(['due_date' => '2030-05-05']);   // موعد يدوي

        $contract->update(['down_payment_date' => '2024-01-10']);
        $this->assertSame(2, $this->contracts->fillMissingDueDates($contract->fresh()));

        $this->assertSame('2030-05-05', $first->fresh()->due_date->toDateString());
    }

    public function test_a_missing_due_date_can_be_filled_on_a_paid_installment_but_not_changed(): void
    {
        $contract = $this->contractWithoutDownDate();
        $first = $contract->installments->first();
        (new InstallmentPaymentService())->pay($first, '2025-01-20');

        // null -> تاريخ: مسموح
        $first->refresh()->update(['due_date' => '2025-01-10']);
        $this->assertSame('2025-01-10', $first->fresh()->due_date->toDateString());

        // تغيير موعد موجود لقسط مسدد: مرفوض
        $this->expectException(DomainException::class);
        $first->refresh()->update(['due_date' => '2025-02-10']);
    }

    public function test_adding_a_current_representative_ends_the_previous_one(): void
    {
        $citizen = Citizen::create(['name' => 'مواطن']);

        $old = $citizen->representatives()->create(['name' => 'أحمد']);
        $new = $citizen->representatives()->create(['name' => 'محمود']);

        $this->assertNotNull($old->fresh()->ended_at);
        $this->assertNull($new->fresh()->ended_at);
        $this->assertSame('محمود', $citizen->fresh()->currentRepresentative->name);
    }

    public function test_adding_an_already_ended_representative_does_not_end_the_current_one(): void
    {
        $citizen = Citizen::create(['name' => 'مواطن']);
        $current = $citizen->representatives()->create(['name' => 'الحالي']);

        // نائب قديم بيتسجل للتاريخ (ended_at موجود)
        $citizen->representatives()->create(['name' => 'قديم', 'ended_at' => '2020-01-01']);

        $this->assertNull($current->fresh()->ended_at);
        $this->assertSame(1, Representative::query()->whereNull('ended_at')->count());
    }

    public function test_contract_notes_come_from_all_its_installments(): void
    {
        $contract = $this->contractWithoutDownDate('N-1');
        $contract->installments[0]->notes()->create(['note' => 'أولى']);
        $contract->installments[2]->notes()->create(['note' => 'تالتة']);

        $this->assertSame(2, $contract->notes()->count());
    }

    public function test_current_representative_relation_ignores_older_ended_representatives_with_higher_ids(): void
    {
        $citizen = Citizen::create(['name' => 'مواطن']);
        $citizen->representatives()->create(['name' => 'الحالي']);

        // نائب قديم اتضاف بعد الحالي (id أكبر) لكن نيابته منتهية
        $citizen->representatives()->create(['name' => 'قديم', 'ended_at' => '2020-01-01']);

        $this->assertSame('الحالي', $citizen->fresh()->currentRepresentative->name);

        // ونفس النتيجة مع eager loading (اللي بتستخدمه شاشة المواطنين)
        $loaded = Citizen::query()->with('currentRepresentative')->findOrFail($citizen->id);
        $this->assertSame('الحالي', $loaded->currentRepresentative->name);
    }

    public function test_citizen_without_a_current_representative_has_none(): void
    {
        $citizen = Citizen::create(['name' => 'مواطن']);
        $citizen->representatives()->create(['name' => 'قديم', 'ended_at' => '2020-01-01']);

        $this->assertNull($citizen->fresh()->currentRepresentative);
    }

    public function test_sync_down_payment_creates_the_record_then_updates_the_same_one(): void
    {
        $contract = $this->contractWithoutDownDate('D-1');   // مبلغ مقدم 1000 من غير تاريخ => مفيش سجل دفع

        $this->assertSame(0, Payment::where('contract_id', $contract->id)->count());
        $this->contracts->syncDownPayment($contract->fresh());   // من غير تاريخ: مفيش حاجة تتعمل
        $this->assertSame(0, Payment::where('contract_id', $contract->id)->count());

        // المستخدم ضاف التاريخ بعدين
        $contract->update(['down_payment_date' => '2024-01-10']);
        $this->contracts->syncDownPayment($contract->fresh(), 1);

        $payment = Payment::where('contract_id', $contract->id)->where('type', Payment::TYPE_DOWN_PAYMENT)->firstOrFail();
        $this->assertSame('1000.00', $payment->amount);
        $this->assertSame('2024-01-10', $payment->paid_at->toDateString());

        // تعديل المبلغ والتاريخ بيحدّث نفس السجل
        $contract->update(['down_payment_amount' => 1200, 'down_payment_date' => '2024-02-01']);
        $this->contracts->syncDownPayment($contract->fresh());

        $this->assertSame(1, Payment::where('contract_id', $contract->id)->where('type', Payment::TYPE_DOWN_PAYMENT)->count());
        $this->assertSame('1200.00', $payment->fresh()->amount);
        $this->assertSame('2024-02-01', $payment->fresh()->paid_at->toDateString());
    }
}
