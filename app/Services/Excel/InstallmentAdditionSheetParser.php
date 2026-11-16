<?php

namespace App\Services\Excel;

use DateTimeInterface;
use Illuminate\Support\Carbon;
use OpenSpout\Reader\SheetInterface;
use OpenSpout\Reader\XLSX\Options;
use OpenSpout\Reader\XLSX\Reader;

/**
 * قارئ ملف «إضافة أقساط» (تقرير الرسوم الحكومية).
 *
 * - أول شيت بس.
 * - صف العناوين رقم 9، والبيانات من صف 10.
 * - الأعمدة بتتعرف بالعنوان مش بالموضع: رقم الطلب / تاريخ الإصدار / المبلغ /
 *   تاريخ الأذن / الاسم / التليفون / الرقم القومي / م.
 * - «تاريخ الإصدار» = تاريخ السداد الفعلي، و«المبلغ» = قيمة القسط المدفوعة.
 * - الأخطاء اللي على مستوى الصف بترجع جوه الصف نفسه في 'errors'.
 */
class InstallmentAdditionSheetParser
{
    public const HEADER_ROW = 9;

    public const FIRST_DATA_ROW = 10;

    /** العنوان => المفتاح الداخلي */
    public const FIELDS = [
        'رقم الطلب' => 'request_no',
        'تاريخ الإصدار' => 'issue_date',
        'المبلغ' => 'amount',
        'تاريخ الأذن' => 'permit_date',
        'الاسم' => 'name',
        'التليفون' => 'phone',
        'الهاتف' => 'phone',
        'الرقم القومي' => 'national_id',
        'م' => 'serial',
    ];

    private const DIGITS = [
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
    ];

    /** @var array<string,string> */
    private array $fieldIndex = [];

    public function __construct()
    {
        foreach (self::FIELDS as $header => $key) {
            $this->fieldIndex[$this->norm($header)] ??= $key;
        }
    }

    /**
     * @return list<array<string,mixed>>
     *
     * @throws ImportFormatException
     */
    public function parse(string $path): array
    {
        (new ExcelFileGuard)->assertCanBeOpened($path);

        $options = new Options;
        // محتاجين أرقام الصفوف الحقيقية علشان صف العناوين رقم 9
        $options->SHOULD_PRESERVE_EMPTY_ROWS = true;
        OpenSpoutTempFolder::configure($options, $path);

        $reader = new Reader($options);

        try {
            $reader->open($path);
        } catch (\Throwable $e) {
            report($e);

            throw new ImportFormatException((new ExcelFileGuard)->messageForOpenError($e), previous: $e);
        }

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                return $this->parseSheet($sheet);   // أول شيت بس
            }
        } finally {
            $reader->close();
        }

        throw new ImportFormatException('الملف فاضي.');
    }

    /** @return list<array<string,mixed>> */
    private function parseSheet(SheetInterface $sheet): array
    {
        $map = null;
        $rows = [];

        foreach ($sheet->getRowIterator() as $rowNumber => $row) {
            $cells = $row->toArray();

            if ($rowNumber < self::HEADER_ROW) {
                continue;
            }

            if ($rowNumber === self::HEADER_ROW) {
                $map = $this->mapHeaders($cells);

                continue;
            }

            if ($this->isEmpty($cells)) {
                continue;
            }

            $parsed = $this->parseRow((int) $rowNumber, $cells, $map);

            if ($parsed !== null) {
                $rows[] = $parsed;
            }
        }

        if ($map === null) {
            throw new ImportFormatException(
                'الملف مش مطابق: لازم يكون فيه صف عناوين رقم 9 وبيانات من صف 10. راجع ترتيب الملف وجرّب تاني.'
            );
        }

        return $rows;
    }

    /** @return array<string,int> */
    private function mapHeaders(array $cells): array
    {
        $fields = [];

        foreach ($cells as $index => $cell) {
            $header = $this->norm($this->text($cell));
            if ($header === '') {
                continue;
            }

            if (isset($this->fieldIndex[$header])) {
                $fields[$this->fieldIndex[$header]] ??= $index;
            }
        }

        $hasRequestNo = isset($fields['request_no']);
        $hasNameAndPhone = isset($fields['name'], $fields['phone']);
        $hasAmountOrPermit = isset($fields['amount']) || isset($fields['permit_date']);

        if ((! $hasRequestNo && ! $hasNameAndPhone) || ! $hasAmountOrPermit) {
            throw new ImportFormatException(
                'الملف مش مطابق: لازم يكون فيه «رقم الطلب» أو («الاسم» و«التليفون»)، وكذلك «المبلغ» أو «تاريخ الأذن» في صف العناوين رقم 9.'
            );
        }

        return $fields;
    }

    /** @return array<string,mixed>|null */
    private function parseRow(int $rowNumber, array $cells, array $fields): ?array
    {
        $errors = [];
        $warnings = [];
        $get = fn (string $field): mixed => isset($fields[$field]) ? ($cells[$fields[$field]] ?? null) : null;

        $requestNo = $this->text($get('request_no'));
        $name = $this->collapse($this->text($get('name')));
        $phone = $this->phone($get('phone'), $warnings);
        $nationalId = $this->nationalId($get('national_id'), $warnings);
        $serial = $this->integer($get('serial'), 'م', $warnings);

        $amount = $this->amount($get('amount'), $errors);
        $permitDate = $this->date($get('permit_date'), 'تاريخ الأذن', $errors, $warnings);
        $issueDate = $this->date($get('issue_date'), 'تاريخ الإصدار', $errors, $warnings);

        // صف مفيهوش أي بيانات من الأعمدة المهمة (مثلاً صف الإجماليات)
        if ($requestNo === '' && $name === '' && $phone === '' && $nationalId === ''
            && $amount === null && $permitDate === null && $issueDate === null) {
            return null;
        }

        if ($permitDate !== null && $permitDate->gt(today())) {
            $errors[] = 'تاريخ الأذن ('.$permitDate->format('Y-m-d').') في المستقبل — الصف مرفوض.';
        }

        if ($issueDate !== null && $issueDate->gt(today())) {
            $errors[] = 'تاريخ الإصدار ('.$issueDate->format('Y-m-d').') في المستقبل — الصف مرفوض.';
        }

        if ($requestNo === '' && ($name === '' || $phone === '')) {
            $errors[] = 'رقم الطلب مطلوب، أو الاسم والتليفون مع بعض للمطابقة.';
        }

        $requestNo = $this->collapse($requestNo);
        $contractCode = $this->contractCode($requestNo);

        return [
            'row' => $rowNumber,
            'serial' => $serial,
            'request_no' => $requestNo,
            'contract_code' => $contractCode,
            'name' => $name,
            'phone' => $phone,
            'national_id' => $nationalId,
            'issue_date' => $issueDate,
            'permit_date' => $permitDate,
            'amount' => $amount,
            'warnings' => $warnings,
            'errors' => $errors,
        ];
    }

    private function contractCode(string $requestNo): string
    {
        if ($requestNo === '') {
            return '';
        }

        $parts = preg_split('#[/\\\\]#', $requestNo);
        $code = trim((string) ($parts[0] ?? ''));

        return $code !== '' ? $code : $requestNo;
    }

    private function amount(mixed $value, array &$errors): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            $amount = (float) $value;
        } elseif (is_string($value)) {
            $text = strtr(trim($value), self::DIGITS + ['٬' => '', ',' => '', '٫' => '.', ' ' => '']);

            if ($text === '') {
                return null;
            }

            if (! is_numeric($text)) {
                $errors[] = 'قيمة غير رقمية في «المبلغ»';

                return null;
            }

            $amount = (float) $text;
        } else {
            $errors[] = 'قيمة غير رقمية في «المبلغ»';

            return null;
        }

        if ($amount <= 0) {
            $errors[] = 'قيمة «المبلغ» لازم تكون أكبر من صفر';

            return null;
        }

        return round($amount, 2);
    }

    private function date(mixed $value, string $label, array &$errors, array &$warnings): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)->startOfDay();
        }

        // رقم تاريخ إكسيل خام (الخلية مش منسقة كتاريخ)
        if ((is_int($value) || is_float($value)) && $value >= 20000 && $value <= 80000) {
            return Carbon::create(1899, 12, 30)->addDays((int) $value)->startOfDay();
        }

        if (is_string($value)) {
            $text = strtr(trim($value), self::DIGITS);

            $year = $month = $day = null;

            // سنة/شهر/يوم (2026/04/08)
            if (preg_match('/^(\d{4})[\/\-.](\d{1,2})[\/\-.](\d{1,2})(?:[ T].*)?$/', $text, $matches)) {
                [$year, $month, $day] = [(int) $matches[1], (int) $matches[2], (int) $matches[3]];
            }
            // يوم/شهر/سنة (08/04/2026)
            elseif (preg_match('/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})(?:[ T].*)?$/', $text, $matches)) {
                [$day, $month, $year] = [(int) $matches[1], (int) $matches[2], (int) $matches[3]];
            }
            // رقم تاريخ إكسيل جاي كنص
            elseif (preg_match('/^\d{5}(?:\.\d+)?$/', $text)) {
                $serial = (int) $text;

                if ($serial >= 20000 && $serial <= 80000) {
                    return Carbon::create(1899, 12, 30)->addDays($serial)->startOfDay();
                }
            }

            if ($year !== null && $month >= 1 && $month <= 12 && $day >= 1 && $day <= 31) {
                $lastDay = (int) Carbon::create($year, $month, 1)->format('t');
                $fixed = $day > $lastDay;
                $date = Carbon::create($year, $month, min($day, $lastDay))->startOfDay();

                if ($fixed) {
                    $warnings[] = "«{$label}»: التاريخ ({$text}) مش موجود في التقويم، اتحسب {$date->format('Y-m-d')}.";
                }

                return $date;
            }
        }

        $errors[] = "تاريخ غير مفهوم في «{$label}»";

        return null;
    }

    private function integer(mixed $value, string $label, array &$warnings): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $text = strtr($this->text($value), self::DIGITS);

        if (! preg_match('/^\d+$/', $text)) {
            $warnings[] = "قيمة «{$label}» مش رقم صحيح (اتجاهل).";

            return null;
        }

        return (int) $text;
    }

    private function nationalId(mixed $value, array &$warnings): string
    {
        $digits = preg_replace('/\D+/', '', strtr($this->text($value), self::DIGITS)) ?? '';

        if ($digits === '') {
            return '';
        }

        if (strlen($digits) !== 14) {
            $warnings[] = 'الرقم القومي لازم يكون 14 رقم (اتجاهل).';

            return '';
        }

        return $digits;
    }

    private function phone(mixed $value, array &$warnings): string
    {
        $digits = preg_replace('/\D+/', '', strtr($this->text($value), self::DIGITS)) ?? '';

        if ($digits === '') {
            return '';
        }

        // إكسيل بيشيل الصفر الأول من أرقام الموبايل
        if (strlen($digits) === 10 && $digits[0] === '1') {
            $digits = '0'.$digits;
        }

        if (strlen($digits) < 7 || strlen($digits) > 15) {
            $warnings[] = "رقم تليفون غير صحيح ({$digits}) اتجاهل.";

            return '';
        }

        return $digits;
    }

    private function isEmpty(array $cells): bool
    {
        foreach ($cells as $cell) {
            if ($this->text($cell) !== '') {
                return false;
            }
        }

        return true;
    }

    private function text(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        if (is_float($value) && floor($value) == $value && abs($value) < 1e15) {
            return (string) (int) $value;
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return trim((string) $value);
    }

    private function collapse(string $value): string
    {
        return trim(preg_replace('/[\s\x{00A0}]+/u', ' ', $value) ?? '');
    }

    /** تنضيف العناوين للمطابقة بس */
    private function norm(string $value): string
    {
        $value = strtr($value, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ى' => 'ي', 'ة' => 'ه', 'ـ' => '']);

        return $this->collapse($value);
    }
}
