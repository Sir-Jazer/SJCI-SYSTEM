<?php

namespace App\Models;

use App\Enums\CollectionStatus;
use App\Enums\ExpenseCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A spend drawn from an outreach's Infrastructure Fund (the 90% of offerings).
 * Reduces the fund only once approved (locked). Never affects the Main Church's
 * 10%, which is carved out at collection.
 */
class Expense extends Model
{
    protected $fillable = [
        'church_id', 'category', 'spent_on', 'amount', 'purpose', 'attachments',
        'status', 'returned_reason', 'submitted_by', 'approved_by', 'approved_at',
    ];

    protected $attributes = [
        'status' => CollectionStatus::Pending->value,
    ];

    protected function casts(): array
    {
        return [
            'category' => ExpenseCategory::class,
            'status' => CollectionStatus::class,
            'spent_on' => 'date',
            'amount' => 'decimal:2',
            'approved_at' => 'datetime',
            'attachments' => 'array',
        ];
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
    protected function pending(Builder $query): void
    {
        $query->where('status', CollectionStatus::Pending);
    }
}