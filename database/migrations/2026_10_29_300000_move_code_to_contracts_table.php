<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * «الكود» في الإكسيل فريد لكل عقد (ونفس المواطن ممكن له أكواد مختلفة)،
 * فمكانه الصح جدول contracts مش citizens.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP VIEW IF EXISTS overdue_installments');

        Schema::table('contracts', function (Blueprint $table) {
            $table->string('code', 50)->nullable()->after('id');
        });

        // نسخ كود المواطن الحالي لعقوده
        DB::statement('UPDATE contracts c JOIN citizens z ON z.id = c.citizen_id SET c.code = z.code');

        // لو مواطن عنده أكتر من عقد، نميّز الأكواد المكررة بإضافة id العقد
        DB::statement(
            'UPDATE contracts c
               JOIN (SELECT code FROM contracts GROUP BY code HAVING COUNT(*) > 1) d ON d.code = c.code
                SET c.code = CONCAT(c.code, \'-\', c.id)'
        );

        DB::statement('ALTER TABLE contracts MODIFY code VARCHAR(50) NOT NULL');

        Schema::table('contracts', function (Blueprint $table) {
            $table->unique('code');
        });

        Schema::table('citizens', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn('code');
        });

        DB::statement($this->viewSql('c.code AS contract_code'));
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS overdue_installments');

        Schema::table('citizens', function (Blueprint $table) {
            $table->string('code')->nullable()->after('id');
        });

        DB::statement(
            'UPDATE citizens z
               JOIN (SELECT citizen_id, MIN(code) AS code FROM contracts GROUP BY citizen_id) x ON x.citizen_id = z.id
                SET z.code = x.code'
        );
        DB::statement("UPDATE citizens SET code = CONCAT('CIT-', id) WHERE code IS NULL");
        DB::statement('ALTER TABLE citizens MODIFY code VARCHAR(255) NOT NULL');

        Schema::table('citizens', function (Blueprint $table) {
            $table->unique('code');
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn('code');
        });

        DB::statement($this->viewSql('ctz.code AS citizen_code'));
    }

    private function viewSql(string $codeColumn): string
    {
        // المتأخر = لسه pending وتاريخ استحقاقه عدّى بيوم أو أكتر (بدون فترة سماح)
        return <<<SQL
            CREATE OR REPLACE VIEW overdue_installments AS
            SELECT
                i.id                                         AS installment_id,
                i.contract_id,
                {$codeColumn},
                c.serial_no,
                c.payment_plan_id,
                pp.name                                      AS payment_plan_name,
                ctz.id                                       AS citizen_id,
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
                i.paid_amount,
                (i.amount - i.paid_amount)                   AS remaining_amount
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
