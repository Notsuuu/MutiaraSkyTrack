<?php

namespace App\Console\Commands;

use App\Models\Airline;
use App\Models\FlightTraffic;
use App\Services\GoogleSheetsService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SyncFlightTrafficFromSheets extends Command
{
    protected $signature = 'flight:sync {--sheet= : Nama sheet tertentu saja}';
    protected $description = 'Sinkronkan data lalu lintas udara dari Google Sheets instansi';

    /** cache nama maskapai → id */
    private array $airlineCache = [];
    private int $created = 0;
    private int $updated = 0;
    private int $skipped = 0;

    public function handle(GoogleSheetsService $sheets): int
    {
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
        foreach ($rows as $i => $row) {
            $rowNumber = $i + 11; // baris asli di sheet

            if (empty(array_filter($row, fn ($v) => $v !== null && $v !== ''))) {
                continue;
            }

            try {
                $data = $this->mapRow($row);
                if (! $data) {
                    $this->skipped++;
                    continue;
                }

                // Kunci unik: schedule_date + flight_number + movement + airline
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
    }

    /**
     * Map kolom A:AA dari sheet ke field model.
     * Index 0-based, sesuai layout: NO di kolom A, dst.
     */
    private function mapRow(array $row): ?array
    {
        $get = fn (int $i) => trim((string) ($row[$i] ?? ''));

        $airlineName = $get(8); // I - Nama operator
        if ($airlineName === '' || $airlineName === '-') {
            return null;
        }

        $airline = $this->resolveOrCreateAirline($airlineName, $get(9));

        $scheduleDate = $this->parseDate($row[1] ?? null); // B
        if (! $scheduleDate) {
            return null;
        }

        return [
            'import_batch_id' => 'sheets-sync',

            'airline_id' => $airline->id,
            'created_by' => Auth::id() ?? 1,

            'schedule_date' => $scheduleDate,
            'schedule_time' => $this->parseTime($row[3] ?? null), // D
            'actual_date'   => $this->parseDate($row[2] ?? null), // C
            'actual_time'   => $this->parseTime($row[4] ?? null), // E

            'flight_number'         => strtoupper($get(13)),     // N
            'aircraft_registration' => strtoupper($get(14)) ?: null, // O
            'aircraft_type'         => strtoupper($get(15)) ?: null, // P
            'seat_capacity'         => (int) ($row[16] ?? 0),     // Q

            'flight_status' => $this->normalizeStatus($get(5)),   // F (kategori delay) + M
            'activity_type' => $this->normalizeActivity($get(10)), // K
            'coverage'      => $this->normalizeCoverage($get(11)), // L
            'movement'      => $this->normalizeMovement($get(17)), // R

            'origin_iata'      => strtoupper($get(6)),  // G
            'destination_iata' => strtoupper($get(7)),  // H

            'delay_category' => $get(5) ?: null,
            'delay_reason'   => $get(12) ?: null,       // M

            'pax_adult'  => (int) ($row[18] ?? 0),      // S
            'pax_child'  => (int) ($row[19] ?? 0),      // T
            'pax_infant' => (int) ($row[20] ?? 0),      // U

            'transit_pax_adult'  => (int) ($row[21] ?? 0), // V
            'transit_pax_child'  => (int) ($row[22] ?? 0), // W
            'transit_pax_infant' => (int) ($row[23] ?? 0), // X

            'baggage_kg' => (float) ($row[24] ?? 0),    // Y
            'cargo_kg'   => (float) ($row[25] ?? 0),    // Z
            'mail_kg'    => (float) ($row[26] ?? 0),    // AA
        ];
    }

    private function resolveOrCreateAirline(string $name, string $icao): Airline
    {
        $key = Str::lower($name);

        if (isset($this->airlineCache[$key])) {
            return Airline::find($this->airlineCache[$key]);
        }

        $airline = Airline::whereRaw('LOWER(brand_name) = ?', [$key])->first();

        if (! $airline) {
            $airline = Airline::create([
                'icao_code'          => $icao && $icao !== '-' ? strtoupper($icao) : null,
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

        $this->airlineCache[$key] = $airline->id;
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
            return Carbon::parse($v)->toDateString();
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
            return Carbon::parse($v)->format('H:i:s');
        } catch (\Throwable) { return null; }
    }

    private function normalizeStatus(string $v): string
    {
        $v = Str::lower($v);
        return match (true) {
            str_contains($v, 'cancel'), str_contains($v, 'batal') => 'Cancel',
            str_contains($v, 'delay'), str_contains($v, 'terlambat'),
            str_contains($v, 'manajemen'), str_contains($v, 'cuaca'),
            str_contains($v, 'teknis') => 'Delay',
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
            str_contains($v, 'militer')         => 'Militer',
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
            'a', 'arrival', 'datang'      => 'Arrival',
            default                        => 'Departure',
        };
    }
}
