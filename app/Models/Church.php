<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Church extends Model
{
    protected $fillable = ['name', 'is_main', 'pastor_id'];

    protected function casts(): array
    {
        return [
            'is_main' => 'boolean',
        ];
    }

    /** The pastor assigned to lead this church. */
    public function pastor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pastor_id');
    }

    /** All users belonging to this church. */
    public function members(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function collections(): HasMany
    {
        return $this->hasMany(Collection::class);
    }

    public function remittances(): HasMany
    {
        return $this->hasMany(Remittance::class);
    }
}
