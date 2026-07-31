<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isHeadPastor();
    }

    public function view(User $user, User $model): bool
    {
        return $user->isHeadPastor();
    }

    public function create(User $user): bool
    {
        return $user->isHeadPastor();
    }

    public function update(User $user, User $model): bool
    {
        return $user->isHeadPastor();
    }

    /** Only the Head Pastor manages accounts, and never deletes their own. */
    public function delete(User $user, User $model): bool
    {
        return $user->isHeadPastor() && $user->id !== $model->id;
    }
}