<?php

namespace App\Services\Excel;

use DateTimeImmutable;
use DateTimeInterface;
use OpenSpout\Reader\XLSX\Options;
use OpenSpout\Reader\XLSX\Reader;
use XMLReader;
use ZipArchive;

/**
 * بيقرأ ملف الإكسيل ويحوّله لصفوف منظّمة. (PHP صافي - مفيهوش قاعدة بيانات)
 *
 * - الأعمدة بتتعرف من العناوين بعد تنضيفها (مسافات زيادة، أ/إ/آ، ة/ه ...).
 * - أعمدة الأقساط بتتعرف بالموضع: عمود «القسط ...» وبعده على طول عمود تاريخ السداد.
 * - «اجمالى الثمن بالفايدة»: بيتقري للمقارنة بس (ممكن يكون معادلة)، والإجمالي الفعلي = المقدم + الأقساط.
 * - تاريخ الاستحقاق مش في الملف: بيتحسب = تاريخ المقدم + N سنة.
 * - تاريخ مش موجود في التقويم (30/02/2020) بيتحسب آخر يوم في الشهر ومعاه تنبيه، مش خطأ.
 * - الأخطاء اللي على مستوى الصف (رقم مش مفهوم، تاريخ غلط ...) بترجع جوه الصف نفسه في 'errors'.
 */
class ArrearsSheetParser
{
    /** العنوان الأساسي => المفتاح الداخلي */
    public const FIELDS = [
        'نوع القسط' => 'type',
        'م' => 'serial',
        'كود' => 'code',
        'الاسم' => 'name',
        'المركز' => 'center',
        'مبلغ الدفعة المقدمة' => 'down_amount',
        'تاريخ المقدم' => 'down_date',
        'الفائدة' => 'interest',
        'ملاحظات' => 'notes',
        'الرقم القومي' => 'national_id',
        'هاتف المواطن' => 'citizen_phones',
        'اسم النائب' => 'rep_name',
        'صلة القرابة' => 'rep_relationship',
        'هاتف النائب' => 'rep_phones',
        'اجمالى الثمن بالفايدة' => 'total',
    ];

    public const ORDINALS = [
        'الأول' => 1, 'الثاني' => 2, 'الثالث' => 3, 'الرابع' => 4,
        'الخامس' => 5, 'السادس' => 6, 'السابع' => 7,
    ];

    private const DIGITS = [
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
    ];

    /** @var array<string,string> */
    private array $fieldIndex = [];
    /** @var array<string,int> */
    private array $ordinalIndex = [];

    public function __construct()
    {
        foreach (self::FIELDS as $header => $key) {
            $this->fieldIndex[$this->norm($header)] = $key;
        }
        foreach (self::ORDINALS as $word => $no) {
            $this->ordinalIndex[$this->norm($word)] = $no;
        }
    }

    /**
     * @return list<array<string,mixed>>
     * @throws ImportFormatException
     */
    public function parse(string $path): array
    {
        $this->assertFileCanBeOpened($path);

        $options = new Options();
        $this->useWritableTempFolder($options, $path);

        $reader = new Reader($options);

        try {
            $reader->open($path);
        } catch (\Throwable $e) {
            report($e);

            throw new ImportFormatException($this->openErrorMessage($e), previous: $e);
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

    /**
     * OpenSpout بيرمي ملفات مؤقتة (shared strings) في temp النظام. على سيرفرات
     * كتير C:\Windows\TEMP أو /tmp مش قابل للكتابة لمستخدم PHP، فبنختار أول
     * مجلد قابل للكتابة فعليًا (بنجرب نكتب فيه ملف حقيقي مش بس is_writable).
     *
     * @throws ImportFormatException
     */
    private function useWritableTempFolder(Options $options, string $filePath): void
    {
        $candidates = [
            dirname($filePath) . DIRECTORY_SEPARATOR . 'openspout-temp',   // نفس مكان الملف المرفوع
            storage_path('app/private/openspout-temp'),
            storage_path('app/openspout-temp'),
            storage_path('framework/cache'),
            sys_get_temp_dir(),
        ];

        foreach ($candidates as $folder) {
            if ($folder === '') {
                continue;
            }

            if (! is_dir($folder) && ! @mkdir($folder, 0775, true) && ! is_dir($folder)) {
                continue;
            }

            // is_writable ساعات بتدّي نتيجة غلط على ويندوز، فبنجرب كتابة فعلية.
            $probe = $folder . DIRECTORY_SEPARATOR . 'write-test-' . uniqid('', true);
            if (@file_put_contents($probe, 'ok') === false) {
                continue;
            }
            @unlink($probe);

            try {
                $options->setTempFolder($folder);

                return;
            } catch (\Throwable $e) {
                report($e);
            }
        }

        throw new ImportFormatException(
            'مجلد الملفات المؤقتة على السيرفر مش قابل للكتابة. راجع صلاحيات storage/app/private و storage/app للمستخدم اللي بيشغّل PHP.'
        );
    }

    /**
     * فحص سريع قبل ما نحاول نفتح الملف، علشان نطلع رسالة مفهومة بدل الخطأ العام.
     *
     * @throws ImportFormatException
     */
    private function assertFileCanBeOpened(string $path): void
    {
        if (! class_exists(ZipArchive::class)) {
            throw new ImportFormatException(
                'السيرفر ناقصه إضافة PHP Zip (ext-zip). فعّلها من php.ini أو ثبّت php-zip، وبعدها أعد تشغيل PHP/السيرفر.'
            );
        }

        if (! class_exists(XMLReader::class)) {
            throw new ImportFormatException(
                'السيرفر ناقصه إضافة PHP XMLReader (ext-xmlreader). فعّلها من php.ini أو ثبّت php-xml، وبعدها أعد تشغيل PHP/السيرفر.'
            );
        }

        if (! is_file($path)) {
            throw new ImportFormatException(
                'الملف المرفوع مش موجود على السيرفر. جرّب ترفعه تاني، ولو المشكلة كررت راجع صلاحيات مجلد storage/app/private.'
            );
        }

        if (! is_readable($path)) {
            throw new ImportFormatException(
                'الملف مرفوع لكن السيرفر مش قادر يقرأه. راجع صلاحيات مجلد storage/app/private للمستخدم اللي بيشغّل PHP.'
            );
        }
    }

    /** ترجمة أخطاء OpenSpout الشائعة لرسالة مفهومة للمستخدم */
    private function openErrorMessage(\Throwable $e): string
    {
        $message = $e->getMessage();

        if (str_contains($message, 'Class "ZipArchive" not found')) {
            return 'السيرفر ناقصه إضافة PHP Zip (ext-zip). فعّلها من php.ini أو ثبّت php-zip، وبعدها أعد تشغيل PHP/السيرفر.';
        }

        if (str_contains($message, 'Class "XMLReader" not found')) {
            return 'السيرفر ناقصه إضافة PHP XMLReader (ext-xmlreader). فعّلها من php.ini أو ثبّت php-xml، وبعدها أعد تشغيل PHP/السيرفر.';
        }

        if (str_contains($message, 'Not a zip archive') || str_contains($message, 'Zip archive inconsistent')) {
            return 'الملف تالف أو مش xlsx حقيقي. افتحه في Excel واعمله Save As بصيغة xlsx وجرّب تاني.';
        }

        if (str_contains($message, 'not readable') || str_contains($message, 'Cannot perform I/O')
            || str_contains($message, 'Can\'t open file') || str_contains($message, 'Permission denied')) {
            return 'السيرفر مش قادر يقرأ الملف. راجع صلاحيات مجلد storage/app/private.';
        }

        if (str_contains($message, 'writable folder') || str_contains($message, 'Unable to create folder')) {
            return 'مجلد الملفات المؤقتة على السيرفر مش قابل للكتابة. راجع صلاحيات storage/app/private.';
        }

        return 'مش قادر أفتح الملف. اتأكد إنه ملف Excel بصيغة xlsx، وراجع سجل الأخطاء laravel.log للتفاصيل.';
    }

    private function parseSheet(\OpenSpout\Reader\SheetInterface $sheet): array
    {
        $map = null;
        $rows = [];

        foreach ($sheet->getRowIterator() as $rowNumber => $row) {
            $cells = $row->toArray();

            if ($map === null) {
                $map = $this->mapHeaders($cells);
                continue;
            }

            if ($this->isEmpty($cells)) {
                continue;
            }

            $rows[] = $this->parseRow((int) $rowNumber, $cells, $map);
        }

        if ($map === null) {
            throw new ImportFormatException('الملف فاضي.');
        }

        return $rows;
    }

    /** @return array{fields: array<string,int>, installments: array<int,array{amount:int,paid:int}>} */
    private function mapHeaders(array $cells): array
    {
        $fields = [];
        $installments = [];

        foreach ($cells as $index => $cell) {
            $header = $this->norm($this->text($cell));
            if ($header === '') {
                continue;
            }

            if (isset($this->fieldIndex[$header])) {
                $fields[$this->fieldIndex[$header]] ??= $index;
                continue;
            }

            if (preg_match('/^' . preg_quote($this->norm('القسط'), '/') . ' (.+)$/u', $header, $m)
                && isset($this->ordinalIndex[$m[1]])) {
                // عمود تاريخ السداد بيجي بعد عمود القسط على طول
                $installments[$this->ordinalIndex[$m[1]]] = ['amount' => $index, 'paid' => $index + 1];
            }
        }

        $missing = [];
        foreach (['type' => 'نوع القسط', 'code' => 'كود', 'name' => 'الاسم'] as $key => $label) {
            if (! isset($fields[$key])) {
                $missing[] = $label;
            }
        }

        if ($missing !== []) {
            throw new ImportFormatException('الملف ناقص أعمدة أساسية: ' . implode('، ', $missing) . '. استخدم القالب الرسمي.');
        }

        if ($installments === []) {
            throw new ImportFormatException('مفيش أعمدة أقساط في الملف (القسط الأول ... السابع).');
        }

        ksort($installments);

        return ['fields' => $fields, 'installments' => $installments];
    }

    private function parseRow(int $rowNumber, array $cells, array $map): array
    {
        $errors = [];
        $warnings = [];
        $get = fn (string $field): mixed => isset($map['fields'][$field]) ? ($cells[$map['fields'][$field]] ?? null) : null;

        $code = $this->text($get('code'));
        $name = $this->collapse($this->text($get('name')));

        if ($code === '') {
            $errors[] = 'الكود مطلوب';
        }
        if ($name === '') {
            $errors[] = 'الاسم مطلوب';
        }

        $installments = [];
        foreach ($map['installments'] as $no => $idx) {
            $amount = $this->number($cells[$idx['amount']] ?? null, "قيمة القسط {$no}", $errors);
            $paidAt = $this->date($cells[$idx['paid']] ?? null, "تاريخ سداد القسط {$no}", $errors, $warnings);

            if ($amount !== null || $paidAt !== null) {
                $installments[] = ['no' => $no, 'amount' => $amount, 'paid_at' => $paidAt];
            }
        }

        // حقول مش جوهرية: الغلط فيها تنبيه، والصف بيتستورد
        $soft = [];
        $serial = $this->number($get('serial'), 'م', $soft);
        $interest = $this->number($get('interest'), 'الفائدة', $soft) ?? 0.0;
        $downDate = $this->date($get('down_date'), 'تاريخ المقدم', $soft, $warnings);
        foreach ($soft as $message) {
            $warnings[] = $message . ' (اتجاهل)';
        }

        $nationalId = preg_replace('/\D+/', '', strtr($this->text($get('national_id')), self::DIGITS));
        if ($nationalId !== '' && strlen($nationalId) !== 14) {
            $warnings[] = 'الرقم القومي لازم يكون 14 رقم (اتجاهل)';
            $nationalId = '';
        }

        $plan = $this->planType($this->text($get('type')), $errors);

        return [
            'row' => $rowNumber,
            'code' => $code,
            'name' => $name,
            'center' => $this->collapse($this->text($get('center'))),
            'plan_kind' => $plan['kind'],     // cash | years | completed
            'plan_years' => $plan['years'],   // 0 للكاش، 3/5/7، أو null لـ completed
            'serial' => $serial === null ? null : (int) $serial,
            'down_amount' => $this->number($get('down_amount'), 'مبلغ الدفعة المقدمة', $errors) ?? 0.0,
            'down_date' => $downDate,
            'interest' => $interest,
            'total' => $this->total($get('total')),
            'installments' => $installments,
            'notes' => $this->text($get('notes')),
            'national_id' => $nationalId,
            'citizen_phones' => $this->phones($get('citizen_phones'), 'هاتف المواطن', $warnings),
            'rep_name' => $this->collapse($this->text($get('rep_name'))),
            'rep_relationship' => $this->collapse($this->text($get('rep_relationship'))),
            'rep_phones' => $this->phones($get('rep_phones'), 'هاتف النائب', $warnings),
            'warnings' => $warnings,
            'errors' => $errors,
        ];
    }

    /**
     * أنواع «نوع القسط» في الملف:
     *  - «سداد 100%» أو كاش            => cash (دفعة واحدة)
     *  - «تم سداد كامل ثمن الأرض»      => completed (عقد مقسّط خلّص أقساطه، ونوعه من عدد الأقساط)
     *  - ثلاث/خمس/سبع سنوات            => years
     *
     * @return array{kind: ?string, years: ?int}
     */
    private function planType(string $text, array &$errors): array
    {
        $t = strtr($this->norm($text), self::DIGITS);

        if ($t === '') {
            $errors[] = 'نوع السداد مطلوب';
            return ['kind' => null, 'years' => null];
        }

        if (str_contains($t, $this->norm('كامل')) && str_contains($t, 'سداد')) {
            return ['kind' => 'completed', 'years' => null];
        }

        if (str_contains($t, 'كاش') || str_contains($t, 'نقد') || preg_match('/100\s*%/', $t)) {
            return ['kind' => 'cash', 'years' => 0];
        }

        foreach ([3 => ['ثلاث', 'تلات', '3'], 5 => ['خمس', '5'], 7 => ['سبع', '7']] as $years => $words) {
            foreach ($words as $word) {
                if (str_contains($t, $this->norm($word))) {
                    return ['kind' => 'years', 'years' => $years];
                }
            }
        }

        $errors[] = "نوع السداد غير معروف ({$text})";
        return ['kind' => null, 'years' => null];
    }

    /** إجمالي الإكسيل للمقارنة بس: المعادلات (=J2+L2...) والفاضي بيرجعوا null */
    private function total(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (is_string($value)) {
            $t = trim($value);
            if ($t === '' || str_starts_with($t, '=')) {
                return null;
            }

            $t = strtr($t, self::DIGITS + ['٬' => '', ',' => '', ' ' => '']);
            if (is_numeric($t)) {
                return (float) $t;
            }
        }

        return null;
    }

    private function number(mixed $value, string $label, array &$errors): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (is_string($value)) {
            $t = strtr(trim($value), self::DIGITS + ['٬' => '', ',' => '', '٫' => '.', ' ' => '']);
            if ($t === '') {
                return null;
            }
            if (is_numeric($t)) {
                return (float) $t;
            }
        }

        $errors[] = "قيمة غير رقمية في «{$label}»";
        return null;
    }

    private function date(mixed $value, string $label, array &$errors, array &$warnings): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value)->setTime(0, 0);
        }

        // رقم تاريخ إكسيل خام (الخلية مش منسقة كتاريخ)
        if ((is_int($value) || is_float($value)) && $value >= 20000 && $value <= 80000) {
            return (new DateTimeImmutable('1899-12-30'))->modify(((int) $value) . ' days');
        }

        if (is_string($value)) {
            $t = strtr(trim($value), self::DIGITS);

            // سنة/شهر/يوم  (2019/02/04)  أو  يوم/شهر/سنة  (30/02/2020)
            if (preg_match('/^(\d{4})[\/\-.](\d{1,2})[\/\-.](\d{1,2})$/', $t, $m)) {
                [$y, $mo, $d] = [(int) $m[1], (int) $m[2], (int) $m[3]];
            } elseif (preg_match('/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})$/', $t, $m)) {
                [$d, $mo, $y] = [(int) $m[1], (int) $m[2], (int) $m[3]];
            }

            if (isset($y, $mo, $d) && $mo >= 1 && $mo <= 12 && $d >= 1 && $d <= 31) {
                $last = (int) (new DateTimeImmutable(sprintf('%04d-%02d-01', $y, $mo)))->format('t');
                $fixed = $d > $last;
                $dt = (new DateTimeImmutable(sprintf('%04d-%02d-01', $y, $mo)))->setDate($y, $mo, min($d, $last))->setTime(0, 0);

                if ($fixed) {
                    $warnings[] = "«{$label}»: التاريخ ({$t}) مش موجود في التقويم، اتحسب {$dt->format('Y-m-d')}.";
                }

                return $dt;
            }
        }

        $errors[] = "تاريخ غير مفهوم في «{$label}»";
        return null;
    }

    /** @return list<string> */
    private function phones(mixed $value, string $label, array &$warnings): array
    {
        $raw = strtr($this->text($value), self::DIGITS);
        if ($raw === '') {
            return [];
        }

        $out = [];
        foreach (preg_split('/[,،;؛\/|\n\r]+/u', $raw) as $part) {
            $digits = preg_replace('/\D+/', '', $part);
            if ($digits === '') {
                continue;
            }

            // إكسيل بيشيل الصفر الأول من أرقام الموبايل
            if (strlen($digits) === 10 && $digits[0] === '1') {
                $digits = '0' . $digits;
            }

            if (strlen($digits) < 7 || strlen($digits) > 15) {
                $warnings[] = "رقم هاتف غير صحيح في «{$label}» ({$part}) اتجاهل";
                continue;
            }

            $out[$digits] = $digits;
        }

        return array_values($out);
    }

    private function isEmpty(array $cells): bool
    {
        foreach ($cells as $index => $cell) {
            if ($index === 0) {
                continue;   // عمود الترقيم
            }
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
        return trim(preg_replace('/[\s\x{00A0}]+/u', ' ', $value));
    }

    /** تنضيف العناوين للمطابقة بس */
    private function norm(string $value): string
    {
        $value = strtr($value, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ى' => 'ي', 'ة' => 'ه', 'ـ' => '']);

        return $this->collapse($value);
    }
}
