<?php

namespace App\Filament\Widgets;

use App\Models\FlightTraffic;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class FlightCoverageChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Kategori & Rute Penerbangan';

    protected int | string | array $columnSpan = [
        'default' => 1,
        'lg'      => 1,
    ];

    protected function getData(): array
    {
        $tahun = $this->filters['tahun'] ?? '2026';

        $domestik      = FlightTraffic::whereYear('schedule_date', $tahun)->where('coverage', 'Domestik')->count();
        $internasional = FlightTraffic::whereYear('schedule_date', $tahun)->where('coverage', 'Internasional')->count();

        return [
            'datasets' => [
                [
                    'data'            => [$domestik, $internasional],
                    'backgroundColor' => ['#6366f1', '#38bdf8'],
                    'borderWidth'     => 0,
                ],
            ],
            'labels' => ['Domestik', 'Internasional'],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'maintainAspectRatio' => false,
            'cutout'              => '65%',
            // ⚡ ANIMASI: berputar + membesar dari 0
            'animation' => [
                'animateRotate' => true,
                'animateScale'  => true,
                'duration'      => 1400,
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
