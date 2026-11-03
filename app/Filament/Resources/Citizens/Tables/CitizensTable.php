<?php

namespace App\Filament\Resources\Citizens\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CitizensTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with(['center', 'phones', 'currentRepresentative'])
                ->withCount('contracts'))
            ->columns([
                TextColumn::make('name')
                    ->label('الاسم')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('national_id')
                    ->label('الرقم القومي')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('center.name')
                    ->label('المركز')
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('phones.phone')
                    ->label('تليفون المواطن')
                    ->listWithLineBreaks()
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('currentRepresentative.name')
                    ->label('النائب الحالي')
                    ->placeholder('—'),

                TextColumn::make('contracts_count')
                    ->label('العقود')
                    ->badge()
                    ->sortable(),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('center_id')
                    ->label('المركز')
                    ->relationship('center', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                EditAction::make(),
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
