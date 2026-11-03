<?php

namespace App\Filament\Resources\Contracts\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** كل ملاحظات أقساط العقد (قراءة فقط). الإضافة من زرار «ملاحظة» على القسط نفسه. */
class NotesRelationManager extends RelationManager
{
    protected static string $relationship = 'notes';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return 'الملاحظات';
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['installment', 'user']))
            ->columns([
                TextColumn::make('installment.installment_no')
                    ->label('القسط')
                    ->formatStateUsing(fn ($state): string => 'القسط ' . $state),

                TextColumn::make('note')
                    ->label('الملاحظة')
                    ->wrap(),

                TextColumn::make('user.name')
                    ->label('كتبها')
                    ->placeholder('—'),

                TextColumn::make('created_at')
                    ->label('التاريخ')
                    ->dateTime('Y-m-d H:i'),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated(false);
    }
}
