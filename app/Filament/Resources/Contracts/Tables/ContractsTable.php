<?php

namespace App\Filament\Resources\Contracts\Tables;

use App\Models\Center;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ContractsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('الكود')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('citizen.name')
                    ->label('المواطن')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('citizen.center.name')
                    ->label('المركز')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('paymentPlan.name')
                    ->label('نوع السداد')
                    ->badge(),

                TextColumn::make('total_price')
                    ->label('إجمالي الثمن')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),

                TextColumn::make('down_payment_date')
                    ->label('تاريخ المقدم')
                    ->date('Y-m-d')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('paid_installments_count')
                    ->label('المسدد')
                    ->formatStateUsing(fn ($state, $record): string => $record->paymentPlan?->is_cash
                        ? 'كاش'
                        : "{$state} / {$record->installments_count}"),

                TextColumn::make('overdue_installments_count')
                    ->label('المتأخر')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state > 0 ? $state . ' متأخر' : '—')
                    ->color(fn ($state): string => $state > 0 ? 'danger' : 'gray'),

                IconColumn::make('missing_due_date_count')
                    ->label('بيانات ناقصة')
                    ->icon(fn ($state): ?string => $state > 0 ? 'heroicon-o-exclamation-triangle' : null)
                    ->color('warning')
                    ->tooltip(fn ($state): ?string => $state > 0 ? $state . ' قسط بدون موعد استحقاق' : null),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('payment_plan_id')
                    ->label('نوع السداد')
                    ->relationship('paymentPlan', 'name'),

                SelectFilter::make('center')
                    ->label('المركز')
                    ->options(fn (): array => Center::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereHas('citizen', fn (Builder $q) => $q->where('center_id', $data['value']))
                        : $query),

                Filter::make('has_overdue')
                    ->label('فيه أقساط متأخرة')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->whereHas('installments', fn (Builder $q) => $q->overdue())),

                Filter::make('missing_data')
                    ->label('بيانات ناقصة (قسط بدون موعد استحقاق)')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->whereHas('installments', fn (Builder $q) => $q->missingDueDate())),
            ])
            ->recordActions([
                EditAction::make()->label('فتح'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25);
    }
}
