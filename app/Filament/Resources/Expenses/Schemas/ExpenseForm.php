<?php

namespace App\Filament\Resources\Expenses\Schemas;

use App\Enums\ExpenseCategory;
use App\Models\Expense;
use App\Support\DashboardStats;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class ExpenseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('category')
                    ->label('Purpose category')
                    ->options(ExpenseCategory::options())
                    ->default(ExpenseCategory::Operations->value)
                    ->native(false)
                    ->required(),

                DatePicker::make('spent_on')
                    ->label('Date spent')
                    ->default(now())
                    ->maxDate(now())
                    ->required(),

                TextInput::make('amount')
                    ->label('Amount spent')
                    ->numeric()
                    ->minValue(0.01)
                    ->step('0.01')
                    ->prefix('₱')
                    ->required()
                    ->live(onBlur: true)
                    ->helperText(function (?Expense $record): string {
                        $churchId = Auth::user()?->church_id;
                        $available = DashboardStats::availableToSpend($churchId, $record?->getKey());

                        return 'Available in fund: ₱'.number_format($available, 2);
                    }),

                Textarea::make('purpose')
                    ->label('What was it for?')
                    ->required()
                    ->rows(3)
                    ->maxLength(500),

                FileUpload::make('attachments')
                    ->label('Receipt / proof of spending')
                    ->helperText('Photo or file of the receipt or proof. Images or PDF, up to 5 files.')
                    ->multiple()
                    ->acceptedFileTypes(['image/*', 'application/pdf'])
                    ->maxFiles(5)
                    ->maxSize(5120) // 5 MB each
                    ->disk('public')
                    ->directory('expense-receipts')
                    ->downloadable()
                    ->openable()
                    ->reorderable()
                    ->required(fn (Get $get): bool => (float) ($get('amount') ?? 0) > 0)
                    ->columnSpanFull(),
            ]);
    }
}