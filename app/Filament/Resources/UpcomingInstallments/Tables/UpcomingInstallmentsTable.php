<?php

namespace App\Filament\Resources\UpcomingInstallments\Tables;

use App\Models\Center;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UpcomingInstallmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('contract_code')
                    ->label('رقم العقد')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('citizen_name')
                    ->label('اسم المواطن')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('center_name')
                    ->label('المركز')
                    ->sortable(),

                TextColumn::make('payment_plan_name')
                    ->label('نظام السداد')
                    ->toggleable(),

                TextColumn::make('installment_no')
                    ->label('رقم القسط')
                    ->sortable(),

                TextColumn::make('due_date')
                    ->label('تاريخ الاستحقاق')
                    ->date('Y-m-d')
                    ->sortable(),

                TextColumn::make('days_remaining')
                    ->label('الأيام المتبقية')
                    ->numeric()
                    ->badge()
                    ->color(fn ($state): string => match (true) {
                        (int) $state === 0 => 'danger',
                        (int) $state <= 7 => 'warning',
                        default => 'success',
                    })
                    ->formatStateUsing(fn ($state): string =>
                        (int) $state === 0
                            ? 'مستحق اليوم'
                            : 'متبقي ' . $state . ' يوم'
                    )
                    ->sortable(),

                TextColumn::make('amount')
                    ->label('قيمة القسط')
                    ->numeric(decimalPlaces: 2)
                    ->summarize(Sum::make()->label('الإجمالي')),

                TextColumn::make('remaining_amount')
                    ->label('المبلغ المتبقي')
                    ->numeric(decimalPlaces: 2)
                    ->summarize(Sum::make()->label('إجمالي المتبقي')),
            ])

            ->filters([
                SelectFilter::make('center_id')
                    ->label('المركز')
                    ->options(fn () => Center::query()
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all()),

                SelectFilter::make('days_range')
                    ->label('موعد الاستحقاق')
                    ->options([
                        'today' => 'مستحق اليوم',
                        '1_7' => 'خلال 7 أيام',
                        '8_15' => 'من 8 إلى 15 يومًا',
                        '16_30' => 'من 16 إلى 30 يومًا',
                    ])
                    ->query(function (
                        Builder $query,
                        array $data
                    ): Builder {
                        return match ($data['value'] ?? null) {
                            'today' => $query->where('days_remaining', 0),

                            '1_7' => $query->whereBetween(
                                'days_remaining',
                                [1, 7]
                            ),

                            '8_15' => $query->whereBetween(
                                'days_remaining',
                                [8, 15]
                            ),

                            '16_30' => $query->whereBetween(
                                'days_remaining',
                                [16, 30]
                            ),

                            default => $query,
                        };
                    }),
            ])

            ->defaultSort('days_remaining', 'asc')
            ->striped()
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25);
    }
}
