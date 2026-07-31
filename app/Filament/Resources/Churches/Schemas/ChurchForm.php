<?php

namespace App\Filament\Resources\Churches\Schemas;

use App\Enums\UserRole;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ChurchForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Church name')
                    ->required()
                    ->maxLength(255),

                Toggle::make('is_main')
                    ->label('This is the main church')
                    ->helperText('The main church receives remittances; outreaches send them.')
                    ->default(false),

                Select::make('pastor_id')
                    ->label('Assigned pastor')
                    ->relationship(
                        name: 'pastor',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn ($query) => $query->whereIn('role', [
                            UserRole::HeadPastor->value,
                            UserRole::OutreachPastor->value,
                        ]),
                    )
                    ->searchable()
                    ->preload()
                    ->nullable()
                    ->helperText('The one pastor responsible for this church.'),
            ]);
    }
}