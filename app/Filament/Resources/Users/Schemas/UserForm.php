<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserRole;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

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
                    ->label('Temporary password')
                    ->password()
                    ->revealable()
                    ->maxLength(255)
                    // Required when creating; on edit, blank means "keep current".
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->helperText(fn (string $operation): string => $operation === 'edit'
                        ? 'Leave blank to keep the current password. Setting a new one makes it temporary again — the pastor must change it on next login.'
                        : 'Share this with the pastor. They will be required to set their own password when they first log in.')
                    // One-click generator so the Head Pastor never has to invent a password.
                    ->hintAction(
                        Action::make('generatePassword')
                            ->label('Generate')
                            ->icon(Heroicon::Sparkles)
                            ->action(function (Set $set): void {
                                $set('password', Str::password(12));
                            }),
                    ),
            ]);
    }
}