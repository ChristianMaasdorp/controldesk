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

class ViewTicket extends ViewRecord implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = TicketResource::class;

    protected static string $view = 'filament.resources.tickets.view';

    public string $tab = 'comments';

    protected $listeners = ['doDeleteComment'];

    public $selectedCommentId;

    public function mount($record): void
    {
        parent::mount($record);
        $this->form->fill();
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

                    TextInput::make('time')
                        ->label(__('Time to log per hour'))
                        ->numeric()
                        ->required(),

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
                        'value' => $data['time'],
                        'comment' => $data['comment'] ?? null
                    ]);

                    // Add comment to ticket comments if a comment was provided
                    if (!empty($data['comment'])) {
                        $userName = User::find($data['user_id'])->name;
                        $activityName = Activity::find($data['activity_id'])?->name ?? '';
                        $hours = $data['time'];

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

    public function doDeleteComment(int $commentId): void
    {
        TicketComment::where('id', $commentId)->delete();
        $this->record->refresh();
        $this->notify('success', __('Comment deleted'));
    }

    public function cancelEditComment(): void
    {
        $this->form->fill();
        $this->selectedCommentId = null;
    }
}
