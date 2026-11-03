<?php

namespace App\Support;

/**
 * تنسيق سجل العمليات بالعربي (PHP صافي - مفيهوش اعتماد على Laravel).
 *
 * شكل attribute_changes في spatie/laravel-activitylog v5:
 *   ['attributes' => [حقل => القيمة الجديدة], 'old' => [حقل => القيمة القديمة]]
 *   الإضافة: attributes بس. الحذف: old بس. التعديل: الاتنين (للحقول اللي اتغيرت بس).
 */
class ActivityChanges
{
    /** حقول تقنية مش مفيدة في العرض */
    private const HIDDEN = ['created_by', 'updated_by', 'created_at', 'updated_at', 'password', 'remember_token'];

    private const FIELD_LABELS = [
        'name' => 'الاسم',
        'national_id' => 'الرقم القومي',
        'center_id' => 'المركز (رقم)',
        'address' => 'العنوان',
        'governorate' => 'المحافظة',
        'code' => 'الكود',
        'serial_no' => 'م',
        'citizen_id' => 'المواطن (رقم)',
        'payment_plan_id' => 'نوع السداد (رقم)',
        'down_payment_amount' => 'مبلغ المقدم',
        'down_payment_date' => 'تاريخ المقدم',
        'interest_amount' => 'الفائدة',
        'total_price' => 'إجمالي الثمن',
        'contract_id' => 'العقد (رقم)',
        'installment_id' => 'القسط (رقم)',
        'installment_no' => 'رقم القسط',
        'due_date' => 'تاريخ السداد المستحق',
        'amount' => 'المبلغ',
        'paid_amount' => 'المبلغ المدفوع',
        'paid_at' => 'تاريخ السداد الفعلي',
        'status' => 'الحالة',
        'type' => 'النوع',
        'receipt_no' => 'رقم الإيصال',
        'phone' => 'رقم الهاتف',
        'label' => 'ملاحظة الرقم',
        'is_primary' => 'الرقم الأساسي',
        'relationship' => 'صلة القرابة',
        'started_at' => 'بداية النيابة',
        'ended_at' => 'نهاية النيابة',
        'note' => 'الملاحظة',
        'user_id' => 'المستخدم (رقم)',
        'email' => 'البريد الإلكتروني',
        'role' => 'الدور',
        'is_active' => 'الحساب مفعّل',
        'installments_count' => 'عدد الأقساط',
        'is_cash' => 'كاش',
    ];

    private const VALUE_LABELS = [
        'status' => ['pending' => 'لم يُسدد', 'paid' => 'مسدد'],
        'type' => ['down_payment' => 'مقدم', 'installment' => 'قسط', 'cash' => 'سداد كاش'],
        'role' => ['super_admin' => 'مشرف عام', 'regulator' => 'مسئول تقنين', 'leadership' => 'قيادة'],
    ];

    /** الموديل (اسم الكلاس) => اسمه بالعربي */
    public const SUBJECT_LABELS = [
        'App\\Models\\Citizen' => 'مواطن',
        'App\\Models\\CitizenPhone' => 'هاتف مواطن',
        'App\\Models\\Representative' => 'نائب',
        'App\\Models\\RepresentativePhone' => 'هاتف نائب',
        'App\\Models\\Contract' => 'عقد',
        'App\\Models\\Installment' => 'قسط',
        'App\\Models\\Payment' => 'دفعة',
        'App\\Models\\InstallmentNote' => 'ملاحظة قسط',
        'App\\Models\\Center' => 'مركز',
        'App\\Models\\PaymentPlan' => 'نوع سداد',
        'App\\Models\\User' => 'مستخدم',
    ];

    private const EVENT_LABELS = [
        'created' => 'إضافة',
        'updated' => 'تعديل',
        'deleted' => 'حذف',
        'restored' => 'استرجاع',
    ];

    public static function eventLabel(?string $event): string
    {
        return self::EVENT_LABELS[$event ?? ''] ?? ($event ?: '—');
    }

    public static function subjectLabel(?string $class): string
    {
        if ($class === null || $class === '') {
            return '—';
        }

        return self::SUBJECT_LABELS[$class] ?? self::basename($class);
    }

    /**
     * @param  array<string, mixed>|null  $changes
     * @return list<array{field: string, old: string, new: string}>
     */
    public static function rows(?array $changes, ?string $event): array
    {
        $new = (array) ($changes['attributes'] ?? []);
        $old = (array) ($changes['old'] ?? []);

        $rows = [];
        foreach (array_unique(array_merge(array_keys($new), array_keys($old))) as $field) {
            if (in_array($field, self::HIDDEN, true)) {
                continue;
            }

            $rows[] = [
                'field' => self::FIELD_LABELS[$field] ?? (string) $field,
                'old' => $event === 'created' ? '—' : self::value($field, $old[$field] ?? null),
                'new' => $event === 'deleted' ? '—' : self::value($field, $new[$field] ?? null),
            ];
        }

        return $rows;
    }

    /** HTML جاهز للعرض في نافذة التفاصيل (كل النصوص متأمّنة) */
    public static function html(?array $changes, ?string $event): string
    {
        $rows = self::rows($changes, $event);

        if ($rows === []) {
            return '<p>مفيش تفاصيل حقول مسجلة للعملية دي.</p>';
        }

        $cell = 'style="padding:6px 10px;border-bottom:1px solid rgba(128,128,128,.25);text-align:start;"';
        $html = '<table style="width:100%;border-collapse:collapse;font-size:.9em;">'
            . "<thead><tr><th $cell>الحقل</th><th $cell>قبل</th><th $cell>بعد</th></tr></thead><tbody>";

        foreach ($rows as $row) {
            $html .= "<tr><td $cell>" . self::e($row['field']) . "</td><td $cell>" . self::e($row['old'])
                . "</td><td $cell>" . self::e($row['new']) . '</td></tr>';
        }

        return $html . '</tbody></table>';
    }

    private static function value(string|int $field, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }
        if (is_bool($value)) {
            return $value ? 'نعم' : 'لا';
        }
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE) ?: '—';
        }

        $text = (string) $value;

        if (isset(self::VALUE_LABELS[$field][$text])) {
            return self::VALUE_LABELS[$field][$text];
        }

        // is_primary / is_active / is_cash بتتخزن 0/1
        if (in_array($field, ['is_primary', 'is_active', 'is_cash'], true) && in_array($text, ['0', '1'], true)) {
            return $text === '1' ? 'نعم' : 'لا';
        }

        return mb_strlen($text) > 200 ? mb_substr($text, 0, 200) . '…' : $text;
    }

    private static function e(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** آخر جزء من اسم الكلاس */
    private static function basename(string $class): string
    {
        $pos = strrpos($class, '\\');

        return $pos === false ? $class : substr($class, $pos + 1);
    }
}
