<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserRole;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),

                TextInput::make('email')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),

                Select::make('role')
                    ->options([
                        UserRole::HeadPastor->value => 'Head Pastor',
                        UserRole::OutreachPastor->value => 'Outreach Pastor',
                    ])
                    ->default(UserRole::OutreachPastor->value)
                    ->native(false)
                    ->required(),

                Select::make('church_id')
                    ->label('Church')
                    ->relationship('church', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),

                TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->maxLength(255)
                    // Required when creating; on edit, blank means "keep current".
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->helperText(fn (string $operation): ?string => $operation === 'edit' ? 'Leave blank to keep the current password.' : null),
            ]);
    }
}