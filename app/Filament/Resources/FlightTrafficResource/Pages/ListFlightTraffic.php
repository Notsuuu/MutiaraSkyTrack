<?php

namespace App\Filament\Resources\FlightTrafficResource\Pages;

use App\Filament\Resources\FlightTrafficResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListFlightTraffic extends ListRecords
{
    protected static string $resource = FlightTrafficResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Tambah Data Manual')
                ->icon('heroicon-o-plus'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'semua' => Tab::make('Semua Penerbangan'),

            'domestik' => Tab::make('Domestik')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('coverage', 'Domestik')),

            'internasional' => Tab::make('Internasional')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('coverage', 'Internasional')),
        ];
    }
}
