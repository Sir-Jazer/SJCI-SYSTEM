<?php

namespace App\Filament\Resources\Expenses\Tables;

use App\Enums\CollectionStatus;
use App\Enums\ExpenseCategory;
use App\Models\AuditLog;
use App\Models\Expense;
use App\Support\DashboardStats;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class ExpensesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('spent_on', 'desc')
            ->columns([
                TextColumn::make('church.name')
                    ->label('Church')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('spent_on')
                    ->label('Date')
                    ->date('M j, Y')
                    ->sortable(),

                TextColumn::make('category')
                    ->badge()
                    ->formatStateUsing(fn (ExpenseCategory $state): string => $state->label())
                    ->color('gray'),

                TextColumn::make('purpose')
                    ->limit(40)
                    ->tooltip(fn (Expense $record): string => $record->purpose)
                    ->wrap(),

                TextColumn::make('amount')
                    ->money('PHP')
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (CollectionStatus $state): string => $state->label())
                    ->color(fn (CollectionStatus $state): string => match ($state) {
                        CollectionStatus::Pending => 'warning',
                        CollectionStatus::Returned => 'danger',
                        CollectionStatus::Locked => 'success',
                    }),

                TextColumn::make('receipt')
                    ->label('Receipt')
                    ->state(fn (Expense $record): string => ($n = count($record->attachments ?? [])) > 0
                        ? $n.' file'.($n > 1 ? 's' : '')
                        : '—')
                    ->badge()
                    ->icon(fn (Expense $record): ?string => count($record->attachments ?? []) > 0 ? 'heroicon-m-paper-clip' : null)
                    ->color(fn (Expense $record): string => count($record->attachments ?? []) > 0 ? 'success' : 'gray'),

                TextColumn::make('submitter.name')
                    ->label('Recorded by')
                    ->toggleable()
                    ->toggledHiddenByDefault(true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        CollectionStatus::Pending->value => 'Pending approval',
                        CollectionStatus::Returned->value => 'Returned',
                        CollectionStatus::Locked->value => 'Locked',
                    ]),

                SelectFilter::make('category')
                    ->options(ExpenseCategory::options()),

                SelectFilter::make('church')
                    ->relationship('church', 'name')
                    ->visible(fn (): bool => Auth::user()?->isHeadPastor() ?? false),
            ])
            ->recordActions([
                ViewAction::make(),

                EditAction::make(),

                // Head Pastor approves a spend → it locks and reduces the fund.
                Action::make('approve')
                    ->label('Approve & Lock')
                    ->icon('heroicon-o-lock-closed')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('Approving locks this spend and deducts it from the Infrastructure Fund.')
                    ->visible(fn (Expense $record): bool => (Auth::user()?->isHeadPastor() ?? false)
                        && $record->status === CollectionStatus::Pending)
                    ->action(function (Expense $record): void {
                        // Re-check against the live fund: other spends may have been approved since.
                        $available = DashboardStats::infrastructureFund($record->church_id);

                        if ((float) $record->amount > $available) {
                            Notification::make()
                                ->title('Cannot approve — exceeds the fund')
                                ->body('Only ₱'.number_format($available, 2).' remains in the Infrastructure Fund.')
                                ->danger()
                                ->send();

                            return;
                        }

                        $record->update([
                            'status' => CollectionStatus::Locked,
                            'approved_by' => Auth::id(),
                            'approved_at' => now(),
                            'returned_reason' => null,
                        ]);

                        AuditLog::record('approve_expense', $record, [
                            'category' => $record->category->value,
                            'amount' => (string) $record->amount,
                        ]);

                        Notification::make()->title('Spend approved and locked')->success()->send();
                    }),

                Action::make('return')
                    ->label('Return')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->visible(fn (Expense $record): bool => (Auth::user()?->isHeadPastor() ?? false)
                        && $record->status === CollectionStatus::Pending)
                    ->schema([
                        Textarea::make('returned_reason')
                            ->label('Reason for returning')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (Expense $record, array $data): void {
                        $record->update([
                            'status' => CollectionStatus::Returned,
                            'returned_reason' => $data['returned_reason'],
                        ]);

                        AuditLog::record('return_expense', $record, ['reason' => $data['returned_reason']]);

                        Notification::make()->title('Spend returned to the outreach pastor')->warning()->send();
                    }),

                Action::make('resubmit')
                    ->label('Resubmit')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->visible(fn (Expense $record): bool => Auth::id() === $record->submitted_by
                        && $record->status === CollectionStatus::Returned)
                    ->action(function (Expense $record): void {
                        $record->update([
                            'status' => CollectionStatus::Pending,
                            'returned_reason' => null,
                        ]);

                        AuditLog::record('resubmit_expense', $record);

                        Notification::make()->title('Spend resubmitted for approval')->success()->send();
                    }),

                DeleteAction::make(),
            ]);
    }
}