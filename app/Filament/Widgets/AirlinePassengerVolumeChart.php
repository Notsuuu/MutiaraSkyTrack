<?php

namespace App\Filament\Widgets;

use App\Models\FlightTraffic;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class AirlinePassengerVolumeChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Volume Penumpang per Maskapai';

    protected int | string | array $columnSpan = [
        'default' => 1,
        'md'      => 1,
        'xl'      => 3,
    ];

    protected string $view = 'filament.widgets.animated-chart';

    protected function getData(): array
    {
        $tgl   = filled($this->filters['tgl'] ?? null)   ? $this->filters['tgl'] : null;
        $bulan = filled($this->filters['bulan'] ?? null) ? $this->filters['bulan'] : null;
        $tahun = filled($this->filters['tahun'] ?? null) ? $this->filters['tahun'] : '2026';

        $data = FlightTraffic::with('airline')
            ->when($tgl, fn ($q) => $q->whereDate('schedule_date', $tgl))
            ->when(! $tgl && $bulan, fn ($q) => $q->whereMonth('schedule_date', $bulan))
            ->when(! $tgl && $tahun, fn ($q) => $q->whereYear('schedule_date', $tahun))
            ->selectRaw('
                airline_id,
                SUM(COALESCE(pax_adult, 0) + COALESCE(pax_child, 0) + COALESCE(pax_infant, 0)) as total_penumpang
            ')
            ->groupBy('airline_id')
            ->orderByDesc('total_penumpang')
            ->limit(7)
            ->get();

        return [
            'datasets' => [
                [
                    'label'           => 'Jumlah Orang',
                    'data'            => $data->map(fn ($item) => (int) $item->total_penumpang)->toArray(),
                    'backgroundColor' => '#3b82f6',
                    'borderRadius'    => 6,
                    'maxBarThickness' => 48,
                ],
            ],
            'labels' => $data->map(fn ($item) => $item->airline?->brand_name ?? 'N/A')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'responsive'          => true,
            'maintainAspectRatio' => false,
            'animation' => [
                'duration' => 1000,
                'easing'   => 'easeOutQuart',
            ],
            'plugins' => [
                'legend' => ['display' => false],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks'       => ['font' => ['size' => 11]],
                    'grid'        => ['color' => 'rgba(0,0,0,0.05)'],
                ],
                'x' => [
                    'ticks' => [
                        'font'        => ['size' => 10],
                        'maxRotation' => 45,
                        'minRotation' => 45,
                    ],
                    'grid' => ['display' => false],
                ],
            ],
        ];
    }
}
