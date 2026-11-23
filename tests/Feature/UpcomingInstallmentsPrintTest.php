<?php

namespace Tests\Feature;

use App\Models\Center;
use App\Models\Citizen;
use App\Models\PaymentPlan;
use App\Models\User;
use App\Services\ContractService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpcomingInstallmentsPrintTest extends TestCase
{
    use RefreshDatabase;

    public function test_print_page_requires_authentication(): void
    {
        $this->get(route('admin.upcoming-installments.print'))
            ->assertRedirect();
    }

    public function test_upcoming_installments_page_shows_print_action(): void
    {
        $this->actingAs(User::factory()->create(['is_active' => true]));

        $this->get('/admin/upcoming-installments')
            ->assertOk()
            ->assertSee('طباعة');
    }

    public function test_print_page_applies_center_and_due_date_filters(): void
    {
        $this->actingAs(User::factory()->create(['is_active' => true]));

        $firstCenter = Center::create(['name' => 'المركز أ']);
        $secondCenter = Center::create(['name' => 'المركز ب']);
        $plan = PaymentPlan::create([
            'name' => 'قسط واحد',
            'installments_count' => 1,
            'is_cash' => false,
        ]);

        $this->createContract('PRINT-1', $firstCenter, $plan, today()->toDateString());
        $this->createContract('PRINT-2', $secondCenter, $plan, today()->addDays(7)->toDateString());

        $this->get('/admin/upcoming-installments')
            ->assertOk()
            ->assertSeeInOrder(['PRINT-1', 'PRINT-2']);

        $this->get(route('admin.upcoming-installments.print'))
            ->assertOk()
            ->assertSee('PRINT-1')
            ->assertSee('PRINT-2');

        $this->get(route('admin.upcoming-installments.print', ['center_id' => $firstCenter->id]))
            ->assertOk()
            ->assertSee('PRINT-1')
            ->assertDontSee('PRINT-2');

        $later = today()->addDays(7)->toDateString();

        $this->get(route('admin.upcoming-installments.print', ['from' => $later, 'to' => $later]))
            ->assertOk()
            ->assertSee('PRINT-2')
            ->assertDontSee('PRINT-1');

        $this->get(route('admin.upcoming-installments.print', ['days_range' => 'today']))
            ->assertOk()
            ->assertSee('PRINT-1')
            ->assertDontSee('PRINT-2');

        $this->get(route('admin.upcoming-installments.print', [
            'sort' => 'contract_code',
            'direction' => 'desc',
        ]))
            ->assertOk()
            ->assertSeeInOrder(['PRINT-1', 'PRINT-2']);
    }

    public function test_print_supports_10_to_20_days_filter(): void
    {
        $this->actingAs(User::factory()->create(['is_active' => true]));

        $center = Center::create(['name' => 'المركز ج']);
        $plan = PaymentPlan::create([
            'name' => 'قسط واحد',
            'installments_count' => 1,
            'is_cash' => false,
        ]);

        $this->createContract('PRINT-15', $center, $plan, today()->addDays(15)->toDateString());
        $this->createContract('PRINT-5', $center, $plan, today()->addDays(5)->toDateString());

        $this->get(route('admin.upcoming-installments.print', ['days_range' => '10_20']))
            ->assertOk()
            ->assertSee('PRINT-15')
            ->assertDontSee('PRINT-5');
    }

    private function createContract(
        string $code,
        Center $center,
        PaymentPlan $plan,
        string $dueDate,
    ): void {
        (new ContractService)->create(
            Citizen::create(['name' => 'مواطن '.$code, 'center_id' => $center->id]),
            $plan,
            ['code' => $code, 'down_payment_amount' => 0, 'total_price' => 100],
            [100],
            [$dueDate],
        );
    }
}
