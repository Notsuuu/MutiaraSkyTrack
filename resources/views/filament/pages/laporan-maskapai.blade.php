<x-filament-panels::page>
    @php
        $cssPath = public_path('css/filament/laporan.css');
        $cssVersion = file_exists($cssPath) ? filemtime($cssPath) : time();
        $airline = $this->selectedAirline;
    @endphp
    <link rel="stylesheet" href="{{ asset('css/filament/laporan.css') }}?v={{ $cssVersion }}">

    {{-- ═══════════════════════════════════════════════════════
         TOOLBAR
         ═══════════════════════════════════════════════════════ --}}
    <div class="lap-toolbar">
        <div class="lap-tabs">
            <button
                type="button"
                class="lap-tab @if($mode === 'bulanan') active @endif"
                wire:click="setMode('bulanan')"
            >
                📅 Rekap Bulanan
            </button>
            <button
                type="button"
                class="lap-tab @if($mode === 'harian') active @endif"
                wire:click="setMode('harian')"
            >
                📆 Rekap Harian
            </button>
        </div>

        <div class="lap-actions">
            {{-- Filter Maskapai --}}
            <select class="lap-select" wire:model.live="airline_id" style="font-weight: 600; min-width: 200px;">
                @foreach (\App\Models\Airline::orderBy('brand_name')->get() as $a)
                    <option value="{{ $a->id }}">
                        ✈️ {{ $a->brand_name }} {{ $a->icao_code ? "({$a->icao_code})" : '' }}
                    </option>
                @endforeach
            </select>

            {{-- Filter Tahun --}}
            <select class="lap-select" wire:model.live="tahun">
                @foreach (range(now()->year, 2020) as $y)
                    <option value="{{ $y }}">Tahun {{ $y }}</option>
                @endforeach
            </select>

            {{-- Filter Bulan (hanya mode harian) --}}
            @if ($mode === 'harian')
                <select class="lap-select" wire:model.live="bulan">
                    @foreach ([
                        1 => 'Januari', 2 => 'Februari', 3 => 'Maret',
                        4 => 'April', 5 => 'Mei', 6 => 'Juni',
                        7 => 'Juli', 8 => 'Agustus', 9 => 'September',
                        10 => 'Oktober', 11 => 'November', 12 => 'Desember',
                    ] as $num => $label)
                        <option value="{{ $num }}">{{ $label }}</option>
                    @endforeach
                </select>
            @endif

            {{-- Tombol Export --}}
            <button
                type="button"
                class="lap-btn lap-btn-excel"
                wire:click="exportExcel"
            >
                📊 Ekspor Excel
            </button>
            <button
                type="button"
                class="lap-btn lap-btn-image"
                onclick="window.print()"
            >
                🖼️ Ekspor Gambar
            </button>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════
         KOP LAPORAN
         ═══════════════════════════════════════════════════════ --}}
    <div class="lap-card" id="lap-report">
        <div class="lap-header">
            <div class="lap-header-line1">KEMENTERIAN PERHUBUNGAN — DIREKTORAT JENDERAL PERHUBUNGAN UDARA</div>
            <div class="lap-header-line2">KANTOR UPBU MUTIARA SIS AL-JUFRI PALU</div>
            <div class="lap-header-line3">
                REKAPITULASI {{ strtoupper($mode) }} LLAU — MASKAPAI {{ strtoupper($airline?->brand_name ?? 'SEMUA') }}
            </div>
            <div class="lap-header-line4">
                @if ($airline?->operator_name)
                    <span>Operator: {{ $airline->operator_name }} | </span>
                @endif
                Periode
                @if ($mode === 'bulanan')
                    Tahun {{ $tahun }}
                @else
                    {{ ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'][$bulan - 1] }}
                    {{ $tahun }}
                @endif
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════════
             TABEL A — REKAP LALU LINTAS UTAMA MASKAPAI
             ═══════════════════════════════════════════════════════ --}}
        <div class="lap-table-title">
            <span>📋</span>
            <span>TABEL A — REKAP LALU LINTAS UTAMA ({{ strtoupper($airline?->brand_name) }})</span>
        </div>

        @php
            $rows = $mode === 'bulanan' ? $this->getRekapBulanan() : $this->getRekapHarian();
            $periodLabel = $mode === 'bulanan' ? 'BULAN' : 'TANGGAL';
        @endphp

        <div class="lap-table-wrapper">
            <table class="lap-table">
                <thead>
                    <tr>
                        <th rowspan="2" class="th-no">NO</th>
                        <th rowspan="2" class="th-bulan">{{ $periodLabel }}</th>
                        <th colspan="3" class="th-flight">PENERBANGAN (FLIGHT)</th>
                        <th colspan="2" class="th-rute">TIPE RUTE</th>
                        <th colspan="7" class="th-pax">PENUMPANG (PAX)</th>
                        <th colspan="3" class="th-transit">TRANSIT</th>
                        <th colspan="3" class="th-bagasi">BAGASI (KG)</th>
                        <th colspan="3" class="th-kargo">KARGO (KG)</th>
                        <th colspan="3" class="th-pos">POS (KG)</th>
                        <th colspan="2" class="th-status">STATUS</th>
                    </tr>
                    <tr>
                        <th class="th-flight">Arr</th>
                        <th class="th-flight">Dep</th>
                        <th class="th-flight">Total</th>
                        <th class="th-rute">Dom</th>
                        <th class="th-rute">Intl</th>
                        <th class="th-pax">Arr</th>
                        <th class="th-pax">Dep</th>
                        <th class="th-pax" title="Dewasa & Remaja (≥12 Tahun)">Dws</th>
                        <th class="th-pax" title="Anak-Anak (2-11 Tahun)">Ank</th>
                        <th class="th-pax" title="Bayi (<2 Tahun)">Byi</th>
                        <th class="th-pax">Total</th>
                        <th class="th-pax">Dom</th>
                        <th class="th-transit">Arr</th>
                        <th class="th-transit">Dep</th>
                        <th class="th-transit">Total</th>
                        <th class="th-bagasi">Arr</th>
                        <th class="th-bagasi">Dep</th>
                        <th class="th-bagasi">Total</th>
                        <th class="th-kargo">Arr</th>
                        <th class="th-kargo">Dep</th>
                        <th class="th-kargo">Total</th>
                        <th class="th-pos">Arr</th>
                        <th class="th-pos">Dep</th>
                        <th class="th-pos">Total</th>
                        <th class="th-status">Delay</th>
                        <th class="th-status">Cancel</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $sum = [
                            'arr' => 0, 'dep' => 0, 'total' => 0,
                            'dom' => 0, 'intl' => 0,
                            'pax_arr' => 0, 'pax_dep' => 0, 'pax_adult' => 0, 'pax_child' => 0, 'pax_infant' => 0, 'pax_total' => 0, 'pax_dom' => 0,
                            'tr_arr' => 0, 'tr_dep' => 0, 'tr_total' => 0,
                            'bag_arr' => 0, 'bag_dep' => 0, 'bag_total' => 0,
                            'cargo_arr' => 0, 'cargo_dep' => 0, 'cargo_total' => 0,
                            'mail_arr' => 0, 'mail_dep' => 0, 'mail_total' => 0,
                            'delayed' => 0, 'cancelled' => 0,
                        ];
                    @endphp

                    @foreach ($rows as $i => $row)
                        @php
                            $d = $row['data'];
                            foreach ($sum as $key => $val) {
                                $sum[$key] += $d[$key] ?? 0;
                            }
                        @endphp
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td style="text-align: left; padding-left: 0.75rem;">{{ $row['label'] }}</td>
                            <td class="cell-bold-blue">{{ $d['arr'] ?: '' }}</td>
                            <td class="cell-bold-blue">{{ $d['dep'] ?: '' }}</td>
                            <td class="cell-total">{{ $d['total'] ?: '' }}</td>
                            <td>{{ $d['dom'] ?: '' }}</td>
                            <td>{{ $d['intl'] ?: '' }}</td>
                            <td class="cell-bold-green">{{ $d['pax_arr'] ? number_format($d['pax_arr'], 0, ',', '.') : '' }}</td>
                            <td class="cell-bold-green">{{ $d['pax_dep'] ? number_format($d['pax_dep'], 0, ',', '.') : '' }}</td>
                            <td>{{ $d['pax_adult'] ? number_format($d['pax_adult'], 0, ',', '.') : '' }}</td>
                            <td>{{ $d['pax_child'] ? number_format($d['pax_child'], 0, ',', '.') : '' }}</td>
                            <td>{{ $d['pax_infant'] ? number_format($d['pax_infant'], 0, ',', '.') : '' }}</td>
                            <td class="cell-bold-green">{{ $d['pax_total'] ? number_format($d['pax_total'], 0, ',', '.') : '' }}</td>
                            <td>{{ $d['pax_dom'] ? number_format($d['pax_dom'], 0, ',', '.') : '' }}</td>
                            <td class="cell-bold-orange">{{ $d['tr_arr'] ? number_format($d['tr_arr'], 0, ',', '.') : '' }}</td>
                            <td class="cell-bold-orange">{{ $d['tr_dep'] ? number_format($d['tr_dep'], 0, ',', '.') : '' }}</td>
                            <td class="cell-bold-orange">{{ $d['tr_total'] ? number_format($d['tr_total'], 0, ',', '.') : '' }}</td>
                            <td>{{ $d['bag_arr'] ? number_format($d['bag_arr'], 0, ',', '.') : '' }}</td>
                            <td>{{ $d['bag_dep'] ? number_format($d['bag_dep'], 0, ',', '.') : '' }}</td>
                            <td class="cell-bold-purple">{{ $d['bag_total'] ? number_format($d['bag_total'], 0, ',', '.') : '' }}</td>
                            <td>{{ $d['cargo_arr'] ? number_format($d['cargo_arr'], 0, ',', '.') : '' }}</td>
                            <td>{{ $d['cargo_dep'] ? number_format($d['cargo_dep'], 0, ',', '.') : '' }}</td>
                            <td class="cell-bold-purple">{{ $d['cargo_total'] ? number_format($d['cargo_total'], 0, ',', '.') : '' }}</td>
                            <td>{{ $d['mail_arr'] ? number_format($d['mail_arr'], 0, ',', '.') : '' }}</td>
                            <td>{{ $d['mail_dep'] ? number_format($d['mail_dep'], 0, ',', '.') : '' }}</td>
                            <td class="cell-bold-purple">{{ $d['mail_total'] ? number_format($d['mail_total'], 0, ',', '.') : '' }}</td>
                            <td class="cell-bold-red">{{ $d['delayed'] ?: '' }}</td>
                            <td class="cell-bold-red">{{ $d['cancelled'] ?: '' }}</td>
                        </tr>
                    @endforeach

                    {{-- Total --}}
                    <tr class="row-grand-total">
                        <td colspan="2">TOTAL {{ $mode === 'bulanan' ? 'TAHUN' : 'BULAN' }}</td>
                        <td>{{ $sum['arr'] }}</td>
                        <td>{{ $sum['dep'] }}</td>
                        <td>{{ $sum['total'] }}</td>
                        <td>{{ $sum['dom'] }}</td>
                        <td>{{ $sum['intl'] }}</td>
                        <td>{{ number_format($sum['pax_arr'], 0, ',', '.') }}</td>
                        <td>{{ number_format($sum['pax_dep'], 0, ',', '.') }}</td>
                        <td>{{ number_format($sum['pax_adult'], 0, ',', '.') }}</td>
                        <td>{{ number_format($sum['pax_child'], 0, ',', '.') }}</td>
                        <td>{{ number_format($sum['pax_infant'], 0, ',', '.') }}</td>
                        <td>{{ number_format($sum['pax_total'], 0, ',', '.') }}</td>
                        <td>{{ number_format($sum['pax_dom'], 0, ',', '.') }}</td>
                        <td>{{ number_format($sum['tr_arr'], 0, ',', '.') }}</td>
                        <td>{{ number_format($sum['tr_dep'], 0, ',', '.') }}</td>
                        <td>{{ number_format($sum['tr_total'], 0, ',', '.') }}</td>
                        <td>{{ number_format($sum['bag_arr'], 0, ',', '.') }}</td>
                        <td>{{ number_format($sum['bag_dep'], 0, ',', '.') }}</td>
                        <td>{{ number_format($sum['bag_total'], 0, ',', '.') }}</td>
                        <td>{{ number_format($sum['cargo_arr'], 0, ',', '.') }}</td>
                        <td>{{ number_format($sum['cargo_dep'], 0, ',', '.') }}</td>
                        <td>{{ number_format($sum['cargo_total'], 0, ',', '.') }}</td>
                        <td>{{ number_format($sum['mail_arr'], 0, ',', '.') }}</td>
                        <td>{{ number_format($sum['mail_dep'], 0, ',', '.') }}</td>
                        <td>{{ number_format($sum['mail_total'], 0, ',', '.') }}</td>
                        <td>{{ $sum['delayed'] }}</td>
                        <td>{{ $sum['cancelled'] }}</td>
                    </tr>

                    {{-- Rata-rata --}}
                    <tr class="row-average">
                        <td colspan="2">RATA-RATA {{ $mode === 'bulanan' ? 'BULANAN' : 'HARIAN' }}</td>
                        @php $divisor = $mode === 'bulanan' ? 12 : (count($rows) ?: 1); @endphp
                        @foreach (['arr', 'dep', 'total', 'dom', 'intl',
                                   'pax_arr', 'pax_dep', 'pax_adult', 'pax_child', 'pax_infant', 'pax_total', 'pax_dom',
                                   'tr_arr', 'tr_dep', 'tr_total',
                                   'bag_arr', 'bag_dep', 'bag_total',
                                   'cargo_arr', 'cargo_dep', 'cargo_total',
                                   'mail_arr', 'mail_dep', 'mail_total',
                                   'delayed', 'cancelled'] as $key)
                            <td>{{ number_format(round($sum[$key] / $divisor), 0, ',', '.') }}</td>
                        @endforeach
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- ═══════════════════════════════════════════════════════
             TABEL B — MATRIKS RUTE OPERASIONAL MASKAPAI
             ═══════════════════════════════════════════════════════ --}}
        @php $ruteData = $this->getBreakdownRute(); @endphp

        <div class="lap-table-title" style="margin-top: 2rem;">
            <span>🛫</span>
            <span>TABEL B — MATRIKS RUTE PENERBANGAN ({{ strtoupper($airline?->brand_name) }})</span>
        </div>

        <div class="lap-table-wrapper">
            <table class="lap-table">
                <thead>
                    <tr>
                        <th class="th-no">NO</th>
                        <th class="th-flight">BANDARA ASAL</th>
                        <th class="th-flight">BANDARA TUJUAN</th>
                        <th class="th-rute">TIPE</th>
                        <th class="th-flight">FREKUENSI (FLIGHT)</th>
                        <th class="th-pax">TOTAL PENUMPANG (PAX)</th>
                        <th class="th-kargo">TOTAL KARGO (KG)</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $totFlight = 0; $totPax = 0; $totCargo = 0;
                    @endphp
                    @forelse ($ruteData as $i => $rute)
                        @php
                            $totFlight += $rute->total_flight;
                            $totPax += $rute->total_pax;
                            $totCargo += $rute->total_cargo;
                        @endphp
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td class="cell-bold-blue">{{ $rute->origin_iata }}</td>
                            <td class="cell-bold-blue">{{ $rute->destination_iata }}</td>
                            <td>
                                <span class="badge @if($rute->movement === 'Arrival') badge-info @else badge-warning @endif">
                                    {{ $rute->movement === 'Arrival' ? 'KEDATANGAN (ARR)' : 'KEBERANGKATAN (DEP)' }}
                                </span>
                            </td>
                            <td class="cell-bold-blue">{{ $rute->total_flight }}</td>
                            <td class="cell-bold-green">{{ number_format($rute->total_pax, 0, ',', '.') }}</td>
                            <td class="cell-bold-purple">{{ number_format($rute->total_cargo, 1, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="text-align: center; color: #94a3b8; padding: 1.5rem;">
                                Belum ada pergerakan penerbangan untuk maskapai ini pada periode terpilih.
                            </td>
                        </tr>
                    @endforelse

                    @if ($ruteData->isNotEmpty())
                        <tr class="row-grand-total">
                            <td colspan="4">TOTAL</td>
                            <td>{{ $totFlight }}</td>
                            <td>{{ number_format($totPax, 0, ',', '.') }}</td>
                            <td>{{ number_format($totCargo, 1, ',', '.') }}</td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>

        {{-- ═══════════════════════════════════════════════════════
             TABEL C — BREAKDOWN KEGIATAN MASKAPAI
             ═══════════════════════════════════════════════════════ --}}
        @php
            $kegiatan = $this->getBreakdownKegiatan();
            $categories = ['Berjadwal', 'Tidak Berjadwal', 'Extra Flight', 'Perintis', 'Haji', 'Militer', 'Bukan Niaga'];
        @endphp

        <div class="lap-table-title" style="margin-top: 2rem;">
            <span>📈</span>
            <span>TABEL C — BREAKDOWN KEGIATAN ({{ strtoupper($airline?->brand_name) }})</span>
        </div>

        <div class="lap-table-wrapper">
            <table class="lap-table">
                <thead>
                    <tr>
                        <th class="th-no">NO</th>
                        <th class="th-bulan">{{ $periodLabel }}</th>
                        @foreach ($categories as $cat)
                            <th class="th-flight">{{ strtoupper($cat) }}</th>
                        @endforeach
                        <th class="th-pax">TOTAL FLIGHT</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $totals = array_fill_keys($categories, 0);
                        $grandTotal = 0;
                    @endphp

                    @foreach ($kegiatan as $i => $row)
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td style="text-align: left; padding-left: 0.75rem;">
                                {{ $row['label'] }}
                            </td>
                            @foreach ($categories as $cat)
                                @php $totals[$cat] += $row[$cat]; @endphp
                                <td class="@if($row[$cat] > 0) cell-bold-blue @endif">
                                    {{ $row[$cat] ?: '' }}
                                </td>
                            @endforeach
                            @php $grandTotal += $row['total']; @endphp
                            <td class="cell-bold-orange">{{ $row['total'] ?: '' }}</td>
                        </tr>
                    @endforeach

                    <tr class="row-grand-total">
                        <td colspan="2">TOTAL {{ $mode === 'bulanan' ? 'TAHUN' : 'BULAN' }}</td>
                        @foreach ($categories as $cat)
                            <td>{{ $totals[$cat] ?: '' }}</td>
                        @endforeach
                        <td>{{ $grandTotal ?: '' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</x-filament-panels::page>
