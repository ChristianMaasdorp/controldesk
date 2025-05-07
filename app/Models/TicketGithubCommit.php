<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketGithubCommit extends Model
{
    protected $fillable = [
        'ticket_id',
        'sha',
        'author',
        'message',
        'committed_at',
        'branch',
    ];

    protected $casts = [
        'committed_at' => 'datetime',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }
}
