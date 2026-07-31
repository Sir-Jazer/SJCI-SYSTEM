<?php

namespace App\Models;

use App\Enums\CollectionStatus;
use App\Enums\CollectionType;
use App\Enums\RemittanceStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Remittance extends Model
{
    /** The Main Church's share of an outreach's offerings. */
    public const RATE = 0.10;

    /** Inclusive start/end dates for a calendar quarter. */
    public static function quarterBounds(int $year, int $quarter): array
    {
        $startMonth = (($quarter - 1) * 3) + 1;
        $start = CarbonImmutable::create($year, $startMonth, 1)->startOfDay();

        return [$start, $start->addMonths(3)->subDay()->endOfDay()];
    }

    public static function currentQuarter(?CarbonImmutable $date = null): int
    {
        $date ??= CarbonImmutable::now();

        return intdiv($date->month - 1, 3) + 1;
    }

    /**
     * Recompute every outreach church's Tithes of Tithes for a quarter from its
     * approved offerings (adjustments included). Rows already approved or remitted
     * are left frozen. Returns the number of rows created or updated.
     */
    public static function computeForQuarter(int $year, int $quarter): int
    {
        [$start, $end] = self::quarterBounds($year, $quarter);
        $touched = 0;

        foreach (Church::where('is_main', false)->get() as $church) {
            $row = self::firstOrNew([
                'church_id' => $church->id,
                'year' => $year,
                'quarter' => $quarter,
            ]);

            // Once a remittance is approved or remitted, its figure is locked.
            if ($row->exists && $row->status !== RemittanceStatus::Due) {
                continue;
            }

            $total = Collection::query()
                ->where('church_id', $church->id)
                ->where('type', CollectionType::Offering)
                ->where('status', CollectionStatus::Locked)
                ->whereBetween('week_of', [$start, $end])
                ->sum('amount');

            $row->offerings_total = round((float) $total, 2);
            $row->amount_due = round((float) $total * self::RATE, 2);
            $row->status = RemittanceStatus::Due;
            $row->save();

            $touched++;
        }

        return $touched;
    }

    public function periodLabel(): string
    {
        return "Q{$this->quarter} {$this->year}";
    }

    protected $fillable = [
        'church_id', 'year', 'quarter', 'offerings_total', 'amount_due',
        'status', 'reviewed_by', 'remitted_by', 'remitted_at',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'quarter' => 'integer',
            'offerings_total' => 'decimal:2',
            'amount_due' => 'decimal:2',
            'status' => RemittanceStatus::class,
            'remitted_at' => 'datetime',
        ];
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function remitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'remitted_by');
    }
}
