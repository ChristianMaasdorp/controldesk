<?php

namespace App\Filament\Resources\TicketResource\Pages;

use App\Filament\Resources\TicketResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;
use App\Services\GithubService;
use App\Models\TicketComment;
use Illuminate\Support\Facades\Notification;

class EditTicket extends EditRecord
{
    protected static string $resource = TicketResource::class;

    protected function getActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\Action::make('createGithubBranch')
                ->label(__('Create GitHub Branch'))
                ->icon('heroicon-o-code')
                ->color('primary')
                ->button()
                ->visible(function () {
                    return !empty($this->record->project->github_repository_url) &&
                           !empty($this->record->project->github_api_key) &&
                           empty($this->record->branch);
                })
                ->action(function (): void {
                    try {
                        $result = GithubService::createBranchFromTicket($this->record);

                        // Update the ticket with the branch name
                        $this->record->update([
                            'branch' => $result['branch']
                        ]);

                        // Add a system comment about the branch creation
                        TicketComment::create([
                            'user_id' => auth()->user()->id,
                            'ticket_id' => $this->record->id,
                            'content' => "**System:** " . __('GitHub branch created: ') . $result['branch']
                        ]);

                        // Refresh the record to update UI
                        $this->record->refresh();

                        Notification::make()
                            ->success()
                            ->title(__('GitHub Branch Created'))
                            ->body($result['message'])
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->danger()
                            ->title(__('GitHub Branch Creation Failed'))
                            ->body($e->getMessage())
                            ->send();
                    }
                }),
            Actions\DeleteAction::make(),
        ];
    }
}
