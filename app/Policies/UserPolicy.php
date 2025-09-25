<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_user');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user): bool
    {
        return $user->can('view_user');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_user');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model): bool
    {
        if ($user->hasRole('admin') && $model->hasRole('admin')) {
            return false;
        }

        // Cek apakah model punya role yang mengandung 'manager' atau 'manajer'
        $modelHasManagerRole = $model->roles->pluck('name')->contains(function ($roleName) {
            $roleName = strtolower($roleName);

            return str_contains($roleName, 'manager') || str_contains($roleName, 'manajer') || $roleName === 'admin';
        });

        // Cek apakah user punya role yang mengandung 'manager' atau 'manajer'
        $userIsManager = $user->roles->pluck('name')->contains(function ($roleName) {
            $roleName = strtolower($roleName);

            return str_contains($roleName, 'manager') || str_contains($roleName, 'manajer');
        });

        if ($modelHasManagerRole && $userIsManager) {
            return false;
        }

        // Super admin strict check
        if ($model->hasRole(['super_admin', 'admin']) && ! $user->hasRole('super_admin')) {
            return false;
        }

        if ($user->id === $model->id) {
            return false;
        }

        return $user->can('update_user');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, User $model): bool
    {
        if ($model->hasRole(['super_admin', 'admin']) && ! $user->hasRole('super_admin')) {
            return false;
        }

        // Cek apakah model punya role yang mengandung 'manager' atau 'manajer'
        $modelHasManagerRole = $model->roles->pluck('name')->contains(function ($roleName) {
            $roleName = strtolower($roleName);

            return str_contains($roleName, 'manager') || str_contains($roleName, 'manajer') || $roleName === 'admin';
        });

        // Cek apakah user punya role yang mengandung 'manager' atau 'manajer'
        $userIsManager = $user->roles->pluck('name')->contains(function ($roleName) {
            $roleName = strtolower($roleName);

            return str_contains($roleName, 'manager') || str_contains($roleName, 'manajer');
        });

        if ($modelHasManagerRole && $userIsManager) {
            return false;
        }

        // Larang pengguna menghapus dirinya sendiri.
        if ($user->id === $model->id) {
            return false;
        }

        return $user->can('delete_user');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_user');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user): bool
    {
        return $user->can('force_delete_user');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_user');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user): bool
    {
        return $user->can('restore_user');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_user');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function replicate(User $user): bool
    {
        return $user->can('replicate_user');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_user');
    }
}
