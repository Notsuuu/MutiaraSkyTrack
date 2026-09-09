<?php

namespace App\Providers;

use App\Listeners\UpdateLastLogin;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(Login::class, UpdateLastLogin::class);

        Event::listen(Logout::class, function ($event) {
            if ($event->user) {
                activity()
                    ->causedBy($event->user)
                    ->performedOn($event->user)
                    ->log('Logout dari sistem');
            }
        });

        // Filament Macro agar ->uppercase() dan ->upperCase() berfungsi pada TextInput
        TextInput::macro('uppercase', function () {
            return $this
                ->extraInputAttributes(['class' => 'uppercase', 'style' => 'text-transform: uppercase'])
                ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? strtoupper($state) : null);
        });

        TextInput::macro('upperCase', function () {
            return $this->uppercase();
        });

        // Filament Macro untuk TextColumn tabel
        TextColumn::macro('uppercase', function () {
            return $this->formatStateUsing(fn (?string $state): ?string => filled($state) ? strtoupper($state) : null);
        });

        TextColumn::macro('upperCase', function () {
            return $this->uppercase();
        });
    }
}
