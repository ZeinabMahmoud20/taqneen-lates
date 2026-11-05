<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\OverdueInstallments\Tables\OverdueInstallmentsTable;
use App\Models\OverdueInstallment;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class OverdueReportTable extends TableWidget
{
    use InteractsWithPageFilters;

    protected string $view = 'filament.widgets.overdue-report-table';

    protected static ?string $heading = 'تقرير الأقساط المتأخرة';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return OverdueInstallmentsTable::configure($table)
            ->query(fn (): Builder => $this->getRecordsQuery())
            ->filters([])
            ->paginated(false);
    }

    private function getRecordsQuery(): Builder
    {
        $centerId = $this->positiveInteger($this->pageFilters['center_id'] ?? null);
        $paymentPlanId = $this->positiveInteger($this->pageFilters['payment_plan_id'] ?? null);

        return OverdueInstallment::query()
            ->when($centerId, fn ($query) => $query->where('center_id', $centerId))
            ->when($paymentPlanId, fn ($query) => $query->where('payment_plan_id', $paymentPlanId))
            ->orderByDesc('days_overdue');
    }

    private function positiveInteger(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    public function getPrintScript(): string
    {
        $totalRemaining = (float) $this->getRecordsQuery()->reorder()->sum('remaining_amount');

        $script = <<<'JS'
            const widget = document.querySelector('.fi-wi-table');
            if (!widget?.querySelector('.fi-ta-table')) {
                alert('لم يتم العثور على بيانات الجدول.');
                return;
            }

            const printRoot = document.createElement('div');
            printRoot.id = 'report-print-root';
            printRoot.style.display = 'none';

            const clone = widget.cloneNode(true);
            clone.querySelector('.report-print-button')?.remove();
            [clone, ...clone.querySelectorAll('*')].forEach(element => {
                Array.from(element.attributes).forEach(attribute => {
                    if (attribute.name.startsWith('wire:') || attribute.name.startsWith('x-')) {
                        element.removeAttribute(attribute.name);
                    }
                });
            });

            clone.querySelectorAll('.fi-ta-summary-row').forEach(row => row.remove());
            const table = clone.querySelector('.fi-ta-table');
            if (table) {
                const body = table.querySelector('tbody:last-of-type') ?? table.createTBody();
                const summary = document.createElement('tr');
                summary.className = 'print-summary-row';
                summary.innerHTML = '<th colspan="10">الإجمالي</th><td colspan="2">' + Number('__TOTAL_REMAINING__').toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '</td>';
                body.append(summary);
            }

            printRoot.append(clone);
            document.body.append(printRoot);

            const style = document.createElement('style');
            style.media = 'print';
            style.textContent = `
                body > *:not(#report-print-root) { display: none !important; }
                #report-print-root { display: block !important; width: 100%; direction: rtl; background: #fff !important; color: #000 !important; }
                #report-print-root, #report-print-root * { box-sizing: border-box; color: #000 !important; }
                #report-print-root .fi-ta-header-ctn .fi-btn,
                #report-print-root .fi-ta-header-toolbar,
                #report-print-root .fi-ta-pagination { display: none !important; }
                #report-print-root .fi-ta-ctn,
                #report-print-root .fi-ta-content-ctn,
                #report-print-root .fi-ta-content { width: 100% !important; max-width: none !important; overflow: visible !important; background: #fff !important; box-shadow: none !important; }
                #report-print-root .fi-ta-table { width: 100% !important; min-width: 0 !important; table-layout: fixed !important; border-collapse: collapse !important; background: #fff !important; }
                #report-print-root .fi-ta-table thead { display: table-header-group !important; }
                #report-print-root .fi-ta-table tr { break-inside: avoid !important; page-break-inside: avoid !important; }
                #report-print-root .fi-ta-table th,
                #report-print-root .fi-ta-table td { border: 1px solid #777 !important; background: #fff !important; color: #000 !important; text-align: right !important; padding: 4px !important; font-size: 8pt !important; line-height: 1.25 !important; white-space: normal !important; overflow-wrap: anywhere !important; }
                #report-print-root .fi-ta-table th:nth-child(2), #report-print-root .fi-ta-table td:nth-child(2) { width: 27.27% !important; }
                #report-print-root .fi-ta-table th:nth-child(1), #report-print-root .fi-ta-table td:nth-child(1),
                #report-print-root .fi-ta-table th:nth-child(3), #report-print-root .fi-ta-table td:nth-child(3),
                #report-print-root .fi-ta-table th:nth-child(7), #report-print-root .fi-ta-table td:nth-child(7),
                #report-print-root .fi-ta-table th:nth-child(8), #report-print-root .fi-ta-table td:nth-child(8),
                #report-print-root .fi-ta-table th:nth-child(9), #report-print-root .fi-ta-table td:nth-child(9),
                #report-print-root .fi-ta-table th:nth-child(10), #report-print-root .fi-ta-table td:nth-child(10) { width: 8.62% !important; }
                #report-print-root .fi-ta-table th:nth-child(11), #report-print-root .fi-ta-table td:nth-child(11),
                #report-print-root .fi-ta-table th:nth-child(12), #report-print-root .fi-ta-table td:nth-child(12) { width: 10.5% !important; }
                #report-print-root .fi-ta-table th:nth-child(4), #report-print-root .fi-ta-table th:nth-child(5), #report-print-root .fi-ta-table th:nth-child(6),
                #report-print-root .fi-ta-table td:nth-child(4), #report-print-root .fi-ta-table td:nth-child(5), #report-print-root .fi-ta-table td:nth-child(6) { display: none !important; }
                #report-print-root .print-summary-row { font-weight: 700 !important; }
                #report-print-root .print-summary-row th { text-align: right !important; font-weight: 700 !important; }
                #report-print-root .print-summary-row td:last-child { width: 21% !important; text-align: right !important; font-weight: 700 !important; }
                @page { size: landscape; margin: 10mm; }
            `;
            document.head.append(style);

            window.addEventListener('afterprint', () => {
                printRoot.remove();
                style.remove();
            }, { once: true });

            window.print();
            JS;

        return str_replace('__TOTAL_REMAINING__', number_format($totalRemaining, 2, '.', ''), $script);
    }
}
