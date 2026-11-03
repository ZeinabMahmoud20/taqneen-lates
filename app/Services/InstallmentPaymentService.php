<?php

namespace App\Services;

use App\Models\Installment;
use App\Models\Payment;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InstallmentPaymentService
{
    /**
     * تسجيل سداد قسط. القسط بيتسدد بقيمته كاملة بالظبط (مفيش دفع جزئي).
     * لو المبلغ الفعلي مختلف، عدّل قيمة القسط الأول وبعدين سجّل السداد.
     */
    public function pay(
        Installment $installment,
        CarbonInterface|string $paidAt,
        ?string $receiptNo = null,
        ?int $userId = null,
    ): Payment {
        $paidAt = Carbon::parse($paidAt)->startOfDay();

        if ($paidAt->isFuture()) {
            $this->fail('paid_at', 'تاريخ السداد لا يمكن أن يكون في المستقبل.');
        }

        return DB::transaction(function () use ($installment, $paidAt, $receiptNo, $userId) {
            // قفل الصف علشان مفيش موظفين يسجلوا نفس القسط مرتين في نفس اللحظة
            $locked = Installment::query()->whereKey($installment->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->isPaid()) {
                $this->fail('installment', 'القسط مسدد بالفعل.');
            }

            $payment = Payment::create([
                'contract_id' => $locked->contract_id,
                'installment_id' => $locked->id,
                'type' => Payment::TYPE_INSTALLMENT,
                'amount' => $locked->amount,
                'paid_at' => $paidAt->toDateString(),
                'receipt_no' => $receiptNo,
                'created_by' => $userId,
            ]);

            $locked->update([
                'paid_amount' => $locked->amount,
                'paid_at' => $paidAt->toDateString(),
                'status' => Installment::STATUS_PAID,
                'updated_by' => $userId,
            ]);

            return $payment;
        });
    }

    /**
     * إلغاء سداد قسط (تصحيح خطأ إدخال). بيرجّع القسط pending.
     * ده للمشرف العام بس (الصلاحية بتتفحص في الشاشة)، والعملية بتتسجل في سجل العمليات.
     */
    public function void(Installment $installment, ?int $userId = null): void
    {
        DB::transaction(function () use ($installment, $userId) {
            $locked = Installment::query()->whereKey($installment->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->isPaid()) {
                $this->fail('installment', 'القسط غير مسدد أصلاً.');
            }

            Payment::query()
                ->where('installment_id', $locked->id)
                ->where('type', Payment::TYPE_INSTALLMENT)
                ->get()
                ->each->delete();   // each->delete علشان الحذف يتسجل في سجل العمليات

            $locked->update([
                'paid_amount' => 0,
                'paid_at' => null,
                'status' => Installment::STATUS_PENDING,
                'updated_by' => $userId,
            ]);
        });
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
