<?php

namespace App\Filament\Resources\Remittances\Pages;

use App\Filament\Resources\Remittances\RemittanceResource;
use App\Models\Remittance;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;

class ListRemittances extends ListRecords
{
    protected static string $resource = RemittanceResource::class;

    protected function getHeaderActions(): array
    {
        $currentYear = (int) now()->year;

        return [
            // Recompute every outreach's Tithes of Tithes for a chosen quarter
            // from its approved offerings. Already-approved rows stay frozen.
            Action::make('compute')
                ->label('Compute quarter')
                ->icon('heroicon-o-calculator')
                ->visible(fn (): bool => Auth::user()?->isHeadPastor() ?? false)
                ->schema([
                    Select::make('year')
                        ->options(array_combine(
                            range($currentYear, $currentYear - 3),
                            range($currentYear, $currentYear - 3),
                        ))
                        ->default($currentYear)
                        ->required(),
                    Select::make('quarter')
                        ->options([
                            1 => 'Q1 (Jan–Mar)',
                            2 => 'Q2 (Apr–Jun)',
                            3 => 'Q3 (Jul–Sep)',
                            4 => 'Q4 (Oct–Dec)',
                        ])
                        ->default(Remittance::currentQuarter())
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $year = (int) $data['year'];
                    $quarter = (int) $data['quarter'];

                    $count = Remittance::computeForQuarter($year, $quarter);

                    Notification::make()
                        ->title("Computed Q{$quarter} {$year}")
                        ->body("{$count} outreach remittance(s) updated from approved offerings.")
                        ->success()
                        ->send();
                }),
        ];
    }
}