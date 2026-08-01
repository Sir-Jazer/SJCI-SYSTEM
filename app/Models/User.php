<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'must_change_password', 'role', 'church_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'role' => UserRole::class,
        ];
    }

    /** A pastor with a Head-Pastor-provisioned (temporary) password. */
    public function mustChangePassword(): bool
    {
        return (bool) $this->must_change_password;
    }

    /** Only pastor roles may access the admin panel in v1. */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->role?->canLogin() ?? false;
    }

    public function isHeadPastor(): bool
    {
        return $this->role === UserRole::HeadPastor;
    }

    public function isOutreachPastor(): bool
    {
        return $this->role === UserRole::OutreachPastor;
    }

    /** The church this user belongs to (Head Pastor -> main church). */
    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }
}
