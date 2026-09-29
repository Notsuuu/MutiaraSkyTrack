<?php

namespace App\Filament\Pages;

use App\Models\Airline;
use App\Models\FlightTraffic;
use Carbon\Carbon;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Facades\Excel;
use UnitEnum;

class LaporanMaskapai extends Page
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-paper-airplane';

    protected static ?string $navigationLabel = 'Rekap Maskapai';

    protected static string | UnitEnum | null $navigationGroup = 'Laporan';

    protected static ?int $navigationSort = 101;

    protected static ?string $title = 'Laporan & Rekap per Maskapai';

    protected string $view = 'filament.pages.laporan-maskapai';

    /** ID Maskapai yang dipilih */
    public ?int $airline_id = null;

    /** Mode: 'bulanan' atau 'harian' */
    public string $mode = 'bulanan';

    /** Filter tahun */
    public int $tahun;

    /** Filter bulan (1-12) */
    public int $bulan;

    public function mount(): void
    {
        $this->tahun = (int) now()->year;
        $this->bulan = (int) now()->month;

        // Pilih maskapai pertama yang ada sebagai default
        $firstAirline = Airline::query()->orderBy('brand_name')->first();
        $this->airline_id = $firstAirline?->id;
    }

    public function setMode(string $mode): void
    {
        if (in_array($mode, ['bulanan', 'harian'])) {
            $this->mode = $mode;
        }
    }

    public function getSelectedAirlineProperty(): ?Airline
    {
        return $this->airline_id ? Airline::find($this->airline_id) : null;
    }

    // ══════════════════════════════════════════════
    // TABEL A — REKAP LALU LINTAS UTAMA MASKAPAI
    // ══════════════════════════════════════════════

    public function getRekapBulanan(): Collection
    {
        if (! $this->airline_id) {
            return collect();
        }

        $months = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret',
            4 => 'April', 5 => 'Mei', 6 => 'Juni',
            7 => 'Juli', 8 => 'Agustus', 9 => 'September',
            10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        $rows = collect();

        foreach ($months as $m => $label) {
            $rows->push([
                'label' => $label,
                'bulan' => $m,
                'data'  => $this->aggregateData(
                    FlightTraffic::query()
                        ->where('airline_id', $this->airline_id)
                        ->whereYear('schedule_date', $this->tahun)
                        ->whereMonth('schedule_date', $m)
                ),
            ]);
        }

        return $rows;
    }

    public function getRekapHarian(): Collection
    {
        if (! $this->airline_id) {
            return collect();
        }

        $daysInMonth = Carbon::createFromDate($this->tahun, $this->bulan, 1)->daysInMonth;
        $rows = collect();

        for ($d = 1; $d <= $daysInMonth; $d++) {
            $rows->push([
                'label' => 'Tgl ' . $d,
                'hari'  => $d,
                'data'  => $this->aggregateData(
                    FlightTraffic::query()
                        ->where('airline_id', $this->airline_id)
                        ->whereYear('schedule_date', $this->tahun)
                        ->whereMonth('schedule_date', $this->bulan)
                        ->whereDay('schedule_date', $d)
                ),
            ]);
        }

        return $rows;
    }

    protected function aggregateData($query): array
    {
        $traffics = (clone $query)->get();

        $arrival   = $traffics->where('movement', 'Arrival');
        $departure = $traffics->where('movement', 'Departure');

        $arrCount = $arrival->count();
        $depCount = $departure->count();
        $totalFlights = $traffics->count();

        $dom  = $traffics->where('coverage', 'Domestik')->count();
        $intl = $traffics->where('coverage', 'Internasional')->count();

        $paxArr   = $arrival->sum(fn ($f) => (int) ($f->pax_adult + $f->pax_child + $f->pax_infant));
        $paxDep   = $departure->sum(fn ($f) => (int) ($f->pax_adult + $f->pax_child + $f->pax_infant));
        $paxAdult = (int) $traffics->sum('pax_adult');
        $paxChild = (int) $traffics->sum('pax_child');
        $paxInfant = (int) $traffics->sum('pax_infant');
        $paxTotal = $paxAdult + $paxChild + $paxInfant;
        $paxDom   = (int) $traffics->where('coverage', 'Domestik')->sum(fn ($f) => $f->pax_adult + $f->pax_child + $f->pax_infant);

        $trArr   = (int) $arrival->sum(fn ($f) => (int) ($f->transit_pax_adult + $f->transit_pax_child + $f->transit_pax_infant));
        $trDep   = (int) $departure->sum(fn ($f) => (int) ($f->transit_pax_adult + $f->transit_pax_child + $f->transit_pax_infant));
        $trTotal = $trArr + $trDep;

        $bagArr = (float) $arrival->sum('baggage_kg');
        $bagDep = (float) $departure->sum('baggage_kg');

        $cargoArr = (float) $arrival->sum('cargo_kg');
        $cargoDep = (float) $departure->sum('cargo_kg');

        $mailArr = (float) $arrival->sum('mail_kg');
        $mailDep = (float) $departure->sum('mail_kg');

        $delayed   = $traffics->where('flight_status', 'Delay')->count();
        $cancelled = $traffics->where('flight_status', 'Cancel')->count();

        return [
            'arr'         => $arrCount,
            'dep'         => $depCount,
            'total'       => $totalFlights,
            'dom'         => $dom,
            'intl'        => $intl,
            'pax_arr'     => $paxArr,
            'pax_dep'     => $paxDep,
            'pax_adult'   => $paxAdult,
            'pax_child'   => $paxChild,
            'pax_infant'  => $paxInfant,
            'pax_total'   => $paxTotal,
            'pax_dom'     => $paxDom,
            'tr_arr'      => $trArr,
            'tr_dep'      => $trDep,
            'tr_total'    => $trTotal,
            'bag_arr'     => $bagArr,
            'bag_dep'     => $bagDep,
            'bag_total'   => $bagArr + $bagDep,
            'cargo_arr'   => $cargoArr,
            'cargo_dep'   => $cargoDep,
            'cargo_total' => $cargoArr + $cargoDep,
            'mail_arr'    => $mailArr,
            'mail_dep'    => $mailDep,
            'mail_total'  => $mailArr + $mailDep,
            'delayed'     => $delayed,
            'cancelled'   => $cancelled,
        ];
    }

    // ══════════════════════════════════════════════
    // TABEL B — BREAKDOWN RUTE PENERBANGAN MASKAPAI
    // ══════════════════════════════════════════════

    public function getBreakdownRute(): Collection
    {
        if (! $this->airline_id) {
            return collect();
        }

        $query = FlightTraffic::query()
            ->where('airline_id', $this->airline_id)
            ->whereYear('schedule_date', $this->tahun);

        if ($this->mode === 'harian') {
            $query->whereMonth('schedule_date', $this->bulan);
        }

        return $query->selectRaw('
                origin_iata,
                destination_iata,
                movement,
                COUNT(*) as total_flight,
                SUM(COALESCE(pax_adult, 0) + COALESCE(pax_child, 0) + COALESCE(pax_infant, 0)) as total_pax,
                SUM(COALESCE(cargo_kg, 0)) as total_cargo
            ')
            ->groupBy('origin_iata', 'destination_iata', 'movement')
            ->orderByDesc('total_flight')
            ->get();
    }

    // ══════════════════════════════════════════════
    // TABEL C — BREAKDOWN KEGIATAN MASKAPAI
    // ══════════════════════════════════════════════

    public function getBreakdownKegiatan(): Collection
    {
        if (! $this->airline_id) {
            return collect();
        }

        $categories = ['Berjadwal', 'Tidak Berjadwal', 'Extra Flight', 'Perintis', 'Haji', 'Militer', 'Bukan Niaga'];

        $query = FlightTraffic::query()
            ->where('airline_id', $this->airline_id)
            ->whereYear('schedule_date', $this->tahun);

        if ($this->mode === 'harian') {
            $query->whereMonth('schedule_date', $this->bulan);
            $raw = $query->selectRaw('DAY(schedule_date) as periode, activity_type, COUNT(*) as total')
                ->groupBy('periode', 'activity_type')
                ->get()
                ->groupBy('periode');

            $limit = Carbon::createFromDate($this->tahun, $this->bulan, 1)->daysInMonth;
            $prefix = 'Tgl ';
        } else {
            $raw = $query->selectRaw('MONTH(schedule_date) as periode, activity_type, COUNT(*) as total')
                ->groupBy('periode', 'activity_type')
                ->get()
                ->groupBy('periode');

            $limit = 12;
            $months = [
                1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
                7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
            ];
        }

        $rows = collect();

        for ($p = 1; $p <= $limit; $p++) {
            $periodData = $raw->get($p, collect())->keyBy('activity_type');

            $row = [
                'label' => $this->mode === 'harian' ? ($prefix . $p) : $months[$p],
                'periode' => $p,
                'total' => 0,
            ];

            foreach ($categories as $cat) {
                $count = $periodData->get($cat)->total ?? 0;
                $row[$cat] = $count;
                $row['total'] += $count;
            }

            $rows->push($row);
        }

        return $rows;
    }

    // ══════════════════════════════════════════════
    // EXPORT EXCEL KHUSUS MASKAPAI
    // ══════════════════════════════════════════════

    public function exportExcel()
    {
        $airline = $this->selectedAirline;
        $airlineCode = $airline ? ($airline->icao_code ?: str_replace(' ', '-', $airline->brand_name)) : 'ALL';

        $filename = $this->mode === 'bulanan'
            ? "Rekap-LLAU-{$airlineCode}-Bulanan-{$this->tahun}.xlsx"
            : "Rekap-LLAU-{$airlineCode}-Harian-{$this->tahun}-{$this->bulan}.xlsx";

        $export = new class($this->mode, $this->tahun, $this->bulan, $this) implements FromArray, WithHeadings {
            public function __construct(
                protected string $mode,
                protected int $tahun,
                protected int $bulan,
                protected LaporanMaskapai $page,
            ) {}

            public function headings(): array
            {
                return [
                    'No', $this->mode === 'bulanan' ? 'Bulan' : 'Tanggal',
                    'Arr', 'Dep', 'Total', 'Dom', 'Intl',
                    'Pax Arr', 'Pax Dep', 'Dewasa', 'Anak', 'Bayi', 'Total Pax', 'Dom',
                    'Transit Arr', 'Transit Dep', 'Transit Total',
                    'Bagasi Arr', 'Bagasi Dep', 'Bagasi Total',
                    'Kargo Arr', 'Kargo Dep', 'Kargo Total',
                    'Pos Arr', 'Pos Dep', 'Pos Total',
                    'Delay', 'Cancel',
                ];
            }

            public function array(): array
            {
                $rows = $this->mode === 'bulanan'
                    ? $this->page->getRekapBulanan()
                    : $this->page->getRekapHarian();

                $result = [];
                $no = 1;

                foreach ($rows as $row) {
                    $d = $row['data'];
                    $result[] = [
                        $no++,
                        $row['label'],
                        $d['arr'], $d['dep'], $d['total'], $d['dom'], $d['intl'],
                        $d['pax_arr'], $d['pax_dep'], $d['pax_adult'], $d['pax_child'], $d['pax_infant'],
                        $d['pax_total'], $d['pax_dom'],
                        $d['tr_arr'], $d['tr_dep'], $d['tr_total'],
                        $d['bag_arr'], $d['bag_dep'], $d['bag_total'],
                        $d['cargo_arr'], $d['cargo_dep'], $d['cargo_total'],
                        $d['mail_arr'], $d['mail_dep'], $d['mail_total'],
                        $d['delayed'], $d['cancelled'],
                    ];
                }

                return $result;
            }
        };

        return Excel::download($export, $filename);
    }
}
