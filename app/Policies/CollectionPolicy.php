<?php

namespace App\Policies;

use App\Models\Collection;
use App\Models\User;

class CollectionPolicy
{
    /** Both pastor roles can browse the collections list. */
    public function viewAny(User $user): bool
    {
        return $user->isHeadPastor() || $user->isOutreachPastor();
    }

    /** Head Pastor sees every church; an outreach pastor only their own. */
    public function view(User $user, Collection $collection): bool
    {
        if ($user->isHeadPastor()) {
            return true;
        }

        return $collection->church_id === $user->church_id;
    }

    public function create(User $user): bool
    {
        return $user->isHeadPastor() || $user->isOutreachPastor();
    }

    /**
     * Only the submitter may edit, and only while the record is not locked.
     * This also blocks the Head Pastor from editing an outreach's records.
     */
    public function update(User $user, Collection $collection): bool
    {
        return $collection->submitted_by === $user->id && ! $collection->isLocked();
    }

    /** Same rule as editing: submitter only, never once locked. */
    public function delete(User $user, Collection $collection): bool
    {
        return $collection->submitted_by === $user->id && ! $collection->isLocked();
    }
}