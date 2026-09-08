<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use App\Models\FlightTraffic;
use Illuminate\Support\Facades\DB;

class PassengerCargoTrendChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Tren Perbandingan Penumpang & Kargo';
    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        $tahun = $this->filters['tahun'] ?? '2026';
        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

        $paxData = [];
        $cargoData = [];

        for ($m = 1; $m <= 12; $m++) {
            $paxData[] = FlightTraffic::whereYear('schedule_date', $tahun)
                ->whereMonth('schedule_date', $m)
                ->sum(DB::raw('pax_adult + pax_child + pax_infant'));

            $cargoData[] = FlightTraffic::whereYear('schedule_date', $tahun)
                ->whereMonth('schedule_date', $m)
                ->sum('cargo_kg');
        }

        return [
            'datasets' => [
                [
                    'label' => 'Penumpang (Orang)',
                    'data' => $paxData,
                    'borderColor' => '#3b82f6',
                    'tension' => 0.4,
                ],
                [
                    'label' => 'Kargo (Kg)',
                    'data' => $cargoData,
                    'borderColor' => '#f59e0b',
                    'tension' => 0.4,
                ],
            ],
            'labels' => $months,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
