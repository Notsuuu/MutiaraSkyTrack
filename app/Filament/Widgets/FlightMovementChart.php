<?php

namespace App\Filament\Widgets;

use App\Models\FlightTraffic;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class FlightMovementChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Tren Pergerakan Penerbangan';

    protected int | string | array $columnSpan = [
        'default' => 1,
        'lg'      => 2,
    ];

    protected function getData(): array
    {
        $tahun = $this->filters['tahun'] ?? '2026';
        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

        $arrivalData = [];
        $departureData = [];

        for ($m = 1; $m <= 12; $m++) {
            $arrivalData[] = FlightTraffic::whereYear('schedule_date', $tahun)
                ->whereMonth('schedule_date', $m)
                ->where('movement', 'Arrival')
                ->count();

            $departureData[] = FlightTraffic::whereYear('schedule_date', $tahun)
                ->whereMonth('schedule_date', $m)
                ->where('movement', 'Departure')
                ->count();
        }

        return [
            'datasets' => [
                [
                    'label'                => 'Arrival (Kedatangan)',
                    'data'                 => $arrivalData,
                    'borderColor'          => '#4f46e5',
                    'backgroundColor'      => 'rgba(79, 70, 229, 0.12)',
                    'fill'                 => true,
                    'tension'              => 0.4,
                    'pointRadius'          => 0,
                    'pointHoverRadius'     => 5,
                    'borderWidth'          => 2.5,
                    'pointBackgroundColor' => '#4f46e5',
                    'pointBorderColor'     => '#ffffff',
                    'pointBorderWidth'     => 2,
                ],
                [
                    'label'                => 'Departure (Keberangkatan)',
                    'data'                 => $departureData,
                    'borderColor'          => '#10b981',
                    'backgroundColor'      => 'rgba(16, 185, 129, 0.12)',
                    'fill'                 => true,
                    'tension'              => 0.4,
                    'pointRadius'          => 0,
                    'pointHoverRadius'     => 5,
                    'borderWidth'          => 2.5,
                    'pointBackgroundColor' => '#10b981',
                    'pointBorderColor'     => '#ffffff',
                    'pointBorderWidth'     => 2,
                ],
            ],
            'labels' => $months,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'maintainAspectRatio' => false,
            'interaction' => [
                'mode'      => 'index',
                'intersect' => false,
            ],
            'plugins' => [
                'legend' => [
                    'display'  => true,
                    'position' => 'top',
                    'align'    => 'center',
                    'labels'   => [
                        'usePointStyle' => true,
                        'boxWidth'      => 8,
                        'boxHeight'     => 8,
                        'padding'       => 16,
                        'font'          => [
                            'size'   => 12,
                            'weight' => '500',
                        ],
                        'color' => '#64748b',
                    ],
                ],
                'tooltip' => [
                    'backgroundColor' => 'rgba(15, 23, 42, 0.95)',
                    'titleColor'      => '#f1f5f9',
                    'bodyColor'       => '#cbd5e1',
                    'borderColor'     => 'rgba(148, 163, 184, 0.2)',
                    'borderWidth'     => 1,
                    'padding'         => 12,
                    'cornerRadius'    => 8,
                    'displayColors'   => true,
                    'boxWidth'        => 8,
                    'boxHeight'       => 8,
                    'usePointStyle'   => true,
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks'       => [
                        'stepSize' => 50,
                        'font'     => ['size' => 11],
                        'color'    => '#94a3b8',
                    ],
                    'grid' => [
                        'color'     => 'rgba(148, 163, 184, 0.1)',
                        'drawBorder' => false,
                    ],
                ],
                'x' => [
                    'ticks' => [
                        'font'  => ['size' => 11],
                        'color' => '#94a3b8',
                    ],
                    'grid' => [
                        'display' => false,
                    ],
                ],
            ],
        ];
    }
}
