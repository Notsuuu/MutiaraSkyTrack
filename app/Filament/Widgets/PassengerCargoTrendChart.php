<?php

namespace App\Filament\Widgets;

use App\Models\FlightTraffic;
use Carbon\Carbon;
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
        $tgl   = filled($this->filters['tgl'] ?? null)   ? $this->filters['tgl'] : null;
        $bulan = filled($this->filters['bulan'] ?? null) ? (int) $this->filters['bulan'] : null;
        $tahun = filled($this->filters['tahun'] ?? null) ? (int) $this->filters['tahun'] : 2026;

        $baseQuery = FlightTraffic::query()
            ->when($tgl, fn ($q) => $q->whereDate('schedule_date', $tgl))
            ->when(! $tgl && $bulan, fn ($q) => $q->whereMonth('schedule_date', $bulan))
            ->when(! $tgl && $tahun, fn ($q) => $q->whereYear('schedule_date', $tahun));

        // Jika filter Bulan aktif: Tampilkan data harian
        if ($bulan && ! $tgl) {
            $daysInMonth = Carbon::create($tahun, $bulan, 1)->daysInMonth;
            $labels = [];
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $labels[] = (string) $d;
            }

            $dailyData = (clone $baseQuery)
                ->selectRaw('
                    DAY(schedule_date) as hari,
                    SUM(COALESCE(pax_adult, 0) + COALESCE(pax_child, 0) + COALESCE(pax_infant, 0)) as total_penumpang,
                    SUM(COALESCE(cargo_kg, 0)) as total_kargo
                ')
                ->groupBy('hari')
                ->get()
                ->keyBy('hari');

            $paxData   = [];
            $cargoData = [];

            for ($d = 1; $d <= $daysInMonth; $d++) {
                $row = $dailyData->get($d);
                $paxData[]   = (int) ($row->total_penumpang ?? 0);
                $cargoData[] = (float) ($row->total_kargo ?? 0);
            }
        }
        // Jika filter Tanggal aktif
        elseif ($tgl) {
            $labels = [Carbon::parse($tgl)->translatedFormat('d M Y')];
            $single = (clone $baseQuery)
                ->selectRaw('
                    SUM(COALESCE(pax_adult, 0) + COALESCE(pax_child, 0) + COALESCE(pax_infant, 0)) as total_penumpang,
                    SUM(COALESCE(cargo_kg, 0)) as total_kargo
                ')
                ->first();

            $paxData   = [(int) ($single->total_penumpang ?? 0)];
            $cargoData = [(float) ($single->total_kargo ?? 0)];
        }
        // Default (Semua Bulan / Reset): Sumbu X 12 Bulan (Jan - Des)
        else {
            $labels = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

            $monthlyData = (clone $baseQuery)
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
                    'pointRadius'     => count($labels) > 20 ? 1.5 : 3,
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
                    'pointRadius'     => count($labels) > 20 ? 1.5 : 3,
                    'borderWidth'     => 2,
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
