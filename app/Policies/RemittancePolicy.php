<?php

namespace App\Policies;

use App\Models\Remittance;
use App\Models\User;

class RemittancePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isHeadPastor() || $user->isOutreachPastor();
    }

    /** Head Pastor sees all; an outreach pastor only their own church's. */
    public function view(User $user, Remittance $remittance): bool
    {
        if ($user->isHeadPastor()) {
            return true;
        }

        return $remittance->church_id === $user->church_id;
    }

    /** Remittances are system-computed and progressed only via audited actions. */
    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Remittance $remittance): bool
    {
        return false;
    }

    public function delete(User $user, Remittance $remittance): bool
    {
        return false;
    }
}