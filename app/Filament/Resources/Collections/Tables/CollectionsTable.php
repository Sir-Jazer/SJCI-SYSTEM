<?php

namespace App\Filament\Resources\Collections\Tables;

use App\Enums\CollectionStatus;
use App\Enums\CollectionType;
use App\Models\AuditLog;
use App\Models\Collection;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class CollectionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('week_of', 'desc')
            ->columns([
                TextColumn::make('church.name')
                    ->label('Church')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('week_of')
                    ->label('Week of')
                    ->date('M j, Y')
                    ->sortable(),

                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (CollectionType $state): string => $state->label())
                    ->color(fn (CollectionType $state): string => $state === CollectionType::Offering ? 'info' : 'gray')
                    ->description(fn (Collection $record): ?string => $record->adjusts_id ? "Correction of #{$record->adjusts_id}" : null),

                TextColumn::make('amount')
                    ->money('PHP')
                    ->sortable()
                    ->alignEnd(),

                TextColumn::make('main_share')
                    ->label('10% Main')
                    ->money('PHP')
                    ->alignEnd()
                    ->toggleable(),

                TextColumn::make('outreach_share')
                    ->label('90% Fund')
                    ->money('PHP')
                    ->alignEnd()
                    ->toggleable(),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (CollectionStatus $state): string => $state->label())
                    ->color(fn (CollectionStatus $state): string => match ($state) {
                        CollectionStatus::Pending => 'warning',
                        CollectionStatus::Returned => 'danger',
                        CollectionStatus::Locked => 'success',
                    }),

                TextColumn::make('proof')
                    ->label('Proof')
                    ->state(fn (Collection $record): string => ($n = count($record->attachments ?? [])) > 0
                        ? $n.' file'.($n > 1 ? 's' : '')
                        : '—')
                    ->badge()
                    ->icon(fn (Collection $record): ?string => count($record->attachments ?? []) > 0 ? 'heroicon-m-paper-clip' : null)
                    ->color(fn (Collection $record): string => count($record->attachments ?? []) > 0 ? 'success' : 'gray'),

                TextColumn::make('submitter.name')
                    ->label('Recorded by')
                    ->toggleable()
                    ->toggledHiddenByDefault(true),

                TextColumn::make('approved_at')
                    ->label('Locked at')
                    ->dateTime('M j, Y g:i A')
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

                SelectFilter::make('type')
                    ->options([
                        CollectionType::Offering->value => 'Offering',
                        CollectionType::Tithe->value => 'Tithe',
                    ]),

                SelectFilter::make('church')
                    ->relationship('church', 'name')
                    ->visible(fn (): bool => Auth::user()?->isHeadPastor() ?? false),
            ])
            ->recordActions([
                ViewAction::make(),

                EditAction::make(),

                // Head Pastor approves a pending report → it locks permanently.
                Action::make('approve')
                    ->label('Approve & Lock')
                    ->icon('heroicon-o-lock-closed')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Approve and lock this report?')
                    ->modalDescription('Once locked, the record can never be edited. Corrections are made through a separate adjustment entry.')
                    ->visible(fn (Collection $record): bool => (Auth::user()?->isHeadPastor() ?? false)
                        && $record->status === CollectionStatus::Pending)
                    ->action(function (Collection $record): void {
                        $record->update([
                            'status' => CollectionStatus::Locked,
                            'approved_by' => Auth::id(),
                            'approved_at' => now(),
                            'returned_reason' => null,
                        ]);

                        AuditLog::record('approve', $record, [
                            'type' => $record->type->value,
                            'amount' => (string) $record->amount,
                        ]);

                        Notification::make()->title('Report approved and locked')->success()->send();
                    }),

                // Head Pastor returns a report to the outreach pastor with a reason.
                Action::make('return')
                    ->label('Return')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->visible(fn (Collection $record): bool => (Auth::user()?->isHeadPastor() ?? false)
                        && $record->status === CollectionStatus::Pending)
                    ->schema([
                        Textarea::make('returned_reason')
                            ->label('Reason for returning')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (Collection $record, array $data): void {
                        $record->update([
                            'status' => CollectionStatus::Returned,
                            'returned_reason' => $data['returned_reason'],
                        ]);

                        AuditLog::record('return', $record, ['reason' => $data['returned_reason']]);

                        Notification::make()->title('Report returned to the outreach pastor')->warning()->send();
                    }),

                // The submitter fixes a returned report and sends it back for approval.
                Action::make('resubmit')
                    ->label('Resubmit')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->visible(fn (Collection $record): bool => Auth::id() === $record->submitted_by
                        && $record->status === CollectionStatus::Returned)
                    ->action(function (Collection $record): void {
                        $record->update([
                            'status' => CollectionStatus::Pending,
                            'returned_reason' => null,
                        ]);

                        AuditLog::record('resubmit', $record);

                        Notification::make()->title('Report resubmitted for approval')->success()->send();
                    }),

                // Fix a locked record without editing it: post a signed adjustment
                // that references the original and goes through approval like any report.
                Action::make('correct')
                    ->label('Post correction')
                    ->icon('heroicon-o-pencil-square')
                    ->color('warning')
                    ->modalHeading('Post a correction')
                    ->modalDescription(fn (Collection $record): string => 'The locked record #'.$record->id.' stays untouched. This posts a separate adjustment of the difference, which the Head Pastor must approve.')
                    ->visible(fn (Collection $record): bool => $record->status === CollectionStatus::Locked
                        && $record->adjusts_id === null
                        && Auth::user()?->church_id === $record->church_id)
                    ->schema([
                        TextInput::make('corrected_amount')
                            ->label('What the amount should have been')
                            ->numeric()
                            ->minValue(0)
                            ->step('0.01')
                            ->prefix('₱')
                            ->required()
                            ->default(fn (Collection $record): string => (string) $record->amount),
                        Textarea::make('reason')
                            ->label('Reason for the correction')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (Collection $record, array $data): void {
                        $corrected = round((float) $data['corrected_amount'], 2);
                        $delta = round($corrected - (float) $record->amount, 2);

                        if (abs($delta) < 0.005) {
                            Notification::make()
                                ->title('No change to record')
                                ->body('The corrected amount is the same as the current amount.')
                                ->warning()
                                ->send();

                            return;
                        }

                        $adjustment = Collection::create([
                            'church_id' => $record->church_id,
                            'type' => $record->type,
                            'week_of' => $record->week_of,
                            'amount' => $delta,
                            'submitted_by' => Auth::id(),
                            'adjusts_id' => $record->id,
                            'note' => sprintf(
                                'Correction of #%d: %s (was ₱%s, should be ₱%s)',
                                $record->id,
                                $data['reason'],
                                number_format((float) $record->amount, 2),
                                number_format($corrected, 2),
                            ),
                        ]);

                        AuditLog::record('adjust', $adjustment, [
                            'adjusts_id' => $record->id,
                            'delta' => (string) $delta,
                            'reason' => $data['reason'],
                        ]);

                        Notification::make()->title('Correction posted for approval')->success()->send();
                    }),

                DeleteAction::make(),
            ]);
    }
}
