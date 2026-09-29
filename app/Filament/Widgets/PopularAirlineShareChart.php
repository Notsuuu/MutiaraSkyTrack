<?php

namespace App\Filament\Widgets;

use App\Models\FlightTraffic;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class PopularAirlineShareChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Pangsa Pasar Maskapai Terpopuler';

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

        $records = FlightTraffic::with('airline')
            ->when($tgl, fn ($q) => $q->whereDate('schedule_date', $tgl))
            ->when(! $tgl && $bulan, fn ($q) => $q->whereMonth('schedule_date', $bulan))
            ->when(! $tgl && $tahun, fn ($q) => $q->whereYear('schedule_date', $tahun))
            ->selectRaw('
                airline_id,
                SUM(COALESCE(pax_adult, 0) + COALESCE(pax_child, 0) + COALESCE(pax_infant, 0)) as total_penumpang
            ')
            ->groupBy('airline_id')
            ->orderByDesc('total_penumpang')
            ->get();

        $topCount = 5;
        $topAirlines = $records->take($topCount);
        $otherPax = (int) $records->skip($topCount)->sum('total_penumpang');

        $labels = [];
        $values = [];

        foreach ($topAirlines as $item) {
            $pax = (int) $item->total_penumpang;
            if ($pax > 0) {
                $labels[] = $item->airline?->brand_name ?? 'N/A';
                $values[] = $pax;
            }
        }

        if ($otherPax > 0) {
            $labels[] = 'Lainnya';
            $values[] = $otherPax;
        }

        if (empty($values)) {
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

        $colors = [
            '#3b82f6',
            '#ec4899',
            '#06b6d4',
            '#f59e0b',
            '#8b5cf6',
            '#94a3b8',
        ];

        return [
            'datasets' => [
                [
                    'data'            => $values,
                    'backgroundColor' => array_slice($colors, 0, count($values)),
                    'borderWidth'     => 0,
                    'hoverOffset'     => 6,
                ],
            ],
            'labels' => $labels,
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
                        'padding'       => 12,
                        'font'          => ['size' => 11],
                    ],
                ],
            ],
        ];
    }
}
