<?php

namespace App\Filament\Resources\Contracts\RelationManagers;

use App\Models\Payment;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** سجل المدفوعات (قراءة فقط): المقدم، والكاش، وسداد الأقساط. */
class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return 'الدفعات';
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['installment', 'creator']))
            ->columns([
                TextColumn::make('type')
                    ->label('النوع')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => match ($state) {
                        Payment::TYPE_DOWN_PAYMENT => 'مقدم',
                        Payment::TYPE_INSTALLMENT => 'قسط',
                        Payment::TYPE_CASH => 'سداد كاش',
                        default => (string) $state,
                    }),

                TextColumn::make('installment.installment_no')
                    ->label('القسط')
                    ->formatStateUsing(fn ($state): string => 'القسط ' . $state)
                    ->placeholder('—'),

                TextColumn::make('amount')
                    ->label('المبلغ')
                    ->numeric(decimalPlaces: 2),

                TextColumn::make('paid_at')
                    ->label('تاريخ الدفع')
                    ->date('Y-m-d'),

                TextColumn::make('receipt_no')
                    ->label('رقم الإيصال')
                    ->placeholder('—'),

                TextColumn::make('creator.name')
                    ->label('سجّلها')
                    ->placeholder('—'),
            ])
            ->defaultSort('paid_at', 'desc')
            ->paginated(false);
    }
}
