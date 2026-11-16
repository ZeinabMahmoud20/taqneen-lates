<?php

namespace App\Services;

/** نتيجة تشغيل «إضافة أقساط» (مع أو بدون حفظ). */
class InstallmentAdditionResult
{
    public int $total = 0;              // عدد الصفوف اللي اتقرت

    public int $applied = 0;            // صفوف اتعدل عليها فعلاً

    public int $skipped = 0;            // صفوف اتجاهلت (قسط مسدد / بدون بيانات)

    public int $paidInstallments = 0;   // عدد الأقساط اللي اتسددت

    public int $permitsSaved = 0;       // عدد تواريخ الأذن اللي اتسجلت

    public float $paidTotal = 0.0;      // مجموع المبالغ المدفوعة

    /** @var list<array{row:int,code:string,message:string}> */
    public array $errors = [];

    /** @var list<array{row:int,code:string,message:string}> */
    public array $warnings = [];

    public function __construct(public bool $dryRun = false) {}

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
            ."اتقرى {$this->total} صف: {$this->applied} صف اتعالج"
            .($this->dryRun ? ' (صالح للتنفيذ)' : '')
            ."، {$this->skipped} اتجاهل"
            .'، '.$this->paidInstallments.' قسط اتسدد بإجمالي '
            .number_format($this->paidTotal, 2, '.', '')
            .'، '.$this->permitsSaved.' تاريخ أذن اتسجل'
            .'، وأخطاء '.count($this->errors).' وتنبيهات '.count($this->warnings).'.';
    }
}
