<?php

namespace Tests\Feature;

use App\Models\Center;
use App\Models\Citizen;
use App\Models\PaymentPlan;
use App\Services\ContractService;
use App\Services\InstallmentPaymentService;
use App\Services\OverdueStats;
use App\Support\OverdueFilters;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** أرقام لوحة التحكم. (نفس الاستعلامات اتجرّبت على MariaDB بحالات حدّية.) */
class OverdueStatsTest extends TestCase
{
    use RefreshDatabase;

    private PaymentPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->plan = PaymentPlan::create(['name' => 'ثلاث سنوات', 'installments_count' => 3, 'is_cash' => false]);
    }

    /** عقد 3 أقساط بمواعيد استحقاق نسبية لتاريخ النهارده (أيام: سالب = فات) */
    private function contract(Citizen $citizen, string $code, array $amounts, array $dayOffsets)
    {
        $contract = (new ContractService())->create(
            $citizen,
            $this->plan,
            ['code' => $code, 'down_payment_amount' => 0, 'total_price' => array_sum($amounts)],
            $amounts,
        );

        foreach ($contract->installments as $i => $installment) {
            $installment->update(['due_date' => Carbon::today()->addDays($dayOffsets[$i])->toDateString()]);
        }

        return $contract->fresh('installments');
    }

    public function test_totals_aging_and_plan_breakdown_count_only_overdue_pending_installments(): void
    {
        $citizen = Citizen::create(['name' => 'أحمد']);
        $citizen->phones()->create(['phone' => '0100']);

        // قسط متأخر 10 يوم (100) + متأخر 400 يوم (200) + لسه معاده مجاش (300)
        $this->contract($citizen, 'S-1', [100, 200, 300], [-10, -400, 100]);

        $stats = new OverdueStats();

        $this->assertSame(['installments' => 2, 'citizens' => 1, 'total' => 300.0], $stats->totals());

        $aging = collect($stats->aging())->keyBy('label');
        $this->assertSame(['amount' => 100.0, 'count' => 1], Arr::only($aging['1 - 30 يوم'], ['amount', 'count']));
        $this->assertSame(['amount' => 200.0, 'count' => 1], Arr::only($aging['أكتر من سنة'], ['amount', 'count']));
        $this->assertSame(0, $aging['31 - 90 يوم']['count']);

        $this->assertSame(
            [['label' => 'ثلاث سنوات', 'count' => 2, 'total' => 300.0]],
            $stats->byPlan()
        );
    }

    public function test_installment_due_today_is_not_counted(): void
    {
        $this->contract(Citizen::create(['name' => 'بسمة']), 'S-2', [100, 200, 300], [0, 5, 10]);

        $this->assertSame(0, (new OverdueStats())->totals()['installments']);
    }

    public function test_paid_installment_leaves_the_numbers(): void
    {
        $contract = $this->contract(Citizen::create(['name' => 'جمال']), 'S-3', [100, 200, 300], [-10, -20, 100]);
        $this->assertSame(300.0, (new OverdueStats())->totals()['total']);

        (new InstallmentPaymentService())->pay($contract->installments[0], Carbon::today());

        $this->assertSame(200.0, (new OverdueStats())->totals()['total']);
    }

    public function test_top_citizens_are_ranked_by_overdue_total_with_phones(): void
    {
        $small = Citizen::create(['name' => 'صغير']);
        $big = Citizen::create(['name' => 'كبير']);
        $big->phones()->create(['phone' => '0100']);
        $big->phones()->create(['phone' => '0101']);
        $rep = $big->representatives()->create(['name' => 'نائب']);
        $rep->phones()->create(['phone' => '0111']);

        $this->contract($small, 'S-4', [50, 50, 50], [-5, 10, 20]);
        $this->contract($big, 'S-5', [1000, 2000, 300], [-50, -60, 100]);

        $top = (new OverdueStats())->topCitizens(10);

        $this->assertSame([$big->id, $small->id], array_keys($top));
        $this->assertSame('كبير', $top[$big->id]['citizen_name']);
        $this->assertSame(3000.0, (float) $top[$big->id]['total']);
        $this->assertSame(2, (int) $top[$big->id]['installments']);
        $this->assertSame(60, (int) $top[$big->id]['max_days']);
        // ترتيب الأرقام جوه GROUP_CONCAT مش مضمون، فبنتأكد إن الاتنين موجودين
        $this->assertStringContainsString('0100', $top[$big->id]['citizen_phones']);
        $this->assertStringContainsString('0101', $top[$big->id]['citizen_phones']);
        $this->assertSame('0111', $top[$big->id]['representative_phones']);

        $this->assertCount(1, (new OverdueStats())->topCitizens(1));
    }

    public function test_empty_system_gives_zeros_not_errors(): void
    {
        $stats = new OverdueStats();

        $this->assertSame(['installments' => 0, 'citizens' => 0, 'total' => 0.0], $stats->totals());
        $this->assertSame([], $stats->byPlan());
        $this->assertSame([], $stats->topCitizens());
        $this->assertSame(0.0, $stats->aging()[0]['amount']);
    }

    public function test_period_filter_is_inclusive_on_both_ends_and_uses_the_due_date(): void
    {
        // متأخر 100 يوم (100) + 30 يوم (200) + 20 يوم (300)
        $this->contract(Citizen::create(['name' => 'أحمد']), 'F-1', [100, 200, 300], [-100, -30, -20]);

        $filters = OverdueFilters::fromArray([
            'from' => Carbon::today()->subDays(100)->toDateString(),
            'until' => Carbon::today()->subDays(30)->toDateString(),
        ]);

        $stats = new OverdueStats();

        // الطرفين شاملين: قسط 100 يوم وقسط 30 يوم داخلين، وقسط 20 يوم برا
        $this->assertSame(['installments' => 2, 'citizens' => 1, 'total' => 300.0], $stats->totals($filters));
        $this->assertSame(300.0, $stats->aging($filters)[0]['amount'] + $stats->aging($filters)[2]['amount']);

        // من غير فلتر: التلاتة
        $this->assertSame(600.0, $stats->totals()['total']);
    }

    public function test_center_filter_and_by_center_breakdown(): void
    {
        $a = Center::create(['name' => 'مركز أ']);
        $b = Center::create(['name' => 'مركز ب']);

        $this->contract(Citizen::create(['name' => 'س1', 'center_id' => $a->id]), 'C-1', [100, 100, 100], [-10, -20, 50]);
        $this->contract(Citizen::create(['name' => 'س2', 'center_id' => $b->id]), 'C-2', [500, 100, 100], [-10, 50, 60]);
        $this->contract(Citizen::create(['name' => 'س3']), 'C-3', [50, 100, 100], [-10, 50, 60]);   // من غير مركز

        $stats = new OverdueStats();
        $rows = $stats->byCenter();

        // مرتّبة بالإجمالي تنازلي: ب (500) ثم أ (200) ثم بدون مركز (50)
        $this->assertSame([$b->id, $a->id, 0], array_keys($rows));
        $this->assertSame(['مركز ب', 'مركز أ', 'بدون مركز'], array_column($rows, 'center_name'));
        $this->assertSame([500.0, 200.0, 50.0], array_column($rows, 'total'));
        $this->assertNull($rows[0]['center_id']);
        $this->assertEqualsWithDelta(100.0, array_sum(array_column($rows, 'share')), 0.2);

        // فلتر المركز بيأثر على كل الأرقام
        $onlyA = OverdueFilters::fromArray(['center_id' => $a->id]);
        $this->assertSame(['installments' => 2, 'citizens' => 1, 'total' => 200.0], $stats->totals($onlyA));
        $this->assertSame([$a->id], array_keys($stats->byCenter($onlyA)));
        $this->assertSame('س1', array_values($stats->topCitizens(10, $onlyA))[0]['citizen_name']);
    }

    public function test_filters_that_match_nothing_give_empty_results_not_errors(): void
    {
        $this->contract(Citizen::create(['name' => 'أحمد']), 'F-2', [100, 200, 300], [-10, -20, 30]);

        $none = OverdueFilters::fromArray(['from' => Carbon::today()->addDays(10)->toDateString()]);
        $stats = new OverdueStats();

        $this->assertSame(['installments' => 0, 'citizens' => 0, 'total' => 0.0], $stats->totals($none));
        $this->assertSame([], $stats->byCenter($none));
        $this->assertSame([], $stats->topCitizens(10, $none));
        $this->assertSame([], $stats->byPlan($none));
    }
}
