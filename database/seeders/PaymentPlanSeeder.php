<?php

namespace Database\Seeders;

use App\Models\PaymentPlan;
use Illuminate\Database\Seeder;

class PaymentPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            ['name' => 'كاش',        'installments_count' => 0, 'is_cash' => true],
            ['name' => 'ثلاث سنوات', 'installments_count' => 3, 'is_cash' => false],
            ['name' => 'خمس سنوات',  'installments_count' => 5, 'is_cash' => false],
            ['name' => 'سبع سنوات',  'installments_count' => 7, 'is_cash' => false],
        ];

        foreach ($plans as $plan) {
            PaymentPlan::updateOrCreate(['name' => $plan['name']], $plan);
        }
    }
}
