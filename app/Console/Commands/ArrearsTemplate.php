<?php

namespace App\Console\Commands;

use App\Services\Excel\ArrearsTemplateWriter;
use Illuminate\Console\Command;

class ArrearsTemplate extends Command
{
    protected $signature = 'arrears:template {path? : مكان حفظ القالب}';

    protected $description = 'إنشاء قالب Excel لاستيراد العقود والأقساط';

    public function handle(): int
    {
        $path = $this->argument('path') ?: storage_path('app/arrears-import-template.xlsx');

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        (new ArrearsTemplateWriter())->write($path);
        $this->info("اتعمل القالب: {$path}");

        return self::SUCCESS;
    }
}
