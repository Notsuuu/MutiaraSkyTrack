<?php

namespace App\Filament\Widgets;

use App\Models\FlightTraffic;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class FlightActivityChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Kegiatan Penerbangan';

    protected int | string | array $columnSpan = [
        'default' => 1,
        'md'      => 1,
        'xl'      => 2,
    ];

    protected string $view = 'filament.widgets.animated-chart';

    protected function getData(): array
    {
        $tahun = $this->filters['tahun'] ?? '2026';

        $types  = ['Berjadwal', 'Extra Flight', 'Tidak Berjadwal', 'Bukan Niaga', 'Perintis', 'Haji', 'Militer', 'Lainnya'];
        $counts = [];

        foreach ($types as $type) {
            $counts[] = FlightTraffic::whereYear('schedule_date', $tahun)
                ->where('activity_type', $type)
                ->count();
        }

        return [
            'datasets' => [
                [
                    'data'            => $counts,
                    'backgroundColor' => ['#10b981', '#06b6d4', '#f59e0b', '#8b5cf6', '#ec4899', '#14b8a6', '#3b82f6', '#64748b'],
                    'borderWidth'     => 0,
                    'hoverOffset'     => 6,
                ],
            ],
            'labels' => $types,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'cutout' => '65%',
            'animation' => [
                'animateRotate' => true,
                'animateScale'  => true,
                'duration'      => 1600,
                'easing'        => 'easeOutQuart',
            ],
            'plugins' => [
                'legend' => [
                    'display'  => true,
                    'position' => 'bottom',
                    'labels'   => [
                        'usePointStyle' => true,
                        'boxWidth'      => 8,
                        'padding'       => 14,
                        'font'          => ['size' => 12],
                    ],
                ],
            ],
        ];
    }
}
