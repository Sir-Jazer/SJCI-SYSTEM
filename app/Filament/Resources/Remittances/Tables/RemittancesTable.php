<?php

namespace App\Filament\Resources\Remittances\Tables;

use App\Enums\RemittanceStatus;
use App\Models\AuditLog;
use App\Models\Remittance;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class RemittancesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('year', 'desc')
            ->columns([
                TextColumn::make('church.name')
                    ->label('Outreach')
                    ->sortable(),

                TextColumn::make('period')
                    ->label('Quarter')
                    ->state(fn (Remittance $record): string => $record->periodLabel())
                    ->sortable(['year', 'quarter']),

                TextColumn::make('offerings_total')
                    ->label('Approved offerings')
                    ->money('PHP')
                    ->alignEnd(),

                TextColumn::make('amount_due')
                    ->label('10% Due')
                    ->money('PHP')
                    ->weight('bold')
                    ->alignEnd(),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (RemittanceStatus $state): string => $state->label())
                    ->color(fn (RemittanceStatus $state): string => match ($state) {
                        RemittanceStatus::Due => 'warning',
                        RemittanceStatus::Approved => 'info',
                        RemittanceStatus::Remitted => 'success',
                    }),

                TextColumn::make('remitted_at')
                    ->label('Remitted on')
                    ->date('M j, Y')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('reviewer.name')
                    ->label('Approved by')
                    ->toggleable()
                    ->toggledHiddenByDefault(true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        RemittanceStatus::Due->value => 'Due',
                        RemittanceStatus::Approved->value => 'Approved',
                        RemittanceStatus::Remitted->value => 'Remitted',
                    ]),

                SelectFilter::make('church')
                    ->relationship('church', 'name')
                    ->visible(fn (): bool => Auth::user()?->isHeadPastor() ?? false),
            ])
            ->recordActions([
                // Head Pastor reviews and approves the computed figure; it then freezes.
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-badge')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalDescription('Approving locks this quarter\'s figure. Later offering changes will no longer affect it.')
                    ->visible(fn (Remittance $record): bool => (Auth::user()?->isHeadPastor() ?? false)
                        && $record->status === RemittanceStatus::Due)
                    ->action(function (Remittance $record): void {
                        $record->update([
                            'status' => RemittanceStatus::Approved,
                            'reviewed_by' => Auth::id(),
                        ]);

                        AuditLog::record('approve_remittance', $record, [
                            'period' => $record->periodLabel(),
                            'amount_due' => (string) $record->amount_due,
                        ]);

                        Notification::make()->title('Remittance approved')->success()->send();
                    }),

                // Recorded when the outreach physically hands the cash to the Main Church.
                Action::make('remit')
                    ->label('Mark remitted')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(fn (Remittance $record): bool => (Auth::user()?->isHeadPastor() ?? false)
                        && $record->status === RemittanceStatus::Approved)
                    ->schema([
                        DatePicker::make('remitted_on')
                            ->label('Date received')
                            ->default(now())
                            ->maxDate(now())
                            ->required(),
                    ])
                    ->action(function (Remittance $record, array $data): void {
                        $record->update([
                            'status' => RemittanceStatus::Remitted,
                            'remitted_at' => $data['remitted_on'],
                            'remitted_by' => Auth::id(),
                        ]);

                        AuditLog::record('remit', $record, [
                            'period' => $record->periodLabel(),
                            'amount_due' => (string) $record->amount_due,
                        ]);

                        Notification::make()->title('Remittance marked as received')->success()->send();
                    }),
            ]);
    }
}