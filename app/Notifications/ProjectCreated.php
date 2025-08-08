<?php

namespace App\Notifications;

use App\Models\Project;
use App\Models\User;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProjectCreated extends Notification implements ShouldQueue
{
    use Queueable;

    private Project $project;

    /**
     * Create a new notification instance.
     *
     * @param Project $project
     * @return void
     */
    public function __construct(Project $project)
    {
        $this->project = $project;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param mixed $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->line(__('A new project has just been created and you have been assigned to it.'))
            ->line('- ' . __('Project name:') . ' ' . $this->project->name)
            ->line('- ' . __('Description:') . ' ' . ($this->project->description ?? __('No description provided')))
            ->line('- ' . __('Owner:') . ' ' . $this->project->owner->name)
            ->line('- ' . __('Status:') . ' ' . $this->project->status->name)
            ->line('- ' . __('Created:') . ' ' . $this->project->created_at->format('Y-m-d H:i'))
            ->line(__('You can view the project details by clicking on the button below:'))
            ->action(__('View Project'), route('filament.resources.projects.view', $this->project));
    }

    /**
     * Get the database representation of the notification.
     *
     * @param User $notifiable
     * @return array
     */
    public function toDatabase(User $notifiable): array
    {
        return FilamentNotification::make()
            ->title(__('New project created'))
            ->icon('heroicon-o-folder')
            ->body(fn() => __('You have been assigned to the project: :name', ['name' => $this->project->name]))
            ->actions([
                Action::make('view')
                    ->label(__('View Project'))
                    ->icon('heroicon-s-eye')
                    ->url(fn() => route('filament.resources.projects.view', $this->project)),
            ])
            ->getDatabaseMessage();
    }
}
