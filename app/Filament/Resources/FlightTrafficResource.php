<?php

namespace App\Filament\Resources;

use App\Exports\FlightTrafficTemplateExport;
use App\Filament\Resources\FlightTrafficResource\Pages;
use App\Imports\FlightTrafficImport;
use App\Models\Airline;
use App\Models\FlightTraffic;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;

class FlightTrafficResource extends Resource
{
    protected static ?string $model = FlightTraffic::class;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationLabel = 'Data Lalu Lintas';

    protected static ?string $navigationGroup = 'Penerbangan';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Data Penerbangan';

    protected static ?string $pluralModelLabel = 'Data Penerbangan';

    protected static ?string $recordTitleAttribute = 'flight_number';

    // ──────────────────────────────────────
    // FORM
    // ──────────────────────────────────────

    public static function form(Form $form): Form
    {
        return $form
            ->schema([

                // ── Section 1: Jadwal & Identitas ─────────────────────────────────

                Forms\Components\Section::make('Jadwal & Identitas Penerbangan')
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
                                    ->uppercase()
                                    ->nullable(),
                                Forms\Components\TextInput::make('iata_code')
                                    ->label('Kode IATA')
                                    ->maxLength(3)
                                    ->uppercase()
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

                Forms\Components\Section::make('Klasifikasi & Rute')
                    ->description('Status penerbangan, jenis kegiatan, dan informasi rute.')
                    ->icon('heroicon-o-map-pin')
                    ->schema([
                        Forms\Components\ToggleButtons::make('flight_status')
                            ->label('Status Penerbangan')
                            ->options([
                                'Ontime' => 'On Time / Realisasi',
                                'Delay'  => 'Delay',
                                'Cancel' => 'Cancel / Batal',
                            ])
                            ->colors([
                                'Ontime' => 'success',
                                'Delay'  => 'warning',
                                'Cancel' => 'danger',
                            ])
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
                            ->options([
                                'Arrival'   => 'Arrival (Datang)',
                                'Departure' => 'Departure (Berangkat)',
                            ])
                            ->colors([
                                'Arrival'   => 'info',
                                'Departure' => 'warning',
                            ])
                            ->icons([
                                'Arrival'   => 'heroicon-o-arrow-down-tray',
                                'Departure' => 'heroicon-o-arrow-up-tray',
                            ])
                            ->inline()
                            ->required()
                            ->default('Departure'),

                        Forms\Components\Select::make('activity_type')
                            ->label('Jenis Kegiatan')
                            ->options([
                                'Berjadwal'       => 'Berjadwal (Scheduled)',
                                'Tidak Berjadwal' => 'Tidak Berjadwal (Unscheduled)',
                                'Extra Flight'    => 'Extra Flight',
                                'Perintis'        => 'Perintis (Pioneer)',
                                'Haji'            => 'Haji',
                                'Militer'         => 'Militer',
                                'Charter'         => 'Charter',
                                'Kargo'           => 'Kargo (Cargo Only)',
                            ])
                            ->required()
                            ->native(false)
                            ->default('Berjadwal'),

                        Forms\Components\ToggleButtons::make('coverage')
                            ->label('Cakupan')
                            ->options([
                                'Domestik'      => 'Domestik',
                                'Internasional' => 'Internasional',
                            ])
                            ->colors([
                                'Domestik'      => 'info',
                                'Internasional' => 'warning',
                            ])
                            ->inline()
                            ->required()
                            ->default('Domestik'),

                        Forms\Components\Fieldset::make('Informasi Delay')
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

                        Forms\Components\TextInput::make('origin_iata')
                            ->label('Bandara Asal')
                            ->required()
                            ->maxLength(20)
                            ->placeholder('CGK atau LOCAL AREA')
                            ->upperCase()
                            ->prefixIcon('heroicon-o-arrow-right-start-on-rectangle')
                            ->helperText('3 huruf kode IATA, atau "LOCAL AREA" untuk rute non-standar (perintis/helikopter)'),

                        Forms\Components\TextInput::make('destination_iata')
                            ->label('Bandara Tujuan')
                            ->required()
                            ->maxLength(20)
                            ->placeholder('PLW atau LOCAL AREA')
                            ->upperCase()
                            ->prefixIcon('heroicon-o-arrow-left-end-on-rectangle')
                            ->helperText('3 huruf kode IATA, atau "LOCAL AREA" untuk rute non-standar (perintis/helikopter)'),
                    ])
                    ->columns(2),

                // ── Section 3: Manifest Penumpang ─────────────────────────────────

                Forms\Components\Section::make('Manifest Penumpang')
                    ->description('Data penumpang utama dan transit.')
                    ->icon('heroicon-o-user-group')
                    ->schema([
                        Forms\Components\Fieldset::make('Penumpang Utama')
                            ->schema([
                                Forms\Components\TextInput::make('pax_adult')
                                    ->label('Dewasa')
                                    ->numeric()
                                    ->integer()
                                    ->minValue(0)
                                    ->default(0)
                                    ->suffix('org'),

                                Forms\Components\TextInput::make('pax_child')
                                    ->label('Anak-Anak')
                                    ->numeric()
                                    ->integer()
                                    ->minValue(0)
                                    ->default(0)
                                    ->suffix('org'),

                                Forms\Components\TextInput::make('pax_infant')
                                    ->label('Bayi (Infant)')
                                    ->numeric()
                                    ->integer()
                                    ->minValue(0)
                                    ->default(0)
                                    ->suffix('org'),
                            ])
                            ->columns(3),

                        Forms\Components\Fieldset::make('Penumpang Transit')
                            ->schema([
                                Forms\Components\TextInput::make('transit_pax_adult')
                                    ->label('Dewasa (Transit)')
                                    ->numeric()
                                    ->integer()
                                    ->minValue(0)
                                    ->default(0)
                                    ->suffix('org'),

                                Forms\Components\TextInput::make('transit_pax_child')
                                    ->label('Anak-Anak (Transit)')
                                    ->numeric()
                                    ->integer()
                                    ->minValue(0)
                                    ->default(0)
                                    ->suffix('org'),

                                Forms\Components\TextInput::make('transit_pax_infant')
                                    ->label('Bayi (Transit)')
                                    ->numeric()
                                    ->integer()
                                    ->minValue(0)
                                    ->default(0)
                                    ->suffix('org'),
                            ])
                            ->columns(3),
                    ]),

                // ── Section 4: Muatan Logistik ────────────────────────────────────

                Forms\Components\Section::make('Muatan Logistik')
                    ->description('Data berat muatan dalam satuan kilogram.')
                    ->icon('heroicon-o-scale')
                    ->schema([
                        Forms\Components\TextInput::make('baggage_kg')
                            ->label('Bagasi')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->step(0.01)
                            ->suffix('kg')
                            ->prefixIcon('heroicon-o-briefcase'),

                        Forms\Components\TextInput::make('cargo_kg')
                            ->label('Kargo')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->step(0.01)
                            ->suffix('kg')
                            ->prefixIcon('heroicon-o-archive-box'),

                        Forms\Components\TextInput::make('mail_kg')
                            ->label('Pos / Mail')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->step(0.01)
                            ->suffix('kg')
                            ->prefixIcon('heroicon-o-envelope'),
                    ])
                    ->columns(3),

                // ── Section 5: Keterangan ─────────────────────────────────────────

                Forms\Components\Section::make('Keterangan Tambahan')
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
                    ->description(fn (FlightTraffic $record) => $record->airline?->icao_code
                        ? null
                        : '⚠ belum ada kode ICAO'),

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

                Tables\Columns\BadgeColumn::make('flight_status')
                    ->label('Status')
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

                Tables\Columns\BadgeColumn::make('movement')
                    ->label('Pergerakan')
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

                Tables\Columns\BadgeColumn::make('coverage')
                    ->label('Cakupan')
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
                // Filter Status
                SelectFilter::make('flight_status')
                    ->label('Status Penerbangan')
                    ->options([
                        'Ontime' => 'On Time / Realisasi',
                        'Delay'  => 'Delay',
                        'Cancel' => 'Cancel / Batal',
                    ])
                    ->native(false),

                // Filter Pergerakan
                SelectFilter::make('movement')
                    ->label('Pergerakan')
                    ->options([
                        'Arrival'   => 'Arrival (Datang)',
                        'Departure' => 'Departure (Berangkat)',
                    ])
                    ->native(false),

                // Filter Cakupan
                SelectFilter::make('coverage')
                    ->label('Cakupan')
                    ->options([
                        'Domestik'      => 'Domestik',
                        'Internasional' => 'Internasional',
                    ])
                    ->native(false),

                // Filter Kegiatan
                SelectFilter::make('activity_type')
                    ->label('Jenis Kegiatan')
                    ->options([
                        'Berjadwal'       => 'Berjadwal',
                        'Tidak Berjadwal' => 'Tidak Berjadwal',
                        'Extra Flight'    => 'Extra Flight',
                        'Perintis'        => 'Perintis',
                        'Haji'            => 'Haji',
                        'Militer'         => 'Militer',
                        'Charter'         => 'Charter',
                        'Kargo'           => 'Kargo',
                    ])
                    ->native(false),

                // Filter Maskapai
                SelectFilter::make('airline_id')
                    ->label('Maskapai')
                    ->relationship('airline', 'brand_name')
                    ->searchable()
                    ->preload()
                    ->native(false),

                // Filter Info Delay
                TernaryFilter::make('has_delay_info')
                    ->label('Ada Catatan Delay')
                    ->placeholder('Semua Data')
                    ->trueLabel('Ada catatan delay')
                    ->falseLabel('Tanpa catatan delay')
                    ->queries(
                        true: fn (Builder $query) => $query->where(function (Builder $q) {
                            $q->whereNotNull('delay_category')->orWhereNotNull('delay_reason');
                        }),
                        false: fn (Builder $query) => $query->whereNull('delay_category')->whereNull('delay_reason'),
                    )
                    ->native(false),

                // Filter Rentang Tanggal
                Filter::make('schedule_date_range')
                    ->label('Rentang Tanggal Rencana')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('Dari Tanggal')
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                        Forms\Components\DatePicker::make('until')
                            ->label('Hingga Tanggal')
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $q, $date) => $q->whereDate('schedule_date', '>=', $date)
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $q, $date) => $q->whereDate('schedule_date', '<=', $date)
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['from'] ?? null) {
                            $indicators['from'] = 'Dari: ' . \Carbon\Carbon::parse($data['from'])->format('d/m/Y');
                        }

                        if ($data['until'] ?? null) {
                            $indicators['until'] = 'Hingga: ' . \Carbon\Carbon::parse($data['until'])->format('d/m/Y');
                        }

                        return $indicators;
                    }),

                // Filter Bulan & Tahun
                Filter::make('month_year')
                    ->label('Bulan & Tahun')
                    ->form([
                        Forms\Components\Select::make('month')
                            ->label('Bulan')
                            ->options([
                                '1'  => 'Januari',
                                '2'  => 'Februari',
                                '3'  => 'Maret',
                                '4'  => 'April',
                                '5'  => 'Mei',
                                '6'  => 'Juni',
                                '7'  => 'Juli',
                                '8'  => 'Agustus',
                                '9'  => 'September',
                                '10' => 'Oktober',
                                '11' => 'November',
                                '12' => 'Desember',
                            ])
                            ->native(false)
                            ->placeholder('Semua Bulan'),

                        Forms\Components\Select::make('year')
                            ->label('Tahun')
                            ->options(array_combine(
                                range(now()->year, 2020),
                                range(now()->year, 2020)
                            ))
                            ->native(false)
                            ->default(now()->year),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['month'],
                                fn (Builder $q, $month) => $q->whereMonth('schedule_date', $month)
                            )
                            ->when(
                                $data['year'],
                                fn (Builder $q, $year) => $q->whereYear('schedule_date', $year)
                            );
                    }),

                Tables\Filters\TrashedFilter::make()
                    ->label('Tampilkan Dihapus'),
            ])
            ->filtersFormColumns(3)
            ->filtersLayout(Tables\Enums\FiltersLayout::AboveContent)
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make()->label('Lihat'),
                    Tables\Actions\EditAction::make()->label('Edit'),
                    Tables\Actions\DeleteAction::make()->label('Hapus'),
                    Tables\Actions\RestoreAction::make()->label('Pulihkan'),
                    Tables\Actions\ForceDeleteAction::make()->label('Hapus Permanen'),
                ]),
            ])
            ->headerActions([
                // Tombol Download Template
                Tables\Actions\Action::make('download_template')
                    ->label('Unduh Template')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->action(function () {
                        return Excel::download(
                            new FlightTrafficTemplateExport(),
                            'template-import-flight-traffic.xlsx'
                        );
                    }),

            // Tombol Import Excel
            Tables\Actions\Action::make('import_excel')
                ->label('Import Excel')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('success')
                ->form([
                    Forms\Components\FileUpload::make('attachment')
                        ->label('File Excel (.xlsx)')
                        ->disk('local')
                        ->directory('imports/flight-traffic')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel',
                        ])
                        ->required()
                        ->maxSize(10 * 1024) // 10 MB
                        ->helperText('Upload file .xlsx sesuai format rekap operasional. Maksimal 10MB.'),

                    Forms\Components\Placeholder::make('import_info')
                        ->label('Informasi')
                        ->content('File yang sudah dipakai petugas (format rekap harian) bisa langsung diupload tanpa perlu diubah strukturnya. Maskapai yang belum terdaftar akan otomatis dibuat dan ditandai untuk diverifikasi.')
                        ->columnSpanFull(),
                ])
                ->action(function (array $data): void {
                    // Dapatkan absolute path secara dinamis dari disk 'local'
                    $filePath = Storage::disk('local')->path($data['attachment']);

                    // Fallback jika file tersimpan di disk 'public'
                    if (!file_exists($filePath)) {
                        $filePath = Storage::disk('public')->path($data['attachment']);
                    }

                    try {
                        $import = new FlightTrafficImport();
                        Excel::import($import, $filePath);

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

                        // Log aktivitas import (dikoreksi dari $data['file'] ke $data['attachment'])
                        activity()
                            ->causedBy(Auth::user())
                            ->withProperties([
                                'success_count'      => $successCount,
                                'skip_count'         => $totalIssues,
                                'auto_created_count' => $autoCreatedCount,
                                'file'               => $data['attachment'],
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
                        // Hapus file temporary setelah selesai
                        if (file_exists($filePath)) {
                            @unlink($filePath);
                        }
                    }
                })
                ->modalWidth('lg')
                ->modalHeading('Import Data Penerbangan dari Excel')
                ->modalDescription('Upload file Excel rekap operasional untuk mengimport data secara massal.')
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()->label('Hapus Terpilih'),
                    Tables\Actions\RestoreBulkAction::make()->label('Pulihkan Terpilih'),
                    Tables\Actions\ForceDeleteBulkAction::make()->label('Hapus Permanen'),
                ]),
            ])
            ->striped()
            ->paginated([25, 50, 100, 'all'])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading('Belum ada data penerbangan')
            ->emptyStateDescription('Tambahkan data secara manual atau import dari file Excel.')
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
            'view'   => Pages\ViewFlightTraffic::route('/{record}'),
            'edit'   => Pages\EditFlightTraffic::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ])
            ->with(['airline', 'creator']);
    }
}
