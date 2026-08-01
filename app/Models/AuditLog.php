<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Auth;

class AuditLog extends Model
{
    protected $fillable = [
        'user_id', 'action', 'auditable_type', 'auditable_id', 'details',
    ];

    protected function casts(): array
    {
        return [
            'details' => 'array',
        ];
    }

    /** Convenience recorder used across the app to keep the audit trail complete. */
    public static function record(string $action, ?Model $auditable = null, array $details = []): self
    {
        return static::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'details' => $details ?: null,
        ]);
    }

    public function actionLabel(): string
    {
        return match ($this->action) {
            'login' => 'Signed in',
            'logout' => 'Signed out',
            'set_password' => 'Set own password',
            'submit' => 'Submitted report',
            'update' => 'Edited report',
            'approve' => 'Approved & locked',
            'return' => 'Returned report',
            'resubmit' => 'Resubmitted report',
            'adjust' => 'Posted correction',
            'approve_remittance' => 'Approved remittance',
            'remit' => 'Marked remitted',
            'submit_expense' => 'Submitted spend',
            'update_expense' => 'Edited spend',
            'approve_expense' => 'Approved spend',
            'return_expense' => 'Returned spend',
            'resubmit_expense' => 'Resubmitted spend',
            default => ucfirst(str_replace('_', ' ', $this->action)),
        };
    }

    public function actionColor(): string
    {
        return match ($this->action) {
            'approve', 'approve_remittance', 'remit', 'approve_expense' => 'success',
            'return', 'return_expense' => 'danger',
            'submit', 'resubmit', 'submit_expense', 'resubmit_expense' => 'info',
            'adjust', 'update', 'update_expense' => 'warning',
            default => 'gray',
        };
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
