<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * يقرأ من view اسمه overdue_installments - للعرض والتصدير فقط.
 */
class OverdueInstallment extends Model
{
    protected $table = 'overdue_installments';
    protected $primaryKey = 'installment_id';
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'remaining_amount' => 'decimal:2',
            'days_overdue' => 'integer',
        ];
    }

    public function save(array $options = []): never
    {
        throw new LogicException('OverdueInstallment is read-only.');
    }

    public function delete(): never
    {
        throw new LogicException('OverdueInstallment is read-only.');
    }
}
