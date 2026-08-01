<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /**
     * Accounts are invite-only: the Head Pastor sets a temporary password that
     * the pastor is forced to replace on first login.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['must_change_password'] = true;

        return $data;
    }
}
