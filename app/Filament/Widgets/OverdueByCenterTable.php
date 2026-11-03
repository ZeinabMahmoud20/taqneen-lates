<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\OverdueInstallments\OverdueInstallmentResource;
use App\Services\OverdueStats;
use App\Support\OverdueFilters;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;

/** المتأخرات حسب المركز (بتتأثر بفلتر الفترة). */
class OverdueByCenterTable extends TableWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 4;

    protected static ?string $heading = 'المتأخرات حسب المركز';

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): array => app(OverdueStats::class)->byCenter(OverdueFilters::fromArray($this->pageFilters)))
            ->columns([
                TextColumn::make('center_name')
                    ->label('المركز'),

                TextColumn::make('citizens')
                    ->label('عدد المتأخرين')
                    ->badge(),

                TextColumn::make('installments')
                    ->label('عدد الأقساط'),

                TextColumn::make('total')
                    ->label('إجمالي المتأخر')
                    ->numeric(decimalPlaces: 2),

                TextColumn::make('share')
                    ->label('النسبة من الإجمالي')
                    ->formatStateUsing(fn ($state): string => $state . '%'),
            ])
            ->recordActions([
                // بيفتح شاشة المتأخرات مفلترة على المركز ده
                Action::make('open')
                    ->label('عرض المتأخرات')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->visible(fn (array $record): bool => $record['center_id'] !== null)
                    ->url(fn (array $record): string => OverdueInstallmentResource::getUrl('index', [
                        'filters' => ['center_id' => ['value' => $record['center_id']]],
                    ])),
            ])
            ->paginated(false)
            ->emptyStateHeading('مفيش متأخرات');
    }
}
