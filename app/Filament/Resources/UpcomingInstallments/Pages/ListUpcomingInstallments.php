<?php

namespace App\Filament\Resources\UpcomingInstallments\Pages;

use App\Filament\Resources\UpcomingInstallments\UpcomingInstallmentResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListUpcomingInstallments extends ListRecords
{
    protected static string $resource = UpcomingInstallmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('print')
                ->label('طباعة')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn (): string => route(
                    'admin.upcoming-installments.print',
                    $this->getPrintQuery()
                ))
                ->openUrlInNewTab(),
        ];
    }

    /** الفلاتر والبحث والترتيب الظاهرين دلوقتي، عشان الطباعة تكون لنفس النتائج. */
    private function getPrintQuery(): array
    {
        return array_filter([
            'center_id' => $this->getTableFilterState('center_id')['value'] ?? null,
            'from' => $this->getTableFilterState('due_date_between')['from'] ?? null,
            'to' => $this->getTableFilterState('due_date_between')['to'] ?? null,
            'days_range' => $this->getTableFilterState('days_range')['value'] ?? null,
            'q' => $this->getTableSearch(),
        ], fn ($value): bool => filled($value));
    }
}
