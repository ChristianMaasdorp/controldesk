<?php

namespace App\Notifications;

use App\Models\Ticket;
use App\Models\User;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketAssigned extends Notification implements ShouldQueue
{
    use Queueable;

    private Ticket $ticket;

    /**
     * Create a new notification instance.
     *
     * @param Ticket $ticket
     * @return void
     */
    public function __construct(Ticket $ticket)
    {
        $this->ticket = $ticket;
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
            ->subject(__('New ticket assigned to you: :name', ['name' => $this->ticket->name]))
            ->line(__('A new ticket has been assigned to you.'))
            ->line('- ' . __('Ticket name:') . ' ' . $this->ticket->name)
            ->line('- ' . __('Project:') . ' ' . $this->ticket->project->name)
            ->line('- ' . __('Created by:') . ' ' . $this->ticket->owner->name)
            ->line('- ' . __('Status:') . ' ' . $this->ticket->status->name)
            ->line('- ' . __('Type:') . ' ' . $this->ticket->type->name)
            ->line('- ' . __('Priority:') . ' ' . $this->ticket->priority->name)
            ->line(__('Click the button below to view the ticket details and start working on it:'))
            ->action(__('View ticket'), route('filament.resources.tickets.share', $this->ticket->code));
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
            ->title(__('New ticket created'))
            ->icon('heroicon-o-ticket')
            ->body(fn() => $this->ticket->name)
            ->actions([
                Action::make('view')
                    ->link()
                    ->icon('heroicon-s-eye')
                    ->url(fn() => route('filament.resources.tickets.share', $this->ticket->code)),
            ])
            ->getDatabaseMessage();
    }
}
