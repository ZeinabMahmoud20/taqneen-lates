<?php

namespace App\Filament\Resources\Contracts\Pages;

use App\Filament\Resources\Contracts\ContractResource;
use App\Models\Citizen;
use App\Models\PaymentPlan;
use App\Services\ContractService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateContract extends CreateRecord
{
    protected static string $resource = ContractResource::class;

    /**
     * الإنشاء بيتم عن طريق ContractService (مش insert مباشر) علشان:
     *  - جدول الأقساط يتعمل مع العقد في transaction واحدة،
     *  - مواعيد الاستحقاق تتحسب = تاريخ المقدم + N سنة،
     *  - المقدم والكاش يتسجلوا كدفعات.
     */
    protected function handleRecordCreation(array $data): Model
    {
        $citizen = Citizen::query()->findOrFail($data['citizen_id']);
        $plan = PaymentPlan::query()->findOrFail($data['payment_plan_id']);

        $payload = [
            'code' => $data['code'],
            'serial_no' => filled($data['serial_no'] ?? null) ? (int) $data['serial_no'] : null,
            'interest_amount' => $data['interest_amount'] ?? 0,
            'created_by' => auth()->id(),
        ];

        $amounts = [];

        if ($plan->is_cash) {
            // سداد 100%: دفعة واحدة
            $payload += [
                'down_payment_amount' => 0,
                'down_payment_date' => null,
                'total_price' => $data['cash_amount'] ?? 0,
                'cash_paid_at' => $data['cash_paid_at'] ?? null,
            ];
        } else {
            $amounts = collect($data['installments'] ?? [])
                ->pluck('amount')
                ->filter(fn ($amount) => filled($amount))
                ->values()
                ->all();

            $down = (float) ($data['down_payment_amount'] ?? 0);

            // إجمالي الثمن = المقدم + مجموع الأقساط
            $payload += [
                'down_payment_amount' => $down,
                'down_payment_date' => $data['down_payment_date'] ?? null,
                'total_price' => round($down + array_sum(array_map('floatval', $amounts)), 2),
            ];
        }

        try {
            return app(ContractService::class)->create($citizen, $plan, $payload, $amounts);
        } catch (ValidationException $e) {
            Notification::make()
                ->danger()
                ->title('لم يتم تسجيل العقد')
                ->body(collect($e->errors())->flatten()->implode(' '))
                ->persistent()
                ->send();
            $this->halt();
        }
    }
}
