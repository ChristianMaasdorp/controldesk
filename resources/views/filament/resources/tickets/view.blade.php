@php($record = $this->record)
<x-filament::page>

    <a href="{{ route('filament.pages.kanban/{project}', ['project' => $record->project->id]) }}"
       class="flex items-center gap-1 text-gray-500 hover:text-gray-700 font-medium text-xs">
        <x-heroicon-o-arrow-left class="w-4 h-4"/> {{ __('Back to kanban board') }}
    </a>

    <div class="w-full flex md:flex-row flex-col gap-5">

        <x-filament::card class="md:w-2/3 w-full flex flex-col gap-5">
            <div class="w-full flex flex-col gap-0">
                <div class="flex items-center gap-2">
                    <span class="flex items-center gap-1 text-sm text-primary-500 font-medium">
                        <x-heroicon-o-ticket class="w-4 h-4"/>
                        {{ $record->code }}
                    </span>
                    <span class="text-sm text-gray-400 font-light">|</span>
                    <span class="flex items-center gap-1 text-sm text-gray-500">
                        {{ $record->project->name }}
                    </span>
                </div>
                <span class="text-xl text-gray-700">
                    {{ $record->name }}
                </span>
            </div>
            <div class="w-full flex items-center gap-2">
                <div class="px-2 py-1 rounded flex items-center justify-center text-center text-xs text-white"
                     style="background-color: {{ $record->status->color }};">
                    {{ $record->status->name }}
                </div>
                <div class="px-2 py-1 rounded flex items-center justify-center text-center text-xs text-white"
                     style="background-color: {{ $record->priority->color }};">
                    {{ $record->priority->name }}
                </div>
                <div class="px-2 py-1 rounded flex items-center justify-center text-center text-xs text-white"
                     style="background-color: {{ $record->type->color }};">
                    <x-icon class="h-3 text-white" name="{{ $record->type->icon }}"/>
                    <span class="ml-2">
                        {{ $record->type->name }}
                    </span>
                </div>
            </div>
            <div class="w-full flex flex-col gap-0 pt-5">
                <span class="text-gray-500 text-sm font-medium">
                    {{ __('Content') }}
                </span>
                <div class="w-full prose">
                    {!! $record->content !!}
                </div>
            </div>
        </x-filament::card>

        <x-filament::card class="md:w-1/3 w-full flex flex-col">
            <div class="w-full flex flex-col gap-1" wire:ignore>
                <span class="text-gray-500 text-sm font-medium">
                    {{ __('Owner') }}
                </span>
                <div class="w-full flex items-center gap-1 text-gray-500">
                    <x-user-avatar :user="$record->owner"/>
                    {{ $record->owner->name }}
                </div>
            </div>

            <div class="w-full flex flex-col gap-1 pt-3" wire:ignore>
                <span class="text-gray-500 text-sm font-medium">
                    {{ __('Responsible') }}
                </span>
                <div class="w-full flex items-center gap-1 text-gray-500">
                    @if($record->responsible)
                        <x-user-avatar :user="$record->responsible"/>
                    @endif
                    {{ $record->responsible?->name ?? '-' }}
                </div>
            </div>

            @if($record->project->type === 'scrum')
                <div class="w-full flex flex-col gap-1 pt-3">
                    <span class="text-gray-500 text-sm font-medium">
                        {{ __('Sprint') }}
                    </span>
                    <div class="w-full flex flex-col justify-center gap-1 text-gray-500">
                        @if($record->sprint)
                            {{ $record->sprint->name }}
                            <span class="text-xs text-gray-400">
                                {{ __('Starts at:') }} {{ $record->sprint->starts_at->format(__('Y-m-d')) }} -
                                {{ __('Ends at:') }} {{ $record->sprint->ends_at->format(__('Y-m-d')) }}
                            </span>
                        @else
                            -
                        @endif
                    </div>
                </div>
            @else
                <div class="w-full flex flex-col gap-1 pt-3">
                    <span class="text-gray-500 text-sm font-medium">
                        {{ __('Epic') }}
                    </span>
                    <div class="w-full flex items-center gap-1 text-gray-500">
                        @if($record->epic)
                            {{ $record->epic->name }}
                        @else
                            -
                        @endif
                    </div>
                </div>
            @endif

            <div class="w-full flex flex-col gap-1 pt-3">
                <span class="text-gray-500 text-sm font-medium">
                    {{ __('Estimation') }}
                </span>
                <div class="w-full text-gray-500">
                    <div class="flex items-center gap-2">
                        <span class="font-medium">{{ number_format($record->total_estimation, 2) }} {{ __('hours') }}</span>
                        <span class="text-gray-400">({{ $record->estimation_hours ?? 0 }}h {{ $record->estimation_minutes ?? 0 }}m)</span>
                    </div>
                    @if($record->estimation_start_date)
                        <div class="mt-1">
                            <span class="text-gray-400">{{ __('Start Date') }}:</span>
                            <span class="ml-1">{{ $record->estimation_start_date->format(__('Y-m-d g:i A')) }}</span>
                        </div>
                    @endif
                </div>
            </div>

            <div class="w-full flex flex-col gap-1 pt-3">
                <span class="text-gray-500 text-sm font-medium">
                    {{ __('Total time logged') }}
                </span>
                @if($record->hours()->count())
                    @if($record->estimation)
                        <div class="flex justify-between mb-1">
                            <span class="text-base font-medium
                                         text-{{ $record->estimationProgress > 100 ? 'danger' : 'primary' }}-700
                                         dark:text-white">
                                {{ $record->totalLoggedHours }}
                            </span>
                            <span class="text-sm font-medium
                                         text-{{ $record->estimationProgress > 100 ? 'danger' : 'primary' }}-700
                                         dark:text-white">
                            {{ round($record->estimationProgress) }}%
                        </span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2.5 dark:bg-gray-700">
                            <div class="bg-{{ $record->estimationProgress > 100 ? 'danger' : 'primary' }}-600
                                        h-2.5 rounded-full"
                                 style="width: {{ $record->estimationProgress > 100 ?
                                                    100
                                                    : $record->estimationProgress }}%">
                            </div>
                        </div>
                    @else
                        <div class="w-full flex items-center gap-1 text-gray-500">
                            {{ $record->totalLoggedHours }}
                        </div>
                    @endif
                @else
                    -
                @endif
            </div>

            <div class="w-full flex flex-col gap-1 pt-3">
                <span class="text-gray-500 text-sm font-medium">
                    {{ __('Subscribers') }}
                </span>
                <div class="w-full flex items-center gap-1 text-gray-500">
                    @if($record->subscribers->count())
                        @foreach($record->subscribers as $subscriber)
                            <x-user-avatar :user="$subscriber"/>
                        @endforeach
                    @else
                        {{ '-' }}
                    @endif
                </div>
            </div>

            <div class="w-full flex flex-col gap-1 pt-3">
                <span class="text-gray-500 text-sm font-medium">
                    {{ __('Creation date') }}
                </span>
                <div class="w-full text-gray-500">
                    {{ $record->created_at->format(__('Y-m-d g:i A')) }}
                    <span class="text-xs text-gray-400">
                        ({{ $record->created_at->diffForHumans() }})
                    </span>
                </div>
            </div>

            <div class="w-full flex flex-col gap-1 pt-3">
                <span class="text-gray-500 text-sm font-medium">
                    {{ __('Last update') }}
                </span>
                <div class="w-full text-gray-500">
                    {{ $record->updated_at->format(__('Y-m-d g:i A')) }}
                    <span class="text-xs text-gray-400">
                        ({{ $record->updated_at->diffForHumans() }})
                    </span>
                </div>
            </div>

            @if($record->relations->count())
                <div class="w-full flex flex-col gap-1 pt-3">
                    <span class="text-gray-500 text-sm font-medium">
                        {{ __('Ticket relations') }}
                    </span>
                    <div class="w-full text-gray-500">
                        @foreach($record->relations as $relation)
                            <div class="w-full flex items-center gap-1 text-xs">
                                <span class="rounded px-2 py-1 text-white
                                             bg-{{ config('system.tickets.relations.colors.' . $relation->type) }}-600">
                                    {{ __(config('system.tickets.relations.list.' . $relation->type)) }}
                                </span>
                                <a target="_blank" class="font-medium hover:underline"
                                   href="{{ route('filament.resources.tickets.share', $relation->relation->code) }}">
                                    {{ $relation->relation->code }}
                                </a>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </x-filament::card>

    </div>

    <div class="w-full flex md:flex-row flex-col gap-5">

        <x-filament::card class="md:w-2/3 w-full flex flex-col">
            <div class="w-full flex items-center gap-2 flex-wrap">
                <button wire:click="selectTab('comments')"
                        class="md:text-xl text-sm p-3 border-b-2 border-transparent hover:border-primary-500 flex items-center
                        gap-1 @if($tab === 'comments') border-primary-500 text-primary-500 @else text-gray-700 @endif">
                    {{ __('Comments') }}
                </button>
                <button wire:click="selectTab('notes')"
                        class="md:text-xl text-sm p-3 border-b-2 border-transparent hover:border-primary-500 flex items-center
                        gap-1 @if($tab === 'notes') border-primary-500 text-primary-500 @else text-gray-700 @endif">
                    {{ __('Notes') }}
                    @if(isset($record->unreadNotesCount) && $record->unreadNotesCount > 0)
                        <span class="ml-1.5 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                            {{ $record->unreadNotesCount }}
                        </span>
                    @endif
                </button>
                <button wire:click="selectTab('activities')"
                        class="md:text-xl text-sm p-3 border-b-2 border-transparent hover:border-primary-500
                        @if($tab === 'activities') border-primary-500 text-primary-500 @else text-gray-700 @endif">
                    {{ __('Activities') }}
                </button>
                <button wire:click="selectTab('time')"
                        class="md:text-xl text-sm p-3 border-b-2 border-transparent hover:border-primary-500
                        @if($tab === 'time') border-primary-500 text-primary-500 @else text-gray-700 @endif">
                    {{ __('Time logged') }}
                </button>
                <button wire:click="selectTab('attachments')"
                        class="md:text-xl text-sm p-3 border-b-2 border-transparent hover:border-primary-500
                        @if($tab === 'attachments') border-primary-500 text-primary-500 @else text-gray-700 @endif">
                    {{ __('Attachments') }}
                </button>
                {{-- GitHub Tab --}}
                @if(!empty($record->github_branch))
                    <button wire:click="selectTab('github')"
                            class="md:text-xl text-sm p-3 border-b-2 border-transparent hover:border-primary-500 flex items-center
                            gap-1 @if($tab === 'github') border-primary-500 text-primary-500 @else text-gray-700 @endif">
                        <x-heroicon-o-code class="w-5 h-5"/> {{-- Added icon --}}
                        {{ __('GitHub') }}
                    </button>
                @endif
            </div>
            @if($tab === 'comments')
                @if($this->canSubmitComment())
                    <form wire:submit.prevent="submitComment" class="pb-5">
                        {{ $this->form }}
                        <button type="submit"
                                class="px-3 py-2 bg-primary-500 hover:bg-primary-600 text-white rounded mt-3">
                            {{ __($selectedCommentId ? 'Edit comment' : 'Add comment') }}
                        </button>
                        @if($selectedCommentId)
                            <button type="button" wire:click="cancelEditComment"
                                    class="px-3 py-2 bg-warning-500 hover:bg-warning-600 text-white rounded mt-3">
                                {{ __('Cancel') }}
                            </button>
                        @endif
                    </form>
                @else
                    <div class="mt-4 bg-yellow-50 p-4 rounded-lg text-yellow-800 border border-yellow-200">
                        <p>{{ __('Only the responsible person can add comments to this ticket.') }}</p>
                    </div>
                @endif
                @foreach($record->comments->sortByDesc('created_at') as $comment)
                    <div
                        class="w-full flex flex-col gap-2 @if(!$loop->last) pb-5 mb-5 border-b border-gray-200 @endif ticket-comment">
                        <div class="w-full flex justify-between">
                            <span class="flex items-center gap-1 text-gray-500 text-sm">
                                <span class="font-medium flex items-center gap-1">
                                    <x-user-avatar :user="$comment->user"/>
                                    {{ $comment->user->name }}
                                </span>
                                <span class="text-gray-400 px-2">|</span>
                                {{ $comment->created_at->format('Y-m-d g:i A') }}
                                ({{ $comment->created_at->diffForHumans() }})
                            </span>
                            @if($this->isAdministrator() || $comment->user_id === auth()->user()->id)
                                <div class="actions flex items-center gap-2">
                                    <button type="button" wire:click="editComment({{ $comment->id }})"
                                            class="text-primary-500 text-xs hover:text-primary-600 hover:underline">
                                        {{ __('Edit') }}
                                    </button>
                                    <span class="text-gray-300">|</span>
                                    <button type="button" wire:click="deleteComment({{ $comment->id }})"
                                            class="text-danger-500 text-xs hover:text-danger-600 hover:underline">
                                        {{ __('Delete') }}
                                    </button>
                                </div>
                            @endif
                        </div>
                        <div class="w-full prose">
                            {!! $comment->content !!}
                        </div>
                    </div>
                @endforeach
            @endif
            @if($tab === 'notes')
                <form wire:submit.prevent="submitNote" class="pb-5">
                    {{ $this->noteForm }}
                    <button type="submit"
                            class="px-3 py-2 bg-primary-500 hover:bg-primary-600 text-white rounded mt-3">
                        {{ __($selectedNoteId ? 'Edit note' : 'Add note') }}
                    </button>
                    @if($selectedNoteId)
                        <button type="button" wire:click="cancelEditNote"
                                class="px-3 py-2 bg-warning-500 hover:bg-warning-600 text-white rounded mt-3">
                            {{ __('Cancel') }}
                        </button>
                    @endif
                </form>
                @if(isset($record->notes) && $record->notes->count() > 0)
                    @foreach($record->notes->sortByDesc('created_at') as $note)
                        <div class="w-full flex flex-col gap-2 @if(!$loop->last) pb-5 mb-5 border-b border-gray-200 @endif {{ $note->is_read ? '' : 'border-l-4 border-blue-500 pl-3' }}">
                            <div class="w-full flex justify-between">
                                <span class="flex items-center gap-1 text-gray-500 text-sm">
                                    <span class="font-medium flex items-center gap-1">
                                        <x-user-avatar :user="$note->user"/>
                                        {{ $note->user->name }}
                                    </span>
                                    <span class="text-gray-400 px-2">|</span>
                                    {{ $note->created_at->format('Y-m-d g:i A') }}
                                    ({{ $note->created_at->diffForHumans() }})
                                </span>
                                <div class="flex items-center space-x-2">
                                    @if($note->intended_for_id)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            {{ __('For:') }} {{ $note->intendedFor->name }}
                                        </span>
                                    @endif

                                    @if($note->category)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                            {{ $note->category }}
                                        </span>
                                    @endif

                                    @if($note->priority)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                            {{ $note->priority === 'high' ? 'bg-red-100 text-red-800' :
                                              ($note->priority === 'medium' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800') }}">
                                            {{ __($note->priority) }}
                                        </span>
                                    @endif

                                    @if(auth()->user()->id === $note->user_id)
                                        <div class="actions flex items-center gap-2 ml-4">
                                            <button type="button" wire:click="editNote({{ $note->id }})"
                                                    class="text-primary-500 text-xs hover:text-primary-600 hover:underline">
                                                {{ __('Edit') }}
                                            </button>
                                            <span class="text-gray-300">|</span>
                                            <button type="button" wire:click="deleteNote({{ $note->id }})"
                                                    class="text-danger-500 text-xs hover:text-danger-600 hover:underline">
                                                {{ __('Delete') }}
                                            </button>
                                        </div>
                                    @endif
                                </div>
                            </div>
                            <div class="w-full prose">
                                {!! $note->content !!}
                            </div>
                        </div>
                    @endforeach
                @else
                    <div class="w-full py-5 text-center text-gray-500">
                        {{ __('No notes yet.') }}
                    </div>
                @endif
            @endif
            @if($tab === 'activities')
                <div class="w-full flex flex-col pt-5">
                    @if($record->activities->count())
                        @foreach($record->activities->sortByDesc('created_at') as $activity)
                            <div class="w-full flex flex-col gap-2
                                 @if(!$loop->last) pb-5 mb-5 border-b border-gray-200 @endif">
                                <span class="flex items-center gap-1 text-gray-500 text-sm">
                                    <span class="font-medium flex items-center gap-1">
                                        <x-user-avatar :user="$activity->user"/>
                                        {{ $activity->user->name }}
                                    </span>
                                    <span class="text-gray-400 px-2">|</span>
                                    {{ $activity->created_at->format('Y-m-d g:i A') }}
                                    ({{ $activity->created_at->diffForHumans() }})
                                </span>
                                <div class="w-full flex items-center gap-10">
                                    <span class="text-gray-400">{{ $activity->oldStatus->name }}</span>
                                    <x-heroicon-o-arrow-right class="w-6 h-6"/>
                                    <span style="color: {{ $activity->newStatus->color }}">
                                        {{ $activity->newStatus->name }}
                                    </span>
                                </div>
                                @if(isset($activity->old_responsible_id) && isset($activity->new_responsible_id) && $activity->old_responsible_id != $activity->new_responsible_id)
                                    <div class="w-full flex items-center gap-10">
                                        <span class="text-gray-400">{{ optional($activity->oldResponsible)->name ?? __('Unassigned') }}</span>
                                        <x-heroicon-o-arrow-right class="w-6 h-6"/>
                                        <span class="text-primary-600">
                                            {{ optional($activity->newResponsible)->name ?? __('Unassigned') }}
                                        </span>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    @else
                        <span class="text-gray-400 text-sm font-medium">
                            {{ __('No activities yet!') }}
                        </span>
                    @endif
                </div>
            @endif
            @if($tab === 'time')
                <livewire:timesheet.time-logged :ticket="$record" />
            @endif
            @if($tab === 'attachments')
                <livewire:ticket.attachments :ticket="$record" />
            @endif
            {{-- GitHub Tab Content --}}
            @if($tab === 'github' && !empty($record->github_branch))
                <div class="w-full pt-5">
                    @if(!is_null($githubCommits))
                        @if(empty($githubCommits))
                            <div class="text-center text-gray-500 py-5">
                                {{ __('No commits found for branch:') }} {{ $record->github_branch }}
                            </div>
                        @else
                            <div class="flow-root">
                                <ul role="list" class="-mb-8">
                                    @foreach($githubCommits as $commit)
                                        <li>
                                            <div class="relative pb-8">
                                                @if(!$loop->last)
                                                    <span class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-gray-200" aria-hidden="true"></span>
                                                @endif
                                                <div class="relative flex space-x-3">
                                                    <div>
                                                        <span class="h-8 w-8 rounded-full bg-gray-400 flex items-center justify-center ring-8 ring-white">
                                                            <x-heroicon-s-code class="h-5 w-5 text-white" />
                                                        </span>
                                                    </div>
                                                    <div class="min-w-0 flex-1 pt-1.5 flex justify-between space-x-4">
                                                        <div>
                                                            <p class="text-sm text-gray-500">
                                                                {{ $commit['author'] }} {{ __('committed') }}
                                                                <a href="{{ 'https://github.com/jacquestrdx123/CibaRebuildSystem/commit/' . $commit['sha'] }}"
                                                                   target="_blank"
                                                                   class="font-medium text-gray-900 hover:underline">{{ substr($commit['sha'], 0, 7) }}</a>
                                                            </p>
                                                            <p class="text-sm text-gray-800 font-medium mt-1">{{ Str::limit(explode("\n", $commit['message'])[0], 80) }}</p>
                                                        </div>
                                                        <div class="text-right text-sm whitespace-nowrap text-gray-500">
                                                            <time datetime="{{ $commit['date'] }}">{{ \Carbon\Carbon::parse($commit['date'])->diffForHumans() }}</time>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    @else
                        <div class="text-center py-5">
                            <div class="inline-flex items-center px-4 py-2 font-semibold leading-6 text-sm shadow rounded-md text-white bg-primary-500 hover:bg-primary-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition ease-in-out duration-150 cursor-not-allowed">
                                <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                {{ __('Loading GitHub commits...') }}
                            </div>
                        </div>
                    @endif
                </div>
            @endif
        </x-filament::card>

        <div class="md:w-1/3 w-full flex flex-col"></div>

    </div>

</x-filament::page>

@push('scripts')
    <script>
        window.addEventListener('shareTicket', (e) => {
            const text = e.detail.url;
            const textArea = document.createElement("textarea");
            textArea.value = text;
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            try {
                document.execCommand('copy');
            } catch (err) {
                console.error('Unable to copy to clipboard', err);
            }
            document.body.removeChild(textArea);
            new Notification()
                .success()
                .title('{{ __('Url copied to clipboard') }}')
                .duration(6000)
                .send()
        });
    </script>
@endpush
