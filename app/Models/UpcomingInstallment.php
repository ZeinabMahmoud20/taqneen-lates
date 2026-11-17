<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class UpcomingInstallment extends Model
{
    protected $table = 'upcoming_installments';

    protected $primaryKey = 'installment_id';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'remaining_amount' => 'decimal:2',
            'days_remaining' => 'integer',
        ];
    }

    public function save(array $options = []): never
    {
        throw new LogicException('UpcomingInstallment is read-only.');
    }

    public function delete(): never
    {
        throw new LogicException('UpcomingInstallment is read-only.');
    }
}
