<?php

namespace App\Filament\Resources;

use App\Exports\FlightTrafficTemplateExport;
use App\Filament\Resources\FlightTrafficResource\Pages;
use App\Imports\FlightTrafficImport;
use App\Models\Airline;
use App\Models\FlightTraffic;
use Filament\Actions;
use Filament\Forms;
use Filament\Navigation\NavigationItem;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use UnitEnum;

class FlightTrafficResource extends Resource
{
    protected static ?string $model = FlightTraffic::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationLabel = 'Data Lalu Lintas';

    protected static string | UnitEnum | null $navigationGroup = 'Kelola Data';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Data Penerbangan';

    protected static ?string $pluralModelLabel = 'Data Penerbangan';

    protected static ?string $recordTitleAttribute = 'flight_number';

    public static function getNavigationItems(): array
    {
        return [
            NavigationItem::make('Data Penerbangan')
                ->group('Kelola Data')
                ->icon('heroicon-o-paper-airplane')
                ->activeIcon('heroicon-s-paper-airplane')
                ->url(static::getUrl('index'))
                ->isActiveWhen(fn (): bool => request()->routeIs(static::getRouteBaseName() . '.index'))
                ->sort(1),

            NavigationItem::make('Tambah Data Manual')
                ->group('Kelola Data')
                ->icon('heroicon-o-plus-circle')
                ->activeIcon('heroicon-s-plus-circle')
                ->url(static::getUrl('create'))
                ->isActiveWhen(fn (): bool => request()->routeIs(static::getRouteBaseName() . '.create'))
                ->sort(2),

            NavigationItem::make('Import Excel')
                ->group('Kelola Data')
                ->icon('heroicon-o-arrow-up-tray')
                ->activeIcon('heroicon-s-arrow-up-tray')
                ->url(static::getUrl('import'))
                ->isActiveWhen(fn (): bool => request()->routeIs(static::getRouteBaseName() . '.import'))
                ->sort(3),
        ];
    }

    public static function getStatusOptions(): array
    {
        return [
            'Ontime' => 'On Time / Realisasi',
            'Delay'  => 'Delay',
            'Cancel' => 'Cancel / Batal',
        ];
    }

    public static function getActivityTypeOptions(): array
    {
        return [
            'Berjadwal'       => 'Berjadwal (Scheduled)',
            'Tidak Berjadwal' => 'Tidak Berjadwal (Unscheduled)',
            'Extra Flight'    => 'Extra Flight',
            'Perintis'        => 'Perintis (Pioneer)',
            'Haji'            => 'Haji',
            'Militer'         => 'Militer',
            'Charter'         => 'Charter',
            'Kargo'           => 'Kargo (Cargo Only)',
        ];
    }

    private static function paxInput(string $name, string $label, ?string $helper = null): Forms\Components\TextInput
    {
        return Forms\Components\TextInput::make($name)
            ->label($label)
            ->helperText($helper)
            ->numeric()
            ->integer()
            ->minValue(0)
            ->default(0)
            ->suffix('org');
    }

    private static function kgInput(string $name, string $label, string $icon): Forms\Components\TextInput
    {
        return Forms\Components\TextInput::make($name)
            ->label($label)
            ->numeric()
            ->minValue(0)
            ->default(0)
            ->step(0.01)
            ->suffix('kg')
            ->prefixIcon($icon);
    }

    private static function airportInput(string $name, string $label, string $icon, string $placeholder): Forms\Components\TextInput
    {
        return Forms\Components\TextInput::make($name)
            ->label($label)
            ->required()
            ->maxLength(20)
            ->placeholder($placeholder)
            ->upperCase()
            ->prefixIcon($icon)
            ->helperText('3 huruf kode IATA, atau "LOCAL AREA" untuk rute non-standar (perintis/helikopter)');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Armada')
                    ->icon('heroicon-o-paper-airplane')
                    ->schema([
                        Forms\Components\Select::make('airline_id')
                            ->label('Maskapai')
                            ->relationship('airline', 'brand_name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->native(false)
                            ->getOptionLabelFromRecordUsing(
                                fn (Airline $record) => $record->icao_code
                                    ? "[{$record->icao_code}] {$record->brand_name}"
                                    : "{$record->brand_name}" . ($record->is_auto_generated ? ' (belum diverifikasi)' : '')
                            )
                            ->createOptionForm([
                                Forms\Components\TextInput::make('brand_name')
                                    ->label('Nama Brand / Merek')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('operator_name')
                                    ->label('Nama Operator (Legal)')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('icao_code')
                                    ->label('Kode ICAO')
                                    ->maxLength(4)
                                    ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                                    ->dehydrateStateUsing(fn (?string $state) => $state ? strtoupper($state) : null)
                                    ->nullable(),
                                Forms\Components\TextInput::make('iata_code')
                                    ->label('Kode IATA')
                                    ->maxLength(3)
                                    ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                                    ->dehydrateStateUsing(fn (?string $state) => $state ? strtoupper($state) : null)
                                    ->nullable(),
                            ])
                            ->columnSpanFull(),

                        Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('flight_number')
                                    ->label('Nomor Penerbangan')
                                    ->required()
                                    ->maxLength(10)
                                    ->placeholder('GA-102 atau 781')
                                    ->upperCase()
                                    ->prefixIcon('heroicon-o-hashtag'),

                                Forms\Components\TextInput::make('aircraft_type')
                                    ->label('Tipe Pesawat')
                                    ->maxLength(20)
                                    ->placeholder('B738')
                                    ->upperCase()
                                    ->prefixIcon('heroicon-o-paper-airplane'),

                                Forms\Components\TextInput::make('aircraft_registration')
                                    ->label('Registrasi Pesawat')
                                    ->maxLength(15)
                                    ->nullable()
                                    ->placeholder('PK-GFA')
                                    ->upperCase()
                                    ->prefixIcon('heroicon-o-tag'),

                                Forms\Components\TextInput::make('seat_capacity')
                                    ->label('Kapasitas Kursi')
                                    ->numeric()
                                    ->integer()
                                    ->minValue(0)
                                    ->maxValue(1000)
                                    ->default(0)
                                    ->suffix('kursi')
                                    ->prefixIcon('heroicon-o-users'),
                            ]),
                    ]),

                Section::make('Rute & Jadwal')
                    ->icon('heroicon-o-map-pin')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                self::airportInput('origin_iata', 'Bandara Asal', 'heroicon-o-arrow-right-start-on-rectangle', 'CGK atau LOCAL AREA'),
                                self::airportInput('destination_iata', 'Bandara Tujuan', 'heroicon-o-arrow-left-end-on-rectangle', 'PLW atau LOCAL AREA'),
                            ]),

                        Grid::make(2)
                            ->schema([
                                Fieldset::make('Waktu Rencana (Schedule)')
                                    ->schema([
                                        Forms\Components\DatePicker::make('schedule_date')
                                            ->label('Tanggal Rencana')
                                            ->required()
                                            ->native(false)
                                            ->displayFormat('d/m/Y')
                                            ->maxDate(now()->addYears(1))
                                            ->prefixIcon('heroicon-o-calendar'),

                                        Forms\Components\TimePicker::make('schedule_time')
                                            ->label('Jam Rencana (STD/STA)')
                                            ->required()
                                            ->seconds(false)
                                            ->prefixIcon('heroicon-o-clock'),
                                    ])
                                    ->columns(1),

                                Fieldset::make('Waktu Realisasi (Actual)')
                                    ->schema([
                                        Forms\Components\DatePicker::make('actual_date')
                                            ->label('Tanggal Realisasi')
                                            ->nullable()
                                            ->native(false)
                                            ->displayFormat('d/m/Y')
                                            ->prefixIcon('heroicon-o-calendar-days'),

                                        Forms\Components\TimePicker::make('actual_time')
                                            ->label('Jam Realisasi (ATD/ATA)')
                                            ->nullable()
                                            ->seconds(false)
                                            ->prefixIcon('heroicon-o-clock'),
                                    ])
                                    ->columns(1),
                            ]),
                    ]),

                Section::make('Klasifikasi & Status')
                    ->icon('heroicon-o-tag')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Forms\Components\ToggleButtons::make('movement')
                                    ->label('Pergerakan')
                                    ->options(['Arrival' => 'Arrival (Datang)', 'Departure' => 'Departure (Berangkat)'])
                                    ->colors(['Arrival' => 'info', 'Departure' => 'warning'])
                                    ->icons(['Arrival' => 'heroicon-o-arrow-down-tray', 'Departure' => 'heroicon-o-arrow-up-tray'])
                                    ->inline()
                                    ->required()
                                    ->default('Departure'),

                                Forms\Components\ToggleButtons::make('coverage')
                                    ->label('Cakupan')
                                    ->options(['Domestik' => 'Domestik', 'Internasional' => 'Internasional'])
                                    ->colors(['Domestik' => 'info', 'Internasional' => 'warning'])
                                    ->inline()
                                    ->required()
                                    ->default('Domestik'),
                            ]),

                        Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('activity_type')
                                    ->label('Jenis Kegiatan')
                                    ->options(self::getActivityTypeOptions())
                                    ->required()
                                    ->native(false)
                                    ->default('Berjadwal'),

                                Forms\Components\ToggleButtons::make('flight_status')
                                    ->label('Status Penerbangan')
                                    ->options(self::getStatusOptions())
                                    ->colors(['Ontime' => 'success', 'Delay' => 'warning', 'Cancel' => 'danger'])
                                    ->icons([
                                        'Ontime' => 'heroicon-o-check-circle',
                                        'Delay'  => 'heroicon-o-clock',
                                        'Cancel' => 'heroicon-o-x-circle',
                                    ])
                                    ->inline()
                                    ->required()
                                    ->live()
                                    ->default('Ontime'),
                            ]),

                        Fieldset::make('Informasi Delay')
                            ->schema([
                                Forms\Components\TextInput::make('delay_category')
                                    ->label('Kategori Delay')
                                    ->maxLength(150)
                                    ->placeholder('Contoh: Manajemen Airlines, Cuaca, Teknis')
                                    ->nullable(),
                                Forms\Components\Textarea::make('delay_reason')
                                    ->label('Keterangan Delay')
                                    ->rows(2)
                                    ->nullable()
                                    ->placeholder('Detail penyebab keterlambatan...'),
                            ])
                            ->columns(2)
                            ->columnSpanFull()
                            ->visible(fn (Get $get) => $get('flight_status') === 'Delay'),
                    ]),

                Section::make('Payload (Muatan)')
                    ->icon('heroicon-o-scale')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Section::make('Penumpang Utama')
                                    ->schema([
                                        self::paxInput('pax_adult', 'Dewasa & Remaja (≥12 thn)', 'Termasuk usia remaja 12–17 tahun'),
                                        self::paxInput('pax_child', 'Anak-Anak (2–11 thn)', 'Anak usia 2 sampai 11 tahun'),
                                        self::paxInput('pax_infant', 'Bayi (< 2 thn)', 'Bayi usia di bawah 2 tahun'),
                                    ])
                                    ->columnSpan(1),

                                Section::make('Penumpang Transit')
                                    ->schema([
                                        self::paxInput('transit_pax_adult', 'Dewasa & Remaja (Transit)'),
                                        self::paxInput('transit_pax_child', 'Anak-Anak (Transit)'),
                                        self::paxInput('transit_pax_infant', 'Bayi (Transit)'),
                                    ])
                                    ->columnSpan(1),

                                Section::make('Muatan Logistik')
                                    ->schema([
                                        self::kgInput('baggage_kg', 'Bagasi', 'heroicon-o-briefcase'),
                                        self::kgInput('cargo_kg', 'Kargo', 'heroicon-o-archive-box'),
                                        self::kgInput('mail_kg', 'Pos / Mail', 'heroicon-o-envelope'),
                                    ])
                                    ->columnSpan(1),
                            ]),

                        Forms\Components\Textarea::make('remarks')
                            ->label('Keterangan Tambahan')
                            ->nullable()
                            ->maxLength(2000)
                            ->rows(2)
                            ->placeholder('Informasi tambahan umum, tidak terbatas pada penyebab delay.')
                            ->columnSpanFull(),
                    ]),
            ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('schedule_date')
                    ->label('Tgl Rencana')
                    ->date('d/m/Y')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('actual_date')
                    ->label('Tgl Realisasi')
                    ->date('d/m/Y')
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('schedule_time')
                    ->label('Jam')
                    ->alignCenter()
                    ->formatStateUsing(fn ($state) => substr($state, 0, 5)),

                Tables\Columns\TextColumn::make('actual_time')
                    ->label('Jam Realisasi')
                    ->alignCenter()
                    ->formatStateUsing(fn ($state) => $state ? substr($state, 0, 5) : '—')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('airline.brand_name')
                    ->label('Maskapai')
                    ->searchable()
                    ->badge()
                    ->color('primary')
                    ->weight('bold')
                    ->description(fn (FlightTraffic $record) => $record->airline?->icao_code ? null : '⚠ belum ada kode ICAO'),

                Tables\Columns\TextColumn::make('flight_number')
                    ->label('No. Penerbangan')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold'),

                Tables\Columns\TextColumn::make('aircraft_type')
                    ->label('Tipe Pesawat')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—')
                    ->default('—')
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('aircraft_registration')
                    ->label('Registrasi')
                    ->placeholder('—')
                    ->default('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('flight_status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'Ontime' => 'success',
                        'Delay'  => 'warning',
                        'Cancel' => 'danger',
                        default  => 'gray',
                    })
                    ->icon(fn (string $state) => match ($state) {
                        'Ontime' => 'heroicon-o-check-circle',
                        'Delay'  => 'heroicon-o-clock',
                        'Cancel' => 'heroicon-o-x-circle',
                        default  => null,
                    }),

                Tables\Columns\IconColumn::make('has_delay_info')
                    ->label('Info Delay')
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->tooltip(fn (FlightTraffic $record) => $record->has_delay_info
                        ? trim("{$record->delay_category}: {$record->delay_reason}", ': ')
                        : null)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('movement')
                    ->label('Pergerakan')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'Arrival'   => 'info',
                        'Departure' => 'warning',
                        default     => 'gray',
                    })
                    ->icon(fn (string $state) => match ($state) {
                        'Arrival'   => 'heroicon-o-arrow-down-tray',
                        'Departure' => 'heroicon-o-arrow-up-tray',
                        default     => null,
                    })
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'Arrival'   => 'ARR',
                        'Departure' => 'DEP',
                        default     => $state,
                    }),

                Tables\Columns\TextColumn::make('origin_iata')
                    ->label('Asal')
                    ->badge()
                    ->color('gray')
                    ->searchable(),

                Tables\Columns\TextColumn::make('destination_iata')
                    ->label('Tujuan')
                    ->badge()
                    ->color('gray')
                    ->searchable(),

                Tables\Columns\TextColumn::make('coverage')
                    ->label('Cakupan')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'Domestik'      => 'info',
                        'Internasional' => 'warning',
                        default         => 'gray',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('activity_type')
                    ->label('Kegiatan')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'Berjadwal'       => 'success',
                        'Tidak Berjadwal' => 'gray',
                        'Extra Flight'    => 'info',
                        'Perintis'        => 'purple',
                        'Haji'            => 'warning',
                        'Militer'         => 'danger',
                        'Charter'         => 'primary',
                        'Kargo'           => 'slate',
                        default           => 'gray',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('pax_adult')
                    ->label('Dewasa & Remaja (≥12th)')
                    ->tooltip('Penumpang usia 12 tahun ke atas (termasuk kategori remaja)')
                    ->numeric()
                    ->sortable()
                    ->alignCenter()
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('pax_child')
                    ->label('Anak (2-11th)')
                    ->tooltip('Penumpang anak-anak usia 2 sampai 11 tahun')
                    ->numeric()
                    ->sortable()
                    ->alignCenter()
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'info' : 'gray')
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('pax_infant')
                    ->label('Bayi (<2th)')
                    ->tooltip('Penumpang bayi usia di bawah 2 tahun')
                    ->numeric()
                    ->sortable()
                    ->alignCenter()
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'warning' : 'gray')
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('total_pax')
                    ->label('Total PAX')
                    ->state(fn (FlightTraffic $record): int =>
                        (int) ($record->pax_adult ?? 0) + (int) ($record->pax_child ?? 0) + (int) ($record->pax_infant ?? 0)
                    )
                    ->numeric()
                    ->alignCenter()
                    ->badge()
                    ->color('success')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('baggage_kg')
                    ->label('Bagasi (kg)')
                    ->numeric(decimalPlaces: 2)
                    ->alignRight()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('cargo_kg')
                    ->label('Kargo (kg)')
                    ->numeric(decimalPlaces: 2)
                    ->alignRight()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('mail_kg')
                    ->label('Pos (kg)')
                    ->numeric(decimalPlaces: 2)
                    ->alignRight()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('seat_capacity')
                    ->label('Seat')
                    ->numeric()
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Diinput Oleh')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('schedule_date', 'desc')
            ->filters([
                SelectFilter::make('pax_demographic')
                    ->label('Kategori Penumpang')
                    ->placeholder('Semua Kategori Penumpang')
                    ->native(false)
                    ->options([
                        'with_children'  => 'Membawa Anak-Anak (2-11 thn)',
                        'with_infants'   => 'Membawa Bayi (< 2 thn)',
                        'adults_only'    => 'Hanya Dewasa & Remaja (Tanpa Anak & Bayi)',
                        'has_passengers' => 'Penerbangan Berpenumpang (PAX > 0)',
                        'no_passengers'  => 'Penerbangan Kosong / Kargo Saja (PAX = 0)',
                    ])
                    ->query(function (Builder $query, array $data) {
                        return match ($data['value'] ?? null) {
                            'with_children'  => $query->where('pax_child', '>', 0),
                            'with_infants'   => $query->where('pax_infant', '>', 0),
                            'adults_only'    => $query->where('pax_adult', '>', 0)
                                                      ->where(fn ($q) => $q->whereNull('pax_child')->orWhere('pax_child', 0))
                                                      ->where(fn ($q) => $q->whereNull('pax_infant')->orWhere('pax_infant', 0)),
                            'has_passengers' => $query->whereRaw('(COALESCE(pax_adult, 0) + COALESCE(pax_child, 0) + COALESCE(pax_infant, 0)) > 0'),
                            'no_passengers'  => $query->whereRaw('(COALESCE(pax_adult, 0) + COALESCE(pax_child, 0) + COALESCE(pax_infant, 0)) = 0'),
                            default          => $query,
                        };
                    }),

                Filter::make('schedule_date')
                    ->label('Tanggal Penerbangan')
                    ->schema([
                        Forms\Components\DatePicker::make('date')
                            ->label('Pilih Tanggal')
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['date'], fn ($q, $date) => $q->whereDate('schedule_date', $date))
                    ),

                SelectFilter::make('month')
                    ->label('Bulan')
                    ->placeholder('Semua Bulan')
                    ->native(false)
                    ->options([
                        '1'  => 'Januari', '2'  => 'Februari', '3'  => 'Maret',
                        '4'  => 'April',   '5'  => 'Mei',      '6'  => 'Juni',
                        '7'  => 'Juli',    '8'  => 'Agustus',  '9'  => 'September',
                        '10' => 'Oktober', '11' => 'November', '12' => 'Desember',
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['value'], fn ($q, $m) => $q->whereMonth('schedule_date', $m))
                    ),

                SelectFilter::make('year')
                    ->label('Tahun')
                    ->placeholder('Semua Tahun')
                    ->native(false)
                    ->options(fn () => array_combine(
                        range(date('Y'), date('Y') - 5),
                        range(date('Y'), date('Y') - 5)
                    ))
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['value'], fn ($q, $y) => $q->whereYear('schedule_date', $y))
                    ),

                SelectFilter::make('airline_id')
                    ->label('Maskapai')
                    ->placeholder('Semua Maskapai')
                    ->native(false)
                    ->relationship('airline', 'brand_name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('movement')
                    ->label('Pergerakan')
                    ->placeholder('Semua Pergerakan')
                    ->native(false)
                    ->options([
                        'Arrival'   => 'Arrival (Datang)',
                        'Departure' => 'Departure (Berangkat)',
                    ]),

                SelectFilter::make('origin_iata')
                    ->label('Bandara Asal')
                    ->placeholder('Semua Asal')
                    ->native(false)
                    ->searchable()
                    ->options(fn () => FlightTraffic::query()
                        ->whereNotNull('origin_iata')
                        ->pluck('origin_iata', 'origin_iata')
                        ->toArray()
                    ),

                SelectFilter::make('destination_iata')
                    ->label('Bandara Tujuan')
                    ->placeholder('Semua Tujuan')
                    ->native(false)
                    ->searchable()
                    ->options(fn () => FlightTraffic::query()
                        ->whereNotNull('destination_iata')
                        ->pluck('destination_iata', 'destination_iata')
                        ->toArray()
                    ),
            ])
            ->recordActions([
                Actions\ActionGroup::make([
                    Actions\ViewAction::make()->label('Lihat'),
                    Actions\EditAction::make()->label('Edit'),
                    Actions\DeleteAction::make()->label('Hapus'),
                ]),
            ])
            ->headerActions([
                // ── TOMBOL SINKRONISASI GOOGLE SHEETS ──────────────────────────────
                Actions\Action::make('sync_sheets')
                    ->label('Sync Google Sheets')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->tooltip('Sinkronkan data penerbangan terbaru langsung dari spreadsheet online instansi')
                    ->requiresConfirmation()
                    ->modalIcon('heroicon-o-arrow-path')
                    ->modalIconColor('info')
                    ->modalHeading('Sinkronisasi Google Sheets')
                    ->modalDescription('Sistem akan membaca spreadsheet online UPBU Mutiara Sis Al-Jufri Palu dan memperbarui database secara real-time. Proses ini memerlukan waktu beberapa detik.')
                    ->modalSubmitActionLabel('⚡ Mulai Sinkronisasi')
                    ->modalCancelActionLabel('Batal')
                    ->modalSubmitAction(fn (Actions\Action $action) => $action
                        ->extraAttributes([
                            'data-sync-submit' => 'true',
                            'x-on:click' => '
                                if (window.SkyTrackToast) {
                                    window.SkyTrackToast.dismissActiveModal();
                                    window.SkyTrackToast.showLoading();
                                }
                            ',
                        ])
                    )
                    ->action(function ($livewire) {
                        set_time_limit(300);

                        try {
                            Artisan::call('flight:sync');
                            $output = Artisan::output();

                            $summary = 'Data berhasil disinkronkan ke database.';
                            if (preg_match('/Created:\s*(\d+),\s*Updated:\s*(\d+),\s*Skipped:\s*(\d+)/i', $output, $matches)) {
                                $created = number_format((int) $matches[1], 0, ',', '.');
                                $updated = number_format((int) $matches[2], 0, ',', '.');
                                $skipped = number_format((int) $matches[3], 0, ',', '.');

                                $summary = "✨ {$created} Data Baru | 🔄 {$updated} Diperbarui | ⏭️ {$skipped} Dilewati";
                            }

                            // Kirim satu event resmi ke toast.js
                            $livewire->dispatch('sk-sync-result', [
                                'type'    => 'success',
                                'title'   => 'Sinkronisasi Berhasil!',
                                'message' => $summary,
                            ]);
                        } catch (\Throwable $e) {
                            $livewire->dispatch('sk-sync-result', [
                                'type'    => 'danger',
                                'title'   => 'Gagal Sinkronisasi',
                                'message' => 'Terjadi kendala saat membaca Google Sheets: ' . $e->getMessage(),
                            ]);
                        }
                    }),

                Actions\Action::make('download_template')
                    ->label('Unduh Template')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->action(fn () => Excel::download(
                        new FlightTrafficTemplateExport(),
                        'template-import-flight-traffic.xlsx'
                    )),

                Actions\Action::make('import_excel')
                    ->label('Import Excel')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('success')
                    ->url(fn () => static::getUrl('import')),
            ])
            ->toolbarActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make()->label('Hapus Terpilih'),
                ]),
            ])
            ->striped()
            ->paginated([25, 50, 100, 'all'])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading('Belum ada data penerbangan')
            ->emptyStateDescription('Tambahkan data secara manual, import dari file Excel, atau gunakan tombol Sync Google Sheets.')
            ->emptyStateIcon('heroicon-o-chart-bar-square');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListFlightTraffic::route('/'),
            'create' => Pages\CreateFlightTraffic::route('/create'),
            'import' => Pages\ImportFlightTraffic::route('/import'),
            'view'   => Pages\ViewFlightTraffic::route('/{record}'),
            'edit'   => Pages\EditFlightTraffic::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['airline', 'creator']);
    }
}
