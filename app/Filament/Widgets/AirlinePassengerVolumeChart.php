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
        'lg'      => 1,
    ];

    protected string $view = 'filament.widgets.animated-chart';

    protected function getData(): array
    {
        $tahun = $this->filters['tahun'] ?? '2026';

        $data = FlightTraffic::with('airline')
            ->whereYear('schedule_date', $tahun)
            ->selectRaw('airline_id, SUM(pax_adult + pax_child + pax_infant) as total_pax')
            ->groupBy('airline_id')
            ->orderByDesc('total_pax')
            ->limit(7)
            ->get();

        return [
            'datasets' => [
                [
                    'label'           => 'Jumlah Orang',
                    'data'            => $data->pluck('total_pax')->toArray(),
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
