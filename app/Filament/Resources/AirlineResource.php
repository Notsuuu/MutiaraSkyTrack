<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AirlineResource\Pages;
use App\Models\Airline;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class AirlineResource extends Resource
{
    protected static ?string $model = Airline::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-paper-airplane';

    protected static ?string $navigationLabel = 'Master Maskapai';

    protected static string | UnitEnum| null $navigationGroup = 'Master Data';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Maskapai';

    protected static ?string $pluralModelLabel = 'Maskapai';

    protected static ?string $recordTitleAttribute = 'brand_name';

    // ──────────────────────────────────────
    // FORM (Schema)
    // ──────────────────────────────────────

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identitas Maskapai')
                    ->description('Kode resmi dan nama maskapai.')
                    ->icon('heroicon-o-identification')
                    ->schema([
                        Forms\Components\TextInput::make('icao_code')
                            ->label('Kode ICAO')
                            ->maxLength(4)
                            ->minLength(2)
                            ->unique(Airline::class, 'icao_code', ignoreRecord: true)
                            ->nullable()
                            ->uppercase()
                            ->placeholder('GIA')
                            ->helperText('3-4 karakter kode ICAO. Boleh dikosongkan untuk maskapai hasil import otomatis.')
                            ->alphaDash(),

                        Forms\Components\TextInput::make('iata_code')
                            ->label('Kode IATA')
                            ->maxLength(3)
                            ->minLength(2)
                            ->unique(Airline::class, 'iata_code', ignoreRecord: true)
                            ->nullable()
                            ->uppercase()
                            ->placeholder('GA')
                            ->helperText('2-3 karakter kode IATA, contoh: GA, ID, SJ')
                            ->alphaDash(),

                        Forms\Components\TextInput::make('brand_name')
                            ->label('Nama Brand / Merek')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Garuda Indonesia')
                            ->helperText('Nama yang dikenal publik.'),

                        Forms\Components\TextInput::make('operator_name')
                            ->label('Nama Operator (Legal)')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('PT Garuda Indonesia (Persero) Tbk')
                            ->helperText('Nama perusahaan resmi sesuai legalitas.'),

                        Forms\Components\Toggle::make('is_auto_generated')
                            ->label('Masih Perlu Verifikasi?')
                            ->helperText('Otomatis true jika dibuat dari import Excel. Matikan setelah kode ICAO/IATA dilengkapi.')
                            ->default(false)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Operasional & Cakupan')
                    ->description('Konfigurasi rute dan status operasional maskapai.')
                    ->icon('heroicon-o-globe-alt')
                    ->schema([
                        Forms\Components\ToggleButtons::make('coverage')
                            ->label('Cakupan Rute')
                            ->options([
                                'domestik'      => 'Domestik',
                                'internasional' => 'Internasional',
                            ])
                            ->colors([
                                'domestik'      => 'info',
                                'internasional' => 'warning',
                            ])
                            ->icons([
                                'domestik'      => 'heroicon-o-home',
                                'internasional' => 'heroicon-o-globe-alt',
                            ])
                            ->inline()
                            ->required()
                            ->default('domestik'),

                        Forms\Components\ToggleButtons::make('operational_status')
                            ->label('Status Operasional')
                            ->options([
                                'beroperasi' => 'Beroperasi',
                                'tidak'      => 'Tidak Beroperasi',
                            ])
                            ->colors([
                                'beroperasi' => 'success',
                                'tidak'      => 'danger',
                            ])
                            ->icons([
                                'beroperasi' => 'heroicon-o-check-circle',
                                'tidak'      => 'heroicon-o-x-circle',
                            ])
                            ->inline()
                            ->required()
                            ->default('beroperasi'),

                        Forms\Components\TextInput::make('flight_frequency')
                            ->label('Frekuensi Penerbangan (per minggu)')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->maxValue(9999)
                            ->default(0)
                            ->suffix('penerbangan/minggu')
                            ->helperText('Rata-rata jumlah penerbangan per minggu ke PLW.'),

                        Forms\Components\Textarea::make('notes')
                            ->label('Catatan')
                            ->nullable()
                            ->maxLength(1000)
                            ->rows(3)
                            ->placeholder('Informasi tambahan tentang maskapai ini...')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    // ──────────────────────────────────────
    // TABLE
    // ──────────────────────────────────────

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('icao_code')
                    ->label('Kode ICAO')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('primary')
                    ->weight('bold')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('iata_code')
                    ->label('IATA')
                    ->searchable()
                    ->badge()
                    ->color('gray')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('brand_name')
                    ->label('Nama Maskapai')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold')
                    ->description(fn (Airline $record) => $record->operator_name),

                Tables\Columns\IconColumn::make('is_auto_generated')
                    ->label('Verifikasi')
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->falseIcon('heroicon-o-check-badge')
                    ->trueColor('warning')
                    ->falseColor('success')
                    ->tooltip(fn (Airline $record) => $record->is_auto_generated
                        ? 'Dibuat otomatis dari import — lengkapi kode ICAO/IATA'
                        : 'Data terverifikasi'),

                Tables\Columns\TextColumn::make('coverage')
                    ->label('Cakupan')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'domestik'      => 'Domestik',
                        'internasional' => 'Internasional',
                        default         => ucfirst($state),
                    })
                    ->color(fn (string $state) => match ($state) {
                        'domestik'      => 'info',
                        'internasional' => 'warning',
                        default         => 'gray',
                    })
                    ->icon(fn (string $state) => match ($state) {
                        'domestik'      => 'heroicon-o-home',
                        'internasional' => 'heroicon-o-globe-alt',
                        default         => null,
                    }),

                Tables\Columns\TextColumn::make('operational_status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'beroperasi' => 'Beroperasi',
                        'tidak'      => 'Tidak Beroperasi',
                        default      => ucfirst($state),
                    })
                    ->color(fn (string $state) => match ($state) {
                        'beroperasi' => 'success',
                        'tidak'      => 'danger',
                        default      => 'gray',
                    }),

                Tables\Columns\TextColumn::make('flight_frequency')
                    ->label('Frekuensi/Minggu')
                    ->numeric()
                    ->sortable()
                    ->suffix(' pnb')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('flightTraffics_count')
                    ->label('Total Data')
                    ->counts('flightTraffics')
                    ->sortable()
                    ->alignCenter()
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->date('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('brand_name', 'asc')
            ->filters([
                Tables\Filters\SelectFilter::make('coverage')
                    ->label('Cakupan')
                    ->options([
                        'domestik'      => 'Domestik',
                        'internasional' => 'Internasional',
                    ])
                    ->native(false),

                Tables\Filters\SelectFilter::make('operational_status')
                    ->label('Status Operasional')
                    ->options([
                        'beroperasi' => 'Beroperasi',
                        'tidak'      => 'Tidak Beroperasi',
                    ])
                    ->native(false),

                Tables\Filters\TernaryFilter::make('is_auto_generated')
                    ->label('Perlu Verifikasi (Auto-generated)')
                    ->trueLabel('Ya, perlu verifikasi')
                    ->falseLabel('Tidak, sudah terverifikasi')
                    ->native(false),

                Tables\Filters\TrashedFilter::make()
                    ->label('Tampilkan Dihapus'),
            ])
            ->recordActions([
                Actions\ActionGroup::make([
                    Actions\ViewAction::make()->label('Lihat'),
                    Actions\EditAction::make()->label('Edit'),
                    Actions\DeleteAction::make()->label('Hapus'),
                    Actions\RestoreAction::make()->label('Pulihkan'),
                    Actions\ForceDeleteAction::make()->label('Hapus Permanen'),
                ]),
            ])
            ->toolbarActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make()->label('Hapus Terpilih'),
                    Actions\RestoreBulkAction::make()->label('Pulihkan Terpilih'),
                    Actions\ForceDeleteBulkAction::make()->label('Hapus Permanen'),
                ]),
            ])
            ->emptyStateHeading('Belum ada data maskapai')
            ->emptyStateDescription('Tambahkan maskapai baru untuk memulai pencatatan data penerbangan.')
            ->emptyStateIcon('heroicon-o-paper-airplane');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListAirlines::route('/'),
            'create' => Pages\CreateAirline::route('/create'),
            'view'   => Pages\ViewAirline::route('/{record}'),
            'edit'   => Pages\EditAirline::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
