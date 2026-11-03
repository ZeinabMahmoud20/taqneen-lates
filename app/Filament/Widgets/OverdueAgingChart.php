<?php

namespace App\Filament\Widgets;

use App\Services\OverdueStats;
use App\Support\OverdueFilters;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class OverdueAgingChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 2;

    protected ?string $heading = 'المتأخرات حسب مدة التأخير (المبلغ المتبقي)';

    protected ?string $maxHeight = '280px';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $aging = app(OverdueStats::class)->aging(OverdueFilters::fromArray($this->pageFilters));

        return [
            'datasets' => [
                [
                    'label' => 'المبلغ المتبقي',
                    'data' => array_column($aging, 'amount'),
                    'backgroundColor' => ['#facc15', '#fb923c', '#f97316', '#ef4444', '#b91c1c'],
                ],
            ],
            'labels' => array_column($aging, 'label'),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => false]],
            'scales' => ['y' => ['beginAtZero' => true]],
        ];
    }
}
