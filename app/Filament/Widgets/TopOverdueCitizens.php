<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Citizens\CitizenResource;
use App\Services\OverdueStats;
use App\Support\OverdueFilters;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;

/** أكتر 10 مواطنين عليهم مبلغ متأخر، ومعاهم التليفونات للتواصل. */
class TopOverdueCitizens extends TableWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 5;

    protected static ?string $heading = 'أكتر 10 مواطنين عليهم متأخرات';

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            // صفوف جاهزة من استعلام SQL (بدل Eloquent) علشان التجميع GROUP BY
            ->records(fn (): array => app(OverdueStats::class)->topCitizens(10, OverdueFilters::fromArray($this->pageFilters)))
            ->columns([
                TextColumn::make('citizen_name')
                    ->label('المواطن'),

                TextColumn::make('center_name')
                    ->label('المركز')
                    ->placeholder('—'),

                TextColumn::make('citizen_phones')
                    ->label('تليفون المواطن')
                    ->placeholder('—'),

                TextColumn::make('representative_name')
                    ->label('النائب')
                    ->placeholder('—'),

                TextColumn::make('representative_phones')
                    ->label('تليفون النائب')
                    ->placeholder('—'),

                TextColumn::make('installments')
                    ->label('عدد الأقساط')
                    ->badge(),

                TextColumn::make('max_days')
                    ->label('أقصى تأخير (يوم)')
                    ->badge()
                    ->color(fn ($state): string => match (true) {
                        $state >= 90 => 'danger',
                        $state >= 30 => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('total')
                    ->label('إجمالي المتأخر')
                    ->numeric(decimalPlaces: 2),
            ])
            ->recordActions([
                Action::make('open')
                    ->label('فتح المواطن')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (array $record): string => CitizenResource::getUrl('edit', ['record' => $record['citizen_id']])),
            ])
            ->paginated(false)
            ->emptyStateHeading('مفيش متأخرات');
    }
}
