<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\Contracts\Pages\EditContract;
use App\Models\Citizen;
use App\Models\Installment;
use App\Models\Payment;
use App\Models\PaymentPlan;
use App\Models\User;
use App\Services\ContractService;
use App\Services\InstallmentPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EditContractPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_plan_can_be_changed_and_installment_rows_follow_it(): void
    {
        $this->actingAs(User::factory()->create([
            'role' => UserRole::SuperAdmin,
            'is_active' => true,
        ]));

        $threeYears = PaymentPlan::create(['name' => 'ثلاث سنوات', 'installments_count' => 3, 'is_cash' => false]);
        $fiveYears = PaymentPlan::create(['name' => 'خمس سنوات', 'installments_count' => 5, 'is_cash' => false]);

        $contract = app(ContractService::class)->create(
            Citizen::create(['name' => 'مواطن']),
            $threeYears,
            [
                'code' => 'EDIT-PAGE-1',
                'down_payment_amount' => 1000,
                'down_payment_date' => '2024-01-10',
                'total_price' => 1600,
            ],
            [100, 200, 300],
        );

        app(InstallmentPaymentService::class)->pay($contract->installments->first(), '2025-01-20');

        Livewire::test(EditContract::class, ['record' => $contract->id])
            // المسدد مش ظاهر في النموذج، وقيمته بتتسجل للإجمالي
            ->assertSet('data.installments', fn (array $rows): bool => count($rows) === 2)
            ->assertSet('data.paid_installments_total', '100.00')
            // تغيير النوع بيعمل عدد جديد من الخانات = أقساط النوع - المسدد
            ->set('data.payment_plan_id', $fiveYears->id)
            ->assertSet('data.installments', fn (array $rows): bool => count($rows) === 4)
            ->set('data.installments.0.amount', 111)
            ->set('data.installments.1.amount', 222)
            ->set('data.installments.2.amount', 333)
            ->set('data.installments.3.amount', 444)
            ->call('save')
            ->assertHasNoFormErrors();

        $contract->refresh();
        $this->assertSame($fiveYears->id, $contract->payment_plan_id);
        $this->assertSame('2210.00', $contract->total_price);
        $this->assertSame(5, $contract->installments()->count());
        $this->assertSame(
            ['100.00', '111.00', '222.00', '333.00', '444.00'],
            $contract->installments()->orderBy('installment_no')->pluck('amount')->all(),
        );

        $paid = $contract->installments()->where('status', Installment::STATUS_PAID)->firstOrFail();
        $this->assertSame('2025-01-20', $paid->paid_at->toDateString());
    }

    public function test_saving_without_changing_the_plan_keeps_the_same_installment_numbers(): void
    {
        $this->actingAs(User::factory()->create([
            'role' => UserRole::SuperAdmin,
            'is_active' => true,
        ]));

        $plan = PaymentPlan::create(['name' => 'ثلاث سنوات', 'installments_count' => 3, 'is_cash' => false]);
        $contract = app(ContractService::class)->create(
            Citizen::create(['name' => 'مواطن']),
            $plan,
            [
                'code' => 'EDIT-PAGE-2',
                'down_payment_amount' => 1000,
                'down_payment_date' => '2024-01-10',
                'total_price' => 1600,
            ],
            [100, 200, 300],
        );

        $page = Livewire::test(EditContract::class, ['record' => $contract->id])
            ->assertSet('data.installments', fn (array $rows): bool => count($rows) === 3);

        // الـ Repeater بيستخدم مفاتيح UUID للصفوف، مش أرقام متسلسلة
        $rowKeys = array_keys($page->get('data.installments'));
        $page->set("data.installments.{$rowKeys[1]}.amount", 250)
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(
            ['100.00', '250.00', '300.00'],
            $contract->installments()->orderBy('installment_no')->pluck('amount')->all(),
        );
        $this->assertSame('1650.00', $contract->fresh()->total_price);
        $this->assertSame(
            ['2025-01-10', '2026-01-10', '2027-01-10'],
            $contract->installments()->orderBy('installment_no')->get()->map(fn (Installment $i) => $i->due_date->toDateString())->all(),
        );
    }

    public function test_switching_to_cash_from_the_edit_form(): void
    {
        $this->actingAs(User::factory()->create([
            'role' => UserRole::SuperAdmin,
            'is_active' => true,
        ]));

        $threeYears = PaymentPlan::create(['name' => 'ثلاث سنوات', 'installments_count' => 3, 'is_cash' => false]);
        $cash = PaymentPlan::create(['name' => 'كاش', 'installments_count' => 0, 'is_cash' => true]);

        $contract = app(ContractService::class)->create(
            Citizen::create(['name' => 'مواطن']),
            $threeYears,
            [
                'code' => 'EDIT-PAGE-3',
                'down_payment_amount' => 1000,
                'down_payment_date' => '2024-01-10',
                'total_price' => 1600,
            ],
            [100, 200, 300],
        );

        Livewire::test(EditContract::class, ['record' => $contract->id])
            ->set('data.payment_plan_id', $cash->id)
            ->set('data.cash_amount', 1500)
            ->call('save')
            ->assertHasNoFormErrors();

        $contract->refresh();
        $this->assertSame($cash->id, $contract->payment_plan_id);
        $this->assertSame('1500.00', $contract->total_price);
        $this->assertSame(0, $contract->installments()->count());
        $this->assertSame(0.0, (float) $contract->down_payment_amount);
        $this->assertNull($contract->down_payment_date);
        $this->assertSame(1, $contract->payments()->where('type', Payment::TYPE_CASH)->count());
        $this->assertSame(0, $contract->payments()->where('type', Payment::TYPE_DOWN_PAYMENT)->count());
    }

    public function test_switching_from_cash_to_an_installment_plan(): void
    {
        $this->actingAs(User::factory()->create([
            'role' => UserRole::SuperAdmin,
            'is_active' => true,
        ]));

        $cash = PaymentPlan::create(['name' => 'كاش', 'installments_count' => 0, 'is_cash' => true]);
        $threeYears = PaymentPlan::create(['name' => 'ثلاث سنوات', 'installments_count' => 3, 'is_cash' => false]);

        $contract = app(ContractService::class)->create(
            Citizen::create(['name' => 'مواطن']),
            $cash,
            ['code' => 'EDIT-PAGE-4', 'total_price' => 1500, 'cash_paid_at' => '2025-02-01'],
        );

        Livewire::test(EditContract::class, ['record' => $contract->id])
            ->assertSet('data.cash_amount', '1500.00')
            ->set('data.payment_plan_id', $threeYears->id)
            ->assertSet('data.installments', fn (array $rows): bool => count($rows) === 3)
            ->set('data.down_payment_amount', 300)
            ->set('data.installments.0.amount', 400)
            ->set('data.installments.1.amount', 400)
            ->set('data.installments.2.amount', 400)
            ->call('save')
            ->assertHasNoFormErrors();

        $contract->refresh();
        $this->assertSame($threeYears->id, $contract->payment_plan_id);
        $this->assertSame('1500.00', $contract->total_price);
        $this->assertSame(3, $contract->installments()->count());
        $this->assertSame(0, $contract->payments()->where('type', Payment::TYPE_CASH)->count());
    }
}
