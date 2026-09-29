<?php

namespace App\Filament\Widgets;

use App\Models\FlightTraffic;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class AirlineFrequencyChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Frekuensi Penerbangan per Maskapai';

    protected int | string | array $columnSpan = [
        'default' => 1,
        'md'      => 1,
        'xl'      => 3,
    ];

    protected string $view = 'filament.widgets.animated-chart';

    protected function getData(): array
    {
        $tahun = $this->filters['tahun'] ?? '2026';

        $data = FlightTraffic::with('airline')
            ->whereYear('schedule_date', $tahun)
            ->selectRaw('airline_id, count(*) as total')
            ->groupBy('airline_id')
            ->orderByDesc('total')
            ->limit(7)
            ->get();

        return [
            'datasets' => [
                [
                    'label'           => 'Jumlah Flight',
                    'data'            => $data->pluck('total')->toArray(),
                    'backgroundColor' => '#6366f1',
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
            'animation' => [
                'duration' => 1200,
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
