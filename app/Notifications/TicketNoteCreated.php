<?php

namespace App\Notifications;

use App\Models\TicketNote;
use App\Models\User;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketNoteCreated extends Notification implements ShouldQueue
{
    use Queueable;

    private TicketNote $note;

    /**
     * Create a new notification instance.
     *
     * @param TicketNote $note
     * @return void
     */
    public function __construct(TicketNote $note)
    {
        $this->note = $note;
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
            ->subject(__('New note on ticket: ') . $this->note->ticket->name);

        // Add priority indication for high priority notes
        if ($this->note->priority === 'high') {
            $mailMessage->line('⚠️ ' . __('HIGH PRIORITY NOTE'));
        }

        $mailMessage->line(__('A new note has been added to a ticket you are responsible for.'))
            ->line(__('Ticket code: ') . $this->note->ticket->code)
            ->line(__('Ticket name: ') . $this->note->ticket->name)
            ->line(__('Note from: ') . $this->note->user->name);

        // Add category if available
        if ($this->note->category) {
            $mailMessage->line(__('Category: ') . $this->note->category);
        }

        $mailMessage->line(__('Note content: '))
            ->line(strip_tags($this->note->content))
            ->action(__('View ticket'), route('filament.resources.tickets.view', $this->note->ticket))
            ->line(__('Thank you for using our project management system!'));

        return $mailMessage;
    }

    /**
     * Get the array representation of the notification.
     *
     * @param User $notifiable
     * @return array
     */
    public function toDatabase(User $notifiable): array
    {
        $body = __('New note from :user', ['user' => $this->note->user->name]);

        if ($this->note->priority === 'high') {
            $body = '⚠️ ' . __('HIGH PRIORITY: ') . $body;
        }

        if ($this->note->category) {
            $body .= ' (' . $this->note->category . ')';
        }

        return FilamentNotification::make()
            ->title(__('New note on ticket: :ticketCode', ['ticketCode' => $this->note->ticket->code]))
            ->icon($this->note->priority === 'high' ? 'heroicon-o-exclamation-circle' : 'heroicon-o-annotation')
            ->body($body)
            ->actions([
                Action::make('view')
                    ->link()
                    ->icon('heroicon-s-eye')
                    ->url(route('filament.resources.tickets.view', $this->note->ticket)),
                Action::make('markAsRead')
                    ->link()
                    ->icon('heroicon-s-check')
                    ->url(route('ticket.note.read', $this->note->id)),
            ])
            ->getDatabaseMessage();
    }
}
