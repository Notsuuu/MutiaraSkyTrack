<?php

namespace App\Filament\Widgets;

use App\Models\FlightTraffic;
use Filament\Widgets\Widget;

class StatsOverview extends Widget
{
    protected string $view = 'filament.widgets.stats-overview';

    protected static ?int $sort = 1;

    protected int | string | array $columnSpan = 'full';

    public function getStats(): array
    {
        $query = FlightTraffic::query()->thisYear();

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
                'label'       => 'TOTAL PENUMPANG',
                'value'       => number_format($totalPassengers, 0, ',', '.'),
                'unit'        => 'Jiwa',
                'unit_icon'   => 'heroicon-m-user-group',
                'icon'        => 'heroicon-o-user-group',
                'color'       => 'indigo',
            ],
            [
                'label'       => 'TOTAL BAGASI',
                'value'       => number_format($totalBaggage, 0, ',', '.'),
                'unit'        => 'Kg',
                'unit_icon'   => 'heroicon-m-briefcase',
                'icon'        => 'heroicon-o-briefcase',
                'color'       => 'blue',
            ],
            [
                'label'       => 'TOTAL KARGO',
                'value'       => number_format($totalCargo, 0, ',', '.'),
                'unit'        => 'Kg',
                'unit_icon'   => 'heroicon-m-archive-box',
                'icon'        => 'heroicon-o-archive-box',
                'color'       => 'amber',
            ],
            [
                'label'       => 'TOTAL PENERBANGAN',
                'value'       => number_format($totalFlights, 0, ',', '.'),
                'unit'        => 'Flight',
                'unit_icon'   => 'heroicon-m-paper-airplane',
                'icon'        => 'heroicon-o-paper-airplane',
                'color'       => 'emerald',
            ],
            [
                'label'       => 'PENERBANGAN DELAY',
                'value'       => number_format($delayCount, 0, ',', '.'),
                'unit'        => null,
                'unit_icon'   => null,
                'icon'        => 'heroicon-o-clock',
                'color'       => 'rose',
                'percent'     => $delayPercent,
            ],
            [
                'label'       => 'PENERBANGAN CANCEL',
                'value'       => number_format($cancelCount, 0, ',', '.'),
                'unit'        => null,
                'unit_icon'   => null,
                'icon'        => 'heroicon-o-x-circle',
                'color'       => 'slate',
                'percent'     => $cancelPercent,
            ],
        ];
    }
}
