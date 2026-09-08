<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use App\Models\FlightTraffic;

class AirlineFrequencyChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Frekuensi Penerbangan per Maskapai';
    protected int | string | array $columnSpan = 1;

    protected function getData(): array
    {
        $tahun = $this->filters['tahun'] ?? '2026';

        $data = FlightTraffic::with('airline')
            ->whereYear('schedule_date', $tahun)
            ->selectRaw('airline_id, count(*) as total')
            ->groupBy('airline_id')
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Jumlah Flight',
                    'data' => $data->pluck('total')->toArray(),
                    'backgroundColor' => '#6366f1',
                    'borderRadius' => 4,
                ],
            ],
            'labels' => $data->map(fn ($item) => $item->airline?->brand_name ?? 'N/A')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
