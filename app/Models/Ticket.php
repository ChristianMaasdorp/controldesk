<?php

namespace App\Models;

use App\Notifications\TicketCreated;
use App\Notifications\TicketCreatedForOwner;
use App\Notifications\TicketAssigned;
use App\Notifications\TicketStatusUpdated;
use Carbon\CarbonInterval;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Ticket extends Model implements HasMedia
{
    use HasFactory, SoftDeletes, InteractsWithMedia;

    protected $fillable = [
        'name',
        'content',
        'markdown_content',
        'owner_id',
        'responsible_id',
        'status_id',
        'project_id',
        'branch',
        'code',
        'order',
        'type_id',
        'priority_id',
        'estimation_hours',
        'estimation_minutes',
        'estimation_start_date',
        'epic_id',
        'sprint_id'
    ];

    protected $casts = [
        'estimation_start_date' => 'datetime',
    ];

    public static function boot()
    {
        parent::boot();

        static::creating(function (Ticket $item) {
            $project = Project::where('id', $item->project_id)->first();
            $count = Ticket::where('project_id', $project->id)->count();
            $order = $project->tickets?->last()?->order ?? -1;
            $item->code = $project->ticket_prefix . '-' . ($count + 1);
            $item->order = $order + 1;
        });

        static::created(function (Ticket $item) {
            if ($item->sprint_id && $item->sprint->epic_id) {
                Ticket::where('id', $item->id)->update(['epic_id' => $item->sprint->epic_id]);
            }

            // Send generic notification to all project watchers
            foreach ($item->watchers as $user) {
                $user->notify(new TicketCreated($item));
            }

            // Send specific notification to the ticket creator
            if ($item->owner) {
                $item->owner->notify(new TicketCreatedForOwner($item));
            }

            // Send specific notification to the assigned user
            if ($item->responsible) {
                $item->responsible->notify(new TicketAssigned($item));
            }
        });

        static::updating(function (Ticket $item) {
            $old = Ticket::where('id', $item->id)->first();

            // Ticket activity based on status
            $oldStatus = $old->status_id;
            $oldResponsible = $old->responsible_id;

            // Create activity if status changes or responsible changes
            if ($oldStatus != $item->status_id || $oldResponsible != $item->responsible_id) {
                TicketActivity::create([
                    'ticket_id' => $item->id,
                    'old_status_id' => $oldStatus,
                    'new_status_id' => $item->status_id,
                    'old_responsible_id' => $oldResponsible,
                    'new_responsible_id' => $item->responsible_id,
                    'user_id' => auth()->user()->id
                ]);

                // Only send notifications for status changes
                if ($oldStatus != $item->status_id) {
                    foreach ($item->watchers as $user) {
                        $user->notify(new TicketStatusUpdated($item));
                    }
                }
            }

            // Ticket sprint update
            $oldSprint = $old->sprint_id;
            if ($oldSprint && !$item->sprint_id) {
                Ticket::where('id', $item->id)->update(['epic_id' => null]);
            } elseif ($item->sprint_id && $item->sprint->epic_id) {
                Ticket::where('id', $item->id)->update(['epic_id' => $item->sprint->epic_id]);
            }
        });
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id', 'id');
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_id', 'id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(TicketStatus::class, 'status_id', 'id')->withTrashed();
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id', 'id')->withTrashed();
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(TicketType::class, 'type_id', 'id')->withTrashed();
    }

    public function priority(): BelongsTo
    {
        return $this->belongsTo(TicketPriority::class, 'priority_id', 'id')->withTrashed();
    }

    public function activities(): HasMany
    {
        return $this->hasMany(TicketActivity::class, 'ticket_id', 'id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class, 'ticket_id', 'id');
    }

    public function subscribers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'ticket_subscribers', 'ticket_id', 'user_id');
    }

    public function relations(): HasMany
    {
        return $this->hasMany(TicketRelation::class, 'ticket_id', 'id');
    }

    public function hours(): HasMany
    {
        return $this->hasMany(TicketHour::class, 'ticket_id', 'id');
    }

    public function epic(): BelongsTo
    {
        return $this->belongsTo(Epic::class, 'epic_id', 'id');
    }

    public function sprint(): BelongsTo
    {
        return $this->belongsTo(Sprint::class, 'sprint_id', 'id');
    }

    public function sprints(): BelongsTo
    {
        return $this->belongsTo(Sprint::class, 'sprint_id', 'id');
    }

    public function watchers(): Attribute
    {
        return new Attribute(
            get: function () {
                $users = $this->project->users;
                $users->push($this->owner);
                if ($this->responsible) {
                    $users->push($this->responsible);
                }
                return $users->unique('id');
            }
        );
    }

    public function totalLoggedHours(): Attribute
    {
        return new Attribute(
            get: function () {
                $seconds = $this->hours->sum('value') * 3600;
                return CarbonInterval::seconds($seconds)->cascade()->forHumans();
            }
        );
    }

    public function totalLoggedSeconds(): Attribute
    {
        return new Attribute(
            get: function () {
                return $this->hours->sum('value') * 3600;
            }
        );
    }

    public function totalLoggedInHours(): Attribute
    {
        return new Attribute(
            get: function () {
                return $this->hours->sum('value');
            }
        );
    }

    public function estimationForHumans(): Attribute
    {
        return new Attribute(
            get: function () {
                return CarbonInterval::seconds($this->estimationInSeconds)->cascade()->forHumans();
            }
        );
    }

    public function estimationInSeconds(): Attribute
    {
        return new Attribute(
            get: function () {
                if (!$this->estimation) {
                    return null;
                }
                return $this->estimation * 3600;
            }
        );
    }

    public function estimationProgress(): Attribute
    {
        return new Attribute(
            get: function () {
                return (($this->totalLoggedSeconds ?? 0) / ($this->estimationInSeconds ?? 1)) * 100;
            }
        );
    }

    public function completudePercentage(): Attribute
    {
        return new Attribute(
            get: fn() => $this->estimationProgress
        );
    }

    public function notes(): HasMany
    {
        return $this->hasMany(TicketNote::class, 'ticket_id', 'id');
    }

    public function unreadNotesCount(): Attribute
    {
        return new Attribute(
            get: function () {
                if ($this->responsible_id !== auth()->user()->id) {
                    return 0;
                }

                return $this->notes()
                    ->where('is_read', false)
                    ->count();
            }
        );
    }

    public function getTotalEstimationAttribute()
    {
        $hours = $this->estimation_hours ?? 0;
        $minutes = $this->estimation_minutes ?? 0;
        return $hours + ($minutes / 60);
    }

    public function githubCommits()
    {
        return $this->hasMany(TicketGithubCommit::class)->orderBy('committed_at', 'desc');
    }

    /**
     * Get the markdown content as HTML
     *
     * @return string|null
     */
    public function getMarkdownAsHtml()
    {
        if (!$this->markdown_content) {
            return null;
        }

        // You can use any markdown parser here
        // For example, if you have league/commonmark installed:
        // return (new \League\CommonMark\GithubFlavoredMarkdownConverter())->convert($this->markdown_content);

        // For now, return the raw markdown content
        return $this->markdown_content;
    }

    /**
     * Check if the ticket has markdown content
     *
     * @return bool
     */
    public function hasMarkdownContent()
    {
        return !empty($this->markdown_content);
    }

    /**
     * Generate markdown content using OpenAI
     *
     * @return string|null
     */
    public function generateMarkdownContent()
    {
        $openAIService = app(\App\Services\OpenAIService::class);

        if (!$openAIService->isConfigured()) {
            throw new \Exception('OpenAI API key is not configured. Please add OPENAI_API_KEY to your .env file.');
        }

        return $openAIService->generateTicketMarkdown($this);
    }

    /**
     * Get all comments with full content
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAllCommentsWithContent()
    {
        return $this->comments()->with('user')->orderBy('created_at', 'asc')->get();
    }

    /**
     * Get all attached files with content for text files
     *
     * @return \Illuminate\Support\Collection
     */
    public function getAttachedFilesWithContent()
    {
        return $this->getMedia()->map(function ($media) {
            $fileData = [
                'id' => $media->id,
                'name' => $media->name,
                'file_name' => $media->file_name,
                'mime_type' => $media->mime_type,
                'size' => $media->size,
                'created_at' => $media->created_at,
                'url' => $media->getUrl(),
            ];

            // Try to include content for text-based files
            if (in_array($media->mime_type, ['text/plain', 'text/markdown', 'text/html', 'application/json', 'application/xml'])) {
                try {
                    $fileData['content'] = $media->getStream()->getContents();
                } catch (\Exception $e) {
                    $fileData['content'] = null;
                }
            }

            return $fileData;
        });
    }

    /**
     * Export tickets to array with relationships
     *
     * @param array $ticketIds Optional array of ticket IDs to export specific tickets
     * @param array $withRelationships Optional array of relationships to include
     * @return array
     */
    public static function exportToArray($ticketIds = null, $withRelationships = null)
    {
        $query = self::query();

        // Filter by specific ticket IDs if provided
        if ($ticketIds) {
            $query->whereIn('id', $ticketIds);
        }

        // Define default relationships to include
        $defaultRelationships = [
            'owner:id,name,email',
            'responsible:id,name,email',
            'status:id,name,color',
            'project:id,name,description',
            'type:id,name,color',
            'priority:id,name,color',
            // 'epic:id,name,starts_at,ends_at', // Temporarily commented out due to column issue
            'sprint:id,name,starts_at,ends_at',
            'activities:id,ticket_id,old_status_id,new_status_id,old_responsible_id,new_responsible_id,user_id,created_at',
            'comments:id,ticket_id,content,user_id,created_at',
            'subscribers:id,name,email',
            'relations:id,ticket_id,relation_id,type',
            'hours:id,ticket_id,value,comment,created_at,user_id',
            'notes:id,ticket_id,content,is_read,created_at',
            'githubCommits:id,ticket_id,sha,message,committed_at'
        ];

        // Use provided relationships or default ones
        $relationships = $withRelationships ?: $defaultRelationships;

        // Load relationships
        $query->with($relationships);

        // Get tickets
        $tickets = $query->get();

        // Transform to array with relationships
        $exportedTickets = [];

        foreach ($tickets as $ticket) {
            $ticketArray = $ticket->toArray();

            // Add computed attributes
            $ticketArray['total_logged_hours'] = $ticket->total_logged_hours;
            $ticketArray['total_logged_seconds'] = $ticket->total_logged_seconds;
            $ticketArray['total_logged_in_hours'] = $ticket->total_logged_in_hours;
            $ticketArray['estimation_for_humans'] = $ticket->estimation_for_humans;
            $ticketArray['estimation_in_seconds'] = $ticket->estimation_in_seconds;
            $ticketArray['estimation_progress'] = $ticket->estimation_progress;
            $ticketArray['completude_percentage'] = $ticket->completude_percentage;
            $ticketArray['total_estimation'] = $ticket->total_estimation;
            $ticketArray['unread_notes_count'] = $ticket->unread_notes_count;

            // Add watchers (computed relationship)
            $ticketArray['watchers'] = $ticket->watchers->map(function ($watcher) {
                return [
                    'id' => $watcher->id,
                    'name' => $watcher->name,
                    'email' => $watcher->email
                ];
            })->toArray();

            // Add attached files
            $ticketArray['attached_files'] = $ticket->getMedia()->map(function ($media) {
                $fileData = [
                    'id' => $media->id,
                    'name' => $media->name,
                    'file_name' => $media->file_name,
                    'mime_type' => $media->mime_type,
                    'size' => $media->size,
                    'created_at' => $media->created_at,
                    'url' => $media->getUrl(),
                ];

                // Try to include content for text-based files
                if (in_array($media->mime_type, ['text/plain', 'text/markdown', 'text/html', 'application/json', 'application/xml'])) {
                    try {
                        $fileData['content'] = $media->getStream()->getContents();
                    } catch (\Exception $e) {
                        $fileData['content'] = null;
                    }
                }

                return $fileData;
            })->toArray();

            $exportedTickets[] = $ticketArray;
        }

        return $exportedTickets;
    }
}
