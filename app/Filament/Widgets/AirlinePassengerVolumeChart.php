<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use App\Models\FlightTraffic;

class AirlinePassengerVolumeChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Volume Penumpang per Maskapai';
    protected int | string | array $columnSpan = 1;

    protected function getData(): array
    {
        $tahun = $this->filters['tahun'] ?? '2026';

        $data = FlightTraffic::with('airline')
            ->whereYear('schedule_date', $tahun)
            ->selectRaw('airline_id, SUM(pax_adult + pax_child + pax_infant) as total_pax')
            ->groupBy('airline_id')
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Jumlah Orang',
                    'data' => $data->pluck('total_pax')->toArray(),
                    'backgroundColor' => '#3b82f6',
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
