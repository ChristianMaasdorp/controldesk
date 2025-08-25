<?php

namespace App\Filament\Resources\TicketResource\Pages;

use App\Exports\TicketHoursExport;
use App\Filament\Resources\TicketResource;
use App\Models\Activity;
use App\Models\TicketActivity;
use App\Models\TicketComment;
use App\Models\TicketHour;
use App\Models\TicketStatus;
use App\Models\TicketSubscriber;
use App\Models\User;
use App\Services\GithubService;
use Exception;
use Filament\Forms;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\TicketNote;
use Illuminate\Support\Facades\Log;

class ViewTicket extends ViewRecord implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = TicketResource::class;

    protected static string $view = 'filament.resources.tickets.view';

    public string $tab = 'comments';

    public ?array $githubCommits = null;

    // Combined listeners for both comments and notes
    protected $listeners = ['doDeleteComment', 'doDeleteNote'];

    public $selectedCommentId, $selectedNoteId;

    protected GithubService $githubService;

    // Using boot for dependency injection in Livewire components
    public function boot(GithubService $githubService): void
    {
        $this->githubService = $githubService;
    }

    public function mount($record): void
    {
        parent::mount($record);
        $this->form->fill();
        $this->noteForm->fill();

        // Remove GitHub commits fetching from mount method
        // We'll fetch them only when the GitHub tab is selected
    }

    protected function getForms(): array
    {
        return [
            'form' => $this->makeForm()
                ->schema($this->getFormSchema()),
            'noteForm' => $this->makeForm()
                ->schema($this->getNoteFormSchema()),
        ];
    }


    protected function getNoteFormSchema(): array
    {
        return [
            Forms\Components\Grid::make()
                ->columns(1)
                ->schema([
                    Forms\Components\Select::make('intended_for_id')
                        ->label(__('Intended for'))
                        ->options(function () {
                            $options = [];

                            // Always include the responsible person if there is one
                            if ($this->record->responsible_id) {
                                $options[$this->record->responsible_id] = $this->record->responsible->name . ' (' . __('Responsible') . ')';
                            }

                            // Include other project members
                            foreach ($this->record->project->users as $user) {
                                if (!isset($options[$user->id])) {
                                    $options[$user->id] = $user->name;
                                }
                            }

                            return $options;
                        })
                        ->default(function () {
                            return $this->record->responsible_id;
                        }),

                    Forms\Components\Select::make('priority')
                        ->label(__('Priority'))
                        ->options([
                            'low' => __('Low'),
                            'medium' => __('Medium'),
                            'high' => __('High'),
                        ])
                        ->default('medium'),

                    Forms\Components\TextInput::make('category')
                        ->label(__('Category'))
                        ->maxLength(50),

                    Forms\Components\RichEditor::make('content')
                        ->disableLabel()
                        ->placeholder(__('Type a new note'))
                        ->required(),
                ]),
        ];
    }

    protected function getFormSchema(): array
    {
        return [
            RichEditor::make('comment')
                ->disableLabel()
                ->placeholder(__('Type a new comment'))
                ->required()
        ];
    }



    protected function getActions(): array
    {
        return [
            Actions\Action::make('assignToMe')
                ->label(__('Assign to Me'))
                ->icon('heroicon-o-user-circle')
                ->color('success')
                ->button()
                ->visible(function () {
                    return $this->record->responsible_id !== auth()->user()->id;
                })
                ->action(function (): void {
                    // Store the previous values for tracking
                    $oldResponsibleId = $this->record->responsible_id;
                    $oldStatusId = $this->record->status_id;

                    // Get user names for the comment
                    $oldResponsibleName = User::find($oldResponsibleId)?->name ?? __('Unassigned');
                    $newResponsibleName = auth()->user()->name;

                    // Find the project's default "active" status
                    $project = $this->record->project;
                    $newStatusId = null;

                    if ($project->status_type === 'custom') {
                        // Try to find an "active" status for custom project statuses
                        $newStatus = TicketStatus::where('project_id', $project->id)
                            ->where(function ($query) {
                                $query->where('name', 'like', '%progress%')
                                    ->orWhere('name', 'like', '%active%')
                                    ->orWhere('name', 'like', '%working%');
                            })
                            ->orderBy('order')
                            ->first();

                        if ($newStatus) {
                            $newStatusId = $newStatus->id;
                        } else {
                            // If no "active" status found, use the first non-default status
                            $newStatus = TicketStatus::where('project_id', $project->id)
                                ->where('is_default', false)
                                ->orderBy('order')
                                ->first();

                            $newStatusId = $newStatus ? $newStatus->id : $oldStatusId;
                        }
                    } else {
                        // Try to find an "active" status for global statuses
                        $newStatus = TicketStatus::whereNull('project_id')
                            ->where(function ($query) {
                                $query->where('name', 'like', '%progress%')
                                    ->orWhere('name', 'like', '%active%')
                                    ->orWhere('name', 'like', '%working%');
                            })
                            ->orderBy('order')
                            ->first();

                        if ($newStatus) {
                            $newStatusId = $newStatus->id;
                        } else {
                            // If no "active" status found, use the first non-default status
                            $newStatus = TicketStatus::whereNull('project_id')
                                ->where('is_default', false)
                                ->orderBy('order')
                                ->first();

                            $newStatusId = $newStatus ? $newStatus->id : $oldStatusId;
                        }
                    }

                    // Get the new status name
                    $oldStatusName = TicketStatus::find($oldStatusId)->name;
                    $newStatusName = TicketStatus::find($newStatusId)->name;

                    // Create the formatted system comment
                    $systemComment = "**System:** " . __('Ticket self-assigned from ') .
                        $oldResponsibleName . __(' to ') . $newResponsibleName;

                    if ($oldStatusId != $newStatusId) {
                        $systemComment .= __(' with status changed from ') .
                            $oldStatusName . __(' to ') . $newStatusName;
                    }

                    // Add the comment to ticket history BEFORE updating the ticket
                    TicketComment::create([
                        'user_id' => auth()->user()->id,
                        'ticket_id' => $this->record->id,
                        'content' => $systemComment
                    ]);

                    // Explicitly create the TicketActivity record with all required fields
                    TicketActivity::create([
                        'ticket_id' => $this->record->id,
                        'old_status_id' => $oldStatusId,
                        'new_status_id' => $newStatusId,
                        'old_responsible_id' => $oldResponsibleId,
                        'new_responsible_id' => auth()->user()->id,
                        'user_id' => auth()->user()->id
                    ]);

                    // Update the ticket
                    $this->record->update([
                        'responsible_id' => auth()->user()->id,
                        'status_id' => $newStatusId,
                    ]);

                    // Refresh the record to update UI
                    $this->record->refresh();
                    $this->notify('success', __('Ticket assigned to you successfully'));
                }),
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
            Actions\Action::make('reassignTicket')
                ->label(__('Reassign Ticket'))
                ->icon('heroicon-o-user-group')
                ->color('primary')
                ->button()
                ->visible(fn() => $this->isAdministrator() || $this->record->responsible_id === auth()->user()->id)
                ->modalWidth('md')
                ->modalHeading(__('Reassign Ticket'))
                ->modalSubheading(__('Update the responsible person and status for this ticket.'))
                ->modalButton(__('Reassign'))
                ->form([
                    Select::make('responsible_id')
                        ->label(__('New Responsible'))
                        ->searchable()
                        ->options(fn() => User::all()->pluck('name', 'id')->toArray())
                        ->required(),

                    Select::make('status_id')
                        ->label(__('New Status'))
                        ->searchable()
                        ->options(function () {
                            $project = $this->record->project;
                            if ($project->status_type === 'custom') {
                                return TicketStatus::where('project_id', $project->id)
                                    ->get()
                                    ->pluck('name', 'id')
                                    ->toArray();
                            } else {
                                return TicketStatus::whereNull('project_id')
                                    ->get()
                                    ->pluck('name', 'id')
                                    ->toArray();
                            }
                        })
                        ->default(function () {
                            $project = $this->record->project;
                            if ($project->status_type === 'custom') {
                                return TicketStatus::where('project_id', $project->id)
                                    ->where('is_default', false)
                                    ->orderBy('order')
                                    ->first()?->id ?? $this->record->status_id;
                            } else {
                                return TicketStatus::whereNull('project_id')
                                    ->where('is_default', false)
                                    ->orderBy('order')
                                    ->first()?->id ?? $this->record->status_id;
                            }
                        })
                        ->required(),

                    Textarea::make('comment')
                        ->label(__('Comment'))
                        ->helperText(__('Please explain why this ticket is being reassigned.'))
                        ->rows(3)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    // Store the previous values for tracking
                    $oldResponsibleId = $this->record->responsible_id;
                    $oldStatusId = $this->record->status_id;
                    $newResponsibleId = $data['responsible_id'];
                    $newStatusId = $data['status_id'];

                    // Get user names for the comment
                    $oldResponsibleName = User::find($oldResponsibleId)?->name ?? __('Unassigned');
                    $newResponsibleName = User::find($newResponsibleId)->name;

                    // Get status names for the comment
                    $oldStatusName = TicketStatus::find($oldStatusId)->name;
                    $newStatusName = TicketStatus::find($newStatusId)->name;

                    // Create the formatted comment with both system info and user comment
                    $systemInfo = "**System:** " . __('Ticket reassigned from ') .
                        $oldResponsibleName . __(' to ') . $newResponsibleName;

                    if ($oldStatusId != $newStatusId) {
                        $systemInfo .= __(' with status changed from ') .
                            $oldStatusName . __(' to ') . $newStatusName;
                    }

                    $combinedComment = $systemInfo . "\n\n" .
                        "**Reason:** " . $data['comment'];

                    // Add the comment to ticket history BEFORE updating the ticket
                    TicketComment::create([
                        'user_id' => auth()->user()->id,
                        'ticket_id' => $this->record->id,
                        'content' => $combinedComment
                    ]);

                    // Explicitly create the TicketActivity record with all required fields
                    TicketActivity::create([
                        'ticket_id' => $this->record->id,
                        'old_status_id' => $oldStatusId,
                        'new_status_id' => $newStatusId,
                        'old_responsible_id' => $oldResponsibleId,
                        'new_responsible_id' => $newResponsibleId,
                        'user_id' => auth()->user()->id
                    ]);

                    // Update the ticket
                    $this->record->update([
                        'responsible_id' => $newResponsibleId,
                        'status_id' => $newStatusId,
                    ]);

                    // Refresh the record to update UI
                    $this->record->refresh();
                    $this->notify('success', __('Ticket reassigned successfully'));
                }),
            Actions\Action::make('toggleSubscribe')
                ->label(
                    fn() => $this->record->subscribers()->where('users.id', auth()->user()->id)->count() ?
                        __('Unsubscribe')
                        : __('Subscribe')
                )
                ->color(
                    fn() => $this->record->subscribers()->where('users.id', auth()->user()->id)->count() ?
                        'danger'
                        : 'success'
                )
                ->icon('heroicon-o-bell')
                ->button()
                ->action(function () {
                    if (
                        $sub = TicketSubscriber::where('user_id', auth()->user()->id)
                            ->where('ticket_id', $this->record->id)
                            ->first()
                    ) {
                        $sub->delete();
                        $this->notify('success', __('You unsubscribed from the ticket'));
                    } else {
                        TicketSubscriber::create([
                            'user_id' => auth()->user()->id,
                            'ticket_id' => $this->record->id
                        ]);
                        $this->notify('success', __('You subscribed to the ticket'));
                    }
                    $this->record->refresh();
                }),
            Actions\Action::make('share')
                ->label(__('Share'))
                ->color('secondary')
                ->button()
                ->icon('heroicon-o-share')
                ->action(fn() => $this->dispatchBrowserEvent('shareTicket', [
                    'url' => route('filament.resources.tickets.share', $this->record->code)
                ])),
            Actions\EditAction::make(),
            Actions\Action::make('logHours')
                ->label(__('Log time'))
                ->icon('heroicon-o-clock')
                ->color('warning')
                ->modalWidth('sm')
                ->modalHeading(__('Log worked time'))
                ->modalSubheading(__('Use the following form to add worked time for this ticket.'))
                ->modalButton(__('Log'))
                ->form([
                    Select::make('user_id')
                        ->label(__('User'))
                        ->searchable()
                        ->options(function ($livewire) {
                            // Get the ticket owner and responsible person
                            $assignedUsers = collect([]);

                            if ($livewire->record->owner_id) {
                                $assignedUsers->put(
                                    $livewire->record->owner_id,
                                    $livewire->record->owner->name
                                );
                            }

                            if ($livewire->record->responsible_id &&
                                $livewire->record->responsible_id != $livewire->record->owner_id) {
                                $assignedUsers->put(
                                    $livewire->record->responsible_id,
                                    $livewire->record->responsible->name
                                );
                            }

                            return $assignedUsers->toArray();
                        })
                        ->default(function () {
                            // Default to current user if they're assigned to the ticket
                            $currentUserId = auth()->user()->id;
                            $record = $this->record;

                            if ($currentUserId == $record->owner_id || $currentUserId == $record->responsible_id) {
                                return $currentUserId;
                            }

                            return null;
                        })
                        ->required(),

                    Forms\Components\Grid::make()
                        ->columns(2)
                        ->schema([
                            TextInput::make('hours')
                                ->label(__('Hours'))
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(999)
                                ->default(0)
                                ->required(),
                            TextInput::make('minutes')
                                ->label(__('Minutes'))
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(59)
                                ->default(0)
                                ->required(),
                        ]),

                    Select::make('activity_id')
                        ->label(__('Activity'))
                        ->searchable()
                        ->options(function () {
                            return Activity::all()->pluck('name', 'id')->toArray();
                        }),

                    Textarea::make('comment')
                        ->label(__('Comment'))
                        ->rows(3),
                ])
                ->action(function (array $data): void {
                    // Create the time log entry
                    TicketHour::create([
                        'ticket_id' => $this->record->id,
                        'activity_id' => $data['activity_id'],
                        'user_id' => $data['user_id'], // The user who did the work
                        'logged_by_id' => auth()->user()->id, // The user who logged the entry
                        'value' => $data['hours'] + ($data['minutes'] / 60),
                        'comment' => $data['comment'] ?? null
                    ]);

                    // Add comment to ticket comments if a comment was provided
                    if (!empty($data['comment'])) {
                        $userName = User::find($data['user_id'])->name;
                        $activityName = Activity::find($data['activity_id'])?->name ?? '';
                        $hours = $data['hours'] + ($data['minutes'] / 60);

                        // Create a formatted comment that includes the time logging details
                        $commentContent = "**Time Logged**: {$hours}h - {$activityName} by {$userName}\n\n{$data['comment']}";

                        TicketComment::create([
                            'user_id' => auth()->user()->id,
                            'ticket_id' => $this->record->id,
                            'content' => $commentContent
                        ]);
                    }

                    $this->record->refresh();
                    $this->notify('success', __('Time logged successfully'));
                }),
            Actions\ActionGroup::make([
                Actions\Action::make('exportLogHours')
                    ->label(__('Export time logged'))
                    ->icon('heroicon-o-document-download')
                    ->color('warning')
                    ->visible(
                        fn() => $this->record->watchers->where('id', auth()->user()->id)->count()
                            && $this->record->hours()->count()
                    )
                    ->action(fn() => Excel::download(
                        new TicketHoursExport($this->record),
                        'time_' . str_replace('-', '_', $this->record->code) . '.csv',
                        \Maatwebsite\Excel\Excel::CSV,
                        ['Content-Type' => 'text/csv']
                    )),
            ])
                ->visible(fn() => (in_array(
                        auth()->user()->id,
                        [$this->record->owner_id, $this->record->responsible_id]
                    )) || (
                        $this->record->watchers->where('id', auth()->user()->id)->count()
                        && $this->record->hours()->count()
                    ))
                ->color('secondary'),
        ];
    }


    public function selectTab(string $tab): void
    {
        $this->tab = $tab;

        // Fetch GitHub commits from the database when the GitHub tab is selected
        if ($tab === 'github' && !empty($this->record->branch)) {
            try {
                // Get commits from the database
                $this->githubCommits = $this->record->githubCommits()
                    ->orderBy('committed_at', 'desc')
                    ->get()
                    ->map(function ($commit) {
                        return [
                            'sha' => $commit->sha,
                            'author' => $commit->author,
                            'message' => $commit->message,
                            'date' => $commit->committed_at->format('Y-m-d H:i:s'),
                        ];
                    })
                    ->toArray();

                // If no commits are found in the database, show a notification
                if (empty($this->githubCommits)) {
                    Notification::make()
                        ->title(__('No GitHub Commits'))
                        ->body(__('No commits found for this branch. The commits will be fetched by the background task.'))
                        ->send();
                }
            } catch (Exception $e) {
                Log::error("Failed to fetch GitHub commits from database for ticket {$this->record->id} branch '{$this->record->branch}': " . $e->getMessage());
                Notification::make()
                    ->danger()
                    ->title(__('Database Error'))
                    ->body(__('Could not fetch commits from database for branch: ') . $this->record->branch)
                    ->send();
                $this->githubCommits = null; // Ensure it's null on error so the tab doesn't render
            }
        }

        // Mark notes as read when opening the notes tab if user is the responsible person or intended recipient
        if ($tab === 'notes' && (
                $this->record->responsible_id === auth()->user()->id ||
                TicketNote::where('ticket_id', $this->record->id)
                    ->where('intended_for_id', auth()->user()->id)
                    ->where('is_read', false)
                    ->exists()
            )
        ) {
            TicketNote::where('ticket_id', $this->record->id)
                ->where('intended_for_id', auth()->user()->id) // Only mark as read if intended for current user
                ->where('is_read', false)
                ->update(['is_read' => true]);

            // No need to call refresh here as Livewire will re-render on property change
            // $this->record->refresh();
        }
    }

    /**
     * Generate markdown documentation for the current ticket
     */
    public function generateMarkdown(): void
    {
        try {
            // Show loading notification
            Notification::make()
                ->title(__('Generating Documentation'))
                ->body(__('Creating comprehensive markdown documentation using AI...'))
                ->send();

            $openAIService = app(\App\Services\OpenAIService::class);
            
            if (!$openAIService->isConfigured()) {
                Notification::make()
                    ->danger()
                    ->title(__('OpenAI Not Configured'))
                    ->body(__('OpenAI API key is not configured. Please add OPENAI_API_KEY to your .env file.'))
                    ->send();
                return;
            }

            $result = $openAIService->generateTicketMarkdown($this->record);
            
            if ($result) {
                // Refresh the record to get the updated markdown content
                $this->record->refresh();
                
                Notification::make()
                    ->success()
                    ->title(__('Documentation Generated'))
                    ->body(__('Comprehensive markdown documentation has been created successfully.'))
                    ->send();
            } else {
                Notification::make()
                    ->danger()
                    ->title(__('Generation Failed'))
                    ->body(__('Failed to generate documentation. Please try again.'))
                    ->send();
            }
        } catch (\Exception $e) {
            Log::error("Failed to generate markdown for ticket {$this->record->id}: " . $e->getMessage());
            
            Notification::make()
                ->danger()
                ->title(__('Error'))
                ->body(__('An error occurred while generating documentation: ') . $e->getMessage())
                ->send();
        }
    }

    /**
     * Manually refresh GitHub commits for the current ticket
     */
    public function refreshGithubCommits(): void
    {
        if (empty($this->record->branch)) {
            Notification::make()
                ->warning()
                ->title(__('No Branch'))
                ->body(__('This ticket does not have a GitHub branch associated with it.'))
                ->send();
            return;
        }

        if (empty($this->record->project->github_repository_url) || empty($this->record->project->github_api_key)) {
            Notification::make()
                ->warning()
                ->title(__('GitHub Not Configured'))
                ->body(__('This project does not have GitHub repository URL or API key configured.'))
                ->send();
            return;
        }

        try {
            // Show loading notification
            Notification::make()
                ->title(__('Refreshing Commits'))
                ->body(__('Fetching commits from GitHub...'))
                ->send();

            // Fetch commits from GitHub
            $commits = $this->githubService->getCommitsForBranch($this->record->branch, $this->record->project);

            // Store commits in the database
            foreach ($commits as $commit) {
                $this->record->githubCommits()->updateOrCreate(
                    ['sha' => $commit['sha']],
                    [
                        'author' => $commit['author'],
                        'message' => $commit['message'],
                        'committed_at' => $commit['date'],
                        'branch' => $this->record->branch,
                    ]
                );
            }

            // Get commits from the database
            $this->githubCommits = $this->record->githubCommits()
                ->orderBy('committed_at', 'desc')
                ->get()
                ->map(function ($commit) {
                    return [
                        'sha' => $commit->sha,
                        'author' => $commit->author,
                        'message' => $commit->message,
                        'date' => $commit->committed_at->format('Y-m-d H:i:s'),
                    ];
                })
                ->toArray();

            // Show success notification
            Notification::make()
                ->success()
                ->title(__('Commits Refreshed'))
                ->body(__('Successfully refreshed GitHub commits.'))
                ->send();
        } catch (\Exception $e) {
            Log::error("Failed to fetch GitHub commits for ticket {$this->record->id} branch '{$this->record->branch}': " . $e->getMessage());
            Notification::make()
                ->danger()
                ->title(__('Error'))
                ->body(__('Failed to fetch GitHub commits: ') . $e->getMessage())
                ->send();
        }
    }

    public function submitNote(): void
    {
        $data = $this->noteForm->getState();

        if ($this->selectedNoteId) {
            TicketNote::where('id', $this->selectedNoteId)
                ->update([
                    'intended_for_id' => $data['intended_for_id'],
                    'priority' => $data['priority'],
                    'category' => $data['category'],
                    'content' => $data['content'],
                    'is_read' => false // Mark as unread when updated
                ]);
        } else {
            TicketNote::create([
                'user_id' => auth()->user()->id,
                'ticket_id' => $this->record->id,
                'intended_for_id' => $data['intended_for_id'],
                'priority' => $data['priority'],
                'category' => $data['category'],
                'content' => $data['content']
            ]);
        }

        $this->record->refresh();
        $this->cancelEditNote();
        $this->notify('success', __('Note saved'));
    }

    public function submitComment(): void
    {
        $data = $this->form->getState();
        if ($this->selectedCommentId) {
            TicketComment::where('id', $this->selectedCommentId)
                ->update([
                    'content' => $data['comment']
                ]);
        } else {
            TicketComment::create([
                'user_id' => auth()->user()->id,
                'ticket_id' => $this->record->id,
                'content' => $data['comment']
            ]);
        }
        $this->record->refresh();
        $this->cancelEditComment();
        $this->notify('success', __('Comment saved'));
    }

    public function isAdministrator(): bool
    {
        return $this->record
                ->project
                ->users()
                ->where('users.id', auth()->user()->id)
                ->where('role', 'administrator')
                ->count() != 0;
    }

    public function editComment(int $commentId): void
    {
        $this->form->fill([
            'comment' => $this->record->comments->where('id', $commentId)->first()?->content
        ]);
        $this->selectedCommentId = $commentId;
    }

    public function editNote(int $noteId): void
    {
        $note = $this->record->notes->where('id', $noteId)->first();

        if ($note) {
            $this->noteForm->fill([
                'intended_for_id' => $note->intended_for_id,
                'priority' => $note->priority,
                'category' => $note->category,
                'content' => $note->content
            ]);
            $this->selectedNoteId = $noteId;
        }
    }

    public function deleteComment(int $commentId): void
    {
        Notification::make()
            ->warning()
            ->title(__('Delete confirmation'))
            ->body(__('Are you sure you want to delete this comment?'))
            ->actions([
                Action::make('confirm')
                    ->label(__('Confirm'))
                    ->color('danger')
                    ->button()
                    ->close()
                    ->emit('doDeleteComment', compact('commentId')),
                Action::make('cancel')
                    ->label(__('Cancel'))
                    ->close()
            ])
            ->persistent()
            ->send();
    }

    public function deleteNote(int $noteId): void
    {
        Notification::make()
            ->warning()
            ->title(__('Delete confirmation'))
            ->body(__('Are you sure you want to delete this note?'))
            ->actions([
                Action::make('confirm')
                    ->label(__('Confirm'))
                    ->color('danger')
                    ->button()
                    ->close()
                    ->emit('doDeleteNote', compact('noteId')),
                Action::make('cancel')
                    ->label(__('Cancel'))
                    ->close()
            ])
            ->persistent()
            ->send();
    }

    public function doDeleteComment(int $commentId): void
    {
        TicketComment::where('id', $commentId)->delete();
        $this->record->refresh();
        $this->notify('success', __('Comment deleted'));
    }

    public function doDeleteNote(int $noteId): void
    {
        TicketNote::where('id', $noteId)->delete();
        $this->record->refresh();
        $this->notify('success', __('Note deleted'));
    }

    public function cancelEditComment(): void
    {
        $this->form->fill();
        $this->selectedCommentId = null;
    }

    public function cancelEditNote(): void
    {
        $this->noteForm->fill();
        $this->selectedNoteId = null;
    }

    public function canSubmitComment(): bool
    {
        // Example logic, adjust as needed
        // Allow commenting if user is owner, responsible, admin, or subscribed
        return $this->record->owner_id === auth()->user()->id ||
               $this->record->responsible_id === auth()->user()->id ||
               $this->isAdministrator() ||
               $this->record->subscribers()->where('users.id', auth()->user()->id)->exists();
    }

    public function canSubmitNote(): bool
    {
        // Example logic, adjust as needed
        // Allow adding notes if user is owner, responsible, admin, or subscribed
        return $this->record->owner_id === auth()->user()->id ||
               $this->record->responsible_id === auth()->user()->id ||
               $this->isAdministrator() ||
               $this->record->subscribers()->where('users.id', auth()->user()->id)->exists();
    }
}
