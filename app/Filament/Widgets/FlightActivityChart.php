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
        $tgl   = filled($this->filters['tgl'] ?? null)   ? $this->filters['tgl'] : null;
        $bulan = filled($this->filters['bulan'] ?? null) ? $this->filters['bulan'] : null;
        $tahun = filled($this->filters['tahun'] ?? null) ? $this->filters['tahun'] : '2026';

        $query = FlightTraffic::query()
            ->when($tgl, fn ($q) => $q->whereDate('schedule_date', $tgl))
            ->when(! $tgl && $bulan, fn ($q) => $q->whereMonth('schedule_date', $bulan))
            ->when(! $tgl && $tahun, fn ($q) => $q->whereYear('schedule_date', $tahun));

        $rawCounts = (clone $query)
            ->selectRaw('activity_type, count(*) as total')
            ->groupBy('activity_type')
            ->pluck('total', 'activity_type')
            ->toArray();

        $types  = ['Berjadwal', 'Extra Flight', 'Tidak Berjadwal', 'Bukan Niaga', 'Perintis', 'Haji', 'Militer', 'Lainnya'];
        $counts = [];

        foreach ($types as $type) {
            $counts[] = (int) ($rawCounts[$type] ?? 0);
        }

        if (array_sum($counts) === 0) {
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
