<?php

namespace App\Filament\Resources\Churches\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ChurchesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('is_main', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label('Church')
                    ->searchable()
                    ->sortable(),

                IconColumn::make('is_main')
                    ->label('Main')
                    ->boolean(),

                TextColumn::make('pastor.name')
                    ->label('Assigned pastor')
                    ->placeholder('— none —'),

                TextColumn::make('collections_count')
                    ->label('Reports')
                    ->counts('collections')
                    ->alignEnd(),

                TextColumn::make('created_at')
                    ->label('Added')
                    ->date('M j, Y')
                    ->toggleable(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}