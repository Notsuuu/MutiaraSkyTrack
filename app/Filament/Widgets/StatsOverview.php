<?php

namespace App\Filament\Widgets;

use App\Models\FlightTraffic;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    use InteractsWithPageFilters;

    // Menentukan urutan prioritas paling atas
    protected static ?int $sort = 1;

    // Memaksa widget membentang penuh 2 kolom di grid Dashboard
    protected int | string | array $columnSpan = 'full';

    protected function getColumns(): int
    {
        return 4; // Layout 4 kartu per baris di dalam widget ini
    }

    protected function getStats(): array
    {
        // Ambil filter dari Dashboard
        $bulan = $this->filters['bulan'] ?? null;
        $tahun = $this->filters['tahun'] ?? null;
        $tgl   = $this->filters['tgl'] ?? null;

        $query = FlightTraffic::query();

        // Penerapan filter dinamis
        if ($tgl) {
            $query->whereDate('schedule_date', $tgl);
        } else {
            if ($tahun) {
                $query->whereYear('schedule_date', $tahun);
            }
            if ($bulan) {
                $query->whereMonth('schedule_date', $bulan);
            }
            // Jika tidak ada filter yang dipilih, gunakan default scope bulan ini
            if (!$tahun && !$bulan) {
                $query->thisMonth();
            }
        }

        $totalFlights    = (clone $query)->count();
        $totalPassengers = (clone $query)->get()->sum(fn (FlightTraffic $f) => $f->total_all_pax);
        $totalBaggage    = (clone $query)->sum('baggage_kg');
        $totalCargo      = (clone $query)->sum('cargo_kg');
        $delayCount      = (clone $query)->delayed()->count();
        $cancelCount     = (clone $query)->cancelled()->count();

        $delayPercent  = $totalFlights > 0 ? round(($delayCount / $totalFlights) * 100, 1) : 0;
        $cancelPercent = $totalFlights > 0 ? round(($cancelCount / $totalFlights) * 100, 1) : 0;

        return [
            Stat::make('TOTAL PENUMPANG', number_format($totalPassengers, 0, ',', '.'))
                ->description('Jiwa')
                ->descriptionIcon('heroicon-m-users')
                ->color('primary')
                ->chart($this->getDailyTrend('pax')),

            Stat::make('TOTAL BAGASI', number_format($totalBaggage, 0, ',', '.') . ' Kg')
                ->description('Kg')
                ->descriptionIcon('heroicon-m-briefcase')
                ->color('info')
                ->chart($this->getDailyTrend('baggage')),

            Stat::make('TOTAL KARGO', number_format($totalCargo, 0, ',', '.') . ' Kg')
                ->description('Kg')
                ->descriptionIcon('heroicon-m-archive-box')
                ->color('warning')
                ->chart($this->getDailyTrend('cargo')),

            Stat::make('TOTAL PENERBANGAN', number_format($totalFlights, 0, ',', '.'))
                ->description('Flight')
                ->descriptionIcon('heroicon-m-paper-airplane')
                ->color('success')
                ->chart($this->getDailyTrend('flights')),

            Stat::make('PENERBANGAN DELAY', number_format($delayCount, 0, ',', '.'))
                ->description("{$delayPercent}% dari total penerbangan")
                ->descriptionIcon('heroicon-m-clock')
                ->color($delayCount > 0 ? 'warning' : 'success'),

            Stat::make('PENERBANGAN CANCEL', number_format($cancelCount, 0, ',', '.'))
                ->description("{$cancelPercent}% dari total penerbangan")
                ->descriptionIcon('heroicon-m-x-circle')
                ->color($cancelCount > 0 ? 'danger' : 'success'),
        ];
    }

    /**
     * Ambil data tren 7 hari terakhir untuk sparkline chart pada kartu Stat.
     */
    private function getDailyTrend(string $metric): array
    {
        $days = collect(range(6, 0))->map(fn ($i) => now()->subDays($i)->toDateString());

        return $days->map(function ($date) use ($metric) {
            $query = FlightTraffic::whereDate('schedule_date', $date);

            return match ($metric) {
                'pax'     => (int) $query->get()->sum(fn (FlightTraffic $f) => $f->total_all_pax),
                'baggage' => (float) $query->sum('baggage_kg'),
                'cargo'   => (float) $query->sum('cargo_kg'),
                'flights' => $query->count(),
                default   => 0,
            };
        })->toArray();
    }
}
