<?php

namespace App\Filament\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Schema;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    // Kosongkan title properti — akan override via getTitle()
    protected static ?string $navigationLabel = 'Dashboard';
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-home';

    /**
     * Override getTitle() — lebih reliable daripada $title property di Dashboard.
     */
    public function getTitle(): string
    {
        return 'Ringkasan Lalu Lintas Udara';
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('bulan')
                    ->options([
                        '01' => 'Januari', '02' => 'Februari', '03' => 'Maret',
                        '04' => 'April',   '05' => 'Mei',      '06' => 'Juni',
                        '07' => 'Juli',    '08' => 'Agustus',  '09' => 'September',
                        '10' => 'Oktober', '11' => 'November', '12' => 'Desember',
                    ])
                    ->placeholder('Semua Bulan')
                    ->native(false),
                Select::make('tahun')
                    ->options([
                        '2024' => '2024',
                        '2025' => '2025',
                        '2026' => '2026',
                    ])
                    ->default('2026')
                    ->native(false),
                DatePicker::make('tgl')
                    ->label('TGL')
                    ->placeholder('mm / dd / yyyy'),
            ])
            ->columns(3);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportImage')
                ->label('Ekspor Dashboard ke Gambar')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('primary')
                ->extraAttributes(['onclick' => 'window.print()']),
        ];
    }

    public function getWidgets(): array
    {
        return [
            \App\Filament\Widgets\StatsOverview::class,
            \App\Filament\Widgets\FlightMovementChart::class,
            \App\Filament\Widgets\FlightCoverageChart::class,
            \App\Filament\Widgets\FlightActivityChart::class,
            \App\Filament\Widgets\AirlineFrequencyChart::class,
            \App\Filament\Widgets\AirlinePassengerVolumeChart::class,
            \App\Filament\Widgets\PassengerCargoTrendChart::class,
        ];
    }

    public function getColumns(): int | array
    {
        return [
            'default' => 1,
            'lg'      => 2,
        ];
    }
}
