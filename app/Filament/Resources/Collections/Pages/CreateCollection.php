<?php

namespace App\Filament\Resources\Collections\Pages;

use App\Filament\Resources\Collections\CollectionResource;
use App\Models\AuditLog;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateCollection extends CreateRecord
{
    protected static string $resource = CollectionResource::class;

    /** A pastor always records for their own church, and is the submitter. */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['church_id'] = Auth::user()->church_id;
        $data['submitted_by'] = Auth::id();

        return $data;
    }

    protected function afterCreate(): void
    {
        AuditLog::record('submit', $this->record, [
            'type' => $this->record->type->value,
            'amount' => (string) $this->record->amount,
        ]);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
