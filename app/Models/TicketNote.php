<?php

namespace App\Models;

use App\Notifications\TicketNoteCreated;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TicketNote extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'ticket_id',
        'intended_for_id',
        'content',
        'priority',
        'category',
        'is_read'
    ];

    protected $casts = [
        'is_read' => 'boolean',
    ];

    public static function boot()
    {
        parent::boot();

        static::created(function (TicketNote $item) {
            // Send notification to the intended person (usually the responsible person)
            if ($item->intended_for_id) {
                $intendedUser = User::find($item->intended_for_id);
                if ($intendedUser) {
                    $intendedUser->notify(new TicketNoteCreated($item));
                }
            }
            // If no specific intended person, notify the ticket's responsible person
            elseif ($item->ticket->responsible_id) {
                $item->ticket->responsible->notify(new TicketNoteCreated($item));
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id', 'id');
    }

    public function intendedFor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'intended_for_id', 'id');
    }

    // Mark the note as read
    public function markAsRead()
    {
        $this->update(['is_read' => true]);
    }

    // Check if this is a high priority note
    public function isHighPriority()
    {
        return $this->priority === 'high';
    }
}
