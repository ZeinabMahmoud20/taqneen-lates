<?php

namespace Tests\Unit;

use App\Support\OverdueFilters;
use PHPUnit\Framework\TestCase;

class OverdueFiltersTest extends TestCase
{
    public function test_empty_input_means_no_filter_and_no_where(): void
    {
        foreach ([null, [], ['from' => null, 'until' => '', 'center_id' => null]] as $raw) {
            $filters = OverdueFilters::fromArray($raw);

            $this->assertFalse($filters->isActive());
            $this->assertSame(['', []], $filters->where());
            $this->assertSame(null, $filters->summary());
        }
    }

    public function test_period_and_center_build_a_parameterised_where(): void
    {
        $filters = OverdueFilters::fromArray(['from' => '2026-01-01', 'until' => '2026-03-31', 'center_id' => '5']);

        $this->assertTrue($filters->isActive());
        $this->assertSame(
            [' WHERE due_date >= ? AND due_date <= ? AND center_id = ?', ['2026-01-01', '2026-03-31', 5]],
            $filters->where()
        );
    }

    public function test_each_filter_works_alone(): void
    {
        $this->assertSame([' WHERE due_date >= ?', ['2026-01-01']], OverdueFilters::fromArray(['from' => '2026-01-01'])->where());
        $this->assertSame([' WHERE due_date <= ?', ['2026-03-31']], OverdueFilters::fromArray(['until' => '2026-03-31'])->where());
        $this->assertSame([' WHERE center_id = ?', [7]], OverdueFilters::fromArray(['center_id' => 7])->where());
    }

    public function test_reversed_period_is_swapped(): void
    {
        $filters = OverdueFilters::fromArray(['from' => '2026-06-01', 'until' => '2026-01-01']);

        $this->assertSame('2026-01-01', $filters->from);
        $this->assertSame('2026-06-01', $filters->until);
    }

    public function test_datetime_values_are_trimmed_to_the_date(): void
    {
        $this->assertSame('2026-02-03', OverdueFilters::fromArray(['from' => '2026-02-03 00:00:00'])->from);
    }

    public function test_garbage_is_ignored_instead_of_breaking_the_page(): void
    {
        $filters = OverdueFilters::fromArray([
            'from' => '31/12/2026',
            'until' => '2026-02-30',          // تاريخ مش موجود
            'center_id' => "5; DROP TABLE x",  // حقن
        ]);

        $this->assertFalse($filters->isActive());
        $this->assertSame(['', []], $filters->where());

        $this->assertSame(null, OverdueFilters::fromArray(['center_id' => 0])->centerId);
        $this->assertSame(null, OverdueFilters::fromArray(['center_id' => -3])->centerId);
        $this->assertSame(null, OverdueFilters::fromArray(['from' => ['x']])->from);
    }

    public function test_values_never_appear_inside_the_sql_text(): void
    {
        [$where] = OverdueFilters::fromArray(['from' => '2026-01-01', 'center_id' => 9])->where();

        $this->assertStringNotContainsString('2026', $where);
        $this->assertStringNotContainsString('9', $where);
    }

    public function test_summary_describes_the_active_filters(): void
    {
        $this->assertSame(
            'الاستحقاق من 2026-01-01 إلى 2026-03-31 | المركز: بلطيم',
            OverdueFilters::fromArray(['from' => '2026-01-01', 'until' => '2026-03-31', 'center_id' => 2])->summary('بلطيم')
        );
        $this->assertSame('الاستحقاق من 2026-01-01', OverdueFilters::fromArray(['from' => '2026-01-01'])->summary());
        $this->assertSame('الاستحقاق حتى 2026-03-31', OverdueFilters::fromArray(['until' => '2026-03-31'])->summary());
        $this->assertSame('المركز: #4', OverdueFilters::fromArray(['center_id' => 4])->summary());
    }
}
