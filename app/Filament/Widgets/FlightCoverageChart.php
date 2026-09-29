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
        'md'      => 1,
        'xl'      => 2,
    ];

    protected string $view = 'filament.widgets.animated-chart';

    protected function getData(): array
    {
        $tgl   = filled($this->filters['tgl'] ?? null)   ? $this->filters['tgl'] : null;
        $bulan = filled($this->filters['bulan'] ?? null) ? $this->filters['bulan'] : null;
        $tahun = filled($this->filters['tahun'] ?? null) ? $this->filters['tahun'] : '2026';

        $query = FlightTraffic::query()
            ->when($tgl, fn ($q) => $q->whereDate('schedule_date', $tgl))
            ->when(! $tgl && $bulan, fn ($q) => $q->whereMonth('schedule_date', $bulan))
            ->when(! $tgl && $tahun, fn ($q) => $q->whereYear('schedule_date', $tahun));

        $domestik      = (clone $query)->where('coverage', 'Domestik')->count();
        $internasional = (clone $query)->where('coverage', 'Internasional')->count();

        if ($domestik === 0 && $internasional === 0) {
            return [
                'datasets' => [
                    [
                        'data'            => [1],
                        'backgroundColor' => ['#e2e8f0'],
                        'borderWidth'     => 0,
                    ],
                ],
                'labels' => ['Belum Ada Data'],
            ];
        }

        return [
            'datasets' => [
                [
                    'data'            => [$domestik, $internasional],
                    'backgroundColor' => ['#6366f1', '#38bdf8'],
                    'borderWidth'     => 0,
                    'hoverOffset'     => 6,
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
            'responsive'          => true,
            'maintainAspectRatio' => false,
            'cutout'              => '65%',
            'animation' => [
                'animateRotate' => true,
                'animateScale'  => true,
                'duration'      => 1200,
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
