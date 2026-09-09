<?php

namespace App\Filament\Resources\FlightTrafficResource\Pages;

use App\Filament\Resources\FlightTrafficResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
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
            Actions\Action::make('importExcel')
                ->label('Import Excel')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('success')
                ->url(fn () => FlightTrafficResource::getUrl('import')),
        ];
    }

    public function getTabs(): array
    {
        return [
            'semua' => Tab::make('Semua Penerbangan'),
            'domestik' => Tab::make('Domestik')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('coverage', 'DOMESTIK')),
            'internasional' => Tab::make('Internasional')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('coverage', 'INTERNASIONAL')),
        ];
    }
}
