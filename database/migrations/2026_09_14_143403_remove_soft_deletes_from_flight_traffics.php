<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Bersihkan dulu: hapus permanen data yang sudah soft-deleted
        DB::table('flight_traffics')->whereNotNull('deleted_at')->delete();

        // Drop kolom deleted_at
        Schema::table('flight_traffics', function (Blueprint $table) {
            $table->dropColumn('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::table('flight_traffics', function (Blueprint $table) {
            $table->softDeletes();
        });
    }
};
