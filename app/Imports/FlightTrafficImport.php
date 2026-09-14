<?php

namespace App\Imports;

use App\Models\Airline;
use App\Models\FlightTraffic;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

/**
 * Import data lalu lintas penerbangan dari file rekap operasional PLW.
 *
 * Header asli pada baris pertama file .xlsx (urutan fisik kolom TIDAK
 * berpengaruh karena Laravel Excel membaca berdasarkan NAMA header):
 *
 * No | Tgl Aktual | Waktu Aktual | Tgl Jadwal | Waktu Jadwal |
 * Operator / Maskapai | No. Penerbangan | Registrasi | Tipe Armada |
 * Kapasitas | Asal | Tujuan | Pergerakan | Kegiatan | Cakupan | Status |
 * Kategori Delay | Keterangan Delay | Pax Dewasa | Pax Anak | Pax Bayi |
 * Total Pax | Transit Dewasa | Transit Anak | Transit Bayi |
 * Bagasi (Kg) | Kargo (Kg) | Pos (Kg)
 *
 * Kolom "No" dan "Total Pax" diabaikan saat import: "No" hanya nomor urut
 * baris, dan "Total Pax" adalah nilai turunan yang sudah dihitung otomatis
 * oleh accessor FlightTraffic::getTotalPaxAttribute().
 *
 * Setiap pemanggilan Import akan menghasilkan UUID batch_id baru. Semua
 * record yang dihasilkan dari 1 file Excel akan memiliki batch_id yang
 * sama sehingga bisa dihapus bersama-sama dari halaman Riwayat Import.
 */
class FlightTrafficImport implements
    ToCollection,
    WithHeadingRow,
    WithValidation,
    WithBatchInserts,
    WithChunkReading,
    SkipsEmptyRows,
    SkipsOnError,
    SkipsOnFailure
{
    use Importable, SkipsErrors, SkipsFailures;

    private int $successCount = 0;
    private int $skipCount = 0;
    private int $autoCreatedAirlineCount = 0;

    /** @var array<string,int> cache nama maskapai (lowercase) → airline_id */
    private array $airlineCache = [];

    /** UUID unik untuk batch import ini — semua record hasil import ini akan memiliki nilai ini */
    private string $batchId;

    public function __construct()
    {
        $this->batchId = (string) Str::uuid();
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // +2: heading row (baris 1) + index 0-based

            try {
                $airline = $this->resolveOrCreateAirline($row['operator_maskapai'] ?? null);

                if (! $airline) {
                    $this->skipCount++;
                    Log::warning('Import FlightTraffic: nama maskapai kosong, baris dilewati.', [
                        'row' => $rowNumber,
                    ]);
                    continue;
                }

                $scheduleDate = $this->parseDate($row['tgl_jadwal'] ?? null);
                $scheduleTime = $this->parseTime($row['waktu_jadwal'] ?? null);
                $actualDate   = $this->parseDate($row['tgl_aktual'] ?? null);
                $actualTime   = $this->parseTime($row['waktu_aktual'] ?? null);

                $paxAdult  = (int) ($row['pax_dewasa'] ?? 0);
                $paxChild  = (int) ($row['pax_anak'] ?? 0);
                $paxInfant = (int) ($row['pax_bayi'] ?? 0);

                $this->assertTotalPaxConsistency($row, $paxAdult, $paxChild, $paxInfant, $rowNumber);

                FlightTraffic::create([
                    // ⚠️ Batch ID — semua record hasil import ini punya nilai yang sama
                    'import_batch_id' => $this->batchId,

                    'airline_id' => $airline->id,
                    'created_by' => Auth::id(),

                    'schedule_date' => $scheduleDate,
                    'schedule_time' => $scheduleTime,
                    'actual_date'   => $actualDate,
                    'actual_time'   => $actualTime,

                    'flight_number'         => strtoupper(trim((string) ($row['no_penerbangan'] ?? ''))),
                    'aircraft_type'         => strtoupper(trim((string) ($row['tipe_armada'] ?? ''))),
                    'aircraft_registration' => strtoupper(trim((string) ($row['registrasi'] ?? ''))),
                    'seat_capacity'         => (int) ($row['kapasitas'] ?? 0),

                    'flight_status' => $this->normalizeStatus((string) ($row['status'] ?? '')),
                    'activity_type' => $this->normalizeActivity((string) ($row['kegiatan'] ?? '')),
                    'coverage'      => $this->normalizeCoverage((string) ($row['cakupan'] ?? '')),
                    'movement'      => $this->normalizeMovement((string) ($row['pergerakan'] ?? '')),

                    'origin_iata'      => strtoupper(trim((string) ($row['asal'] ?? ''))),
                    'destination_iata' => strtoupper(trim((string) ($row['tujuan'] ?? ''))),

                    'delay_category' => $this->nullableTrim($row['kategori_delay'] ?? null),
                    'delay_reason'   => $this->nullableTrim($row['keterangan_delay'] ?? null),

                    'pax_adult'  => $paxAdult,
                    'pax_child'  => $paxChild,
                    'pax_infant' => $paxInfant,

                    'transit_pax_adult'  => (int) ($row['transit_dewasa'] ?? 0),
                    'transit_pax_child'  => (int) ($row['transit_anak'] ?? 0),
                    'transit_pax_infant' => (int) ($row['transit_bayi'] ?? 0),

                    'baggage_kg' => (float) ($row['bagasi_kg'] ?? 0),
                    'cargo_kg'   => (float) ($row['kargo_kg'] ?? 0),
                    'mail_kg'    => (float) ($row['pos_kg'] ?? 0),
                ]);

                $this->successCount++;

            } catch (\Throwable $e) {
                $this->skipCount++;
                Log::error("Import FlightTraffic: error pada baris {$rowNumber}", [
                    'error' => $e->getMessage(),
                    'row'   => $row->toArray(),
                ]);
            }
        }
    }

    // ──────────────────────────────────────
    // Validasi Heading Row
    // ──────────────────────────────────────

    public function rules(): array
    {
        return [
            'tgl_jadwal'        => ['required'],
            'operator_maskapai' => ['required', 'string', 'max:255'],
            'no_penerbangan'    => ['required'],
            'tipe_armada'       => ['required', 'string', 'max:20'],
            'asal'              => ['required', 'string', 'max:20'],
            'tujuan'            => ['required', 'string', 'max:20'],
            'pergerakan'        => ['required'],
            'kegiatan'          => ['required'],
            'cakupan'           => ['required'],
            'status'            => ['required'],
            'pax_dewasa'        => ['nullable', 'numeric', 'min:0'],
            'pax_anak'          => ['nullable', 'numeric', 'min:0'],
            'pax_bayi'          => ['nullable', 'numeric', 'min:0'],
            'transit_dewasa'    => ['nullable', 'numeric', 'min:0'],
            'transit_anak'      => ['nullable', 'numeric', 'min:0'],
            'transit_bayi'      => ['nullable', 'numeric', 'min:0'],
            'bagasi_kg'         => ['nullable', 'numeric', 'min:0'],
            'kargo_kg'          => ['nullable', 'numeric', 'min:0'],
            'pos_kg'            => ['nullable', 'numeric', 'min:0'],
            'kapasitas'         => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'tgl_jadwal.required'        => 'Kolom "Tgl Jadwal" wajib diisi.',
            'operator_maskapai.required' => 'Kolom "Operator / Maskapai" wajib diisi.',
            'no_penerbangan.required'    => 'Kolom "No. Penerbangan" wajib diisi.',
            'tipe_armada.required'       => 'Kolom "Tipe Armada" wajib diisi.',
            'asal.required'              => 'Kolom "Asal" wajib diisi.',
            'tujuan.required'            => 'Kolom "Tujuan" wajib diisi.',
            'pergerakan.required'        => 'Kolom "Pergerakan" wajib diisi (isi D atau A).',
            'kegiatan.required'          => 'Kolom "Kegiatan" wajib diisi.',
            'cakupan.required'           => 'Kolom "Cakupan" wajib diisi.',
            'status.required'            => 'Kolom "Status" wajib diisi.',
        ];
    }

    // ──────────────────────────────────────
    // Performance Settings
    // ──────────────────────────────────────

    public function batchSize(): int
    {
        return 200;
    }

    public function chunkSize(): int
    {
        return 500;
    }

    // ──────────────────────────────────────
    // Getters untuk Ringkasan Import
    // ──────────────────────────────────────

    public function getSuccessCount(): int
    {
        return $this->successCount;
    }

    public function getSkipCount(): int
    {
        return $this->skipCount;
    }

    public function getAutoCreatedAirlineCount(): int
    {
        return $this->autoCreatedAirlineCount;
    }

    /**
     * Getter untuk batch ID — dipakai halaman Import untuk mencatat di activity log
     * dan memungkinkan penghapusan massal berdasarkan batch.
     */
    public function getBatchId(): string
    {
        return $this->batchId;
    }

    // ──────────────────────────────────────
    // Resolusi / Auto-create Maskapai
    // ──────────────────────────────────────

    /**
     * File rekap PLW hanya mencantumkan NAMA maskapai (bukan kode ICAO/IATA),
     * misalnya "Lion Air", "Batik Air", "Garuda Indonesia", "Susi Air",
     * "Intan Angkasa", "Express Air". Dicocokkan case-insensitive terhadap
     * brand_name; jika tidak ditemukan, buat record baru dengan flag
     * is_auto_generated = true agar admin bisa melengkapi kode ICAO/IATA
     * kemudian melalui menu Master Maskapai.
     */
    private function resolveOrCreateAirline(?string $name): ?Airline
    {
        $name = trim((string) $name);

        if (blank($name)) {
            return null;
        }

        $cacheKey = Str::lower($name);

        if (isset($this->airlineCache[$cacheKey])) {
            return Airline::find($this->airlineCache[$cacheKey]);
        }

        $airline = Airline::whereRaw('LOWER(brand_name) = ?', [$cacheKey])->first();

        if (! $airline) {
            $airline = Airline::create([
                'icao_code'          => null,
                'iata_code'          => null,
                'brand_name'         => $name,
                'operator_name'      => $name,
                'coverage'           => 'domestik',
                'operational_status' => 'beroperasi',
                'flight_frequency'   => 0,
                'is_auto_generated'  => true,
                'notes'              => 'Dibuat otomatis dari import Excel pada '
                    . now()->format('d/m/Y H:i') . '. Mohon lengkapi kode ICAO/IATA.',
            ]);

            $this->autoCreatedAirlineCount++;

            Log::info('Import FlightTraffic: maskapai baru dibuat otomatis.', [
                'brand_name' => $name,
                'airline_id' => $airline->id,
            ]);
        }

        $this->airlineCache[$cacheKey] = $airline->id;

        return $airline;
    }

    // ──────────────────────────────────────
    // Helper Parsing & Normalisasi
    // ──────────────────────────────────────

    private function nullableTrim(mixed $value): ?string
    {
        $value = trim((string) $value);
        return blank($value) ? null : $value;
    }

    private function parseDate(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        try {
            if (is_numeric($value)) {
                return Carbon::instance(
                    \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $value)
                )->toDateString();
            }

            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseTime(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        try {
            if (is_numeric($value) && (float) $value < 1) {
                return Carbon::instance(
                    \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $value)
                )->format('H:i:s');
            }

            return Carbon::parse($value)->format('H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Status pada file sumber: "REALISASI" (terlaksana sesuai rencana),
     * "DELAY" (mengalami keterlambatan), atau "BATAL"/"CANCEL" (dibatalkan).
     */
    private function normalizeStatus(string $value): string
    {
        return match (Str::lower(trim($value))) {
            'realisasi', 'realized', 'ontime', 'on time', 'tepat waktu' => 'Ontime',
            'delay', 'delayed', 'terlambat'                             => 'Delay',
            'batal', 'cancel', 'cancelled', 'dibatalkan'                => 'Cancel',
            default                                                      => 'Ontime',
        };
    }

    /**
     * Kegiatan pada file sumber: "BERJADWAL", "PERINTIS", "TIDAK BERJADWAL", dst.
     */
    private function normalizeActivity(string $value): string
    {
        $normalized = Str::lower(trim($value));

        return match (true) {
            str_contains($normalized, 'tidak berjadwal') || str_contains($normalized, 'unscheduled') => 'Tidak Berjadwal',
            str_contains($normalized, 'berjadwal') || str_contains($normalized, 'scheduled')          => 'Berjadwal',
            str_contains($normalized, 'extra')                                                        => 'Extra Flight',
            str_contains($normalized, 'perintis') || str_contains($normalized, 'pioneer')             => 'Perintis',
            str_contains($normalized, 'haji') || str_contains($normalized, 'hajj')                    => 'Haji',
            str_contains($normalized, 'militer') || str_contains($normalized, 'military')             => 'Militer',
            str_contains($normalized, 'charter')                                                       => 'Charter',
            str_contains($normalized, 'kargo') || str_contains($normalized, 'cargo')                  => 'Kargo',
            default                                                                                     => 'Berjadwal',
        };
    }

    private function normalizeCoverage(string $value): string
    {
        return match (Str::lower(trim($value))) {
            'domestik', 'domestic', 'dom'           => 'Domestik',
            'internasional', 'international', 'int' => 'Internasional',
            default                                  => 'Domestik',
        };
    }

    /**
     * Pergerakan pada file sumber memakai kode singkat: "D" (Departure/
     * Berangkat) dan "A" (Arrival/Datang).
     */
    private function normalizeMovement(string $value): string
    {
        return match (Str::lower(trim($value))) {
            'a', 'arrival', 'datang'      => 'Arrival',
            'd', 'departure', 'berangkat' => 'Departure',
            default                        => 'Departure',
        };
    }

    /**
     * Bandingkan kolom "Total Pax" dari file sumber (jika ada) dengan
     * hasil penjumlahan dewasa+anak+bayi. Hanya untuk logging peringatan,
     * TIDAK menggagalkan proses import.
     */
    private function assertTotalPaxConsistency(
        $row,
        int $paxAdult,
        int $paxChild,
        int $paxInfant,
        int $rowNumber
    ): void {
        if (! isset($row['total_pax']) || blank($row['total_pax'])) {
            return;
        }

        $expected = $paxAdult + $paxChild + $paxInfant;
        $actual   = (int) $row['total_pax'];

        if ($expected !== $actual) {
            Log::warning("Import FlightTraffic: Total Pax tidak konsisten pada baris {$rowNumber}.", [
                'total_pax_file'    => $actual,
                'total_pax_dihitung' => $expected,
            ]);
        }
    }
}
