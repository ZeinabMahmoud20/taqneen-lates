<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Representative extends Model
{
    use Auditable;

    protected $fillable = ['citizen_id', 'name', 'relationship', 'started_at', 'ended_at'];

    protected static function booted(): void
    {
        // النائب الحالي = ended_at فاضي، ومواطن واحد ليه نائب حالي واحد بس:
        // إضافة نائب حالي جديد بتنهي النائب الحالي القديم (من أي شاشة أو استيراد)
        static::created(function (Representative $representative) {
            if ($representative->ended_at !== null) {
                return;
            }

            static::query()
                ->where('citizen_id', $representative->citizen_id)
                ->where('id', '!=', $representative->id)
                ->whereNull('ended_at')
                ->get()
                ->each(fn (Representative $old) => $old->update(['ended_at' => today()]));
        });
    }

    protected function casts(): array
    {
        return [
            'started_at' => 'date',
            'ended_at' => 'date',
        ];
    }

    public function citizen(): BelongsTo
    {
        return $this->belongsTo(Citizen::class);
    }

    public function phones(): HasMany
    {
        return $this->hasMany(RepresentativePhone::class);
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->whereNull('ended_at');
    }
}
