<?php

namespace App\Filament\Resources\UpcomingInstallments;

use App\Models\UpcomingInstallment;
use App\Filament\Resources\UpcomingInstallments\Tables\UpcomingInstallmentsTable;
use Filament\Resources\Resource;
use Filament\Tables\Table;

class UpcomingInstallmentResource extends Resource
{
    protected static ?string $model = UpcomingInstallment::class;

    protected static ?string $navigationLabel = 'الأقساط القادمة';

    protected static ?string $modelLabel = 'قسط قادم';

    protected static ?string $pluralModelLabel = 'الأقساط القادمة';

    protected static string|\BackedEnum|null $navigationIcon =
        'heroicon-o-calendar-days';

    protected static ?int $navigationSort = 2;

    public static function getNavigationBadge(): ?string
    {
        return (string) UpcomingInstallment::query()->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function table(Table $table): Table
    {
        return UpcomingInstallmentsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUpcomingInstallments::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }
}
