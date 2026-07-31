<?php

namespace App\Filament\Widgets;

use App\Enums\CollectionType;
use App\Models\Remittance;
use App\Support\DashboardStats;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

/**
 * Whole-church transparency for outreach pastors: consolidated network totals
 * only — never a per-outreach breakdown, so one outreach can't see another's
 * individual figures. The Head Pastor already gets the network view elsewhere.
 */
class NetworkOverview extends StatsOverviewWidget
{
    protected static ?int $sort = -2;

    protected ?string $heading = 'Across all churches';

    protected ?string $description = 'Combined totals for the whole church.';

    public static function canView(): bool
    {
        return Auth::user()?->isOutreachPastor() ?? false;
    }

    protected function getStats(): array
    {
        $year = (int) now()->year;
        $quarter = Remittance::currentQuarter();

        $peso = fn (float $n): string => '₱'.number_format($n, 2);

        // null churchId = aggregate across every church.
        $funds = DashboardStats::infrastructureFund(null);
        $offerings = DashboardStats::quarterTotal(CollectionType::Offering, $year, $quarter, null);
        $tithes = DashboardStats::quarterTotal(CollectionType::Tithe, $year, $quarter, null);
        $spent = DashboardStats::quarterExpenses($year, $quarter, null);
        $remitted = DashboardStats::remittedTotal(null);

        return [
            Stat::make('Total funds on hand', $peso($funds))
                ->description('All churches, net of spending')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make("Offerings · Q{$quarter} {$year}", $peso($offerings))
                ->description('All churches, this quarter')
                ->descriptionIcon('heroicon-m-gift')
                ->color('info'),

            Stat::make("Tithes · Q{$quarter} {$year}", $peso($tithes))
                ->description('All churches, this quarter')
                ->descriptionIcon('heroicon-m-hand-raised')
                ->color('gray'),

            Stat::make("Spent · Q{$quarter} {$year}", $peso($spent))
                ->description('All churches, this quarter')
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color($spent > 0 ? 'warning' : 'gray'),

            Stat::make('Tithes of Tithes received', $peso($remitted))
                ->description('Total received by the main church')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),
        ];
    }
}