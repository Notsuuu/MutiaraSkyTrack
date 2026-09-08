<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Airlines: dukung maskapai yang otomatis dibuat dari proses import ──
        Schema::table('airlines', function (Blueprint $table) {
            $table->string('icao_code', 4)->nullable()->change();
            $table->boolean('is_auto_generated')
                  ->default(false)
                  ->after('notes')
                  ->comment('True jika record dibuat otomatis oleh proses import Excel dan belum diverifikasi admin');
        });

        // ── Flight Traffics: kolom delay & perluasan panjang kolom rute ──
        Schema::table('flight_traffics', function (Blueprint $table) {
            $table->string('delay_category', 150)
                  ->nullable()
                  ->after('flight_status')
                  ->comment('Kategori penyebab delay, e.g. Manajemen Airlines, Cuaca, Teknis');

            $table->text('delay_reason')
                  ->nullable()
                  ->after('delay_category')
                  ->comment('Keterangan detail penyebab delay dari sumber data');

            // Diperlebar dari 3 karakter agar menampung nilai non-IATA
            // seperti "LOCAL AREA" pada penerbangan perintis/helikopter.
            $table->string('origin_iata', 20)->change();
            $table->string('destination_iata', 20)->change();
        });

        // ── Perluas ENUM activity_type: tambahkan "Tidak Berjadwal" ──
        DB::statement("
            ALTER TABLE flight_traffics
            MODIFY activity_type ENUM(
                'Berjadwal',
                'Extra Flight',
                'Perintis',
                'Tidak Berjadwal',
                'Haji',
                'Militer',
                'Charter',
                'Kargo'
            ) NOT NULL DEFAULT 'Berjadwal'
        ");
    }

    public function down(): void
    {
        Schema::table('flight_traffics', function (Blueprint $table) {
            $table->dropColumn(['delay_category', 'delay_reason']);
            $table->string('origin_iata', 3)->change();
            $table->string('destination_iata', 3)->change();
        });

        Schema::table('airlines', function (Blueprint $table) {
            $table->dropColumn('is_auto_generated');
            $table->string('icao_code', 4)->nullable(false)->change();
        });

        DB::statement("
            ALTER TABLE flight_traffics
            MODIFY activity_type ENUM(
                'Berjadwal',
                'Extra Flight',
                'Perintis',
                'Haji',
                'Militer',
                'Charter',
                'Kargo'
            ) NOT NULL DEFAULT 'Berjadwal'
        ");
    }
};
