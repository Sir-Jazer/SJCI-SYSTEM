<?php

namespace App\Filament\Resources\Expenses\Pages;

use App\Filament\Resources\Expenses\ExpenseResource;
use App\Models\AuditLog;
use App\Support\DashboardStats;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditExpense extends EditRecord
{
    protected static string $resource = ExpenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Exclude this record's own pending amount from the available balance.
        $available = DashboardStats::availableToSpend($this->record->church_id, $this->record->getKey());

        if ((float) $data['amount'] > $available) {
            throw ValidationException::withMessages([
                'data.amount' => 'This exceeds the fund. Only ₱'.number_format($available, 2).' is available to spend.',
            ]);
        }

        return $data;
    }

    protected function afterSave(): void
    {
        AuditLog::record('update_expense', $this->record, [
            'category' => $this->record->category->value,
            'amount' => (string) $this->record->amount,
        ]);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}