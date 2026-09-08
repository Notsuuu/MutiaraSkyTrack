<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;

class UpdateLastLogin
{
    public function handle(Login $event): void
    {
        $event->user->update(['last_login_at' => now()]);

        activity()
            ->causedBy($event->user)
            ->performedOn($event->user)
            ->log('Login ke sistem');
    }
}
