<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Contracts\ContractResource;
use App\Filament\Resources\OverdueInstallments\OverdueInstallmentResource;
use App\Models\Center;
use App\Models\Installment;
use App\Models\Payment;
use App\Services\OverdueStats;
use App\Support\OverdueFilters;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OverdueStatsOverview extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 1;

    protected ?string $heading = 'نظرة عامة على المتأخرات';

    protected function getStats(): array
    {
        $filters = OverdueFilters::fromArray($this->pageFilters);
        $centerId = $filters->centerId;
        $totals = app(OverdueStats::class)->totals($filters);

        $summary = $filters->summary($centerId ? Center::query()->whereKey($centerId)->value('name') : null);

        // المركز بيتطبق على التحصيل والبيانات الناقصة كمان. الفترة للمتأخرات بس (بتتحسب على الاستحقاق).
        $inCenter = fn ($query) => $query->when(
            $centerId,
            fn ($q) => $q->whereHas('contract.citizen', fn ($citizen) => $citizen->where('center_id', $centerId))
        );

        $collectedThisMonth = (float) $inCenter(Payment::query())
            ->where('type', Payment::TYPE_INSTALLMENT)
            ->whereBetween('paid_at', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
            ->sum('amount');

        $missingDueDate = $inCenter(Installment::query()->missingDueDate())->count();

        return [
            Stat::make('إجمالي المتأخرات', number_format($totals['total'], 2))
                ->description($totals['installments'] . ' قسط متأخر' . ($summary ? " ({$summary})" : ''))
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger')
                ->url(OverdueInstallmentResource::getUrl('index')),

            Stat::make('عدد المتأخرين', number_format($totals['citizens']))
                ->description('مواطن عليه قسط متأخر أو أكتر')
                ->descriptionIcon('heroicon-m-users')
                ->color('warning'),

            Stat::make('اتحصّل هذا الشهر', number_format($collectedThisMonth, 2))
                ->description('أقساط اتسددت في شهر ' . now()->format('Y-m'))
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('بيانات ناقصة', number_format($missingDueDate))
                ->description('قسط لسه ماتدفعش ومن غير موعد استحقاق')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color($missingDueDate > 0 ? 'warning' : 'gray')
                ->url(ContractResource::getUrl('index', ['filters' => ['missing_data' => ['isActive' => true]]])),
        ];
    }
}
