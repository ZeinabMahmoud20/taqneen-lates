<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * القسط بيتسدد بقيمته كاملة، ومفيش ترحيل تلقائي للفروق.
 * لو المبلغ الفعلي مختلف، الموظف بيعدّل قيمة القسط قبل تسجيل السداد.
 * فبنشيل أعمدة الترحيل وحالة carried_over ونعيد بناء view المتأخرات.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP VIEW IF EXISTS overdue_installments');

        Schema::table('installments', function (Blueprint $table) {
            $table->dropColumn(['carried_in_amount', 'carried_out_amount']);
        });

        DB::statement("ALTER TABLE installments MODIFY status ENUM('pending','paid') NOT NULL DEFAULT 'pending'");

        DB::statement($this->viewSql('(i.amount - i.paid_amount)', ''));
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS overdue_installments');

        DB::statement("ALTER TABLE installments MODIFY status ENUM('pending','paid','carried_over') NOT NULL DEFAULT 'pending'");

        Schema::table('installments', function (Blueprint $table) {
            $table->decimal('carried_in_amount', 14, 2)->default(0)->after('amount');
            $table->decimal('carried_out_amount', 14, 2)->default(0)->after('paid_amount');
        });

        DB::statement($this->viewSql(
            '(i.amount + i.carried_in_amount - i.paid_amount)',
            'i.carried_in_amount,'
        ));
    }

    private function viewSql(string $remainingExpr, string $extraColumns): string
    {
        // المتأخر = لسه pending وتاريخ استحقاقه عدّى بيوم أو أكتر (بدون فترة سماح)
        return <<<SQL
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
                {$extraColumns}
                i.paid_amount,
                {$remainingExpr}                             AS remaining_amount
            FROM installments i
            JOIN contracts c         ON c.id = i.contract_id
            JOIN payment_plans pp    ON pp.id = c.payment_plan_id
            JOIN citizens ctz        ON ctz.id = c.citizen_id
            LEFT JOIN centers cen    ON cen.id = ctz.center_id
            LEFT JOIN representatives rep
                   ON rep.citizen_id = ctz.id AND rep.ended_at IS NULL
            WHERE i.status = 'pending'
              AND i.due_date < CURDATE()
        SQL;
    }
};
