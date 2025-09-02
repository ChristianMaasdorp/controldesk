<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TicketResource\Pages;
use App\Filament\Resources\TicketResource\RelationManagers;
use Filament\Tables\Actions\BulkAction;
use App\Models\Epic;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketPriority;
use App\Models\TicketRelation;
use App\Models\TicketStatus;
use App\Models\TicketType;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Form;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Support\HtmlString;
use pxlrbt\FilamentExcel\Actions\Tables\ExportAction;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;
use pxlrbt\FilamentExcel\Columns\Column;
use Illuminate\Support\Collection;
use Mockery\Generator\StringManipulation\Pass\Pass;

class TicketResource extends Resource
{
    protected static ?string $model = Ticket::class;

    protected static ?string $navigationIcon = 'heroicon-o-ticket';

    protected static ?int $navigationSort = 2;

    protected static function getNavigationLabel(): string
    {
        return __('Tickets');
    }

    public static function getPluralLabel(): ?string
    {
        return static::getNavigationLabel();
    }

    protected static function getNavigationGroup(): ?string
    {
        return __('Management');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Card::make()
                    ->schema([
                        Forms\Components\Grid::make()
                            ->schema([
                                Forms\Components\Select::make('project_id')
                                    ->label(__('Project'))
                                    ->searchable()
                                    ->reactive()
                                    ->afterStateUpdated(function ($get, $set) {
                                        $project = Project::where('id', $get('project_id'))->first();
                                        if ($project?->status_type === 'custom') {
                                            $set(
                                                'status_id',
                                                TicketStatus::where('project_id', $project->id)
                                                    ->where('is_default', true)
                                                    ->first()
                                                    ?->id
                                            );
                                        } else {
                                            $set(
                                                'status_id',
                                                TicketStatus::whereNull('project_id')
                                                    ->where('is_default', true)
                                                    ->first()
                                                    ?->id
                                            );
                                        }
                                    })
                                    ->options(fn() => Project::where('owner_id', auth()->user()->id)
                                        ->orWhereHas('users', function ($query) {
                                            return $query->where('users.id', auth()->user()->id);
                                        })->pluck('name', 'id')->toArray()
                                    )
                                    ->default(fn() => request()->get('project'))
                                    ->required(),
                                Forms\Components\Select::make('epic_id')
                                    ->label(__('Epic'))
                                    ->searchable()
                                    ->reactive()
                                    ->options(function ($get, $set) {
                                        return Epic::where('project_id', $get('project_id'))->pluck('name', 'id')->toArray();
                                    }),
                                Forms\Components\Grid::make()
                                    ->columns(12)
                                    ->columnSpan(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('code')
                                            ->label(__('Ticket code'))
                                            ->visible(fn($livewire) => !($livewire instanceof CreateRecord))
                                            ->columnSpan(2)
                                            ->disabled(),

                                        Forms\Components\TextInput::make('name')
                                            ->label(__('Ticket name'))
                                            ->required()
                                            ->columnSpan(
                                                fn($livewire) => !($livewire instanceof CreateRecord) ? 10 : 12
                                            )
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('branch')
                                            ->label(__('Github Branch'))
                                            ->required()
                                            ->columnSpan(
                                                fn($livewire) => !($livewire instanceof CreateRecord) ? 10 : 12
                                            )
                                            ->maxLength(255),
                                    ]),

                                Forms\Components\Select::make('owner_id')
                                    ->label(__('Ticket owner'))
                                    ->searchable()
                                    ->options(fn() => User::all()->pluck('name', 'id')->toArray())
                                    ->default(fn() => auth()->user()->id)
                                    ->required(),

                                Forms\Components\Select::make('responsible_id')
                                    ->label(__('Ticket responsible'))
                                    ->searchable()
                                    ->options(fn() => User::all()->pluck('name', 'id')->toArray()),

                                Forms\Components\Grid::make()
                                    ->columns(3)
                                    ->columnSpan(2)
                                    ->schema([
                                        Forms\Components\Select::make('status_id')
                                            ->label(__('Ticket status'))
                                            ->searchable()
                                            ->options(function ($get) {
                                                $project = Project::where('id', $get('project_id'))->first();
                                                if ($project?->status_type === 'custom') {
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
                                            ->default(function ($get) {
                                                $project = Project::where('id', $get('project_id'))->first();
                                                if ($project?->status_type === 'custom') {
                                                    return TicketStatus::where('project_id', $project->id)
                                                        ->where('is_default', true)
                                                        ->first()
                                                        ?->id;
                                                } else {
                                                    return TicketStatus::whereNull('project_id')
                                                        ->where('is_default', true)
                                                        ->first()
                                                        ?->id;
                                                }
                                            })
                                            ->required(),

                                        Forms\Components\Select::make('type_id')
                                            ->label(__('Ticket type'))
                                            ->searchable()
                                            ->options(fn() => TicketType::all()->pluck('name', 'id')->toArray())
                                            ->default(fn() => TicketType::where('is_default', true)->first()?->id)
                                            ->required(),

                                        Forms\Components\Select::make('priority_id')
                                            ->label(__('Ticket priority'))
                                            ->searchable()
                                            ->options(fn() => TicketPriority::all()->pluck('name', 'id')->toArray())
                                            ->default(fn() => TicketPriority::where('is_default', true)->first()?->id)
                                            ->required(),
                                    ]),
                            ]),

                        Forms\Components\RichEditor::make('content')
                            ->label(__('Ticket content'))
                            ->required()
                            ->columnSpan(2),

                        Forms\Components\Card::make()
                            ->label(__('Estimation'))
                            ->schema([
                                Forms\Components\Grid::make()
                                    ->columns(3)
                                    ->schema([
                                        Forms\Components\TextInput::make('estimation_hours')
                                            ->label(__('Hours'))
                                            ->numeric()
                                            ->minValue(0)
                                            ->maxValue(999)
                                            ->default(0),
                                        Forms\Components\TextInput::make('estimation_minutes')
                                            ->label(__('Minutes'))
                                            ->numeric()
                                            ->minValue(0)
                                            ->maxValue(59)
                                            ->default(0),
                                        Forms\Components\DateTimePicker::make('estimation_start_date')
                                            ->label(__('Start Date'))
                                            ->displayFormat('Y-m-d H:i')
                                            ->withoutSeconds(),
                                    ]),
                            ]),

                        Forms\Components\Repeater::make('relations')
                            ->itemLabel(function (array $state) {
                                $ticketRelation = TicketRelation::find($state['id'] ?? 0);
                                if ($ticketRelation) {
                                    return __(config('system.tickets.relations.list.' . $ticketRelation->type))
                                        . ' '
                                        . $ticketRelation->relation->name
                                        . ' (' . $ticketRelation->relation->code . ')';
                                }
                                return null;
                            })
                            ->relationship()
                            ->collapsible()
                            ->collapsed()
                            ->orderable()
                            ->defaultItems(0)
                            ->schema([
                                Forms\Components\Grid::make()
                                    ->columns(3)
                                    ->schema([
                                        Forms\Components\Select::make('type')
                                            ->label(__('Relation type'))
                                            ->required()
                                            ->searchable()
                                            ->options(config('system.tickets.relations.list'))
                                            ->default(fn() => config('system.tickets.relations.default')),

                                        Forms\Components\Select::make('relation_id')
                                            ->label(__('Related ticket'))
                                            ->required()
                                            ->searchable()
                                            ->columnSpan(2)
                                            ->options(function ($livewire) {
                                                $query = Ticket::query();
                                                if ($livewire instanceof EditRecord && $livewire->record) {
                                                    $query->where('id', '<>', $livewire->record->id);
                                                }
                                                return $query->get()->pluck('name', 'id')->toArray();
                                            }),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    public static function tableColumns(bool $withProject = true): array
    {
        $columns = [];
        if ($withProject) {
            $columns[] = Tables\Columns\TextColumn::make('project.name')
                ->label(__('Project'))
                ->sortable()
                ->searchable();
        }
        $columns = array_merge($columns, [
            Tables\Columns\TextColumn::make('code')
                ->label(__('Ticket  code'))
                ->sortable()
                ->searchable(),
            Tables\Columns\TextColumn::make('id')
                ->label(__('Ticket ID'))
                ->sortable()
                ->searchable(),
            Tables\Columns\TextColumn::make('name')
                ->label(__('Ticket name'))
                ->sortable()
                ->searchable(),
            Tables\Columns\TextColumn::make('owner.name')
                ->label(__('Owner'))
                ->sortable()
                ->formatStateUsing(fn($record) => view('components.user-avatar', ['user' => $record->owner]))
                ->searchable(),

            Tables\Columns\TextColumn::make('responsible.name')
                ->label(__('Responsible'))
                ->sortable()
                ->formatStateUsing(fn($record) => view('components.user-avatar', ['user' => $record->responsible]))
                ->searchable(),

            Tables\Columns\TextColumn::make('status.name')
                ->label(__('Status'))
                ->formatStateUsing(fn($record) => new HtmlString('
                            <div class="flex gap-2 items-center mt-1">
                                <span class="flex relative w-6 h-6 rounded-md filament-tables-color-column"
                                    style="background-color: ' . $record->status->color . '"></span>
                                <span>' . $record->status->name . '</span>
                            </div>
                        '))
                ->sortable()
                ->searchable(),

            Tables\Columns\TextColumn::make('type.name')
                ->label(__('Type'))
                ->formatStateUsing(
                    fn($record) => view('partials.filament.resources.ticket-type', ['state' => $record->type])
                )
                ->sortable()
                ->searchable(),

            Tables\Columns\TextColumn::make('priority.name')
                ->label(__('Priority'))
                ->formatStateUsing(fn($record) => new HtmlString('
                            <div class="flex gap-2 items-center mt-1">
                                <span class="flex relative w-6 h-6 rounded-md filament-tables-color-column"
                                    style="background-color: ' . $record->priority->color . '"></span>
                                <span>' . $record->priority->name . '</span>
                            </div>
                        '))
                ->sortable()
                ->searchable(),

            Tables\Columns\TextColumn::make('created_at')
                ->label(__('Created at'))
                ->dateTime()
                ->sortable()
                ->searchable(),
            Tables\Columns\TextColumn::make('total_estimation')
                ->label(__('Total Estimation'))
                ->formatStateUsing(fn($state) => number_format($state, 2) . ' ' . __('hours'))
                ->sortable(),
            Tables\Columns\TextColumn::make('estimation_start_date')
                ->label(__('Start Date'))
                ->dateTime()
                ->sortable()
                ->searchable(),
        ]);
        return $columns;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns(self::tableColumns())
            ->filters([
                Tables\Filters\SelectFilter::make('project_id')
                    ->label(__('Project'))
                    ->multiple()
                    ->options(fn() => Project::where('owner_id', auth()->user()->id)
                        ->orWhereHas('users', function ($query) {
                            return $query->where('users.id', auth()->user()->id);
                        })->pluck('name', 'id')->toArray()),
                Tables\Filters\SelectFilter::make('owner_id')
                    ->label(__('Owner'))
                    ->multiple()
                    ->options(fn() => User::all()->pluck('name', 'id')->toArray()),

                Tables\Filters\SelectFilter::make('responsible_id')
                    ->label(__('Responsible'))
                    ->multiple()
                    ->options(fn() => User::all()->pluck('name', 'id')->toArray()),

                Tables\Filters\SelectFilter::make('status_id')
                    ->label(__('Status'))
                    ->multiple()
                    ->options(fn() => TicketStatus::all()->pluck('name', 'id')->toArray()),

                Tables\Filters\SelectFilter::make('type_id')
                    ->label(__('Type'))
                    ->multiple()
                    ->options(fn() => TicketType::all()->pluck('name', 'id')->toArray()),

                Tables\Filters\SelectFilter::make('priority_id')
                    ->label(__('Priority'))
                    ->multiple()
                    ->options(fn() => TicketPriority::all()->pluck('name', 'id')->toArray()),

                Tables\Filters\Filter::make('status_id')
                    ->label(__('Exclude'))
                    ->form([
                        Select::make('status_id')
                            ->label(__('Exclude'))
                            ->options(fn() => TicketStatus::pluck('name', 'id')->toArray())
                            ->multiple()
                    ])
                    ->query(function ($query, $data) {
                        return $query->whereNotIn('status_id', $data['status_id']);
                    })
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                ExportAction::make()->exports([
                    ExcelExport::make('Export Tickets')
                        ->fromTable()
                        ->withColumns([
                            Column::make('project.name')
                                ->formatStateUsing(fn($record) => trim(preg_replace('/\s+/', ' ', $record->project->name ?? ''))),

                            Column::make('name')
                                ->formatStateUsing(fn($record) => trim(preg_replace('/\s+/', ' ', $record->name ?? ''))),

                            Column::make('owner.name')
                                ->formatStateUsing(function($record) {
                                    if (!$record->owner) return '';
                                    return trim($record->owner->name);
                                }),

                            Column::make('responsible.name')
                                ->formatStateUsing(function($record) {
                                    if (!$record->responsible) return '';

                                    // Get just the responsible's name instead of rendering the view
                                    return trim($record->responsible->name);
                                }),

                            Column::make('status.name')
                                ->formatStateUsing(fn($record) => trim($record->status->name ?? '')),

                            Column::make('type.name')
                                ->formatStateUsing(fn($record) => trim($record->type->name ?? '')),

                            Column::make('priority.name')
                                ->formatStateUsing(fn($record) => trim($record->priority->name ?? '')),

                            Column::make('created_at')
                                ->formatStateUsing(fn($state) => $state ? date('Y-m-d H:i:s', strtotime($state)) : ''),
                        ]),
                    // ExcelExport::make('form')->fromForm(),
                ])
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
                BulkAction::make('assignToEpic')
                    ->label('Assign to Epic')
                    ->icon('heroicon-o-user')
                    ->form([
                        Select::make('epic_id')
                            ->label('Epic')
                            ->options(Epic::pluck('name', 'id')->toArray())
                            ->searchable()
                            ->required(),
                ])
                    ->action(function (Collection $records, array $data): void {
                        // dd($data);  Dump form input to teest data requested
                        foreach ($records as $record) {
                            $record->update([
                                'epic_id' => $data['epic_id'],
                            ]);
                        }
                    })
                    ->deselectRecordsAfterCompletion()

                    ->after(function(){
                        Notification::make()
                        ->title('Assigned to Epic successfully')
                        ->success()
                        ->send();
                }),
                Tables\Actions\DeleteBulkAction::make(),
                BulkAction::make('assignUser')
                    ->label('Assign to User')
                    ->icon('heroicon-o-user')
                    ->form([
                        Select::make('user_id')
                            ->label('User')
                            ->options(User::pluck('name', 'id')->toArray())
                            ->searchable()
                            ->required(),
                ])
                    ->action(function (Collection $records, array $data): void {
                        // dd($data);  Dump form input to teest data requested
                        foreach ($records as $record) {
                            $record->update([
                                'user_id' => $data['user_id'],
                                'responsible_id' => $data['user_id'],
                            ]);
                        }
                    })
                    ->deselectRecordsAfterCompletion()

                    ->after(function(){
                        Notification::make()
                        ->title('Assigned successfully')
                        ->success()
                        ->send();
                }),
                ExportBulkAction::make('Export Selected')
                    ->exports([
                        ExcelExport::make('Clean Data')
                            ->withFilename('tickets-export-' . date('Y-m-d'))
                            ->fromTable()
                            ->withColumns([
                                Column::make('project.name')
                                    ->formatStateUsing(fn($record) => trim(preg_replace('/\s+/', ' ', $record->project->name ?? ''))),
                                Column::make('name')
                                    ->formatStateUsing(fn($record) => trim(preg_replace('/\s+/', ' ', $record->name ?? ''))),
                                Column::make('owner.name')
                                    ->formatStateUsing(fn($record) => trim($record->owner->name ?? '')),
                                Column::make('responsible.name')
                                    ->formatStateUsing(fn($record) => trim($record->responsible->name ?? '')),
                                Column::make('status.name')
                                    ->formatStateUsing(fn($record) => trim($record->status->name ?? '')),
                                Column::make('type.name')
                                    ->formatStateUsing(fn($record) => trim($record->type->name ?? '')),
                                Column::make('priority.name')
                                    ->formatStateUsing(fn($record) => trim($record->priority->name ?? '')),
                                Column::make('created_at')
                                    ->formatStateUsing(fn($state) => $state ? date('Y-m-d H:i:s', strtotime($state)) : ''),
                            ]),
                    ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTickets::route('/'),
            'create' => Pages\CreateTicket::route('/create'),
            'view' => Pages\ViewTicket::route('/{record}'),
            'edit' => Pages\EditTicket::route('/{record}/edit'),
        ];
    }
}
