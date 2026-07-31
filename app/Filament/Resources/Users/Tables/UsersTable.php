<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\UserRole;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('role')
                    ->badge()
                    ->formatStateUsing(fn (UserRole $state): string => $state->label())
                    ->color(fn (UserRole $state): string => $state === UserRole::HeadPastor ? 'success' : 'info'),

                TextColumn::make('church.name')
                    ->label('Church')
                    ->placeholder('— none —')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->options([
                        UserRole::HeadPastor->value => 'Head Pastor',
                        UserRole::OutreachPastor->value => 'Outreach Pastor',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}