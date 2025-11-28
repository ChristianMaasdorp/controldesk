<?php

namespace App\Filament\Resources\TicketResource\Pages;

use App\Filament\Resources\TicketResource;
use App\Imports\TicketsImport;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Forms;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Facades\Excel;

class ListTickets extends ListRecords
{
    protected static string $resource = TicketResource::class;

    protected function shouldPersistTableFiltersInSession(): bool
    {
        return true;
    }

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('importTickets')
                ->label('Import Tickets')
                ->icon('heroicon-o-upload')
                ->color('success')
                ->form([
                    Forms\Components\FileUpload::make('file')
                        ->label('Excel / CSV file')
                        ->required()
                        ->disk('local')
                        ->directory('imports')
                        ->acceptedFileTypes([
                            'text/csv',
                            'application/vnd.ms-excel',
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        ]),
                ])
                ->action(function (array $data): void {
                    $file = $data['file'];

                    if ($file instanceof \Livewire\TemporaryUploadedFile || $file instanceof \Illuminate\Http\UploadedFile) {
                        $path = $file->getRealPath();
                    } else {
                        // Stored path relative to the configured disk
                        $path = storage_path('app/' . ltrim($file, '/'));
                    }

                    Excel::import(new TicketsImport, $path);
                }),
        ];
    }

    protected function getTableQuery(): Builder
    {
        return parent::getTableQuery()
            ->where(function ($query) {
                return $query->where('owner_id', auth()->user()->id)
                    ->orWhere('responsible_id', auth()->user()->id)
                    ->orWhereHas('project', function ($query) {
                        return $query->where('owner_id', auth()->user()->id)
                            ->orWhereHas('users', function ($query) {
                                return $query->where('users.id', auth()->user()->id);
                            });
                    });
            });
    }
}
