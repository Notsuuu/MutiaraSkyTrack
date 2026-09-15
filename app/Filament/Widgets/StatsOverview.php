<?php

namespace App\Filament\Widgets;

use App\Models\FlightTraffic;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;

class StatsOverview extends Widget
{
    use InteractsWithPageFilters;

    protected string $view = 'filament.widgets.stats-overview';

    protected static ?int $sort = 1;

    protected int | string | array $columnSpan = 'full';

    public function getStats(): array
    {
        $tgl   = $this->filters['tgl'] ?? null;
        $bulan = $this->filters['bulan'] ?? null;
        $tahun = $this->filters['tahun'] ?? null;

        $query = FlightTraffic::query()
            ->when($tgl, fn (Builder $q) => $q->whereDate('schedule_date', $tgl))
            ->when(! $tgl && $bulan, fn (Builder $q) => $q->whereMonth('schedule_date', $bulan))
            ->when(! $tgl && $tahun, fn (Builder $q) => $q->whereYear('schedule_date', $tahun))
            ->when(! $tgl && ! $bulan && ! $tahun, fn (Builder $q) => $q->thisYear());

        $totalPassengers = (clone $query)->get()->sum(fn (FlightTraffic $f) => $f->total_all_pax);
        $totalBaggage    = (float) (clone $query)->sum('baggage_kg');
        $totalCargo      = (float) (clone $query)->sum('cargo_kg');
        $totalFlights    = (clone $query)->count();
        $delayCount      = (clone $query)->delayed()->count();
        $cancelCount     = (clone $query)->cancelled()->count();

        $delayPercent  = $totalFlights > 0 ? ($delayCount / $totalFlights) * 100 : 0;
        $cancelPercent = $totalFlights > 0 ? ($cancelCount / $totalFlights) * 100 : 0;

        return [
            [
                'label'     => 'TOTAL PENUMPANG',
                'value'     => number_format($totalPassengers, 0, ',', '.'),
                'unit'      => 'Jiwa',
                'unit_icon' => 'heroicon-s-user-group',
                'icon'      => 'heroicon-s-user-group',
                'color'     => 'indigo',
            ],
            [
                'label'     => 'TOTAL BAGASI',
                'value'     => number_format($totalBaggage, 0, ',', '.'),
                'unit'      => 'Kg',
                'unit_icon' => 'heroicon-s-briefcase',
                'icon'      => 'heroicon-s-briefcase',
                'color'     => 'blue',
            ],
            [
                'label'     => 'TOTAL KARGO',
                'value'     => number_format($totalCargo, 0, ',', '.'),
                'unit'      => 'Kg',
                'unit_icon' => 'heroicon-s-archive-box',
                'icon'      => 'heroicon-s-archive-box',
                'color'     => 'amber',
            ],
            [
                'label'     => 'TOTAL PENERBANGAN',
                'value'     => number_format($totalFlights, 0, ',', '.'),
                'unit'      => 'Flight',
                'unit_icon' => 'heroicon-s-paper-airplane',
                'icon'      => 'heroicon-s-paper-airplane',
                'color'     => 'emerald',
            ],
            [
                'label'   => 'PENERBANGAN DELAY',
                'value'   => number_format($delayCount, 0, ',', '.'),
                'unit'    => null,
                'unit_icon' => null,
                'icon'    => 'heroicon-s-clock',
                'color'   => 'rose',
                'percent' => $delayPercent,
            ],
            [
                'label'   => 'PENERBANGAN CANCEL',
                'value'   => number_format($cancelCount, 0, ',', '.'),
                'unit'    => null,
                'unit_icon' => null,
                'icon'    => 'heroicon-s-x-circle',
                'color'   => 'slate',
                'percent' => $cancelPercent,
            ],
        ];
    }
}
