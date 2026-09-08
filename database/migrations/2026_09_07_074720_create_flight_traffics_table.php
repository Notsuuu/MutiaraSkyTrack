<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flight_traffics', function (Blueprint $table) {
            $table->id();

            // ── Foreign Keys ──────────────────────────────
            $table->foreignId('airline_id')
                  ->constrained('airlines')
                  ->restrictOnDelete();
            $table->foreignId('created_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            // ── Jadwal & Waktu ────────────────────────────
            $table->date('schedule_date')->comment('Tanggal rencana penerbangan');
            $table->date('actual_date')->nullable()->comment('Tanggal realisasi penerbangan');
            $table->time('schedule_time')->comment('Jam rencana (STD/STA)');
            $table->time('actual_time')->nullable()->comment('Jam realisasi (ATD/ATA)');

            // ── Identitas Penerbangan ─────────────────────
            $table->string('flight_number', 10)->comment('Kode/nomor penerbangan, e.g. GA-102');
            $table->string('aircraft_type', 20)->comment('Tipe pesawat, e.g. B738, A320');
            $table->string('aircraft_registration', 15)->nullable()->comment('Registrasi pesawat, e.g. PK-GFA');
            $table->unsignedSmallInteger('seat_capacity')->default(0)->comment('Kapasitas kursi pesawat');

            // ── Status & Klasifikasi ──────────────────────
            $table->enum('flight_status', ['Ontime', 'Delay', 'Cancel'])->default('Ontime');
            $table->enum('activity_type', [
                'Berjadwal',
                'Extra Flight',
                'Perintis',
                'Haji',
                'Militer',
                'Charter',
                'Kargo',
            ])->default('Berjadwal')->comment('Jenis kegiatan penerbangan');
            $table->enum('coverage', ['Domestik', 'Internasional'])->default('Domestik');
            $table->enum('movement', ['Arrival', 'Departure'])->comment('Pergerakan: Datang atau Berangkat');

            // ── Rute ─────────────────────────────────────
            $table->string('origin_iata', 3)->comment('Kode IATA bandara asal, e.g. CGK');
            $table->string('destination_iata', 3)->comment('Kode IATA bandara tujuan, e.g. PLW');

            // ── Manifest Penumpang Utama ──────────────────
            $table->unsignedSmallInteger('pax_adult')->default(0)->comment('Penumpang dewasa');
            $table->unsignedSmallInteger('pax_child')->default(0)->comment('Penumpang anak');
            $table->unsignedSmallInteger('pax_infant')->default(0)->comment('Penumpang bayi');

            // ── Manifest Penumpang Transit ────────────────
            $table->unsignedSmallInteger('transit_pax_adult')->default(0)->comment('PAX transit dewasa');
            $table->unsignedSmallInteger('transit_pax_child')->default(0)->comment('PAX transit anak');
            $table->unsignedSmallInteger('transit_pax_infant')->default(0)->comment('PAX transit bayi');

            // ── Muatan Logistik (kg) ──────────────────────
            $table->decimal('baggage_kg', 10, 2)->default(0)->comment('Berat bagasi (kg)');
            $table->decimal('cargo_kg', 10, 2)->default(0)->comment('Berat kargo (kg)');
            $table->decimal('mail_kg', 10, 2)->default(0)->comment('Berat pos/mail (kg)');

            // ── Catatan ───────────────────────────────────
            $table->text('remarks')->nullable()->comment('Keterangan tambahan');

            $table->timestamps();
            $table->softDeletes();

            // ── Indexes ───────────────────────────────────
            $table->index('schedule_date');
            $table->index('actual_date');
            $table->index('flight_status');
            $table->index('movement');
            $table->index('coverage');
            $table->index('activity_type');
            $table->index(['schedule_date', 'movement']);
            $table->index(['airline_id', 'schedule_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flight_traffics');
    }
};
