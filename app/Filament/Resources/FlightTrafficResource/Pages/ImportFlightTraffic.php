<?php

namespace App\Filament\Resources\FlightTrafficResource\Pages;

use App\Filament\Resources\FlightTrafficResource;
use Filament\Resources\Pages\Page;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Form;
use Filament\Notifications\Notification;

class ImportFlightTraffic extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = FlightTrafficResource::class;
    protected static string $view = 'filament.resources.flight-traffic-resource.pages.import-flight-traffic';
    protected static ?string $title = 'Import Excel - Data Lalu Lintas Udara';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                FileUpload::make('attachment')
                    ->label('Unggah Laporan Lalu Lintas Udara')
                    ->placeholder('Seret & letakkan file Excel di sini atau klik untuk memilih file')
                    ->acceptedFileTypes([
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/vnd.ms-excel',
                        'text/csv',
                    ])
                    ->directory('imports')
                    ->required(),
            ])
            ->statePath('data');
    }

    public function submit()
    {
        $formData = $this->form->getState();

        Notification::make()
            ->title('Proses Import Dimulai')
            ->body('File berhasil diunggah dan sedang diproses.')
            ->success()
            ->send();
    }
}
