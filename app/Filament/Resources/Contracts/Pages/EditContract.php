<?php

namespace App\Filament\Resources\Contracts\Pages;

use App\Filament\Resources\Contracts\ContractResource;
use App\Models\Installment;
use App\Models\Payment;
use App\Models\PaymentPlan;
use App\Services\ContractService;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditContract extends EditRecord
{
    protected static string $resource = ContractResource::class;

    /** الأقساط غير المسددة اللي المستخدم كتب قيمتها في النموذج */
    private array $installmentRows = [];

    private ?PaymentPlan $selectedPlan = null;

    private ?string $cashPaidAt = null;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /** الأقساط المسددة مش بتتعرض في النموذج علشان مابتتغيرش، بس قيمتها داخلة في حساب الإجمالي. */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();

        $data['installments'] = $record->installments()
            ->where('status', Installment::STATUS_PENDING)
            ->orderBy('installment_no')
            ->get(['installment_no', 'amount'])
            ->map(fn (Installment $installment): array => ['amount' => $installment->amount])
            ->all();

        $paidTotal = $record->installments()
            ->where('status', Installment::STATUS_PAID)
            ->sum('amount');

        $data['paid_installments_total'] = number_format((float) $paidTotal, 2, '.', '');

        if ($record->paymentPlan?->is_cash) {
            $data['cash_amount'] = $record->total_price;
            $data['cash_paid_at'] = Payment::query()
                ->where('contract_id', $record->id)
                ->where('type', Payment::TYPE_CASH)
                ->value('paid_at');
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by'] = auth()->id();

        $plan = PaymentPlan::query()->findOrFail($data['payment_plan_id']);
        $service = app(ContractService::class);

        if ($plan->is_cash) {
            // الكاش مفيش فيه مقدم ولا أقساط
            $data['down_payment_amount'] = 0;
            $data['down_payment_date'] = null;
        }

        try {
            // بيتحقق من عدد الأقساط في كل حالة، ويحسب الإجمالي = المقدم/الكاش + المسدد + الجديد
            $data['total_price'] = $service->prepareEditTotal($this->getRecord(), $plan, $data);
        } catch (ValidationException $exception) {
            Notification::make()
                ->danger()
                ->title('لم يتم تعديل العقد')
                ->body(collect($exception->errors())->flatten()->implode(' '))
                ->persistent()
                ->send();
            $this->halt();
        }

        $this->installmentRows = array_values($data['installments'] ?? []);
        $this->cashPaidAt = $plan->is_cash
            ? (filled($data['cash_paid_at'] ?? null) ? $data['cash_paid_at'] : today()->toDateString())
            : null;
        $this->selectedPlan = $plan;

        unset($data['installments'], $data['cash_amount'], $data['cash_paid_at'], $data['paid_installments_total']);

        return $data;
    }

    /**
     * بعد الحفظ: جدول الأقساط بيتظبط حسب نوع السداد (المسدد منها مش بيتلمس)،
     * وسجلات الدفع (المقدم والكاش) بتطابق بيانات العقد، ومواعيد الأقساط الناقصة بتتحسب.
     */
    protected function afterSave(): void
    {
        $service = app(ContractService::class);

        $service->syncDownPayment($this->record, auth()->id());
        $service->syncCashPayment($this->record, $this->cashPaidAt, auth()->id());

        $created = $service->syncInstallmentsForEdit($this->record, $this->selectedPlan, $this->installmentRows, auth()->id());

        $filled = $service->fillMissingDueDates($this->record);

        if ($filled > 0 || $created > 0) {
            Notification::make()
                ->success()
                ->title($created > 0
                    ? "تم تحديث الأقساط (أُضيف {$created} قسط)"
                    : "تم حساب مواعيد استحقاق {$filled} قسط من تاريخ المقدم")
                ->send();
        }
    }
}
