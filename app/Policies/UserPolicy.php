<?php

namespace App\Policies;

use App\Models\User;

/**
 * إدارة المستخدمين: للمشرف العام فقط.
 */
class UserPolicy
{
    private function isAdmin(User $user): bool
    {
        return $user->is_active && ($user->role?->canManageUsers() ?? false);
    }

    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function view(User $user, User $model): bool
    {
        return $this->isAdmin($user);
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function update(User $user, User $model): bool
    {
        return $this->isAdmin($user);
    }

    public function delete(User $user, User $model): bool
    {
        // ممنوع المشرف يحذف نفسه
        return $this->isAdmin($user) && $user->id !== $model->id;
    }

    public function deleteAny(User $user): bool
    {
        return $this->isAdmin($user);
    }
}
