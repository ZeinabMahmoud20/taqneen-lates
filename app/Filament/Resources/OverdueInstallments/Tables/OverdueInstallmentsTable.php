<?php

namespace App\Filament\Resources\OverdueInstallments\Tables;

use App\Models\Center;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OverdueInstallmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('contract_code')
                    ->label('الكود')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('citizen_name')
                    ->label('اسم المواطن')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('center_name')
                    ->label('المركز')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('citizen_phones')
                    ->label('تليفون المواطن')
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('representative_name')
                    ->label('النائب')
                    ->description(fn ($record): ?string => $record->representative_relationship)
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('representative_phones')
                    ->label('تليفون النائب')
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('payment_plan_name')
                    ->label('نوع السداد')
                    ->badge()
                    ->toggleable(),

                TextColumn::make('installment_no')
                    ->label('القسط')
                    ->formatStateUsing(fn ($state): string => 'القسط ' . $state)
                    ->sortable(),

                TextColumn::make('due_date')
                    ->label('تاريخ الاستحقاق')
                    ->date('Y-m-d')
                    ->sortable(),

                TextColumn::make('days_overdue')
                    ->label('أيام التأخير')
                    ->badge()
                    ->color(fn ($state): string => match (true) {
                        $state >= 90 => 'danger',
                        $state >= 30 => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('amount')
                    ->label('قيمة القسط')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),

                TextColumn::make('remaining_amount')
                    ->label('المتبقي')
                    ->numeric(decimalPlaces: 2)
                    ->sortable()
                    ->summarize(Sum::make()->label('الإجمالي')),
            ])
            ->defaultSort('days_overdue', 'desc')
            ->filters([
                SelectFilter::make('center_id')
                    ->label('المركز')
                    ->options(fn (): array => Center::query()->orderBy('name')->pluck('name', 'id')->all()),

                SelectFilter::make('days_range')
                    ->label('مدة التأخير')
                    ->options([
                        '30' => '30 يوم فأكثر',
                        '90' => '90 يوم فأكثر',
                        '180' => '180 يوم فأكثر',
                        '365' => 'سنة فأكثر',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->where('days_overdue', '>=', (int) $data['value'])
                        : $query),
            ])
            ->striped()
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25);
    }
}
