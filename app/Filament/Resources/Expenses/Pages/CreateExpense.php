<?php

namespace App\Filament\Resources\Expenses\Pages;

use App\Filament\Resources\Expenses\ExpenseResource;
use App\Models\AuditLog;
use App\Support\DashboardStats;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class CreateExpense extends CreateRecord
{
    protected static string $resource = ExpenseResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $churchId = Auth::user()->church_id;

        $this->assertWithinFund($churchId, (float) $data['amount']);

        $data['church_id'] = $churchId;
        $data['submitted_by'] = Auth::id();

        return $data;
    }

    protected function afterCreate(): void
    {
        AuditLog::record('submit_expense', $this->record, [
            'category' => $this->record->category->value,
            'amount' => (string) $this->record->amount,
        ]);
    }

    /** Block a spend that exceeds what the fund can cover. */
    protected function assertWithinFund(?int $churchId, float $amount): void
    {
        $available = DashboardStats::availableToSpend($churchId);

        if ($amount > $available) {
            throw ValidationException::withMessages([
                'data.amount' => 'This exceeds the fund. Only ₱'.number_format($available, 2).' is available to spend.',
            ]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}