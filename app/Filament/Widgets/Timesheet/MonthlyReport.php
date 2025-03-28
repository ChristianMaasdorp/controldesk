<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Timesheet;

use App\Models\TicketHour;
use App\Models\User;
use Carbon\Carbon;
use Filament\Widgets\BarChartWidget;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class MonthlyReport extends BarChartWidget
{
    // Set to take up full width (bottom)
    protected int|string|array $columnSpan = 'full';

    // Set a lower sort order to appear last
    protected static ?int $sort = 3;

    // Default year filter
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
        return __('Logged time monthly') . ($user->id !== auth()->id() ? ' - ' . $user->name : '');
    }

    // Rest of the methods remain the same
    // ... (include the rest of the existing methods)
    protected function getData(): array
    {
        $user = $this->getUserToDisplay();

        $collection = $this->filter($user, [
            'year' => $this->filter
        ]);

        $datasets = $this->getDatasets($this->buildRapport($collection));

        return [
            'datasets' => [
                [
                    'label' => __('Total time logged for ') . $user->name,
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

    protected function getFilters(): ?array
    {
        return [
            2024 => 2024,
            2025 => 2025,
            2026 => 2026,
        ];
    }

    protected static ?array $options = [
        'plugins' => [
            'legend' => [
                'display' => true,
            ],
        ],
    ];

    protected function filter(User $user, array $params)
    {
        return TicketHour::select([
            DB::raw("DATE_FORMAT(created_at,'%m') as month"),
            DB::raw('SUM(value) as value'),
        ])
            ->whereRaw(
                DB::raw("YEAR(created_at)=" . (is_null($params['year']) ? Carbon::now()->format('Y') : $params['year']))
            )
            ->where('user_id', $user->id)
            ->groupBy(DB::raw("DATE_FORMAT(created_at,'%m')"))
            ->get();
    }

    protected function getDatasets(array $rapportData): array
    {
        $datasets = [
            'sets' => [],
            'labels' => []
        ];

        foreach ($rapportData as $data) {
            $datasets['sets'][] = $data[1];
            $datasets['labels'][] = $data[0];
        }

        return $datasets;
    }

    protected function buildRapport(Collection $collection): array
    {
        $months = [
            1 => ['January', 0],
            2 => ['February', 0],
            3 => ['March', 0],
            4 => ['April', 0],
            5 => ['May', 0],
            6 => ['June', 0],
            7 => ['July', 0],
            8 => ['August', 0],
            9 => ['September', 0],
            10 => ['October', 0],
            11 => ['November', 0],
            12 => ['December', 0]
        ];

        foreach ($collection as $value) {
            if (isset($months[(int)$value->month])) {
                $months[(int)$value->month][1] = (float)$value->value;
            }
        }

        return $months;
    }
}
