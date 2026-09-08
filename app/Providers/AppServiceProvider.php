<?php

namespace App\Providers;

use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use App\Listeners\UpdateLastLogin;

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
    }
}
