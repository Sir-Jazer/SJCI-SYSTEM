<?php

namespace App\Models;

use App\Enums\CollectionStatus;
use App\Enums\CollectionType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Collection extends Model
{
    /** Fixed by the church's official procedure: offerings split 10% / 90%. */
    public const MAIN_CHURCH_RATE = 0.10;

    protected $fillable = [
        'church_id', 'type', 'week_of', 'amount', 'main_share', 'outreach_share',
        'status', 'note', 'attachments', 'returned_reason', 'submitted_by', 'approved_by',
        'approved_at', 'adjusts_id',
    ];

    /** New records start pending, awaiting Head Pastor approval. */
    protected $attributes = [
        'status' => CollectionStatus::Pending->value,
    ];

    protected function casts(): array
    {
        return [
            'type' => CollectionType::class,
            'status' => CollectionStatus::class,
            'week_of' => 'date',
            'amount' => 'decimal:2',
            'main_share' => 'decimal:2',
            'outreach_share' => 'decimal:2',
            'approved_at' => 'datetime',
            'attachments' => 'array',
        ];
    }

    protected static function booted(): void
    {
        // Work out the 10% / 90% split the moment an offering is recorded.
        static::saving(function (Collection $collection) {
            if ($collection->type !== CollectionType::Offering) {
                // Tithes are recorded for transparency only — never split.
                $collection->main_share = 0;
                $collection->outreach_share = 0;

                return;
            }

            if ($collection->isForMainChurch()) {
                // The main church receives the 10%; it keeps 100% of its own offerings.
                $collection->main_share = 0;
                $collection->outreach_share = round((float) $collection->amount, 2);

                return;
            }

            // Outreach offering: carve out 10% for the Main Church, keep 90% in the fund.
            $main = round((float) $collection->amount * self::MAIN_CHURCH_RATE, 2);
            $collection->main_share = $main;
            $collection->outreach_share = round((float) $collection->amount - $main, 2);
        });
    }

    /** Cheap check without hydrating the whole Church model. */
    public function isForMainChurch(): bool
    {
        return (bool) Church::whereKey($this->church_id)->value('is_main');
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** The locked record this entry corrects, if it is an adjustment. */
    public function adjusts(): BelongsTo
    {
        return $this->belongsTo(self::class, 'adjusts_id');
    }

    /** Correcting entries posted against this record. */
    public function adjustments(): HasMany
    {
        return $this->hasMany(self::class, 'adjusts_id');
    }

    public function isLocked(): bool
    {
        return $this->status === CollectionStatus::Locked;
    }

    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function locked(Builder $query): void
    {
        $query->where('status', CollectionStatus::Locked);
    }

    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function offerings(Builder $query): void
    {
        $query->where('type', CollectionType::Offering);
    }

    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function tithes(Builder $query): void
    {
        $query->where('type', CollectionType::Tithe);
    }
}
