<?php

namespace App\Services\Excel;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;

/** تقرير الاستيراد: شيت للصفوف اللي اتتخطت (أخطاء)، وشيت للصفوف اللي اتقبلت وفيها تنبيه. */
class ImportReportWriter
{
    /**
     * @param list<array{row:int,code:string,message:string}> $errors
     * @param list<array{row:int,code:string,message:string}> $warnings
     */
    public function write(array $errors, string $path, array $warnings = []): void
    {
        $options = new Options();
        $options->setColumnWidth(10, 1);
        $options->setColumnWidth(16, 2);
        $options->setColumnWidth(100, 3);

        $writer = new Writer($options);
        $writer->openToFile($path);

        $this->sheet($writer, $writer->getCurrentSheet(), 'أخطاء (اتتخطت)', $errors, Color::rgb(252, 228, 214));

        if ($warnings !== []) {
            $this->sheet($writer, $writer->addNewSheetAndMakeItCurrent(), 'تنبيهات (اتقبلت)', $warnings, Color::rgb(255, 242, 204), true);
        }

        $writer->close();
    }

    private function sheet(Writer $writer, $sheet, string $name, array $items, string $headerColor, bool $applyWidths = false): void
    {
        $sheet->setName($name);
        $sheet->setSheetView((new SheetView())->setRightToLeft(true)->setFreezeRow(2));

        if ($applyWidths) {
            $sheet->setColumnWidth(10, 1);
            $sheet->setColumnWidth(16, 2);
            $sheet->setColumnWidth(100, 3);
        }

        $writer->addRow(Row::fromValues(
            ['رقم الصف', 'الكود', 'السبب'],
            (new Style())->setFontBold()->setBackgroundColor($headerColor),
        ));

        foreach ($items as $item) {
            $writer->addRow(Row::fromValues([$item['row'], $item['code'], $item['message']]));
        }
    }
}
