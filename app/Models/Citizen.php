<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Citizen extends Model
{
    use Auditable;

    protected $fillable = [
        'name', 'national_id', 'center_id', 'address',
        'created_by', 'updated_by',
    ];

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    public function phones(): HasMany
    {
        return $this->hasMany(CitizenPhone::class);
    }

    public function representatives(): HasMany
    {
        return $this->hasMany(Representative::class);
    }

    // النائب الحالي = اللي ended_at عنده فاضي
    public function currentRepresentative(): HasOne
    {
        // الشرط لازم يتحط جوه الـ subquery بتاع ofMany. لو اتحط قبل latestOfMany() بيتطبق
        // بعد اختيار أكبر id، فنائب قديم اتضاف بعد الحالي كان بيخفي النائب الحالي.
        return $this->hasOne(Representative::class)
            ->ofMany(['id' => 'max'], fn ($query) => $query->whereNull('ended_at'));
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }
}
