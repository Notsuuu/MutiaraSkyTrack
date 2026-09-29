<?php

namespace App\Console\Commands;

use App\Models\Airline;
use App\Models\FlightTraffic;
use App\Services\GoogleSheetsService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SyncFlightTrafficFromSheets extends Command
{
    protected $signature = 'flight:sync {--sheet= : Nama sheet tertentu saja}';
    protected $description = 'Sinkronkan data lalu lintas udara dari Google Sheets instansi';

    /** Cache Model Airline: string (lowercase) => Airline Model */
    private array $airlineCache = [];
    private int $created = 0;
    private int $updated = 0;
    private int $skipped = 0;

    public function handle(GoogleSheetsService $sheets): int
    {
        DB::disableQueryLog();

        // Preload semua maskapai yang ada ke memori
        foreach (Airline::all() as $a) {
            $this->airlineCache[Str::lower(trim($a->brand_name))] = $a;
            if ($a->icao_code) {
                $this->airlineCache['icao_' . Str::upper(trim($a->icao_code))] = $a;
            }
        }

        $sheetNames = $this->option('sheet')
            ? [$this->option('sheet')]
            : config('services.google_sheets.sheet_names');

        foreach ($sheetNames as $sheetName) {
            $this->info("→ Sheet: {$sheetName}");
            try {
                $rows = $sheets->getRows($sheetName);
                $this->processRows($rows, $sheetName);
            } catch (\Throwable $e) {
                Log::error("Gagal sync sheet {$sheetName}", ['error' => $e->getMessage()]);
                $this->error("  Gagal: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info("Selesai. Created: {$this->created}, Updated: {$this->updated}, Skipped: {$this->skipped}");

        return self::SUCCESS;
    }

    private function processRows(array $rows, string $sheetName): void
    {
        DB::transaction(function () use ($rows, $sheetName) {
            foreach ($rows as $i => $row) {
                $rowNumber = $i + 11;

                $get = fn (int $idx) => trim((string) ($row[$idx] ?? ''));

                $airlineName = $get(8);  // Kolom I: Operator
                $flightNo    = $get(13); // Kolom N: Nomor Penerbangan
                $schedDate   = $get(1);  // Kolom B: Tanggal Jadwal

                // 1. Abaikan baris template kosong (misal baris hanya berisi nomor urut tanpa data)
                if (($airlineName === '' || $airlineName === '-') && ($flightNo === '' || $flightNo === '-')) {
                    continue;
                }

                // Baris memiliki nomor penerbangan atau maskapai tapi tidak valid tanggalnya
                if ($airlineName === '' || $airlineName === '-' || $schedDate === '') {
                    $this->skipped++;
                    continue;
                }

                try {
                    $data = $this->mapRow($row);
                    if (! $data) {
                        $this->skipped++;
                        continue;
                    }

                    $flight = FlightTraffic::updateOrCreate(
                        [
                            'schedule_date' => $data['schedule_date'],
                            'flight_number' => $data['flight_number'],
                            'movement'      => $data['movement'],
                            'airline_id'    => $data['airline_id'],
                            'schedule_time' => $data['schedule_time'],
                        ],
                        $data
                    );

                    $flight->wasRecentlyCreated ? $this->created++ : $this->updated++;
                } catch (\Throwable $e) {
                    $this->skipped++;
                    Log::warning("Sheet {$sheetName} baris {$rowNumber}: {$e->getMessage()}");
                }
            }
        });
    }

    private function mapRow(array $row): ?array
    {
        $get = fn (int $i) => trim((string) ($row[$i] ?? ''));

        $airlineName = $get(8);
        if ($airlineName === '' || $airlineName === '-') {
            return null;
        }

        $airline = $this->resolveOrCreateAirline($airlineName, $get(9));

        $scheduleDate = $this->parseDate($row[1] ?? null);
        if (! $scheduleDate) {
            return null;
        }

        // Parsing waktu & fallback bila null
        $scheduleTime = $this->parseTime($row[3] ?? null);
        $actualTime   = $this->parseTime($row[4] ?? null);
        $actualDate   = $this->parseDate($row[2] ?? null);

        // Jika schedule_time kosong (penerbangan cancel / perintis), gunakan actual_time atau 00:00:00
        if (! $scheduleTime) {
            $scheduleTime = $actualTime ?: '00:00:00';
        }

        $flightNumber = strtoupper($get(13));
        if ($flightNumber === '' || $flightNumber === '-') {
            $flightNumber = strtoupper($get(14)) ?: 'NO-FLIGHT'; // Fallback ke registrasi pesawat jika ada
        }

        return [
            'import_batch_id' => 'sheets-sync',
            'airline_id'      => $airline->id,
            'created_by'      => Auth::id() ?? 1,

            'schedule_date'   => $scheduleDate,
            'schedule_time'   => $scheduleTime,
            'actual_date'     => $actualDate,
            'actual_time'     => $actualTime,

            'flight_number'         => $flightNumber,
            'aircraft_registration' => strtoupper($get(14)) ?: null,
            'aircraft_type'         => strtoupper($get(15)) ?: null,
            'seat_capacity'         => (int) ($row[16] ?? 0),

            'flight_status'   => $this->normalizeStatus($get(5), $get(12)),
            'activity_type'   => $this->normalizeActivity($get(10)),
            'coverage'        => $this->normalizeCoverage($get(11)),
            'movement'        => $this->normalizeMovement($get(17)),

            'origin_iata'      => strtoupper($get(6)) ?: 'PLW',
            'destination_iata' => strtoupper($get(7)) ?: 'PLW',

            'delay_category'   => $get(5) ?: null,
            'delay_reason'     => $get(12) ?: null,

            'pax_adult'        => (int) ($row[18] ?? 0),
            'pax_child'        => (int) ($row[19] ?? 0),
            'pax_infant'       => (int) ($row[20] ?? 0),

            'transit_pax_adult'  => (int) ($row[21] ?? 0),
            'transit_pax_child'  => (int) ($row[22] ?? 0),
            'transit_pax_infant' => (int) ($row[23] ?? 0),

            'baggage_kg'       => (float) ($row[24] ?? 0),
            'cargo_kg'         => (float) ($row[25] ?? 0),
            'mail_kg'          => (float) ($row[26] ?? 0),
        ];
    }

    private function resolveOrCreateAirline(string $name, string $rawIcao): Airline
    {
        $rawIcao = strtoupper(trim($rawIcao));
        // Kode ICAO dibatasi maksimal 4 karakter sesuai skema DB
        $icao = ($rawIcao !== '' && $rawIcao !== '-' && strlen($rawIcao) <= 4) ? $rawIcao : null;

        // Bersihkan prefiks PT / PT.
        $cleanName = trim((string) preg_replace('/^(pt\.|pt\s+)/i', '', trim($name)));
        $keyName   = Str::lower($cleanName);
        $keyExact  = Str::lower(trim($name));

        // 1. Cek dari memori cache
        if (isset($this->airlineCache[$keyExact])) {
            return $this->airlineCache[$keyExact];
        }
        if (isset($this->airlineCache[$keyName])) {
            return $this->airlineCache[$keyName];
        }

        // 2. Cek database berdasarkan nama
        $airline = Airline::whereRaw('LOWER(brand_name) = ?', [$keyExact])
            ->orWhereRaw('LOWER(brand_name) = ?', [$keyName])
            ->first();

        // 3. Jika belum ditemukan tapi ICAO ada, cek apakah ICAO sudah dipakai maskapai lain
        if (! $airline && $icao) {
            $airlineByIcao = Airline::where('icao_code', $icao)->first();
            // Jika nama mirip (misal Sriwijaya Air), gunakan maskapai tersebut
            if ($airlineByIcao && str_contains(Str::lower($airlineByIcao->brand_name), Str::lower(substr($cleanName, 0, 5)))) {
                $airline = $airlineByIcao;
            }
        }

        // 4. Jika tetap tidak ada, buat baru dengan pengecekan duplikasi ICAO
        if (! $airline) {
            // Hindari duplikasi jika kode ICAO sudah digunakan
            if ($icao && Airline::where('icao_code', $icao)->exists()) {
                $icao = null;
            }

            $airline = Airline::create([
                'icao_code'          => $icao,
                'iata_code'          => null,
                'brand_name'         => $name,
                'operator_name'      => $name,
                'coverage'           => 'domestik',
                'operational_status' => 'beroperasi',
                'flight_frequency'   => 0,
                'is_auto_generated'  => true,
                'notes'              => 'Dibuat otomatis dari sync Google Sheets ' . now()->format('d/m/Y H:i'),
            ]);
        }

        $this->airlineCache[$keyExact] = $airline;
        $this->airlineCache[$keyName]  = $airline;

        return $airline;
    }

    private function parseDate(mixed $v): ?string
    {
        if (blank($v)) return null;
        try {
            if (is_numeric($v)) {
                return Carbon::instance(
                    \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $v)
                )->toDateString();
            }
            $str = trim((string) $v);
            if (preg_match('/^(\d{1,2})-(\d{1,2})-(\d{4})$/', $str, $m)) {
                return Carbon::createFromDate((int) $m[3], (int) $m[2], (int) $m[1])->toDateString();
            }
            return Carbon::parse($str)->toDateString();
        } catch (\Throwable) { return null; }
    }

    private function parseTime(mixed $v): ?string
    {
        if (blank($v)) return null;
        try {
            if (is_numeric($v) && (float) $v < 1) {
                return Carbon::instance(
                    \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $v)
                )->format('H:i:s');
            }
            // Ubah typo pemisah titik koma ';' menjadi titik dua ':'
            $str = str_replace(';', ':', trim((string) $v));
            if (preg_match('/^\d{1,2}:\d{1,2}(:\d{1,2})?$/', $str)) {
                return Carbon::parse($str)->format('H:i:s');
            }
            return Carbon::parse($str)->format('H:i:s');
        } catch (\Throwable) { return null; }
    }

    private function normalizeStatus(string $cat, string $reason = ''): string
    {
        $v = Str::lower($cat . ' ' . $reason);
        return match (true) {
            str_contains($v, 'cancel'), str_contains($v, 'batal') => 'Cancel',
            str_contains($v, 'delay'), str_contains($v, 'terlambat'),
            str_contains($v, 'manajemen'), str_contains($v, 'cuaca'),
            str_contains($v, 'teknis'), str_contains($v, 'operasional') => 'Delay',
            default => 'Ontime',
        };
    }

    private function normalizeActivity(string $v): string
    {
        $v = Str::lower($v);
        return match (true) {
            str_contains($v, 'tidak berjadwal') => 'Tidak Berjadwal',
            str_contains($v, 'perintis')        => 'Perintis',
            str_contains($v, 'extra')           => 'Extra Flight',
            str_contains($v, 'haji')            => 'Haji',
            str_contains($v, 'militer'), str_contains($v, 'bukan niaga') => 'Militer',
            str_contains($v, 'charter')         => 'Charter',
            str_contains($v, 'kargo')           => 'Kargo',
            default                             => 'Berjadwal',
        };
    }

    private function normalizeCoverage(string $v): string
    {
        return str_contains(Str::lower($v), 'internasional') ? 'Internasional' : 'Domestik';
    }

    private function normalizeMovement(string $v): string
    {
        return match (Str::lower(trim($v))) {
            'a', 'arrival', 'datang' => 'Arrival',
            default                  => 'Departure',
        };
    }
}
