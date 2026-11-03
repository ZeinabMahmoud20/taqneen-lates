<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // المتأخر = لسه pending وتاريخ استحقاقه عدّى بيوم أو أكتر (بدون فترة سماح)
        // الفلترة حسب نوع السداد: WHERE payment_plan_id = ?
        DB::statement(<<<'SQL'
            CREATE OR REPLACE VIEW overdue_installments AS
            SELECT
                i.id                                         AS installment_id,
                i.contract_id,
                c.serial_no,
                c.payment_plan_id,
                pp.name                                      AS payment_plan_name,
                ctz.id                                       AS citizen_id,
                ctz.code                                     AS citizen_code,
                ctz.name                                     AS citizen_name,
                ctz.center_id,
                cen.name                                     AS center_name,
                (SELECT GROUP_CONCAT(p.phone SEPARATOR ' - ')
                   FROM citizen_phones p
                  WHERE p.citizen_id = ctz.id)               AS citizen_phones,
                rep.name                                     AS representative_name,
                rep.relationship                             AS representative_relationship,
                (SELECT GROUP_CONCAT(rp.phone SEPARATOR ' - ')
                   FROM representative_phones rp
                  WHERE rp.representative_id = rep.id)       AS representative_phones,
                i.installment_no,
                i.due_date,
                DATEDIFF(CURDATE(), i.due_date)              AS days_overdue,
                i.amount,
                i.carried_in_amount,
                i.paid_amount,
                (i.amount + i.carried_in_amount - i.paid_amount) AS remaining_amount
            FROM installments i
            JOIN contracts c         ON c.id = i.contract_id
            JOIN payment_plans pp    ON pp.id = c.payment_plan_id
            JOIN citizens ctz        ON ctz.id = c.citizen_id
            LEFT JOIN centers cen    ON cen.id = ctz.center_id
            LEFT JOIN representatives rep
                   ON rep.citizen_id = ctz.id AND rep.ended_at IS NULL
            WHERE i.status = 'pending'
              AND i.due_date < CURDATE()
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS overdue_installments');
    }
};
