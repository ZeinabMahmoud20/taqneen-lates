<?php

namespace App\Filament\Resources\OverdueInstallments;

use App\Filament\Resources\OverdueInstallments\Pages\ListOverdueInstallments;
use App\Filament\Resources\OverdueInstallments\Tables\OverdueInstallmentsTable;
use App\Models\OverdueInstallment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * شاشة المتأخرات - قراءة فقط (عرض وتصدير).
 * مبنية على view اسمه overdue_installments.
 */
class OverdueInstallmentResource extends Resource
{
    protected static ?string $model = OverdueInstallment::class;

    public static function getModelLabel(): string
    {
        return 'قسط متأخر';
    }

    public static function getPluralModelLabel(): string
    {
        return 'المتأخرات';
    }

    public static function getNavigationLabel(): string
    {
        return 'المتأخرات';
    }

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return 'heroicon-o-exclamation-triangle';
    }

    public static function getNavigationSort(): ?int
    {
        return 1;
    }

    // عدد الأقساط المتأخرة بجنب اسم الشاشة في القائمة
    public static function getNavigationBadge(): ?string
    {
        return (string) OverdueInstallment::query()->count();
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'danger';
    }

    public static function table(Table $table): Table
    {
        return OverdueInstallmentsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOverdueInstallments::route('/'),
        ];
    }

    // قراءة فقط: مفيش إضافة ولا تعديل ولا حذف
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }
}
