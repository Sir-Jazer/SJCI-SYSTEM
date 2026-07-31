<?php

namespace App\Filament\Resources\Collections\Schemas;

use App\Enums\CollectionType;
use App\Models\Collection;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class CollectionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('type')
                    ->label('Collection type')
                    ->options([
                        CollectionType::Offering->value => 'Offering (split 10% / 90%)',
                        CollectionType::Tithe->value => 'Tithe (recorded only)',
                    ])
                    ->default(CollectionType::Offering->value)
                    ->native(false)
                    ->required(),

                DatePicker::make('week_of')
                    ->label('Week of (service date)')
                    ->default(now())
                    ->maxDate(now())
                    ->required(),

                TextInput::make('amount')
                    ->label('Amount collected')
                    ->numeric()
                    ->minValue(0)
                    ->step('0.01')
                    ->prefix('₱')
                    ->default(0)
                    ->required()
                    ->live(onBlur: true)
                    ->helperText('Enter ₱0.00 if nothing was received — a weekly report is still required.'),

                Textarea::make('note')
                    ->label('Note (optional)')
                    ->rows(3)
                    ->maxLength(1000),

                FileUpload::make('attachments')
                    ->label('Proof of collection')
                    ->helperText('Photo or file of the counting sheet, deposit slip, or cash. Images or PDF, up to 5 files.')
                    ->multiple()
                    ->acceptedFileTypes(['image/*', 'application/pdf'])
                    ->maxFiles(5)
                    ->maxSize(5120) // 5 MB each
                    ->disk('public')
                    ->directory('collection-proofs')
                    ->downloadable()
                    ->openable()
                    ->reorderable()
                    // Required when money was actually received; ₱0.00 declarations need no proof.
                    ->required(fn (Get $get): bool => (float) ($get('amount') ?? 0) > 0)
                    ->columnSpanFull(),
            ]);
    }

    /** Only the submitter of a non-locked record may edit it. */
    public static function canEditRecord(?Collection $record): bool
    {
        return $record !== null && ! $record->isLocked();
    }
}
