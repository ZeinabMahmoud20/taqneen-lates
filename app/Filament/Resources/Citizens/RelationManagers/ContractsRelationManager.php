<?php

namespace App\Filament\Resources\Citizens\RelationManagers;

use App\Filament\Resources\Contracts\ContractResource;
use App\Models\Contract;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** عقود المواطن (عرض + فتح العقد). إضافة عقد جديد بتتم من شاشة العقود. */
class ContractsRelationManager extends RelationManager
{
    protected static string $relationship = 'contracts';

    protected static ?string $recordTitleAttribute = 'code';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return 'العقود';
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with('paymentPlan')
                ->withCount([
                    'installments',
                    'installments as paid_installments_count' => fn (Builder $q) => $q->where('status', 'paid'),
                ]))
            ->columns([
                TextColumn::make('code')
                    ->label('الكود'),

                TextColumn::make('paymentPlan.name')
                    ->label('نوع السداد')
                    ->badge(),

                TextColumn::make('total_price')
                    ->label('إجمالي الثمن')
                    ->numeric(decimalPlaces: 2),

                TextColumn::make('paid_installments_count')
                    ->label('المسدد')
                    ->formatStateUsing(fn ($state, $record): string => $record->paymentPlan?->is_cash
                        ? 'كاش'
                        : "{$state} / {$record->installments_count}"),
            ])
            ->defaultSort('id', 'desc')
            ->paginated(false)
            ->headerActions([
                Action::make('create_contract')
                    ->label('إضافة عقد')
                    ->icon('heroicon-o-plus')
                    ->visible(fn (): bool => auth()->user()?->can('create', Contract::class) ?? false)
                    ->url(fn (): string => ContractResource::getUrl('create', ['citizen_id' => $this->getOwnerRecord()->getKey()])),
            ])
            ->recordActions([
                Action::make('open')
                    ->label('فتح')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Contract $record): string => ContractResource::getUrl('edit', ['record' => $record])),
            ]);
    }
}
