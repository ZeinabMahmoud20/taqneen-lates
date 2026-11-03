<?php

namespace App\Filament\Resources\Contracts\Schemas;

use App\Models\PaymentPlan;
use Filament\Forms\Components\DatePicker;
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
                            ->disabledOn('edit')
                            ->afterStateUpdated(function ($state, Set $set): void {
                                $plan = PaymentPlan::query()->find($state);

                                // بنجهّز خانات الأقساط بعدد أقساط النوع المختار
                                $set(
                                    'installments',
                                    $plan && ! $plan->is_cash
                                        ? array_fill(0, $plan->installments_count, ['amount' => null])
                                        : [],
                                );
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
                            ->default(0),

                        DatePicker::make('down_payment_date')
                            ->label('تاريخ المقدم')
                            ->helperText('مواعيد الأقساط = تاريخ المقدم + N سنة. لو سيبته فاضي الأقساط هتتسجل من غير موعد استحقاق وتقدر تضيفه بعدين.'),
                    ]),

                // ---- عقد مقسّط: الأقساط (عند الإنشاء فقط، وبعدها من تاب الأقساط) ----
                Section::make('الأقساط')
                    ->description('اكتب قيمة كل قسط (المبلغ اللي المفروض المواطن يدفعه). إجمالي الثمن بيتحسب تلقائي = المقدم + الأقساط.')
                    ->visible(fn (Get $get, string $operation): bool => $operation === 'create' && $hasPlan($get) && ! $isCash($get))
                    ->schema([
                        Repeater::make('installments')
                            ->label('الأقساط')
                            ->schema([
                                TextInput::make('amount')
                                    ->label('قيمة القسط')
                                    ->numeric()
                                    ->required()
                                    ->minValue(0.01),
                            ])
                            ->itemNumbers()
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false),
                    ]),

                // ---- سداد 100% ----
                Section::make('السداد الكاش')
                    ->columns(2)
                    ->visible(fn (Get $get, string $operation): bool => $operation === 'create' && $hasPlan($get) && $isCash($get))
                    ->schema([
                        TextInput::make('cash_amount')
                            ->label('المبلغ المدفوع')
                            ->numeric()
                            ->required()
                            ->minValue(0.01),

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
                            ->visible(fn (string $operation): bool => $operation === 'edit'),
                    ]),
            ]);
    }
}
