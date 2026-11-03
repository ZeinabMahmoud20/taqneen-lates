<?php

namespace App\Models\Concerns;

use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * سجل العمليات: بيسجل مين أضاف/عدّل/حذف إيه، والقيم القديمة والجديدة.
 * (المستخدم اللي نفّذ العملية بيتسجل تلقائياً من الجلسة)
 */
trait Auditable
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty();
    }
}
