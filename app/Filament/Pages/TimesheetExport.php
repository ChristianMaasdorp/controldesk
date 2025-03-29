<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\User;
use Filament\Forms\Components\Card;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Forms\Components\DatePicker;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TimesheetExport extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $slug = 'timesheet-export';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.timesheet-export';

    protected static function getNavigationGroup(): ?string
    {
        return __('Timesheet');
    }

    public function mount(): void
    {
        $this->form->fill([
            'user_id' => auth()->id(), // Default to current user
        ]);
    }

    protected function getFormSchema(): array
    {
        return [
            Card::make()->schema([
                Grid::make()
                    ->columns(3)
                    ->schema([
                        // Only show the user selector if the current user has permission
                        // to view other users' data
                        $this->getUserSelect(),

                        DatePicker::make('start_date')
                            ->required()
                            ->reactive()
                            ->label('Start date'),

                        DatePicker::make('end_date')
                            ->required()
                            ->reactive()
                            ->label('End date')
                    ])
            ])
        ];
    }

    protected function getUserSelect()
    {
        // Check if user has permission to view any users
        if (auth()->user()->can('viewAny', User::class)) {
            return Select::make('user_id')
                ->label('Select User')
                ->options(function () {
                    return User::orderBy('name')->pluck('name', 'id');
                })
                ->default(auth()->id())
                ->required();
        }

        // If they don't have permission, return a hidden field with the current user ID
        return Select::make('user_id')
            ->label('User')
            ->options(function () {
                return User::where('id', auth()->id())->pluck('name', 'id');
            })
            ->default(auth()->id())
            ->disabled()
            ->required();
    }

    public function create(): BinaryFileResponse
    {
        $data = $this->form->getState();

        return Excel::download(
            new \App\Exports\TimesheetExport($data),
            'time_' . time() . '.csv',
            \Maatwebsite\Excel\Excel::CSV,
            ['Content-Type' => 'text/csv']
        );
    }
}
