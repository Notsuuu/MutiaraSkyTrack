<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class FlightTrafficTemplateExport implements
    FromArray,
    WithHeadings,
    WithStyles,
    WithColumnWidths,
    ShouldAutoSize,
    WithTitle
{
    public function title(): string
    {
        return 'Template Import';
    }

    /**
     * Header ini identik dengan format rekap operasional yang sudah
     * dipakai petugas PLW, sehingga file existing bisa langsung
     * dipakai/disesuaikan tanpa mengubah struktur kolom.
     */
    public function headings(): array
    {
        return [
            'No',
            'Tgl Aktual',
            'Waktu Aktual',
            'Tgl Jadwal',
            'Waktu Jadwal',
            'Operator / Maskapai',
            'No. Penerbangan',
            'Registrasi',
            'Tipe Armada',
            'Kapasitas',
            'Asal',
            'Tujuan',
            'Pergerakan',
            'Kegiatan',
            'Cakupan',
            'Status',
            'Kategori Delay',
            'Keterangan Delay',
            'Pax Dewasa',
            'Pax Anak',
            'Pax Bayi',
            'Total Pax',
            'Transit Dewasa',
            'Transit Anak',
            'Transit Bayi',
            'Bagasi (Kg)',
            'Kargo (Kg)',
            'Pos (Kg)',
        ];
    }

    public function array(): array
    {
        return [
            // Contoh 1: penerbangan berjadwal, on time, tanpa delay
            [
                1, '2026-08-22', '00:00:00', '2026-08-22', '00:00:00',
                'Lion Air', '781', 'PK LFK', 'B737-900', 215,
                'PLW', 'UPG', 'D', 'BERJADWAL', 'DOMESTIK', 'REALISASI',
                '', '',
                209, 1, 2, 212, 0, 0, 0,
                1389, 0, 0,
            ],
            // Contoh 2: penerbangan perintis dengan rute non-IATA standar
            [
                2, '2026-08-21', '00:00:00', '2026-08-21', '00:00:00',
                'Susi Air', '6108', 'PK BVI', 'C208B', 12,
                'SKO', 'PLW', 'D', 'PERINTIS', 'DOMESTIK', 'REALISASI',
                '', '',
                10, 1, 1, 12, 0, 0, 0,
                136, 0, 0,
            ],
            // Contoh 3: penerbangan tidak berjadwal yang mengalami delay
            // dengan kategori & keterangan delay terisi
            [
                3, '2026-08-21', '00:00:00', '2026-08-21', '00:00:00',
                'Lion Air', 'JDE311', 'PK ECO', 'B737-300', 0,
                'UPG', 'PLW', 'A', 'TIDAK BERJADWAL', 'DOMESTIK', 'DELAY',
                'Manajemen Airlines', 'MANAJEMEN AIRLINES',
                0, 0, 0, 0, 0, 0, 0,
                0, 4277, 0,
            ],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => [
                    'bold'  => true,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType'   => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '0369A1'],
                ],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 6,  'B' => 14, 'C' => 14, 'D' => 14, 'E' => 14,
            'F' => 20, 'G' => 16, 'H' => 12, 'I' => 14, 'J' => 12,
            'K' => 10, 'L' => 10, 'M' => 12, 'N' => 16, 'O' => 14,
            'P' => 12, 'Q' => 18, 'R' => 20, 'S' => 12, 'T' => 10,
            'U' => 10, 'V' => 12, 'W' => 14, 'X' => 14, 'Y' => 14,
            'Z' => 12, 'AA' => 12, 'AB' => 10,
        ];
    }
}
