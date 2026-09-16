<?php

namespace App\Filament\Pages;

use App\Models\Airline;
use App\Models\FlightTraffic;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Facades\Excel;
use UnitEnum;

class Laporan extends Page
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static ?string $navigationLabel = 'Laporan';

    protected static string | UnitEnum | null $navigationGroup = null;

    protected static ?int $navigationSort = 100;

    protected static ?string $title = 'Laporan & Rekap LLAU';

    protected string $view = 'filament.pages.laporan';

    /** Mode: 'bulanan' atau 'harian' */
    public string $mode = 'bulanan';

    /** Filter tahun */
    public int $tahun;

    /** Filter bulan (1-12), hanya untuk mode harian */
    public int $bulan;

    public function mount(): void
    {
        $this->tahun = (int) now()->year;
        $this->bulan = (int) now()->month;
    }

    public function setMode(string $mode): void
    {
        if (in_array($mode, ['bulanan', 'harian'])) {
            $this->mode = $mode;
        }
    }

    // ══════════════════════════════════════════════
    // TABEL A — REKAP LALU LINTAS UTAMA
    // ══════════════════════════════════════════════

    public function getRekapBulanan(): Collection
    {
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
                        ->whereYear('schedule_date', $this->tahun)
                        ->whereMonth('schedule_date', $m)
                ),
            ]);
        }

        return $rows;
    }

    public function getRekapHarian(): Collection
    {
        $daysInMonth = now()->setYear($this->tahun)->setMonth($this->bulan)->daysInMonth;
        $rows = collect();

        for ($d = 1; $d <= $daysInMonth; $d++) {
            $rows->push([
                'label' => 'Tgl ' . $d,
                'hari'  => $d,
                'data'  => $this->aggregateData(
                    FlightTraffic::query()
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

        $dom = $traffics->where('coverage', 'Domestik')->count();
        $intl = $traffics->where('coverage', 'Internasional')->count();

        $paxArr = $arrival->sum(fn ($f) => $f->total_pax);
        $paxDep = $departure->sum(fn ($f) => $f->total_pax);
        $paxAdult = $traffics->sum('pax_adult');
        $paxChild = $traffics->sum('pax_child');
        $paxInfant = $traffics->sum('pax_infant');
        $paxTotal = $paxAdult + $paxChild + $paxInfant;
        $paxDom = $traffics->where('coverage', 'Domestik')->sum(fn ($f) => $f->total_pax);

        $trArr = $arrival->sum(fn ($f) => $f->total_transit_pax);
        $trDep = $departure->sum(fn ($f) => $f->total_transit_pax);
        $trTotal = $trArr + $trDep;

        $bagArr = (float) $arrival->sum('baggage_kg');
        $bagDep = (float) $departure->sum('baggage_kg');

        $cargoArr = (float) $arrival->sum('cargo_kg');
        $cargoDep = (float) $departure->sum('cargo_kg');

        $mailArr = (float) $arrival->sum('mail_kg');
        $mailDep = (float) $departure->sum('mail_kg');

        $delayed = $traffics->where('flight_status', 'Delay')->count();
        $cancelled = $traffics->where('flight_status', 'Cancel')->count();

        return [
            'arr' => $arrCount,
            'dep' => $depCount,
            'total' => $totalFlights,
            'dom' => $dom,
            'intl' => $intl,
            'pax_arr' => (int) $paxArr,
            'pax_dep' => (int) $paxDep,
            'pax_adult' => (int) $paxAdult,
            'pax_child' => (int) $paxChild,
            'pax_infant' => (int) $paxInfant,
            'pax_total' => (int) $paxTotal,
            'pax_dom' => (int) $paxDom,
            'tr_arr' => (int) $trArr,
            'tr_dep' => (int) $trDep,
            'tr_total' => (int) $trTotal,
            'bag_arr' => (float) $bagArr,
            'bag_dep' => (float) $bagDep,
            'bag_total' => $bagArr + $bagDep,
            'cargo_arr' => (float) $cargoArr,
            'cargo_dep' => (float) $cargoDep,
            'cargo_total' => $cargoArr + $cargoDep,
            'mail_arr' => (float) $mailArr,
            'mail_dep' => (float) $mailDep,
            'mail_total' => $mailArr + $mailDep,
            'delayed' => $delayed,
            'cancelled' => $cancelled,
        ];
    }

    // ══════════════════════════════════════════════
    // TABEL B — MATRIKS PRODUKSI PER MASKAPAI
    // ══════════════════════════════════════════════

    public function getMatriksMaskapai(): array
    {
        $airlines = Airline::query()
            ->whereHas('flightTraffics', function ($q) {
                $q->whereYear('schedule_date', $this->tahun);
            })
            ->orderBy('brand_name')
            ->get();

        $airlineIds = $airlines->pluck('id')->toArray();

        $raw = FlightTraffic::query()
            ->selectRaw('
                MONTH(schedule_date) as bulan,
                airline_id,
                COUNT(*) as flight_count,
                SUM(pax_adult + pax_child + pax_infant) as pax_count
            ')
            ->whereYear('schedule_date', $this->tahun)
            ->whereIn('airline_id', $airlineIds)
            ->groupBy('bulan', 'airline_id')
            ->get()
            ->groupBy('bulan');

        $result = [];
        for ($m = 1; $m <= 12; $m++) {
            $row = [
                'bulan' => $m,
                'airlines' => [],
            ];

            $monthData = $raw->get($m, collect())->keyBy('airline_id');

            foreach ($airlines as $airline) {
                $data = $monthData->get($airline->id);
                $row['airlines'][$airline->id] = [
                    'flight' => $data->flight_count ?? 0,
                    'pax' => (int) ($data->pax_count ?? 0),
                ];
            }

            $result[] = $row;
        }

        return [
            'airlines' => $airlines,
            'rows' => $result,
        ];
    }

    // ══════════════════════════════════════════════
    // TABEL C — BREAKDOWN KEGIATAN PENERBANGAN
    // ══════════════════════════════════════════════

    public function getBreakdownKegiatan(): Collection
    {
        $categories = ['Berjadwal', 'Tidak Berjadwal', 'Extra Flight', 'Perintis', 'Haji', 'Militer', 'Bukan Niaga'];

        $raw = FlightTraffic::query()
            ->selectRaw('MONTH(schedule_date) as bulan, activity_type, COUNT(*) as total')
            ->whereYear('schedule_date', $this->tahun)
            ->groupBy('bulan', 'activity_type')
            ->get()
            ->groupBy('bulan');

        $rows = collect();

        for ($m = 1; $m <= 12; $m++) {
            $monthData = $raw->get($m, collect())->keyBy('activity_type');

            $row = ['bulan' => $m, 'total' => 0];
            foreach ($categories as $cat) {
                $count = $monthData->get($cat)->total ?? 0;
                $row[$cat] = $count;
                $row['total'] += $count;
            }

            $rows->push($row);
        }

        return $rows;
    }

    // ══════════════════════════════════════════════
    // EXPORT EXCEL
    // ══════════════════════════════════════════════

    public function exportExcel()
    {
        $filename = $this->mode === 'bulanan'
            ? "Rekap-LLAU-Bulanan-{$this->tahun}.xlsx"
            : "Rekap-LLAU-Harian-{$this->tahun}-{$this->bulan}.xlsx";

        $export = new class($this->mode, $this->tahun, $this->bulan, $this) implements FromArray, WithHeadings {
            public function __construct(
                protected string $mode,
                protected int $tahun,
                protected int $bulan,
                protected Laporan $page,
            ) {}

            public function headings(): array
            {
                if ($this->mode === 'bulanan') {
                    return [
                        'No', 'Bulan',
                        'Arr', 'Dep', 'Total', 'Dom', 'Intl',
                        'Pax Arr', 'Pax Dep', 'Dewasa', 'Anak', 'Bayi', 'Total Pax', 'Dom',
                        'Transit Arr', 'Transit Dep', 'Transit Total',
                        'Bagasi Arr', 'Bagasi Dep', 'Bagasi Total',
                        'Kargo Arr', 'Kargo Dep', 'Kargo Total',
                        'Pos Arr', 'Pos Dep', 'Pos Total',
                    ];
                }

                return [
                    'No', 'Tanggal',
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
                    $line = [$no++, $row['label']];

                    $line = array_merge($line, [
                        $d['arr'], $d['dep'], $d['total'], $d['dom'], $d['intl'],
                        $d['pax_arr'], $d['pax_dep'], $d['pax_adult'], $d['pax_child'], $d['pax_infant'],
                        $d['pax_total'], $d['pax_dom'],
                        $d['tr_arr'], $d['tr_dep'], $d['tr_total'],
                        $d['bag_arr'], $d['bag_dep'], $d['bag_total'],
                        $d['cargo_arr'], $d['cargo_dep'], $d['cargo_total'],
                        $d['mail_arr'], $d['mail_dep'], $d['mail_total'],
                    ]);

                    if ($this->mode === 'harian') {
                        $line[] = $d['delayed'];
                        $line[] = $d['cancelled'];
                    }

                    $result[] = $line;
                }

                return $result;
            }
        };

        return Excel::download($export, $filename);
    }
}
