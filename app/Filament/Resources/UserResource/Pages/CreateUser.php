<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /**
     * Mutate form data before creating the record.
     * Set the user type to 'db' for database users created via Filament.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['type'] = 'db';
        return $data;
    }
}
