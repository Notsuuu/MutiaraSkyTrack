<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use UnitEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Kelola Staf';

    protected static string | UnitEnum | null $navigationGroup = 'Master Data';

    protected static ?int $navigationSort = 20;

    protected static ?string $modelLabel = 'Pengguna';

    protected static ?string $pluralModelLabel = 'Pengguna';

    protected static ?string $recordTitleAttribute = 'name';

    public static function canAccess(): bool
    {
        return Auth::user()?->isAdmin() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Akun')
                    ->description('Data dasar pengguna sistem.')
                    ->icon('heroicon-o-user-circle')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nama Lengkap')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Contoh: Budi Santoso')
                            ->prefixIcon('heroicon-o-user'),

                        Forms\Components\TextInput::make('email')
                            ->label('Alamat Email')
                            ->email()
                            ->required()
                            ->unique(User::class, 'email', ignoreRecord: true)
                            ->maxLength(255)
                            ->placeholder('email@domain.com')
                            ->prefixIcon('heroicon-o-envelope'),

                        Forms\Components\TextInput::make('password')
                            ->label('Kata Sandi')
                            ->password()
                            ->revealable()
                            ->dehydrateStateUsing(fn ($state) => filled($state) ? Hash::make($state) : null)
                            ->dehydrated(fn ($state) => filled($state))
                            ->required(fn (string $operation) => $operation === 'create')
                            ->minLength(8)
                            ->maxLength(255)
                            ->placeholder(fn (string $operation) => $operation === 'edit'
                                ? 'Kosongkan jika tidak ingin mengubah'
                                : 'Minimal 8 karakter')
                            ->helperText('Minimal 8 karakter. Kosongkan saat edit jika tidak berubah.'),

                        Forms\Components\TextInput::make('password_confirmation')
                            ->label('Konfirmasi Kata Sandi')
                            ->password()
                            ->revealable()
                            ->required(fn (string $operation) => $operation === 'create')
                            ->same('password')
                            ->dehydrated(false)
                            ->placeholder('Ulangi kata sandi'),
                    ])
                    ->columns(2),

                Section::make('Hak Akses & Status')
                    ->description('Konfigurasi peran dan status akun.')
                    ->icon('heroicon-o-shield-check')
                    ->schema([
                        Forms\Components\Select::make('role')
                            ->label('Peran / Role')
                            ->options([
                                'admin' => 'Administrator',
                                'staff' => 'Staff Operasional',
                            ])
                            ->required()
                            ->native(false)
                            ->default('staff')
                            ->helperText('Administrator memiliki akses penuh ke seluruh fitur.'),

                        Forms\Components\ToggleButtons::make('status')
                            ->label('Status Akun')
                            ->options([
                                'aktif'    => 'Aktif',
                                'nonaktif' => 'Nonaktif',
                            ])
                            ->colors([
                                'aktif'    => 'success',
                                'nonaktif' => 'danger',
                            ])
                            ->icons([
                                'aktif'    => 'heroicon-o-check-circle',
                                'nonaktif' => 'heroicon-o-x-circle',
                            ])
                            ->inline()
                            ->required()
                            ->default('aktif'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Lengkap')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold')
                    ->description(fn (User $record) => $record->email),

                Tables\Columns\TextColumn::make('role')
                    ->label('Peran')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'admin' => 'Administrator',
                        'staff' => 'Staff',
                        default => ucfirst($state),
                    })
                    ->color(fn (string $state) => match ($state) {
                        'admin' => 'danger',
                        'staff' => 'info',
                        default => 'gray',
                    })
                    ->icon(fn (string $state) => match ($state) {
                        'admin' => 'heroicon-o-shield-check',
                        'staff' => 'heroicon-o-user',
                        default => 'heroicon-o-question-mark-circle',
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'aktif'    => 'Aktif',
                        'nonaktif' => 'Nonaktif',
                        default    => ucfirst($state),
                    })
                    ->color(fn (string $state) => match ($state) {
                        'aktif'    => 'success',
                        'nonaktif' => 'danger',
                        default    => 'gray',
                    })
                    ->icon(fn (string $state) => match ($state) {
                        'aktif'    => 'heroicon-o-check-circle',
                        'nonaktif' => 'heroicon-o-x-circle',
                        default    => 'heroicon-o-question-mark-circle',
                    }),

                Tables\Columns\TextColumn::make('last_login_at')
                    ->label('Login Terakhir')
                    ->dateTime('d M Y, H:i')
                    ->sortable()
                    ->placeholder('Belum pernah login')
                    ->since()
                    ->tooltip(fn (User $record) => $record->last_login_at?->format('d F Y, H:i:s')),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->date('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->label('Filter Peran')
                    ->options([
                        'admin' => 'Administrator',
                        'staff' => 'Staff',
                    ])
                    ->native(false),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Filter Status')
                    ->options([
                        'aktif'    => 'Aktif',
                        'nonaktif' => 'Nonaktif',
                    ])
                    ->native(false),

                Tables\Filters\TrashedFilter::make()
                    ->label('Tampilkan Dihapus'),
            ])
            ->recordActions([
                Actions\ActionGroup::make([
                    Actions\ViewAction::make()->label('Lihat'),
                    Actions\EditAction::make()->label('Edit'),

                    Actions\Action::make('toggle_status')
                        ->label(fn (User $record) => $record->status === 'aktif' ? 'Nonaktifkan' : 'Aktifkan')
                        ->icon(fn (User $record) => $record->status === 'aktif' ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                        ->color(fn (User $record) => $record->status === 'aktif' ? 'danger' : 'success')
                        ->requiresConfirmation()
                        ->modalHeading(fn (User $record) => $record->status === 'aktif'
                            ? 'Nonaktifkan Akun'
                            : 'Aktifkan Akun')
                        ->modalDescription(fn (User $record) => $record->status === 'aktif'
                            ? "Apakah Anda yakin ingin menonaktifkan akun {$record->name}? Pengguna tidak dapat login."
                            : "Apakah Anda yakin ingin mengaktifkan kembali akun {$record->name}?")
                        ->action(function (User $record) {
                            $newStatus = $record->status === 'aktif' ? 'nonaktif' : 'aktif';
                            $record->update(['status' => $newStatus]);

                            Notification::make()
                                ->title('Status diperbarui')
                                ->body("Akun {$record->name} berhasil di-" . ($newStatus === 'aktif' ? 'aktifkan' : 'nonaktifkan') . ".")
                                ->success()
                                ->send();
                        })
                        ->visible(fn (User $record) => $record->id !== Auth::id()),

                    Actions\DeleteAction::make()
                        ->label('Hapus')
                        ->visible(fn (User $record) => $record->id !== Auth::id()),

                    Actions\RestoreAction::make()->label('Pulihkan'),
                ]),
            ])
            ->toolbarActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make()->label('Hapus Terpilih'),
                    Actions\RestoreBulkAction::make()->label('Pulihkan Terpilih'),
                    Actions\ForceDeleteBulkAction::make()->label('Hapus Permanen'),
                ]),
            ])
            ->emptyStateHeading('Belum ada pengguna')
            ->emptyStateDescription('Tambahkan pengguna baru dengan menekan tombol di atas.')
            ->emptyStateIcon('heroicon-o-users');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'view'   => Pages\ViewUser::route('/{record}'),
            'edit'   => Pages\EditUser::route('/{record}/edit'),
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
