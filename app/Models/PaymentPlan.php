<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentPlan extends Model
{
    use Auditable;

    protected $fillable = ['name', 'installments_count', 'is_cash'];

    protected function casts(): array
    {
        return [
            'is_cash' => 'boolean',
            'installments_count' => 'integer',
        ];
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }
}
