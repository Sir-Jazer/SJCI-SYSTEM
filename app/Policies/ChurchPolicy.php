<?php

namespace App\Policies;

use App\Models\Church;
use App\Models\User;

class ChurchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isHeadPastor();
    }

    public function view(User $user, Church $church): bool
    {
        return $user->isHeadPastor();
    }

    public function create(User $user): bool
    {
        return $user->isHeadPastor();
    }

    public function update(User $user, Church $church): bool
    {
        return $user->isHeadPastor();
    }

    /** Never delete the main church or one that already holds financial records. */
    public function delete(User $user, Church $church): bool
    {
        return $user->isHeadPastor()
            && ! $church->is_main
            && $church->collections()->doesntExist();
    }
}