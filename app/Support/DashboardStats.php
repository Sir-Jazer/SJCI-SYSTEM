<?php

namespace App\Support;

use App\Enums\CollectionStatus;
use App\Enums\CollectionType;
use App\Enums\RemittanceStatus;
use App\Models\Collection;
use App\Models\Expense;
use App\Models\Remittance;

/**
 * Read-only aggregates for the dashboard. Pass a $churchId to scope to one
 * outreach (for an outreach pastor), or null for the whole church (Head Pastor).
 */
class DashboardStats
{
    /** The Outreach Infrastructure Fund on hand: 90% of offerings, less approved spending. */
    public static function infrastructureFund(?int $churchId = null): float
    {
        return round(self::offeringFundShare($churchId) - self::approvedExpenses($churchId), 2);
    }

    /** The 90% share of approved offerings that flows into the fund. */
    public static function offeringFundShare(?int $churchId = null): float
    {
        $query = Collection::query()->where('status', CollectionStatus::Locked);

        if ($churchId !== null) {
            $query->where('church_id', $churchId);
        }

        return round((float) $query->sum('outreach_share'), 2);
    }

    /** Approved (locked) spending against the fund. */
    public static function approvedExpenses(?int $churchId = null): float
    {
        $query = Expense::query()->where('status', CollectionStatus::Locked);

        if ($churchId !== null) {
            $query->where('church_id', $churchId);
        }

        return round((float) $query->sum('amount'), 2);
    }

    /** Spends still awaiting approval (optionally excluding one record being edited). */
    public static function pendingExpensesTotal(?int $churchId = null, ?int $excludeId = null): float
    {
        $query = Expense::query()->where('status', CollectionStatus::Pending);

        if ($churchId !== null) {
            $query->where('church_id', $churchId);
        }

        if ($excludeId !== null) {
            $query->whereKeyNot($excludeId);
        }

        return round((float) $query->sum('amount'), 2);
    }

    public static function pendingExpensesCount(?int $churchId = null): int
    {
        $query = Expense::query()->where('status', CollectionStatus::Pending);

        if ($churchId !== null) {
            $query->where('church_id', $churchId);
        }

        return $query->count();
    }

    public static function quarterExpenses(int $year, int $quarter, ?int $churchId = null): float
    {
        [$start, $end] = Remittance::quarterBounds($year, $quarter);

        $query = Expense::query()
            ->where('status', CollectionStatus::Locked)
            ->whereBetween('spent_on', [$start, $end]);

        if ($churchId !== null) {
            $query->where('church_id', $churchId);
        }

        return round((float) $query->sum('amount'), 2);
    }

    /** What may still be spent: the net fund minus other pending spends. */
    public static function availableToSpend(?int $churchId = null, ?int $excludeId = null): float
    {
        return round(self::infrastructureFund($churchId) - self::pendingExpensesTotal($churchId, $excludeId), 2);
    }

    public static function pendingApprovals(?int $churchId = null): int
    {
        return self::countByStatus(CollectionStatus::Pending, $churchId);
    }

    public static function returnedReports(?int $churchId = null): int
    {
        return self::countByStatus(CollectionStatus::Returned, $churchId);
    }

    /** Approved total of one collection type for a quarter. */
    public static function quarterTotal(CollectionType $type, int $year, int $quarter, ?int $churchId = null): float
    {
        [$start, $end] = Remittance::quarterBounds($year, $quarter);

        $query = Collection::query()
            ->where('status', CollectionStatus::Locked)
            ->where('type', $type)
            ->whereBetween('week_of', [$start, $end]);

        if ($churchId !== null) {
            $query->where('church_id', $churchId);
        }

        return round((float) $query->sum('amount'), 2);
    }

    /** Remittances computed but not yet remitted (Due + Approved). */
    public static function remittancesOutstanding(?int $churchId = null): array
    {
        $query = Remittance::query()->whereIn('status', [
            RemittanceStatus::Due,
            RemittanceStatus::Approved,
        ]);

        if ($churchId !== null) {
            $query->where('church_id', $churchId);
        }

        return [
            'count' => (clone $query)->count(),
            'amount' => round((float) $query->sum('amount_due'), 2),
        ];
    }

    /** Total Tithes of Tithes actually remitted (received by the main church). */
    public static function remittedTotal(?int $churchId = null): float
    {
        $query = Remittance::query()->where('status', RemittanceStatus::Remitted);

        if ($churchId !== null) {
            $query->where('church_id', $churchId);
        }

        return round((float) $query->sum('amount_due'), 2);
    }

    private static function countByStatus(CollectionStatus $status, ?int $churchId): int
    {
        $query = Collection::query()->where('status', $status);

        if ($churchId !== null) {
            $query->where('church_id', $churchId);
        }

        return $query->count();
    }
}