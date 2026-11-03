<?php

namespace App\Filament\Widgets;

use App\Services\OverdueStats;
use App\Support\OverdueFilters;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class OverduePlanChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 3;

    protected ?string $heading = 'المتأخرات حسب نوع السداد (عدد الأقساط)';

    protected ?string $maxHeight = '280px';

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $plans = app(OverdueStats::class)->byPlan(OverdueFilters::fromArray($this->pageFilters));

        return [
            'datasets' => [
                [
                    'label' => 'عدد الأقساط المتأخرة',
                    'data' => array_column($plans, 'count'),
                    'backgroundColor' => ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6'],
                ],
            ],
            'labels' => array_column($plans, 'label'),
        ];
    }
}
