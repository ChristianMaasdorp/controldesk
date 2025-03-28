<?php

namespace App\Filament\Widgets\Timesheet;

use App\Models\User;
use Filament\Widgets\Widget;

class UserFilterWidget extends Widget
{
    protected static string $view = 'filament.widgets.user-filter-widget';

    // Make sure this appears at the top
    protected static ?int $sort = -2;

    // Always show this widget
    public static function canView(): bool
    {
        return true;
    }

    // Take up full width
    protected int|string|array $columnSpan = 'full';

    public function getCurrentUserId()
    {
        // Try to get the selected user ID from the dashboard
        if (method_exists($this->getLivewire(), 'getWidgetData')) {
            $data = $this->getLivewire()->getWidgetData();
            if (isset($data['selectedUserId'])) {
                return $data['selectedUserId'];
            }
        }

        // Default to current user
        return auth()->id();
    }

    public function getUsers()
    {
        return User::orderBy('name')->pluck('name', 'id')->toArray();
    }
}
