<?php

namespace App\Services\Excel;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;

/** تصدير المتأخرات لإكسيل بنفس أعمدة الشاشة + صف إجمالي في الآخر. */
class OverdueExporter
{
    /** @param iterable<object> $rows صفوف OverdueInstallment */
    public function write(iterable $rows, string $target): void
    {
        $options = new Options();
        $options->setColumnWidth(12, 1);
        $options->setColumnWidth(24, 2);
        $options->setColumnWidth(14, 3);
        $options->setColumnWidth(24, 4, 7);
        $options->setColumnWidth(18, 5, 6, 8);
        $options->setColumnWidth(12, 9);
        $options->setColumnWidth(16, 10);
        $options->setColumnWidth(12, 11);
        $options->setColumnWidth(16, 12, 13);

        $writer = new Writer($options);
        $writer->openToFile($target);

        $sheet = $writer->getCurrentSheet();
        $sheet->setName('المتأخرات');
        $sheet->setSheetView((new SheetView())->setRightToLeft(true)->setFreezeRow(2));

        $writer->addRow(Row::fromValues([
            'الكود', 'اسم المواطن', 'المركز', 'تليفون المواطن',
            'النائب', 'صلة القرابة', 'تليفون النائب', 'نوع السداد',
            'رقم القسط', 'تاريخ الاستحقاق', 'أيام التأخير', 'قيمة القسط', 'المتبقي',
        ], (new Style())->setFontBold()->setBackgroundColor(Color::rgb(221, 235, 247))));

        $money = (new Style())->setFormat('#,##0.00');
        $totalRemaining = 0.0;

        foreach ($rows as $r) {
            $remaining = (float) $r->remaining_amount;
            $totalRemaining += $remaining;

            $writer->addRow(Row::fromValuesWithStyles([
                (string) $r->contract_code,
                (string) $r->citizen_name,
                (string) ($r->center_name ?? ''),
                (string) ($r->citizen_phones ?? ''),
                (string) ($r->representative_name ?? ''),
                (string) ($r->representative_relationship ?? ''),
                (string) ($r->representative_phones ?? ''),
                (string) $r->payment_plan_name,
                (int) $r->installment_no,
                $this->date($r->due_date),
                (int) $r->days_overdue,
                (float) $r->amount,
                $remaining,
            ], null, [11 => $money, 12 => $money]));
        }

        $bold = (new Style())->setFontBold();
        $writer->addRow(Row::fromValuesWithStyles(
            ['الإجمالي', '', '', '', '', '', '', '', '', '', '', '', $totalRemaining],
            $bold,
            [12 => (new Style())->setFontBold()->setFormat('#,##0.00')],
        ));

        $writer->close();
    }

    private function date(mixed $value): string
    {
        return $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : (string) $value;
    }
}
