<?php

namespace Database\Seeders;

use App\Models\Center;
use App\Models\Citizen;
use App\Models\Contract;
use App\Models\PaymentPlan;
use App\Services\ContractService;
use Illuminate\Database\Seeder;

/**
 * بيانات تجريبية للمتأخرات (أكواد DEMO-*).
 * تمسحها بعد التجربة من php artisan tinker:
 *   Citizen::whereHas('contracts', fn ($q) => $q->where('code', 'like', 'DEMO-%'))->each->delete();
 */
class DemoOverdueSeeder extends Seeder
{
    public function run(): void
    {
        $contracts = new ContractService();
        $centers = collect(['بلطيم', 'كفر الشيخ', 'دسوق'])->map(fn ($n) => Center::firstOrCreate(['name' => $n]))->values();

        // [الاسم، نوع السداد بالسنوات، تاريخ القسط الأول، المقدم، قيمة القسط الأساسية، فيه نائب؟]
        $rows = [
            ['صبري محمد',     3, '2025-01-28', 37170, 84000, true],
            ['سامح أبو العلا', 3, '2025-06-15', 40640, 90000, false],
            ['محمود إبراهيم', 5, '2025-02-10', 30000, 50000, true],
            ['عادل حسن',      5, '2024-11-05', 25000, 45000, false],
            ['خالد عبد الله', 7, '2025-03-20', 20000, 30000, true],
            ['رضا السيد',     7, '2025-09-01', 22000, 32000, false],
        ];

        foreach ($rows as $i => [$name, $years, $firstDue, $down, $base, $hasRep]) {
            $code = sprintf('DEMO-%03d', $i + 1);

            if (Contract::where('code', $code)->exists()) {
                continue;
            }

            $citizen = Citizen::create([
                'name' => $name,
                'center_id' => $centers[$i % $centers->count()]->id,
            ]);
            $citizen->phones()->create(['phone' => '0100000' . sprintf('%04d', $i + 1), 'is_primary' => true]);

            if ($hasRep) {
                $rep = $citizen->representatives()->create(['name' => 'نائب ' . $name, 'relationship' => 'ابن']);
                $rep->phones()->create(['phone' => '0111000' . sprintf('%04d', $i + 1)]);
                $rep->phones()->create(['phone' => '0122000' . sprintf('%04d', $i + 1)]);
            }

            // أقساط متناقصة زي الإكسيل
            $amounts = [];
            for ($n = 0; $n < $years; $n++) {
                $amounts[] = $base - ($n * 4000);
            }

            $plan = PaymentPlan::where('installments_count', $years)->firstOrFail();

            $contracts->create(
                $citizen,
                $plan,
                [
                    'code' => $code,
                    'down_payment_amount' => $down,
                    'down_payment_date' => \Illuminate\Support\Carbon::parse($firstDue)->subYear()->toDateString(),
                    'total_price' => $down + array_sum($amounts),
                ],
                $amounts,
            );
        }
    }
}
