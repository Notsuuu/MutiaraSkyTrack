<?php

namespace App\Filament\Resources\FlightTrafficResource\Pages;

use App\Filament\Resources\FlightTrafficResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewFlightTraffic extends ViewRecord
{
    protected static string $resource = FlightTrafficResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
