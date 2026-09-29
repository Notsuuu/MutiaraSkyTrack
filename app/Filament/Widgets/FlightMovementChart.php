<?php

namespace App\Filament\Widgets;

use App\Models\FlightTraffic;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class FlightMovementChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Tren Pergerakan Penerbangan';

    protected int | string | array $columnSpan = 'full';

    protected string $view = 'filament.widgets.animated-chart';

    protected function getData(): array
    {
        $tgl   = filled($this->filters['tgl'] ?? null)   ? $this->filters['tgl'] : null;
        $bulan = filled($this->filters['bulan'] ?? null) ? (int) $this->filters['bulan'] : null;
        $tahun = filled($this->filters['tahun'] ?? null) ? (int) $this->filters['tahun'] : 2026;

        $baseQuery = FlightTraffic::query()
            ->when($tgl, fn ($q) => $q->whereDate('schedule_date', $tgl))
            ->when(! $tgl && $bulan, fn ($q) => $q->whereMonth('schedule_date', $bulan))
            ->when(! $tgl && $tahun, fn ($q) => $q->whereYear('schedule_date', $tahun))
            ->whereIn('movement', ['Arrival', 'Departure']);

        // Jika filter BULAN aktif: Sumbu X dinamis per hari (Tgl 1 s.d. 28/30/31)
        if ($bulan && ! $tgl) {
            $daysInMonth = Carbon::create($tahun, $bulan, 1)->daysInMonth;
            $labels = [];
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $labels[] = (string) $d;
            }

            $raw = (clone $baseQuery)
                ->selectRaw('DAY(schedule_date) as hari, movement, COUNT(*) as total')
                ->groupBy('hari', 'movement')
                ->get();

            $arrivalData   = [];
            $departureData = [];

            for ($d = 1; $d <= $daysInMonth; $d++) {
                $dayRecords = $raw->where('hari', $d);
                $arrivalData[]   = (int) ($dayRecords->where('movement', 'Arrival')->first()->total ?? 0);
                $departureData[] = (int) ($dayRecords->where('movement', 'Departure')->first()->total ?? 0);
            }
        }
        // Jika filter TANGGAL aktif
        elseif ($tgl) {
            $labels = [Carbon::parse($tgl)->translatedFormat('d M Y')];
            $raw = (clone $baseQuery)
                ->selectRaw('movement, COUNT(*) as total')
                ->groupBy('movement')
                ->get();

            $arrivalData   = [(int) ($raw->where('movement', 'Arrival')->first()->total ?? 0)];
            $departureData = [(int) ($raw->where('movement', 'Departure')->first()->total ?? 0)];
        }
        // Default (Semua Bulan / Reset): Sumbu X 12 Bulan (Jan - Des)
        else {
            $labels = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

            $raw = (clone $baseQuery)
                ->selectRaw('MONTH(schedule_date) as bulan, movement, COUNT(*) as total')
                ->groupBy('bulan', 'movement')
                ->get();

            $arrivalData   = [];
            $departureData = [];

            for ($m = 1; $m <= 12; $m++) {
                $monthRecords = $raw->where('bulan', $m);
                $arrivalData[]   = (int) ($monthRecords->where('movement', 'Arrival')->first()->total ?? 0);
                $departureData[] = (int) ($monthRecords->where('movement', 'Departure')->first()->total ?? 0);
            }
        }

        return [
            'datasets' => [
                [
                    'label'                => 'Arrival (Kedatangan)',
                    'data'                 => $arrivalData,
                    'borderColor'          => '#4f46e5',
                    'backgroundColor'      => 'rgba(79, 70, 229, 0.12)',
                    'fill'                 => true,
                    'tension'              => 0.4,
                    'pointRadius'          => count($labels) > 20 ? 1.5 : 3,
                    'pointHoverRadius'     => 5,
                    'borderWidth'          => 2.5,
                    'pointBackgroundColor' => '#4f46e5',
                    'pointBorderColor'     => '#ffffff',
                    'pointBorderWidth'     => 2,
                ],
                [
                    'label'                => 'Departure (Keberangkatan)',
                    'data'                 => $departureData,
                    'borderColor'          => '#10b981',
                    'backgroundColor'      => 'rgba(16, 185, 129, 0.12)',
                    'fill'                 => true,
                    'tension'              => 0.4,
                    'pointRadius'          => count($labels) > 20 ? 1.5 : 3,
                    'pointHoverRadius'     => 5,
                    'borderWidth'          => 2.5,
                    'pointBackgroundColor' => '#10b981',
                    'pointBorderColor'     => '#ffffff',
                    'pointBorderWidth'     => 2,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'responsive'          => true,
            'maintainAspectRatio' => false,
            'animation' => [
                'duration' => 1200,
                'easing'   => 'easeOutQuart',
            ],
            'interaction' => [
                'mode'      => 'index',
                'intersect' => false,
            ],
            'plugins' => [
                'legend' => [
                    'display'  => true,
                    'position' => 'top',
                    'align'    => 'center',
                    'labels'   => [
                        'usePointStyle' => true,
                        'boxWidth'      => 8,
                        'boxHeight'     => 8,
                        'padding'       => 16,
                        'font'          => [
                            'size'   => 12,
                            'weight' => '500',
                        ],
                        'color' => '#64748b',
                    ],
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks'       => [
                        'font'  => ['size' => 11],
                        'color' => '#94a3b8',
                    ],
                    'grid' => [
                        'color' => 'rgba(148, 163, 184, 0.1)',
                    ],
                ],
                'x' => [
                    'ticks' => [
                        'font'  => ['size' => 11],
                        'color' => '#94a3b8',
                    ],
                    'grid' => [
                        'display' => false,
                    ],
                ],
            ],
        ];
    }
}
