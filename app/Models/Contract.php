<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Contract extends Model
{
    use Auditable;

    protected $fillable = [
        'code', 'citizen_id', 'payment_plan_id', 'serial_no',
        'down_payment_amount', 'down_payment_date',
        'interest_amount', 'total_price',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'down_payment_amount' => 'decimal:2',
            'interest_amount' => 'decimal:2',
            'total_price' => 'decimal:2',
            'down_payment_date' => 'date',
        ];
    }

    public function citizen(): BelongsTo
    {
        return $this->belongsTo(Citizen::class);
    }

    public function paymentPlan(): BelongsTo
    {
        return $this->belongsTo(PaymentPlan::class);
    }

    public function installments(): HasMany
    {
        return $this->hasMany(Installment::class)->orderBy('installment_no');
    }

    /** كل ملاحظات أقساط العقد */
    public function notes(): HasManyThrough
    {
        return $this->hasManyThrough(InstallmentNote::class, Installment::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
