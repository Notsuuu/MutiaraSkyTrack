<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use App\Models\FlightTraffic;

class FlightCoverageChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Kategori & Rute Penerbangan';
    protected int | string | array $columnSpan = 1;

    protected function getData(): array
    {
        $tahun = $this->filters['tahun'] ?? '2026';

        $domestik = FlightTraffic::whereYear('schedule_date', $tahun)->where('coverage', 'Domestik')->count();
        $internasional = FlightTraffic::whereYear('schedule_date', $tahun)->where('coverage', 'Internasional')->count();

        return [
            'datasets' => [
                [
                    'data' => [$domestik, $internasional],
                    'backgroundColor' => ['#6366f1', '#38bdf8'],
                ],
            ],
            'labels' => ['Domestik', 'Internasional'],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
