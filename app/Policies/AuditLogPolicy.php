<?php

namespace App\Policies;

use App\Models\AuditLog;
use App\Models\User;

class AuditLogPolicy
{
    /** Only the Head Pastor reviews the audit trail. */
    public function viewAny(User $user): bool
    {
        return $user->isHeadPastor();
    }

    public function view(User $user, AuditLog $auditLog): bool
    {
        return $user->isHeadPastor();
    }

    /** The trail is append-only and system-written — never edited or deleted. */
    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, AuditLog $auditLog): bool
    {
        return false;
    }

    public function delete(User $user, AuditLog $auditLog): bool
    {
        return false;
    }
}