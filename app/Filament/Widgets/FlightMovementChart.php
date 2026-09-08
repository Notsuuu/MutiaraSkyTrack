<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use App\Models\FlightTraffic;

class FlightMovementChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Tren Pergerakan Penerbangan';
    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        $tahun = $this->filters['tahun'] ?? '2026';
        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

        $arrivalData = [];
        $departureData = [];

        for ($m = 1; $m <= 12; $m++) {
            $arrivalData[] = FlightTraffic::whereYear('schedule_date', $tahun)
                ->whereMonth('schedule_date', $m)
                ->where('movement', 'Arrival')
                ->count();

            $departureData[] = FlightTraffic::whereYear('schedule_date', $tahun)
                ->whereMonth('schedule_date', $m)
                ->where('movement', 'Departure')
                ->count();
        }

        return [
            'datasets' => [
                [
                    'label' => 'Arrival (Kedatangan)',
                    'data' => $arrivalData,
                    'borderColor' => '#6366f1',
                    'backgroundColor' => 'rgba(99, 102, 241, 0.15)',
                    'fill' => true,
                ],
                [
                    'label' => 'Departure (Keberangkatan)',
                    'data' => $departureData,
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.15)',
                    'fill' => true,
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
