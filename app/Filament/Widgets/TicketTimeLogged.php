<?php

namespace App\Filament\Widgets;

use App\Models\Ticket;
use Filament\Widgets\BarChartWidget;

class TicketTimeLogged extends BarChartWidget
{
    protected static ?string $heading = 'Chart';
    protected static ?int $sort = 4;
    protected static ?string $maxHeight = '300px';
    protected int|string|array $columnSpan = [
        'sm' => 1,
        'md' => 6,
        'lg' => 3
    ];

    public static function canView(): bool
    {
        return auth()->user()->can('List tickets');
    }

    protected function getHeading(): string
    {
        return __('Time logged by tickets');
    }

    protected function getData(): array
    {
        $query = Ticket::query();
        $query->has('hours');
        $query->limit(10);
        $tickets = $query->get();
        
        return [
            'datasets' => [
                [
                    'label' => __('Total time logged'),
                    'data' => $tickets->map(function ($ticket) {
                        $totalHours = $ticket->totalLoggedInHours;
                        $hours = floor($totalHours);
                        $minutes = round(($totalHours - $hours) * 60);
                        return sprintf('%dh %dm', $hours, $minutes);
                    })->toArray(),
                    'backgroundColor' => [
                        'rgba(54, 162, 235, .6)'
                    ],
                    'borderColor' => [
                        'rgba(54, 162, 235, .8)'
                    ],
                ],
            ],
            'labels' => $tickets->pluck('code')->toArray(),
        ];
    }
}
