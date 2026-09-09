<?php

namespace App\Filament\Resources\FlightTrafficResource\Pages;

use App\Filament\Resources\FlightTrafficResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFlightTraffic extends CreateRecord
{
    protected static string $resource = FlightTrafficResource::class;

    protected static ?string $title = 'Form Tambah Data Lalu Lintas Udara Manual';

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
