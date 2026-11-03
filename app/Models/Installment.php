<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Installment extends Model
{
    use Auditable;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';

    protected $fillable = [
        'contract_id', 'installment_no', 'due_date', 'amount',
        'paid_amount', 'paid_at', 'status', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'paid_at' => 'date',
            'amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        // القسط المسدد مايتعدلش مبلغه ولا تاريخ استحقاقه
        static::updating(function (Installment $installment) {
            $wasPaid = $installment->getOriginal('status') === self::STATUS_PAID;

            // تعبئة موعد استحقاق كان فاضي (null -> تاريخ) مسموحة حتى لو القسط مسدد.
            // أما تغيير مبلغ أو موعد موجود فمرفوض.
            $changesDueDate = $installment->isDirty('due_date') && $installment->getOriginal('due_date') !== null;

            if ($wasPaid && ($installment->isDirty('amount') || $changesDueDate)) {
                throw new DomainException('لا يمكن تعديل قسط مسدد. ألغِ السداد أولاً.');
            }
        });
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(InstallmentNote::class)->latest();
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    // متأخر = لسه pending وتاريخه عدّى بيوم أو أكتر (بدون فترة سماح)
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING)
            ->whereDate('due_date', '<', today());
    }

    // قسط لسه ماتدفعش ومفيش له موعد استحقاق (بيانات ناقصة محتاجة تكملة يدوي)
    public function scopeMissingDueDate(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING)->whereNull('due_date');
    }

    // المبلغ المتبقي على القسط
    public function getRemainingAmountAttribute(): string
    {
        return number_format(round((float) $this->amount - (float) $this->paid_amount, 2), 2, '.', '');
    }
}
