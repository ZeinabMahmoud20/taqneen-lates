<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** سجل العمليات: عرض فقط، وللمشرف العام بس. مفيش إضافة ولا تعديل ولا حذف لأي دور. */
class ActivityPolicy
{
    private function isAdmin(User $user): bool
    {
        return $user->is_active && ($user->role?->canManageUsers() ?? false);
    }

    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function view(User $user, Model $model): bool
    {
        return $this->isAdmin($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Model $model): bool
    {
        return false;
    }

    public function delete(User $user, Model $model): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
