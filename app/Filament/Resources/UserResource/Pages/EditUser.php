<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getActions(): array
    {
        return [
            Actions\Action::make('resend_verification')
                ->label(__('Resend Email Verification'))
                ->icon('heroicon-o-mail')
                ->color('secondary')
                ->action(function () {
                    $this->record->sendEmailVerificationNotification();

                    $this->notify('success', __('Email verification sent successfully to :email', [
                        'email' => $this->record->email
                    ]));
                }),
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
