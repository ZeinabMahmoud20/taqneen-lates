<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Center extends Model
{
    use Auditable;

    protected $fillable = ['name', 'governorate'];

    public function citizens(): HasMany
    {
        return $this->hasMany(Citizen::class);
    }
}
