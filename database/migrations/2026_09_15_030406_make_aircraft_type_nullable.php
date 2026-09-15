<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('flight_traffics', function (Blueprint $table) {
            $table->string('aircraft_type', 20)->nullable()->change();
            $table->string('aircraft_registration', 15)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('flight_traffics', function (Blueprint $table) {
            $table->string('aircraft_type', 20)->nullable(false)->change();
            $table->string('aircraft_registration', 15)->nullable(false)->change();
        });
    }
};
