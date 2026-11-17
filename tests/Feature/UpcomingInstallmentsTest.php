<?php

namespace Tests\Feature;

use App\Models\Citizen;
use App\Models\Installment;
use App\Models\PaymentPlan;
use App\Services\ContractService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class UpcomingInstallmentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_upcoming_scope_includes_today_and_the_selected_day_and_excludes_other_installments(): void
    {
        $plan = PaymentPlan::create([
            'name' => 'ثلاث سنوات',
            'installments_count' => 3,
            'is_cash' => false,
        ]);
        $contract = (new ContractService())->create(
            Citizen::create(['name' => 'مواطن']),
            $plan,
            ['code' => 'UP-1', 'down_payment_amount' => 0, 'total_price' => 400],
            [100, 100, 100],
        );

        $installments = $contract->installments;
        $installments[0]->update(['due_date' => Carbon::today()->toDateString()]);
        $installments[1]->update(['due_date' => Carbon::today()->addDays(7)->toDateString()]);
        $installments[2]->update(['due_date' => Carbon::today()->addDays(8)->toDateString()]);
        $installments[2]->update(['status' => Installment::STATUS_PAID]);

        $this->assertSame(
            [$installments[0]->id, $installments[1]->id],
            Installment::query()
                ->upcomingWithinDays(7)
                ->orderBy('id')
                ->pluck('id')
                ->all(),
        );
    }

    public function test_upcoming_scope_with_zero_days_only_includes_installments_due_today(): void
    {
        $plan = PaymentPlan::create([
            'name' => 'ثلاث سنوات',
            'installments_count' => 3,
            'is_cash' => false,
        ]);
        $contract = (new ContractService())->create(
            Citizen::create(['name' => 'مواطن']),
            $plan,
            ['code' => 'UP-2', 'down_payment_amount' => 0, 'total_price' => 300],
            [100, 100, 100],
        );

        $installments = $contract->installments;
        $installments[0]->update(['due_date' => Carbon::today()->subDay()->toDateString()]);
        $installments[1]->update(['due_date' => Carbon::today()->toDateString()]);
        $installments[2]->update(['due_date' => Carbon::today()->addDay()->toDateString()]);

        $this->assertSame(
            [$installments[1]->id],
            Installment::query()->upcomingWithinDays(0)->pluck('id')->all(),
        );
    }
}
