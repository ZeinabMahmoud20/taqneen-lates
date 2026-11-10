<?php

namespace App\Filament\Resources\OverdueInstallments;

use App\Filament\Resources\OverdueInstallments\Pages\ListOverdueInstallments;
use App\Filament\Resources\OverdueInstallments\Tables\OverdueInstallmentsTable;
use App\Models\OverdueInstallment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

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
        $table = OverdueInstallmentsTable::configure($table);

        // عمود "مرات التأخير": عدد الأقساط المتأخرة لنفس المواطن، بعد اسمه مباشرة.
        $columns = [];

        foreach ($table->getColumns() as $name => $column) {
            $columns[$name] = $column;

            if ($name === 'citizen_name') {
                $columns['citizen_overdue_count'] = TextColumn::make('citizen_overdue_count')
                    ->label('مرات التأخير')
                    ->badge()
                    ->color(fn ($state): string => ((int) $state) > 1 ? 'danger' : 'gray');
            }
        }

        $table->columns($columns);

        return $table
            // نفس تعريف view المتأخرات (قسط pending وموعد استحقاقه عدّى).
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->addSelect([
                'citizen_overdue_count' => DB::table('overdue_installments as citizen_overdue')
                    ->selectRaw('count(*)')
                    ->whereColumn('citizen_overdue.citizen_id', 'overdue_installments.citizen_id'),
            ]))
            // صفوف كل مركز تظهر تحت بعضها، وداخل المركز الأسماء أبجديًا.
            ->defaultSort(fn (Builder $query): Builder => $query
                ->orderBy('center_name')
                ->orderBy('citizen_name'));
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
