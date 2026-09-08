<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name'     => 'Administrator SkyTrack',
            'email'    => 'admin@mutiaraskytrack.id',
            'password' => Hash::make('admin123'),
            'role'     => 'admin',
            'status'   => 'aktif',
        ]);

        User::create([
            'name'     => 'Staff ATC',
            'email'    => 'staff@mutiaraskytrack.id',
            'password' => Hash::make('staff123'),
            'role'     => 'staff',
            'status'   => 'aktif',
        ]);
    }
}
