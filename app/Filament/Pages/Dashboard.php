<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Schema;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    // ⚠️ INI YANG SEBELUMNYA LUPA — paksa pakai view custom
    protected string $view = 'filament.pages.dashboard';

    protected static ?string $title = 'Ringkasan Lalu Lintas Udara';
    protected static ?string $navigationLabel = 'Ringkasan Lalu Lintas Udara';
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-home';

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
