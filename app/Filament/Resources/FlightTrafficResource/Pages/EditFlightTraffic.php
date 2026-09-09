<?php

namespace App\Filament\Resources\FlightTrafficResource\Pages;

use App\Filament\Resources\FlightTrafficResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFlightTraffic extends EditRecord
{
    protected static string $resource = FlightTrafficResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
