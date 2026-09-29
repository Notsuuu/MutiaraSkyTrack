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
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class FlightTrafficImport implements WithMultipleSheets
{
    use Importable;

    private string $batchId;
    private int $successCount = 0;
    private int $skipCount = 0;
    private int $autoCreatedAirlineCount = 0;
    private array $rowErrors = [];

    public function __construct()
    {
        $this->batchId = (string) Str::uuid();
    }

    public function sheets(): array
    {
        $monthSheets = [
            'JANUARI', 'FEBRUARI', 'MARET', 'APRIL', 'MEI', 'JUNI',
            'JULI', 'AGUSTUS', 'SEPTEMBER', 'OKTOBER', 'NOVEMBER', 'DESEMBER',
        ];

        $sheetImports = [];
        foreach ($monthSheets as $sheetName) {
            $sheetImports[$sheetName] = new FlightTrafficMonthSheetImport($this);
        }

        return $sheetImports;
    }

    public function recordSuccess(): void { $this->successCount++; }
    public function recordSkip(int $row, string $message): void {
        $this->skipCount++;
        $this->rowErrors[] = ['row' => $row, 'message' => $message];
    }
    public function recordAutoCreatedAirline(): void { $this->autoCreatedAirlineCount++; }

    public function getSuccessCount(): int { return $this->successCount; }
    public function getSkipCount(): int { return $this->skipCount; }
    public function getAutoCreatedAirlineCount(): int { return $this->autoCreatedAirlineCount; }
    public function getBatchId(): string { return $this->batchId; }
    public function getRowErrors(): array { return $this->rowErrors; }
}

/**
 * Class pembaca sheet bulanan (mulai baris 11, indeks A-AA)
 */
class FlightTrafficMonthSheetImport implements ToCollection
{
    private FlightTrafficImport $parent;
    private array $airlineCache = [];

    public function __construct(FlightTrafficImport $parent)
    {
        $this->parent = $parent;

        foreach (Airline::all() as $a) {
            $this->airlineCache[Str::lower(trim($a->brand_name))] = $a;
        }
    }

    public function collection(Collection $rows): void
    {
        // Data dimulai dari baris 11 (indeks baris ke-10 pada collection 0-based)
        $dataRows = $rows->slice(10);

        foreach ($dataRows as $index => $row) {
            $rowNumber = $index + 11;

            $get = fn (int $idx) => trim((string) ($row[$idx] ?? ''));

            $airlineName = $get(8);  // Kolom I
            $flightNo    = $get(13); // Kolom N
            $schedDate   = $get(1);  // Kolom B

            // Abaikan baris template kosong di bagian bawah sheet
            if (($airlineName === '' || $airlineName === '-') && ($flightNo === '' || $flightNo === '-')) {
                continue;
            }

            if ($airlineName === '' || $airlineName === '-' || $schedDate === '') {
                $this->parent->recordSkip($rowNumber, 'Data maskapai atau tanggal jadwal tidak lengkap');
                continue;
            }

            try {
                $airline = $this->resolveOrCreateAirline($airlineName, $get(9));

                $scheduleDate = $this->parseDate($row[1] ?? null);
                $actualDate   = $this->parseDate($row[2] ?? null);
                $scheduleTime = $this->parseTime($row[3] ?? null);
                $actualTime   = $this->parseTime($row[4] ?? null);

                if (! $scheduleTime) {
                    $scheduleTime = $actualTime ?: '00:00:00';
                }

                $flightNumber = strtoupper($flightNo);
                if ($flightNumber === '' || $flightNumber === '-') {
                    $flightNumber = strtoupper($get(14)) ?: 'NO-FLIGHT';
                }

                FlightTraffic::updateOrCreate(
                    [
                        'schedule_date' => $scheduleDate,
                        'flight_number' => $flightNumber,
                        'movement'      => $this->normalizeMovement($get(17)),
                        'airline_id'    => $airline->id,
                        'schedule_time' => $scheduleTime,
                    ],
                    [
                        'import_batch_id'       => $this->parent->getBatchId(),
                        'created_by'            => Auth::id() ?? 1,
                        'actual_date'           => $actualDate,
                        'actual_time'           => $actualTime,
                        'aircraft_registration' => strtoupper($get(14)) ?: null,
                        'aircraft_type'         => strtoupper($get(15)) ?: null,
                        'seat_capacity'         => (int) ($row[16] ?? 0),
                        'flight_status'         => $this->normalizeStatus($get(5), $get(12)),
                        'activity_type'         => $this->normalizeActivity($get(10)),
                        'coverage'              => $this->normalizeCoverage($get(11)),
                        'origin_iata'           => strtoupper($get(6)) ?: 'PLW',
                        'destination_iata'      => strtoupper($get(7)) ?: 'PLW',
                        'delay_category'        => $get(5) ?: null,
                        'delay_reason'          => $get(12) ?: null,
                        'pax_adult'             => (int) ($row[18] ?? 0),
                        'pax_child'             => (int) ($row[19] ?? 0),
                        'pax_infant'            => (int) ($row[20] ?? 0),
                        'transit_pax_adult'     => (int) ($row[21] ?? 0),
                        'transit_pax_child'     => (int) ($row[22] ?? 0),
                        'transit_pax_infant'    => (int) ($row[23] ?? 0),
                        'baggage_kg'            => (float) ($row[24] ?? 0),
                        'cargo_kg'              => (float) ($row[25] ?? 0),
                        'mail_kg'               => (float) ($row[26] ?? 0),
                    ]
                );

                $this->parent->recordSuccess();
            } catch (\Throwable $e) {
                $this->parent->recordSkip($rowNumber, $e->getMessage());
                Log::warning("Import Excel baris {$rowNumber}: {$e->getMessage()}");
            }
        }
    }

    private function resolveOrCreateAirline(string $name, string $rawIcao): Airline
    {
        $rawIcao = strtoupper(trim($rawIcao));
        $icao = ($rawIcao !== '' && $rawIcao !== '-' && strlen($rawIcao) <= 4) ? $rawIcao : null;

        $cleanName = trim((string) preg_replace('/^(pt\.|pt\s+)/i', '', trim($name)));
        $keyName   = Str::lower($cleanName);
        $keyExact  = Str::lower(trim($name));

        if (isset($this->airlineCache[$keyExact])) return $this->airlineCache[$keyExact];
        if (isset($this->airlineCache[$keyName]))  return $this->airlineCache[$keyName];

        $airline = Airline::whereRaw('LOWER(brand_name) = ?', [$keyExact])
            ->orWhereRaw('LOWER(brand_name) = ?', [$keyName])
            ->first();

        if (! $airline && $icao) {
            $airlineByIcao = Airline::where('icao_code', $icao)->first();
            if ($airlineByIcao && str_contains(Str::lower($airlineByIcao->brand_name), Str::lower(substr($cleanName, 0, 5)))) {
                $airline = $airlineByIcao;
            }
        }

        if (! $airline) {
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
                'notes'              => 'Dibuat otomatis dari import file Excel spreadsheet ' . now()->format('d/m/Y H:i'),
            ]);

            $this->parent->recordAutoCreatedAirline();
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
