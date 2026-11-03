<?php

namespace App\Filament\Resources\Contracts\RelationManagers;

use App\Models\Installment;
use App\Models\InstallmentNote;
use App\Services\ContractService;
use App\Services\InstallmentPaymentService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * أقساط العقد: تاريخ السداد المستحق (due_date) وتاريخ السداد الفعلي (paid_at).
 * القسط بيتسدد بقيمته كاملة. لو المبلغ الفعلي مختلف، عدّل قيمة القسط الأول وبعدين سجّل السداد.
 */
class InstallmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'installments';

    protected static ?string $recordTitleAttribute = 'installment_no';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return 'الأقساط';
    }

    // نموذج تعديل القسط (للأقساط اللي لسه ماتسددتش بس)
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('amount')
                    ->label('قيمة القسط')
                    ->numeric()
                    ->required()
                    ->minValue(0.01),

                DatePicker::make('due_date')
                    ->label('تاريخ السداد المستحق')
                    ->helperText('سيبه فاضي لو مش معروف. القسط من غير موعد مش بيظهر في المتأخرات.'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount('notes'))
            ->columns([
                TextColumn::make('installment_no')
                    ->label('القسط')
                    ->formatStateUsing(fn ($state): string => 'القسط ' . $state),

                TextColumn::make('due_date')
                    ->label('تاريخ السداد المستحق')
                    ->date('Y-m-d')
                    ->placeholder('بدون موعد استحقاق'),

                TextColumn::make('amount')
                    ->label('المبلغ')
                    ->numeric(decimalPlaces: 2),

                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn ($state, Installment $record): string => match (true) {
                        $state === Installment::STATUS_PAID => 'مسدد',
                        $record->due_date !== null && $record->due_date->lt(today()) => 'متأخر',
                        default => 'لم يُسدد',
                    })
                    ->color(fn ($state, Installment $record): string => match (true) {
                        $state === Installment::STATUS_PAID => 'success',
                        $record->due_date !== null && $record->due_date->lt(today()) => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('paid_at')
                    ->label('تاريخ السداد الفعلي')
                    ->date('Y-m-d')
                    ->placeholder('—'),

                TextColumn::make('notes_count')
                    ->label('ملاحظات')
                    ->formatStateUsing(fn ($state): string => $state > 0 ? (string) $state : '—'),
            ])
            ->paginated(false)
            ->filters([
                Filter::make('missing_due_date')
                    ->label('بدون موعد استحقاق')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->missingDueDate()),
            ])
            ->headerActions([
                $this->fillDueDatesAction(),
            ])
            ->recordActions([
                $this->payAction(),
                $this->noteAction(),
                EditAction::make()
                    ->visible(fn (Installment $record): bool => ! $record->isPaid()),
                $this->voidAction(),
            ]);
    }

    private function payAction(): Action
    {
        return Action::make('pay')
            ->label('تسجيل سداد')
            ->icon('heroicon-o-banknotes')
            ->color('success')
            ->visible(fn (Installment $record): bool => ! $record->isPaid()
                && (auth()->user()?->can('update', $record) ?? false))
            ->modalHeading(fn (Installment $record): string => 'تسجيل سداد القسط رقم ' . $record->installment_no)
            ->modalDescription(fn (Installment $record): string => 'المبلغ المستحق: '
                . number_format((float) $record->amount, 2) . ' (القسط بيتسدد بقيمته كاملة)')
            ->modalSubmitActionLabel('تسجيل السداد')
            ->schema([
                DatePicker::make('paid_at')
                    ->label('تاريخ السداد الفعلي')
                    ->default(today()->toDateString())
                    ->maxDate(today())
                    ->required(),

                TextInput::make('receipt_no')
                    ->label('رقم الإيصال (اختياري)')
                    ->maxLength(100),
            ])
            ->action(function (Installment $record, array $data): void {
                try {
                    app(InstallmentPaymentService::class)->pay(
                        $record,
                        $data['paid_at'],
                        filled($data['receipt_no'] ?? null) ? $data['receipt_no'] : null,
                        auth()->id(),
                    );
                } catch (ValidationException $e) {
                    Notification::make()
                        ->danger()
                        ->title('ماتسجلش السداد')
                        ->body(collect($e->errors())->flatten()->implode(' '))
                        ->send();

                    return;
                }

                Notification::make()->success()->title('اتسجل السداد')->send();
            });
    }

    private function noteAction(): Action
    {
        return Action::make('note')
            ->label('ملاحظة')
            ->icon('heroicon-o-chat-bubble-left-ellipsis')
            ->color('gray')
            ->visible(fn (): bool => auth()->user()?->can('create', InstallmentNote::class) ?? false)
            ->modalHeading(fn (Installment $record): string => 'ملاحظة على القسط رقم ' . $record->installment_no)
            ->schema([
                Textarea::make('note')
                    ->label('الملاحظة')
                    ->required()
                    ->rows(3),
            ])
            ->action(function (Installment $record, array $data): void {
                InstallmentNote::create([
                    'installment_id' => $record->id,
                    'user_id' => auth()->id(),
                    'note' => $data['note'],
                ]);

                Notification::make()->success()->title('اتسجلت الملاحظة')->send();
            });
    }

    // إلغاء سداد (تصحيح خطأ إدخال): للمشرف العام بس عن طريق صلاحية الحذف
    private function voidAction(): Action
    {
        return Action::make('void')
            ->label('إلغاء السداد')
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('إلغاء سداد القسط')
            ->modalDescription('هيتمسح سجل الدفع ويرجع القسط «لم يُسدد». العملية بتتسجل في سجل العمليات.')
            ->visible(fn (Installment $record): bool => $record->isPaid()
                && (auth()->user()?->can('delete', $record) ?? false))
            ->action(function (Installment $record): void {
                try {
                    app(InstallmentPaymentService::class)->void($record, auth()->id());
                } catch (ValidationException $e) {
                    Notification::make()
                        ->danger()
                        ->title('ماتلغاش السداد')
                        ->body(collect($e->errors())->flatten()->implode(' '))
                        ->send();

                    return;
                }

                Notification::make()->success()->title('اتلغى السداد')->send();
            });
    }

    // بعد ما المستخدم يضيف تاريخ المقدم للعقد: يحسب الأقساط اللي موعدها فاضي
    private function fillDueDatesAction(): Action
    {
        return Action::make('fill_due_dates')
            ->label('حساب المواعيد الفاضية من تاريخ المقدم')
            ->icon('heroicon-o-calendar-days')
            ->color('warning')
            ->requiresConfirmation()
            ->modalDescription('هيتحسب موعد كل قسط موعده فاضي = تاريخ المقدم + رقم القسط بالسنين. الأقساط اللي ليها موعد مش هتتغير.')
            ->visible(fn (): bool => $this->getOwnerRecord()->down_payment_date !== null
                && $this->getOwnerRecord()->installments()->whereNull('due_date')->exists()
                && (auth()->user()?->can('update', $this->getOwnerRecord()) ?? false))
            ->action(function (): void {
                $filled = app(ContractService::class)->fillMissingDueDates($this->getOwnerRecord());

                Notification::make()
                    ->success()
                    ->title("اتحسبت مواعيد {$filled} قسط")
                    ->send();
            });
    }
}
