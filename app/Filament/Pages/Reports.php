<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\OverdueReportTable;
use App\Models\Center;
use App\Models\PaymentPlan;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class Reports extends BaseDashboard
{
    use HasFiltersForm;

    protected static string $routePath = '/reports';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return 'التقارير';
    }

    public function getTitle(): string
    {
        return 'التقارير';
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('تصفية التقارير')
                ->description('اختر مركزًا أو نوع سداد لتصفية الأقساط المتأخرة. اترك الحقول فارغة لعرض الكل.')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    Select::make('center_id')
                        ->label('المركز')
                        ->options(fn (): array => Center::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->placeholder('كل المراكز')
                        ->searchable(),
                    Select::make('payment_plan_id')
                        ->label('نوع السداد')
                        ->options(fn (): array => PaymentPlan::query()->orderBy('installments_count')->pluck('name', 'id')->all())
                        ->placeholder('كل أنواع السداد')
                        ->searchable(),
                ]),
        ]);
    }

    public function getWidgets(): array
    {
        return [OverdueReportTable::class];
    }

    public function getColumns(): int|array
    {
        return 1;
    }
}
