<?php

namespace App\Filament\Resources\Contracts\Schemas;

use App\Models\Contract;
use App\Models\Installment;
use App\Models\PaymentPlan;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class ContractForm
{
    public static function configure(Schema $schema): Schema
    {
        // هل نوع السداد المختار كاش (سداد 100%)؟
        $isCash = fn (Get $get): bool => (bool) PaymentPlan::query()
            ->whereKey($get('payment_plan_id'))
            ->value('is_cash');

        $hasPlan = fn (Get $get): bool => filled($get('payment_plan_id'));

        // إجمالي الثمن = المقدم/الكاش + الأقساط المسددة (اللي مابتتغيرش) + الأقساط اللي بتتكتب في النموذج
        $calculateTotal = function (mixed $planId, mixed $downPayment, mixed $cashAmount, mixed $paidTotal, mixed $rows): string {
            $plan = PaymentPlan::query()->find($planId);

            if ($plan?->is_cash) {
                return number_format(round((float) ($cashAmount ?? 0), 2), 2, '.', '');
            }

            $total = (float) ($downPayment ?? 0)
                + (float) ($paidTotal ?? 0)
                + collect(is_array($rows) ? $rows : [])->sum(function (mixed $row): float {
                    return (float) (is_array($row) ? ($row['amount'] ?? 0) : $row);
                });

            return number_format(round($total, 2), 2, '.', '');
        };

        $refreshTotal = function (Get $get, Set $set) use ($calculateTotal): void {
            $set('total_price', $calculateTotal(
                $get('payment_plan_id'),
                $get('down_payment_amount'),
                $get('cash_amount'),
                $get('paid_installments_total'),
                $get('installments'),
            ));
        };

        // داخل صفوف الـ Repeater: مسار الـ Set/Get بيكون نسبي لصف القسط
        $refreshTotalFromInstallment = function (Get $get, Set $set) use ($calculateTotal): void {
            $set('../../total_price', $calculateTotal(
                $get('../../payment_plan_id'),
                $get('../../down_payment_amount'),
                $get('../../cash_amount'),
                $get('../../paid_installments_total'),
                $get('../../installments'),
            ));
        };

        return $schema
            ->components([
                Section::make('بيانات العقد')
                    ->columns(2)
                    ->schema([
                        Select::make('citizen_id')
                            ->label('المواطن')
                            ->relationship('citizen', 'name')
                            ->searchable()
                            ->required()
                            ->default(fn (): ?int => request()->integer('citizen_id') ?: null)
                            ->disabledOn('edit'),

                        Select::make('payment_plan_id')
                            ->label('نوع السداد')
                            ->options(fn (): array => PaymentPlan::query()->orderBy('installments_count')->pluck('name', 'id')->all())
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set, Get $get, mixed $record) use ($refreshTotal): void {
                                $plan = PaymentPlan::query()->find($state);

                                // الأقساط المسددة بتفضل زي ما هي، وبنجهّز خانات غير المسددة بعدد أقساط النوع المختار
                                $paidCount = $record instanceof Contract
                                    ? $record->installments()->where('status', Installment::STATUS_PAID)->count()
                                    : 0;

                                $pendingCount = $plan && ! $plan->is_cash
                                    ? max(0, $plan->installments_count - $paidCount)
                                    : 0;

                                $set('installments', array_fill(0, $pendingCount, ['amount' => null]));
                                $refreshTotal($get, $set);
                            }),

                        TextInput::make('code')
                            ->label('الكود')
                            ->required()
                            ->maxLength(50)
                            ->unique(ignoreRecord: true),

                        TextInput::make('serial_no')
                            ->label('م')
                            ->numeric()
                            ->minValue(0),

                        // مش بيظهر للمستخدم: بيستخدمه حساب الإجمالي في التعديل (الأقساط المسددة مش في النموذج)
                        Hidden::make('paid_installments_total')
                            ->dehydrated(false)
                            ->default(0),
                    ]),

                // ---- عقد مقسّط: المقدم ----
                Section::make('المقدم')
                    ->columns(2)
                    ->visible(fn (Get $get): bool => $hasPlan($get) && ! $isCash($get))
                    ->schema([
                        TextInput::make('down_payment_amount')
                            ->label('مبلغ الدفعة المقدمة')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->live()
                            ->afterStateUpdated($refreshTotal),

                        DatePicker::make('down_payment_date')
                            ->label('تاريخ المقدم')
                            ->helperText('مواعيد الأقساط = تاريخ المقدم + N سنة. لو سيبته فاضي الأقساط هتتسجل من غير موعد استحقاق وتقدر تضيفه بعدين.'),
                    ]),

                // ---- عقد مقسّط: الأقساط غير المسددة (المسددة بتفضل في تاب الأقساط ومابتتغيرش) ----
                Section::make('الأقساط')
                    ->description('اكتب قيمة كل قسط (المبلغ اللي المفروض المواطن يدفعه). إجمالي الثمن بيتحسب تلقائي = المقدم + الأقساط المسددة + الأقساط دي. الأقساط المسددة مش بتظهر هنا ومش هتتغير.')
                    ->visible(fn (Get $get): bool => $hasPlan($get) && ! $isCash($get))
                    ->schema([
                        Repeater::make('installments')
                            ->label('الأقساط')
                            ->schema([
                                TextInput::make('amount')
                                    ->label('قيمة القسط')
                                    ->numeric()
                                    ->required()
                                    ->minValue(0.01)
                                    ->live()
                                    ->afterStateUpdated($refreshTotalFromInstallment),
                            ])
                            ->itemNumbers()
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false),
                    ]),

                // ---- سداد 100% ----
                Section::make('السداد الكاش')
                    ->columns(2)
                    ->visible(fn (Get $get): bool => $hasPlan($get) && $isCash($get))
                    ->schema([
                        TextInput::make('cash_amount')
                            ->label('المبلغ المدفوع')
                            ->numeric()
                            ->required()
                            ->minValue(0.01)
                            ->live()
                            ->afterStateUpdated($refreshTotal),

                        DatePicker::make('cash_paid_at')
                            ->label('تاريخ الدفع')
                            ->default(today()->toDateString())
                            ->maxDate(today()),
                    ]),

                Section::make('الفائدة والإجمالي')
                    ->columns(2)
                    ->visible(fn (Get $get): bool => $hasPlan($get))
                    ->schema([
                        TextInput::make('interest_amount')
                            ->label('الفائدة (إدخال يدوي)')
                            ->numeric()
                            ->minValue(0)
                            ->default(0),

                        TextInput::make('total_price')
                            ->label('إجمالي الثمن بالفايدة')
                            ->numeric()
                            ->minValue(0)
                            ->disabled()
                            ->helperText('بيتحسب تلقائي من المقدم/المبلغ المدفوع ومجموع الأقساط.'),
                    ]),
            ]);
    }
}
