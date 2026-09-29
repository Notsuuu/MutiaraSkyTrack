<?php

namespace App\Filament\Widgets;

use App\Models\FlightTraffic;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class StatsOverview extends Widget
{
    use InteractsWithPageFilters;

    protected string $view = 'filament.widgets.stats-overview';

    protected static ?int $sort = 1;

    protected int | string | array $columnSpan = 'full';

    public function getStats(): array
    {
        $tgl   = filled($this->filters['tgl'] ?? null)   ? $this->filters['tgl'] : null;
        $bulan = filled($this->filters['bulan'] ?? null) ? $this->filters['bulan'] : null;
        $tahun = filled($this->filters['tahun'] ?? null) ? $this->filters['tahun'] : '2026';

        $query = FlightTraffic::query()
            ->when($tgl, fn (Builder $q) => $q->whereDate('schedule_date', $tgl))
            ->when(! $tgl && $bulan, fn (Builder $q) => $q->whereMonth('schedule_date', $bulan))
            ->when(! $tgl && $tahun, fn (Builder $q) => $q->whereYear('schedule_date', $tahun));

        // Agregasi langsung di database (mencegah memory leak / exhausted RAM)
        $totalPassengers = (float) (clone $query)->sum(DB::raw('
            COALESCE(pax_adult, 0) + COALESCE(pax_child, 0) + COALESCE(pax_infant, 0) +
            COALESCE(transit_pax_adult, 0) + COALESCE(transit_pax_child, 0) + COALESCE(transit_pax_infant, 0)
        '));

        $totalBaggage    = (float) (clone $query)->sum('baggage_kg');
        $totalCargo      = (float) (clone $query)->sum('cargo_kg');
        $totalFlights    = (clone $query)->count();
        $delayCount      = (clone $query)->where('flight_status', 'Delay')->count();
        $cancelCount     = (clone $query)->where('flight_status', 'Cancel')->count();

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
                'label'     => 'PENERBANGAN DELAY',
                'value'     => number_format($delayCount, 0, ',', '.'),
                'unit'      => null,
                'unit_icon' => null,
                'icon'      => 'heroicon-s-clock',
                'color'     => 'rose',
                'percent'   => $delayPercent,
            ],
            [
                'label'     => 'PENERBANGAN CANCEL',
                'value'     => number_format($cancelCount, 0, ',', '.'),
                'unit'      => null,
                'unit_icon' => null,
                'icon'      => 'heroicon-s-x-circle',
                'color'     => 'slate',
                'percent'   => $cancelPercent,
            ],
        ];
    }
}
