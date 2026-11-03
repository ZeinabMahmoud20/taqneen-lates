<?php

namespace App\Console\Commands;

use App\Services\ArrearsImporter;
use App\Services\Excel\ImportFormatException;
use App\Services\Excel\ImportReportWriter;
use Illuminate\Console\Command;

class ImportArrears extends Command
{
    protected $signature = 'arrears:import
        {file : مسار ملف الإكسيل}
        {--dry-run : تحقق فقط بدون حفظ أي بيانات}
        {--user= : id المستخدم اللي بينفّذ الاستيراد (للتسجيل)}';

    protected $description = 'استيراد العقود والأقساط من ملف Excel (قالب النظام)';

    public function handle(ArrearsImporter $importer): int
    {
        $file = (string) $this->argument('file');

        if (! is_file($file)) {
            $this->error("الملف مش موجود: {$file}");

            return self::FAILURE;
        }

        try {
            $result = $importer->import(
                $file,
                (bool) $this->option('dry-run'),
                $this->option('user') !== null ? (int) $this->option('user') : null,
            );
        } catch (ImportFormatException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info($result->summary());

        foreach ($result->warnings as $w) {
            $this->warn("صف {$w['row']} ({$w['code']}): {$w['message']}");
        }

        if ($result->hasErrors()) {
            $this->newLine();
            $this->table(
                ['الصف', 'الكود', 'السبب'],
                array_map(fn ($e) => [$e['row'], $e['code'], $e['message']], array_slice($result->errors, 0, 50)),
            );

            $report = dirname($file) . DIRECTORY_SEPARATOR . pathinfo($file, PATHINFO_FILENAME) . '_errors.xlsx';
            (new ImportReportWriter())->write($result->errors, $report);
            $this->line("تقرير الأخطاء الكامل: {$report}");
        }

        return self::SUCCESS;
    }
}
