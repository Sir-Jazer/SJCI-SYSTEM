<?php

namespace App\Filament\Widgets;

use App\Enums\CollectionType;
use App\Models\Church;
use App\Models\Remittance;
use App\Support\DashboardStats;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Per-church figures for the Head Pastor: the main church and each outreach on
 * its own row, so the consolidated totals can be read alongside the parts.
 * Head-Pastor-only, so no outreach sees another outreach's line.
 */
class ChurchBreakdown extends TableWidget
{
    protected static ?int $sort = -2;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return Auth::user()?->isHeadPastor() ?? false;
    }

    public function table(Table $table): Table
    {
        $year = (int) now()->year;
        $quarter = Remittance::currentQuarter();

        return $table
            ->heading('By church')
            ->description("This quarter's figures for the main church and each outreach.")
            ->query(fn (): Builder => Church::query()->orderByDesc('is_main')->orderBy('name'))
            ->paginated(false)
            ->columns([
                TextColumn::make('name')
                    ->label('Church')
                    ->weight('medium')
                    ->description(fn (Church $record): ?string => $record->is_main ? 'Main church' : 'Outreach'),

                TextColumn::make('fund')
                    ->label('Funds on hand')
                    ->state(fn (Church $record): float => DashboardStats::infrastructureFund($record->id))
                    ->money('PHP')
                    ->alignEnd(),

                TextColumn::make('offerings')
                    ->label("Offerings · Q{$quarter}")
                    ->state(fn (Church $record): float => DashboardStats::quarterTotal(CollectionType::Offering, $year, $quarter, $record->id))
                    ->money('PHP')
                    ->alignEnd(),

                TextColumn::make('tithes')
                    ->label("Tithes · Q{$quarter}")
                    ->state(fn (Church $record): float => DashboardStats::quarterTotal(CollectionType::Tithe, $year, $quarter, $record->id))
                    ->money('PHP')
                    ->alignEnd(),

                TextColumn::make('spent')
                    ->label("Spent · Q{$quarter}")
                    ->state(fn (Church $record): float => DashboardStats::quarterExpenses($year, $quarter, $record->id))
                    ->money('PHP')
                    ->alignEnd(),
            ]);
    }
}