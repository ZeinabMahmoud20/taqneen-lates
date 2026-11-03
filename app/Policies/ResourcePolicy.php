<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * سياسة مشتركة لكل جداول البيانات (مواطنين - عقود - أقساط - مدفوعات ...).
 * بتتسجل لكل Model في AppServiceProvider.
 */
class ResourcePolicy
{
    private function role(User $user): ?UserRole
    {
        // الحساب الموقوف مالوش أي صلاحية
        return $user->is_active ? $user->role : null;
    }

    public function viewAny(User $user): bool
    {
        return $this->role($user)?->canView() ?? false;
    }

    public function view(User $user, Model $model): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->role($user)?->canCreateOrUpdate() ?? false;
    }

    public function update(User $user, Model $model): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->role($user)?->canDelete() ?? false;
    }

    public function deleteAny(User $user): bool
    {
        return $this->role($user)?->canDelete() ?? false;
    }

    public function restore(User $user, Model $model): bool
    {
        return $this->deleteAny($user);
    }

    public function restoreAny(User $user): bool
    {
        return $this->deleteAny($user);
    }

    public function forceDelete(User $user, Model $model): bool
    {
        return $this->deleteAny($user);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $this->deleteAny($user);
    }

    /** تصدير: $user->can('export', Citizen::class) */
    public function export(User $user): bool
    {
        return $this->role($user)?->canExport() ?? false;
    }
}
