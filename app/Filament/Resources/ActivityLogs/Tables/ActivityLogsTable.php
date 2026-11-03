<?php

namespace App\Filament\Resources\ActivityLogs\Tables;

use App\Models\User;
use App\Support\ActivityChanges;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Spatie\Activitylog\Models\Activity;

class ActivityLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('التاريخ')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),

                TextColumn::make('causer.name')
                    ->label('المستخدم')
                    ->placeholder('النظام / الاستيراد'),

                TextColumn::make('event')
                    ->label('العملية')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => ActivityChanges::eventLabel($state))
                    ->color(fn ($state): string => match ($state) {
                        'created' => 'success',
                        'updated' => 'warning',
                        'deleted' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('subject_type')
                    ->label('النوع')
                    ->formatStateUsing(fn ($state): string => ActivityChanges::subjectLabel($state)),

                TextColumn::make('subject_id')
                    ->label('العنصر')
                    ->formatStateUsing(fn ($state, Activity $record): string => self::subjectTitle($record)),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('event')
                    ->label('العملية')
                    ->options([
                        'created' => 'إضافة',
                        'updated' => 'تعديل',
                        'deleted' => 'حذف',
                    ]),

                SelectFilter::make('subject_type')
                    ->label('النوع')
                    ->options(ActivityChanges::SUBJECT_LABELS),

                SelectFilter::make('causer_id')
                    ->label('المستخدم')
                    ->options(fn (): array => User::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->where('causer_type', (new User())->getMorphClass())->where('causer_id', $data['value'])
                        : $query),

                Filter::make('created_between')
                    ->label('الفترة')
                    ->schema([
                        DatePicker::make('from')->label('من'),
                        DatePicker::make('until')->label('إلى'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date))),
            ])
            ->recordActions([
                Action::make('details')
                    ->label('التفاصيل')
                    ->icon('heroicon-o-eye')
                    ->modalHeading(fn (Activity $record): string => ActivityChanges::eventLabel($record->event)
                        . ' ' . ActivityChanges::subjectLabel($record->subject_type))
                    ->modalDescription(fn (Activity $record): HtmlString => new HtmlString(
                        ActivityChanges::html($record->attribute_changes?->toArray(), $record->event)
                    ))
                    ->modalWidth('2xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('إغلاق'),
            ])
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25);
    }

    /** اسم/كود العنصر اللي اتعمل عليه العملية (لو لسه موجود) */
    private static function subjectTitle(Activity $record): string
    {
        $subject = $record->subject;

        if ($subject === null) {
            return '#' . $record->subject_id . ($record->event === 'deleted' ? ' (محذوف)' : '');
        }

        $a = $subject->getAttributes();

        return (string) match (true) {
            filled($a['name'] ?? null) => $a['name'],
            filled($a['code'] ?? null) => $a['code'],
            filled($a['installment_no'] ?? null) => 'قسط ' . $a['installment_no'],
            filled($a['phone'] ?? null) => $a['phone'],
            filled($a['amount'] ?? null) => 'مبلغ ' . $a['amount'],
            filled($a['note'] ?? null) => mb_substr((string) $a['note'], 0, 30),
            default => '#' . $subject->getKey(),
        };
    }
}
