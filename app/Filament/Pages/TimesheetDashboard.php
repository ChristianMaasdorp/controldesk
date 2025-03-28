<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\Timesheet\ActivitiesReport;
use App\Filament\Widgets\Timesheet\MonthlyReport;
use App\Filament\Widgets\Timesheet\WeeklyReport;
use App\Filament\Widgets\Timesheet\UserFilterWidget;
use App\Models\User;
use Filament\Pages\Page;

class TimesheetDashboard extends Page
{
    protected static ?string $slug = 'timesheet-dashboard';
    protected static ?int $navigationSort = 2;
    protected static string $view = 'filament::pages.dashboard';

    // Add selected user property
    public $selectedUserId;

    // Listen for the userSelected event from the widget
    protected $listeners = ['userSelected' => 'onUserSelected'];

    public function mount(): void
    {
        $this->selectedUserId = auth()->id();
    }

    // Handle the user selection from the widget
    public function onUserSelected($userId): void
    {
        $this->selectedUserId = $userId;
        $this->emit('userChanged', $userId);
    }

    protected function getHeaderWidgets(): array
    {
        return [
            UserFilterWidget::class,
        ];
    }

    protected function getColumns(): int | array
    {
        return 2; // Set 2 columns for the top widgets
    }

    protected static function getNavigationLabel(): string
    {
        return __('Dashboard');
    }

    protected static function getNavigationGroup(): ?string
    {
        return __('Timesheet');
    }

    protected static function shouldRegisterNavigation(): bool
    {
        return auth()->user()->can('View timesheet dashboard');
    }

    protected function getWidgets(): array
    {
        return [
            WeeklyReport::class,    // Top left
            ActivitiesReport::class, // Top right
            MonthlyReport::class,    // Bottom (full width)
        ];
    }

    protected function getWidgetData(): array
    {
        return [
            'selectedUserId' => $this->selectedUserId,
        ];
    }
}
