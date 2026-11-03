<?php

namespace App\Support;

/**
 * استعلامات لوحة التحكم. كلها على view اسمه overdue_installments:
 * القسط pending وموعد استحقاقه عدّى بيوم أو أكتر (days_overdue >= 1).
 *
 * كل دالة بتاخد جملة WHERE جاهزة (من OverdueFilters::where()) وبتتحط قبل GROUP BY.
 * مكتوبة SQL صريح ومتوافقة مع ONLY_FULL_GROUP_BY اللي Laravel بيشغّله في الوضع strict.
 */
class OverdueStatsSql
{
    /** شرائح التأخير: [المفتاح، من، إلى (null = مفتوح)، الاسم] */
    public const BUCKETS = [
        ['b1', 1, 30, '1 - 30 يوم'],
        ['b2', 31, 90, '31 - 90 يوم'],
        ['b3', 91, 180, '91 - 180 يوم'],
        ['b4', 181, 365, '181 - 365 يوم'],
        ['b5', 366, null, 'أكتر من سنة'],
    ];

    public static function totals(string $where = ''): string
    {
        return <<<SQL
            SELECT
                COUNT(*)                           AS installments,
                COUNT(DISTINCT citizen_id)         AS citizens,
                COALESCE(SUM(remaining_amount), 0) AS total
            FROM overdue_installments{$where}
        SQL;
    }

    public static function byPlan(string $where = ''): string
    {
        return <<<SQL
            SELECT
                payment_plan_name,
                COUNT(*)                           AS installments,
                COALESCE(SUM(remaining_amount), 0) AS total
            FROM overdue_installments{$where}
            GROUP BY payment_plan_id, payment_plan_name
            ORDER BY payment_plan_id
        SQL;
    }

    /** مبلغ وعدد كل شريحة تأخير في استعلام واحد */
    public static function aging(string $where = ''): string
    {
        $columns = [];

        foreach (self::BUCKETS as [$key, $from, $to]) {
            $condition = $to === null
                ? "days_overdue >= {$from}"
                : "days_overdue BETWEEN {$from} AND {$to}";

            $columns[] = "COALESCE(SUM(CASE WHEN {$condition} THEN remaining_amount END), 0) AS {$key}_amount";
            $columns[] = "COUNT(CASE WHEN {$condition} THEN 1 END) AS {$key}_count";
        }

        return 'SELECT ' . implode(', ', $columns) . ' FROM overdue_installments' . $where;
    }

    /** المتأخرات حسب المركز (المواطن من غير مركز بيتجمّع تحت «بدون مركز») */
    public static function byCenter(string $where = ''): string
    {
        return <<<SQL
            SELECT
                center_id,
                COALESCE(MAX(center_name), 'بدون مركز') AS center_name,
                COUNT(DISTINCT citizen_id)              AS citizens,
                COUNT(*)                                AS installments,
                COALESCE(SUM(remaining_amount), 0)      AS total
            FROM overdue_installments{$where}
            GROUP BY center_id
            ORDER BY total DESC, center_id
        SQL;
    }

    /** أكتر المواطنين مبلغ متأخر (مع التليفونات علشان التواصل) */
    public static function topCitizens(int $limit = 10, string $where = ''): string
    {
        $limit = max(1, min(100, $limit));

        return <<<SQL
            SELECT
                citizen_id,
                MAX(citizen_name)                  AS citizen_name,
                MAX(center_name)                   AS center_name,
                MAX(citizen_phones)                AS citizen_phones,
                MAX(representative_name)           AS representative_name,
                MAX(representative_phones)         AS representative_phones,
                COUNT(*)                           AS installments,
                MAX(days_overdue)                  AS max_days,
                COALESCE(SUM(remaining_amount), 0) AS total
            FROM overdue_installments{$where}
            GROUP BY citizen_id
            ORDER BY total DESC, citizen_id
            LIMIT {$limit}
        SQL;
    }
}
