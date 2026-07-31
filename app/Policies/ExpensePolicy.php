<?php

namespace App\Policies;

use App\Models\Expense;
use App\Models\User;

class ExpensePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isHeadPastor() || $user->isOutreachPastor();
    }

    public function view(User $user, Expense $expense): bool
    {
        if ($user->isHeadPastor()) {
            return true;
        }

        return $expense->church_id === $user->church_id;
    }

    public function create(User $user): bool
    {
        return $user->isHeadPastor() || $user->isOutreachPastor();
    }

    /** Only the submitter may edit, and only before it locks. */
    public function update(User $user, Expense $expense): bool
    {
        return $expense->submitted_by === $user->id && ! $expense->isLocked();
    }

    public function delete(User $user, Expense $expense): bool
    {
        return $expense->submitted_by === $user->id && ! $expense->isLocked();
    }
}