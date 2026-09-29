<?php

namespace App\Filament\Widgets;

use App\Models\FlightTraffic;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class PassengerCargoTrendChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Tren Perbandingan Penumpang & Kargo';

    protected int | string | array $columnSpan = 'full';

    protected string $view = 'filament.widgets.animated-chart';

    protected function getData(): array
    {
        $tahun  = $this->filters['tahun'] ?? '2026';
        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

        $monthlyData = FlightTraffic::whereYear('schedule_date', $tahun)
            ->selectRaw('
                MONTH(schedule_date) as bulan,
                SUM(COALESCE(pax_adult, 0) + COALESCE(pax_child, 0) + COALESCE(pax_infant, 0)) as total_penumpang,
                SUM(COALESCE(cargo_kg, 0)) as total_kargo
            ')
            ->groupBy('bulan')
            ->get()
            ->keyBy('bulan');

        $paxData   = [];
        $cargoData = [];

        for ($m = 1; $m <= 12; $m++) {
            $row = $monthlyData->get($m);
            $paxData[]   = (int) ($row->total_penumpang ?? 0);
            $cargoData[] = (float) ($row->total_kargo ?? 0);
        }

        return [
            'datasets' => [
                [
                    'label'           => 'Penumpang (Orang)',
                    'data'            => $paxData,
                    'borderColor'     => '#3b82f6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'fill'            => true,
                    'tension'         => 0.3,
                    'yAxisID'         => 'y',
                    'pointRadius'     => 3,
                    'borderWidth'     => 2,
                ],
                [
                    'label'           => 'Kargo (Kg)',
                    'data'            => $cargoData,
                    'borderColor'     => '#f59e0b',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.1)',
                    'fill'            => true,
                    'tension'         => 0.3,
                    'yAxisID'         => 'y1',
                    'pointRadius'     => 3,
                    'borderWidth'     => 2,
                ],
            ],
            'labels' => $months,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'animation' => [
                'duration' => 1500,
                'easing'   => 'easeOutQuart',
            ],
            'plugins' => [
                'legend' => [
                    'display'  => true,
                    'position' => 'top',
                    'labels'   => [
                        'usePointStyle' => true,
                        'boxWidth'      => 8,
                        'padding'       => 16,
                        'font'          => ['size' => 12],
                    ],
                ],
            ],
            'scales' => [
                'y' => [
                    'type'        => 'linear',
                    'display'     => true,
                    'position'    => 'left',
                    'beginAtZero' => true,
                    'title'       => [
                        'display' => true,
                        'text'    => 'Penumpang (Orang)',
                        'font'    => ['size' => 11],
                    ],
                    'grid' => ['color' => 'rgba(0,0,0,0.05)'],
                ],
                'y1' => [
                    'type'        => 'linear',
                    'display'     => true,
                    'position'    => 'right',
                    'beginAtZero' => true,
                    'title'       => [
                        'display' => true,
                        'text'    => 'Kargo (Kg)',
                        'font'    => ['size' => 11],
                    ],
                    'grid' => ['drawOnChartArea' => false],
                ],
                'x' => [
                    'ticks' => ['font' => ['size' => 11]],
                    'grid'  => ['display' => false],
                ],
            ],
        ];
    }
}
