<?php

namespace App\Filament\Resources\AuditLogs\Tables;

use App\Models\AuditLog;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AuditLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime('M j, Y g:i A')
                    ->sortable(),

                TextColumn::make('user.name')
                    ->label('Who')
                    ->placeholder('System')
                    ->searchable(),

                TextColumn::make('action')
                    ->label('Action')
                    ->badge()
                    ->formatStateUsing(fn (AuditLog $record): string => $record->actionLabel())
                    ->color(fn (AuditLog $record): string => $record->actionColor()),

                TextColumn::make('record')
                    ->label('Record')
                    ->state(fn (AuditLog $record): ?string => $record->auditable_type
                        ? class_basename($record->auditable_type)." #{$record->auditable_id}"
                        : null)
                    ->placeholder('—'),

                TextColumn::make('details')
                    ->label('Details')
                    ->state(fn (AuditLog $record): ?string => filled($record->details)
                        ? collect($record->details)->map(fn ($v, $k): string => "{$k}: {$v}")->implode(' · ')
                        : null)
                    ->placeholder('—')
                    ->wrap()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('action')
                    ->options([
                        'login' => 'Signed in',
                        'logout' => 'Signed out',
                        'submit' => 'Submitted report',
                        'update' => 'Edited report',
                        'approve' => 'Approved & locked',
                        'return' => 'Returned report',
                        'resubmit' => 'Resubmitted report',
                        'adjust' => 'Posted correction',
                        'approve_remittance' => 'Approved remittance',
                        'remit' => 'Marked remitted',
                    ]),

                SelectFilter::make('user')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),
            ]);
    }
}