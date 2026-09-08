<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('airlines', function (Blueprint $table) {
            $table->id();
            $table->string('icao_code', 4)->unique()->comment('Kode ICAO maskapai');
            $table->string('iata_code', 3)->unique()->nullable()->comment('Kode IATA maskapai');
            $table->string('brand_name')->comment('Nama brand/merek maskapai');
            $table->string('operator_name')->comment('Nama legal operator');
            $table->enum('coverage', ['domestik', 'internasional'])->default('domestik');
            $table->enum('operational_status', ['beroperasi', 'tidak'])->default('beroperasi');
            $table->unsignedInteger('flight_frequency')->default(0)->comment('Frekuensi penerbangan');
            $table->text('notes')->nullable()->comment('Catatan tambahan');
            $table->timestamps();
            $table->softDeletes();

            $table->index('brand_name');
            $table->index('operational_status');
            $table->index('coverage');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('airlines');
    }
};
