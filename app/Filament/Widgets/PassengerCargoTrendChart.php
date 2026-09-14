<?php

namespace App\Filament\Widgets;

use App\Models\FlightTraffic;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Facades\DB;

class PassengerCargoTrendChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Tren Perbandingan Penumpang & Kargo';

    protected int | string | array $columnSpan = [
        'default' => 1,
        'lg'      => 2,
    ];

    protected function getData(): array
    {
        $tahun  = $this->filters['tahun'] ?? '2026';
        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

        $paxData   = [];
        $cargoData = [];

        for ($m = 1; $m <= 12; $m++) {
            $paxData[] = (int) FlightTraffic::whereYear('schedule_date', $tahun)
                ->whereMonth('schedule_date', $m)
                ->sum(DB::raw('pax_adult + pax_child + pax_infant'));

            $cargoData[] = (float) FlightTraffic::whereYear('schedule_date', $tahun)
                ->whereMonth('schedule_date', $m)
                ->sum('cargo_kg');
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
            'maintainAspectRatio' => false,
            // ⚡ ANIMASI: garis tergambar dari kiri ke kanan
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
