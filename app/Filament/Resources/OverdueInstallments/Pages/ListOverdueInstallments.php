<?php

namespace App\Filament\Resources\OverdueInstallments\Pages;

use App\Filament\Resources\OverdueInstallments\OverdueInstallmentResource;
use App\Models\OverdueInstallment;
use App\Models\PaymentPlan;
use App\Services\Excel\OverdueExporter;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListOverdueInstallments extends ListRecords
{
    protected static string $resource = OverdueInstallmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('تصدير Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->visible(fn (): bool => auth()->user()?->can('export', OverdueInstallment::class) ?? false)
                ->action(fn (): StreamedResponse => $this->exportToExcel()),
        ];
    }

    /** بيصدّر الصفوف الظاهرة دلوقتي: التاب + الفلاتر + البحث + الترتيب. */
    public function exportToExcel(): StreamedResponse
    {
        abort_unless(auth()->user()?->can('export', OverdueInstallment::class), 403);

        $years = match ($this->activeTab) {
            'three' => 3,
            'five' => 5,
            'seven' => 7,
            default => null,
        };

        /** @var Builder $query */
        $query = $this->scoped($this->getFilteredSortedTableQuery(), $years);

        return response()->streamDownload(
            function () use ($query): void {
                (new OverdueExporter())->write($query->cursor(), 'php://output');
            },
            'overdue-' . now()->format('Y-m-d_H-i') . '.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    public function getTabs(): array
    {
        return [
            'all' => $this->tab('الكل', null),
            'three' => $this->tab('3 سنوات', 3),
            'five' => $this->tab('5 سنوات', 5),
            'seven' => $this->tab('7 سنوات', 7),
        ];
    }

    private function tab(string $label, ?int $years): Tab
    {
        return Tab::make($label)
            ->badge($this->scoped(OverdueInstallment::query(), $years)->count())
            ->modifyQueryUsing(fn (Builder $query): Builder => $this->scoped($query, $years));
    }

    // الفلترة بعدد الأقساط في نوع السداد (مش بالـ id) علشان مايتكسرش لو الـ ids اتغيرت
    private function scoped(Builder $query, ?int $years): Builder
    {
        if ($years === null) {
            return $query;
        }

        return $query->whereIn(
            'payment_plan_id',
            PaymentPlan::query()->where('installments_count', $years)->select('id')
        );
    }
}
