<?php

namespace App\Notifications;

use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\User;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketStatusUpdated extends Notification implements ShouldQueue
{
    use Queueable;

    private Ticket $ticket;
    private TicketActivity $activity;

    /**
     * Create a new notification instance.
     *
     * @param Ticket $ticket
     * @return void
     */
    public function __construct(Ticket $ticket)
    {
        $this->ticket = $ticket;
        $this->activity = $this->ticket->activities->last();
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
        $mailMessage = (new MailMessage)
            ->line(__('The status of ticket :ticket has been updated.', ['ticket' => $this->ticket->name]))
            ->line('- ' . __('Old status:') . ' ' . $this->activity->oldStatus->name)
            ->line('- ' . __('New status:') . ' ' . $this->activity->newStatus->name);

        // Add responsible change information if available
        if ($this->activity->old_responsible_id !== null &&
            $this->activity->new_responsible_id !== null &&
            $this->activity->old_responsible_id != $this->activity->new_responsible_id) {

            $oldResponsibleName = $this->activity->oldResponsible?->name ?? __('Unassigned');
            $newResponsibleName = $this->activity->newResponsible?->name ?? __('Unassigned');

            $mailMessage->line('- ' . __('Old responsible:') . ' ' . $oldResponsibleName)
                ->line('- ' . __('New responsible:') . ' ' . $newResponsibleName);
        }

        $mailMessage->line(__('See more details of this ticket by clicking on the button below:'))
            ->action(__('View details'), route('filament.resources.tickets.share', $this->ticket->code));

        return $mailMessage;
    }

    public function toDatabase(User $notifiable): array
    {
        $body = __('Old status: :oldStatus - New status: :newStatus', [
            'oldStatus' => $this->activity->oldStatus->name,
            'newStatus' => $this->activity->newStatus->name,
        ]);

        // Add responsible change information if available
        if ($this->activity->old_responsible_id !== null &&
            $this->activity->new_responsible_id !== null &&
            $this->activity->old_responsible_id != $this->activity->new_responsible_id) {

            $oldResponsibleName = $this->activity->oldResponsible?->name ?? __('Unassigned');
            $newResponsibleName = $this->activity->newResponsible?->name ?? __('Unassigned');

            $body .= "\n" . __('Responsible changed from :oldResponsible to :newResponsible', [
                    'oldResponsible' => $oldResponsibleName,
                    'newResponsible' => $newResponsibleName,
                ]);
        }

        return FilamentNotification::make()
            ->title(__('Ticket status updated'))
            ->icon('heroicon-o-ticket')
            ->body($body)
            ->actions([
                Action::make('view')
                    ->link()
                    ->icon('heroicon-s-eye')
                    ->url(route('filament.resources.tickets.share', $this->ticket->code)),
            ])
            ->getDatabaseMessage();
    }
}
