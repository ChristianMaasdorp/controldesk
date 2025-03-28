<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Timesheet;

use App\Models\TicketHour;
use App\Models\User;
use Carbon\Carbon;
use Filament\Widgets\BarChartWidget;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ActivitiesReport extends BarChartWidget
{
    // Set to take up 1 column (right side)
    protected int|string|array $columnSpan = 1;

    // Set a high sort order to appear second
    protected static ?int $sort = 2;

    public ?string $filter = '2025';

    // Property to store the selected user ID
    public $selectedUserId = null;

    // Listen for user changes from parent
    protected $listeners = ['userChanged' => 'onUserChanged'];

    public function __construct($id = null)
    {
        parent::__construct($id);

        // Default to current user
        $this->selectedUserId = auth()->id();
    }

    // Called when the user selection changes
    public function onUserChanged($userId): void
    {
        $this->selectedUserId = $userId;

        // Force chart to refresh with new data
        if (method_exists($this, 'updateChartData')) {
            $this->updateChartData();
        }
    }

    protected function getHeading(): string
    {
        $user = $this->getUserToDisplay();
        return __('Logged time by activity') . ($user->id !== auth()->id() ? ' - ' . $user->name : '');
    }

    // Rest of the methods remain the same
    // ... (include the rest of the existing methods)
    protected function getFilters(): ?array
    {
        return [
            2024 => 2024,
            2025 => 2025,
            2026 => 2026,
        ];
    }

    protected function getData(): array
    {
        $user = $this->getUserToDisplay();

        $collection = $this->filter($user, [
            'year' => $this->filter
        ]);

        $datasets = $this->getDatasets($collection);

        return [
            'datasets' => [
                [
                    'label' => __('Total time logged'),
                    'data' => $datasets['sets'],
                    'backgroundColor' => [
                        'rgba(54, 162, 235, .6)'
                    ],
                    'borderColor' => [
                        'rgba(54, 162, 235, .8)'
                    ],
                ],
            ],
            'labels' => $datasets['labels'],
        ];
    }

    // Get the user whose data should be displayed
    protected function getUserToDisplay(): User
    {
        // Get selected user when available
        if (!empty($this->selectedUserId)) {
            $selectedUser = User::find($this->selectedUserId);
            if ($selectedUser) {
                return $selectedUser;
            }
        }

        // Default to current user
        return auth()->user();
    }

    protected function getDatasets(Collection $collection): array
    {
        $datasets = [
            'sets' => [],
            'labels' => []
        ];

        foreach ($collection as $item) {
            $datasets['sets'][] = $item->value;
            $datasets['labels'][] = $item->activity?->name ?? __('No activity');
        }

        return $datasets;
    }

    protected function filter(User $user, array $params): Collection
    {
        return TicketHour::with('activity')
            ->select([
                'activity_id',
                DB::raw('SUM(value) as value'),
            ])
            ->whereRaw(
                DB::raw("YEAR(created_at)=" . (is_null($params['year']) ? Carbon::now()->format('Y') : $params['year']))
            )
            ->where('user_id', $user->id)
            ->groupBy('activity_id')
            ->get();
    }
}
