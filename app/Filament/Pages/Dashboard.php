<?php

namespace App\Filament\Pages;

use App\Models\Center;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * الصفحة الرئيسية بفلتر فترة ومركز. الفلاتر بتتحفظ في الجلسة وبتتطبق على كل الـ Widgets
 * (اللي بتستخدم InteractsWithPageFilters).
 */
class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('تصفية الأرقام')
                ->description('الفترة بتتطبق على تاريخ استحقاق القسط المتأخر. سيب الحقول فاضية لعرض الكل.')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    DatePicker::make('from')
                        ->label('استحقاق من تاريخ'),

                    DatePicker::make('until')
                        ->label('استحقاق حتى تاريخ'),

                    Select::make('center_id')
                        ->label('المركز')
                        ->options(fn (): array => Center::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->placeholder('كل المراكز')
                        ->searchable(),
                ]),
        ]);
    }
}
