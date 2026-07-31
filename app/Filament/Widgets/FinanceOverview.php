<?php

namespace App\Filament\Widgets;

use App\Enums\CollectionType;
use App\Models\Remittance;
use App\Support\DashboardStats;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class FinanceOverview extends StatsOverviewWidget
{
    protected static ?int $sort = -3;

    protected function getStats(): array
    {
        $user = Auth::user();
        $isHead = $user->isHeadPastor();

        // Head Pastor sees the consolidated network; an outreach pastor sees only their church.
        $churchId = $isHead ? null : $user->church_id;

        $year = (int) now()->year;
        $quarter = Remittance::currentQuarter();

        $fund = DashboardStats::infrastructureFund($churchId);
        $offerings = DashboardStats::quarterTotal(CollectionType::Offering, $year, $quarter, $churchId);
        $tithes = DashboardStats::quarterTotal(CollectionType::Tithe, $year, $quarter, $churchId);
        $spent = DashboardStats::quarterExpenses($year, $quarter, $churchId);
        $pending = DashboardStats::pendingApprovals($churchId);
        $returned = DashboardStats::returnedReports($churchId);
        $pendingSpends = DashboardStats::pendingExpensesCount($churchId);
        $outstanding = DashboardStats::remittancesOutstanding($churchId);
        $remitted = DashboardStats::remittedTotal($churchId);

        $peso = fn (float $n): string => '₱'.number_format($n, 2);

        $pendingNote = $returned > 0 ? "{$returned} returned to fix" : 'Reports in the queue';
        if ($pendingSpends > 0) {
            $pendingNote .= " · {$pendingSpends} spend(s) to approve";
        }

        return [
            Stat::make($isHead ? 'Total funds on hand' : 'Infrastructure Fund', $peso($fund))
                ->description($isHead ? 'All churches, net of spending' : 'Your church · net on hand')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make("Offerings · Q{$quarter} {$year}", $peso($offerings))
                ->description('Approved this quarter')
                ->descriptionIcon('heroicon-m-gift')
                ->color('info'),

            Stat::make("Tithes · Q{$quarter} {$year}", $peso($tithes))
                ->description('Recorded this quarter')
                ->descriptionIcon('heroicon-m-hand-raised')
                ->color('gray'),

            Stat::make("Spent · Q{$quarter} {$year}", $peso($spent))
                ->description('Approved fund spending')
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color($spent > 0 ? 'warning' : 'gray'),

            Stat::make($isHead ? 'Pending your approval' : 'Awaiting approval', (string) $pending)
                ->description($pendingNote)
                ->descriptionIcon('heroicon-m-clock')
                ->color(($pending > 0 || $pendingSpends > 0) ? 'warning' : 'gray'),

            Stat::make('Tithes of Tithes outstanding', $peso($outstanding['amount']))
                ->description($outstanding['count'].' quarter(s) not yet remitted')
                ->descriptionIcon('heroicon-m-arrow-up-right')
                ->color($outstanding['amount'] > 0 ? 'danger' : 'gray'),

            Stat::make($isHead ? 'Tithes of Tithes received' : 'Tithes of Tithes remitted', $peso($remitted))
                ->description($isHead ? 'Total received by the main church' : 'Total you have remitted')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),
        ];
    }
}