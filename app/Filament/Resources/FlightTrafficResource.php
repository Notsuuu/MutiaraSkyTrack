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
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
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

    // ──────────────────────────────────────
    // NAVIGATION
    // ──────────────────────────────────────

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

    // ──────────────────────────────────────
    // REUSABLE OPTIONS & HELPERS
    // ──────────────────────────────────────

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

    private static function paxInput(string $name, string $label): Forms\Components\TextInput
    {
        return Forms\Components\TextInput::make($name)
            ->label($label)
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

    // ──────────────────────────────────────
    // FORM SCHEMA
    // ──────────────────────────────────────

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // ── Section 1: Jadwal & Identitas ─────────────────────────────────
                Section::make('Jadwal & Identitas Penerbangan')
                    ->description('Informasi waktu rencana dan realisasi penerbangan.')
                    ->icon('heroicon-o-calendar-days')
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
                            ->columnSpan(2),

                        Forms\Components\TextInput::make('flight_number')
                            ->label('Nomor Penerbangan')
                            ->required()
                            ->maxLength(10)
                            ->placeholder('GA-102 atau 781')
                            ->upperCase()
                            ->prefixIcon('heroicon-o-hashtag'),

                        Forms\Components\TextInput::make('aircraft_type')
                            ->label('Tipe Pesawat')
                            ->required()
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
                    ->columns(4),

                // ── Section 2: Klasifikasi & Rute ─────────────────────────────────
                Section::make('Klasifikasi & Rute')
                    ->description('Status penerbangan, jenis kegiatan, dan informasi rute.')
                    ->icon('heroicon-o-map-pin')
                    ->schema([
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

                        Forms\Components\ToggleButtons::make('movement')
                            ->label('Pergerakan')
                            ->options(['Arrival' => 'Arrival (Datang)', 'Departure' => 'Departure (Berangkat)'])
                            ->colors(['Arrival' => 'info', 'Departure' => 'warning'])
                            ->icons(['Arrival' => 'heroicon-o-arrow-down-tray', 'Departure' => 'heroicon-o-arrow-up-tray'])
                            ->inline()
                            ->required()
                            ->default('Departure'),

                        Forms\Components\Select::make('activity_type')
                            ->label('Jenis Kegiatan')
                            ->options(self::getActivityTypeOptions())
                            ->required()
                            ->native(false)
                            ->default('Berjadwal'),

                        Forms\Components\ToggleButtons::make('coverage')
                            ->label('Cakupan')
                            ->options(['Domestik' => 'Domestik', 'Internasional' => 'Internasional'])
                            ->colors(['Domestik' => 'info', 'Internasional' => 'warning'])
                            ->inline()
                            ->required()
                            ->default('Domestik'),

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

                        self::airportInput('origin_iata', 'Bandara Asal', 'heroicon-o-arrow-right-start-on-rectangle', 'CGK atau LOCAL AREA'),
                        self::airportInput('destination_iata', 'Bandara Tujuan', 'heroicon-o-arrow-left-end-on-rectangle', 'PLW atau LOCAL AREA'),
                    ])
                    ->columns(2),

                // ── Section 3: Manifest Penumpang ─────────────────────────────────
                Section::make('Manifest Penumpang')
                    ->description('Data penumpang utama dan transit.')
                    ->icon('heroicon-o-user-group')
                    ->schema([
                        Fieldset::make('Penumpang Utama')
                            ->schema([
                                self::paxInput('pax_adult', 'Dewasa'),
                                self::paxInput('pax_child', 'Anak-Anak'),
                                self::paxInput('pax_infant', 'Bayi (Infant)'),
                            ])->columns(3),

                        Fieldset::make('Penumpang Transit')
                            ->schema([
                                self::paxInput('transit_pax_adult', 'Dewasa (Transit)'),
                                self::paxInput('transit_pax_child', 'Anak-Anak (Transit)'),
                                self::paxInput('transit_pax_infant', 'Bayi (Transit)'),
                            ])->columns(3),
                    ]),

                // ── Section 4: Muatan Logistik ────────────────────────────────────
                Section::make('Muatan Logistik')
                    ->description('Data berat muatan dalam satuan kilogram.')
                    ->icon('heroicon-o-scale')
                    ->schema([
                        self::kgInput('baggage_kg', 'Bagasi', 'heroicon-o-briefcase'),
                        self::kgInput('cargo_kg', 'Kargo', 'heroicon-o-archive-box'),
                        self::kgInput('mail_kg', 'Pos / Mail', 'heroicon-o-envelope'),
                    ])->columns(3),

                // ── Section 5: Keterangan ─────────────────────────────────────────
                Section::make('Keterangan Tambahan')
                    ->schema([
                        Forms\Components\Textarea::make('remarks')
                            ->label('Keterangan')
                            ->nullable()
                            ->maxLength(2000)
                            ->rows(3)
                            ->placeholder('Informasi tambahan umum, tidak terbatas pada penyebab delay.'),
                    ])
                    ->collapsed(),
            ]);
    }

    // ──────────────────────────────────────
    // TABLE
    // ──────────────────────────────────────

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
                    ->label('Jam Rencana')
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
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('aircraft_registration')
                    ->label('Registrasi')
                    ->placeholder('—')
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
                    ->label('Dewasa')
                    ->numeric()
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('pax_child')
                    ->label('Anak')
                    ->numeric()
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('pax_infant')
                    ->label('Bayi')
                    ->numeric()
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),

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
                // 1. Filter Tanggal
                Filter::make('schedule_date')
                    ->label('')
                    ->schema([
                        Forms\Components\DatePicker::make('date')
                            ->hiddenLabel()
                            ->placeholder('mm / dd / yyyy')
                            ->native(false),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['date'], fn ($q, $date) => $q->whereDate('schedule_date', $date))
                    ),

                // 2. Filter Bulan
                SelectFilter::make('month')
                    ->label('')
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

                // 3. Filter Tahun
                SelectFilter::make('year')
                    ->label('')
                    ->placeholder('Semua Tahun')
                    ->native(false)
                    ->options(fn () => array_combine(
                        range(date('Y'), date('Y') - 5),
                        range(date('Y'), date('Y') - 5)
                    ))
                    ->default(date('Y'))
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['value'], fn ($q, $y) => $q->whereYear('schedule_date', $y))
                    ),

                // 4. Filter Maskapai
                SelectFilter::make('airline_id')
                    ->label('')
                    ->placeholder('Semua Maskapai')
                    ->native(false)
                    ->relationship('airline', 'brand_name')
                    ->searchable()
                    ->preload(),

                // 5. Filter Pergerakan
                SelectFilter::make('movement')
                    ->label('')
                    ->placeholder('Semua Pergerakan')
                    ->native(false)
                    ->options([
                        'Arrival'   => 'Arrival (Datang)',
                        'Departure' => 'Departure (Berangkat)',
                    ]),

                // 6. Filter Asal
                SelectFilter::make('origin_iata')
                    ->label('')
                    ->placeholder('Semua Asal')
                    ->native(false)
                    ->options(fn () => FlightTraffic::query()
                        ->whereNotNull('origin_iata')
                        ->pluck('origin_iata', 'origin_iata')
                        ->toArray()
                    ),

                // 7. Filter Tujuan
                SelectFilter::make('destination_iata')
                    ->label('')
                    ->placeholder('Semua Tujuan')
                    ->native(false)
                    ->options(fn () => FlightTraffic::query()
                        ->whereNotNull('destination_iata')
                        ->pluck('destination_iata', 'destination_iata')
                        ->toArray()
                    ),
            ])
            ->filtersLayout(Tables\Enums\FiltersLayout::AboveContent)
            ->deferFilters(false)
            ->filtersFormColumns([
                'default' => 1,
                'sm' => 2,
                'md' => 4,
                'xl' => 7,
            ])
            ->recordActions([
                Actions\ActionGroup::make([
                    Actions\ViewAction::make()->label('Lihat'),
                    Actions\EditAction::make()->label('Edit'),
                    Actions\DeleteAction::make()->label('Hapus'),
                ]),
            ])
            ->headerActions([
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
                    ->schema([
                        Forms\Components\FileUpload::make('attachment')
                            ->label('File Excel (.xlsx)')
                            ->disk('local')
                            ->directory('imports/flight-traffic')
                            ->acceptedFileTypes([
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'application/vnd.ms-excel',
                            ])
                            ->required()
                            ->maxSize(10 * 1024)
                            ->helperText('Upload file .xlsx sesuai format rekap operasional. Maksimal 10MB.'),

                        Forms\Components\Placeholder::make('import_info')
                            ->label('Informasi')
                            ->content('File yang sudah dipakai petugas (format rekap harian) bisa langsung diupload tanpa perlu diubah strukturnya. Maskapai yang belum terdaftar akan otomatis dibuat dan ditandai untuk diverifikasi.')
                            ->columnSpanFull(),
                    ])
                    ->action(function (array $data): void {
                        $filePath = Storage::disk('local')->path($data['attachment']);

                        if (! file_exists($filePath)) {
                            $filePath = Storage::disk('public')->path($data['attachment']);
                        }

                        try {
                            $import = new FlightTrafficImport();
                            Excel::import($import, $filePath);

                            $batchId          = $import->getBatchId();
                            $successCount     = $import->getSuccessCount();
                            $skipCount        = $import->getSkipCount();
                            $failureCount     = count($import->failures());
                            $errorCount       = count($import->errors());
                            $autoCreatedCount = $import->getAutoCreatedAirlineCount();
                            $totalIssues      = $failureCount + $errorCount + $skipCount;

                            if ($totalIssues > 0) {
                                Notification::make()
                                    ->title('Import selesai dengan peringatan')
                                    ->body("Berhasil: {$successCount} baris | Gagal/dilewati: {$totalIssues} baris"
                                        . ($autoCreatedCount > 0 ? " | {$autoCreatedCount} maskapai baru otomatis dibuat, perlu diverifikasi." : ''))
                                    ->warning()
                                    ->persistent()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('Import berhasil!')
                                    ->body("{$successCount} data penerbangan berhasil diimport."
                                        . ($autoCreatedCount > 0 ? " {$autoCreatedCount} maskapai baru otomatis dibuat, perlu diverifikasi di menu Master Maskapai." : ''))
                                    ->success()
                                    ->persistent($autoCreatedCount > 0)
                                    ->send();
                            }

                            activity()
                                ->causedBy(Auth::user())
                                ->withProperties([
                                    'success_count'      => $successCount,
                                    'skip_count'         => $totalIssues,
                                    'auto_created_count' => $autoCreatedCount,
                                    'file'               => $data['attachment'],
                                    'batch_id'           => $batchId,
                                ])
                                ->log('Import data penerbangan dari Excel');

                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Import gagal!')
                                ->body('Terjadi kesalahan: ' . $e->getMessage())
                                ->danger()
                                ->persistent()
                                ->send();
                        } finally {
                            if (file_exists($filePath)) {
                                @unlink($filePath);
                            }
                        }
                    })
                    ->modalWidth('lg')
                    ->modalHeading('Import Data Penerbangan dari Excel')
                    ->modalDescription('Upload file Excel rekap operasional untuk mengimport data secara massal.'),
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
            ->emptyStateDescription('Tambahkan data secara manual atau import dari file Excel.')
            ->emptyStateIcon('heroicon-o-chart-bar-square');
    }

    // ──────────────────────────────────────
    // RESOURCE PAGES
    // ──────────────────────────────────────

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
