<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('flight_traffics', function (Blueprint $table) {
            $table->string('import_batch_id', 36)
                  ->nullable()
                  ->after('created_by')
                  ->index()
                  ->comment('UUID batch import — semua record dari 1 file Excel punya batch_id sama');
        });
    }

    public function down(): void
    {
        Schema::table('flight_traffics', function (Blueprint $table) {
            $table->dropColumn('import_batch_id');
        });
    }
};
