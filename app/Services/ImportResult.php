<?php

namespace App\Services;

class ImportResult
{
    public int $total = 0;             // عدد الصفوف اللي اتقرت
    public int $contracts = 0;         // عقود اتعملت
    public int $citizensCreated = 0;
    public int $citizensReused = 0;
    public int $paidInstallments = 0;

    /** @var list<array{row:int,code:string,message:string}> */
    public array $errors = [];

    /** @var list<array{row:int,code:string,message:string}> */
    public array $warnings = [];

    public function __construct(public bool $dryRun = false)
    {
    }

    public function addError(int $row, string $code, string $message): void
    {
        $this->errors[] = ['row' => $row, 'code' => $code, 'message' => $message];
    }

    public function addWarning(int $row, string $code, string $message): void
    {
        $this->warnings[] = ['row' => $row, 'code' => $code, 'message' => $message];
    }

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    public function summary(): string
    {
        $prefix = $this->dryRun ? '[تحقق فقط - ماتحفظش حاجة] ' : '';

        return $prefix
            . "اتقرى {$this->total} صف: {$this->contracts} عقد "
            . ($this->dryRun ? 'صالح للاستيراد' : 'اتستورد')
            . "، {$this->paidInstallments} قسط مسدد، "
            . "مواطنين جدد {$this->citizensCreated} وموجودين {$this->citizensReused}"
            . '، وأخطاء ' . count($this->errors) . '.';
    }
}
