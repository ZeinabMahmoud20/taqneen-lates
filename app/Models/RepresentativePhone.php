<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepresentativePhone extends Model
{
    use Auditable;

    protected $fillable = ['representative_id', 'phone', 'label', 'is_primary'];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }

    public function representative(): BelongsTo
    {
        return $this->belongsTo(Representative::class);
    }
}
