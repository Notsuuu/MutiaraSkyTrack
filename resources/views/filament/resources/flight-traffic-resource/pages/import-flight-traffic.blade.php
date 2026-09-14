<x-filament-panels::page>
    <x-filament::section
        heading="Unggah Laporan Lalu Lintas Udara"
        description="Seret & letakkan file Excel di sini atau klik untuk memilih file"
        icon="heroicon-o-cloud-arrow-up"
    >
        <form wire:submit="import">
            {{ $this->form }}

            <x-filament::section
                icon="heroicon-o-information-circle"
                icon-color="info"
                compact
            >
                <x-slot name="heading">Panduan Import Excel</x-slot>
                <p>Kolom yang tidak tersedia atau kosong pada file Anda akan otomatis diisi kosong/nol di dalam sistem tanpa membatalkan proses import.</p>
            </x-filament::section>

            <div style="display: flex; justify-content: flex-end; margin-top: 1rem;">
                <x-filament::button
                    type="submit"
                    icon="heroicon-o-arrow-up-tray"
                    color="primary"
                >
                    Proses Import Berkas
                </x-filament::button>
            </div>
        </form>
    </x-filament::section>

    @php
        $history = $this->getImportHistory();
    @endphp

    <x-filament::section
        heading="Riwayat Berkas Diimpor"
        icon="heroicon-o-clock"
        description="{{ $history->isEmpty() ? 'Belum ada riwayat import. Riwayat akan muncul setelah Anda melakukan import.' : '' }}"
    >
        @if ($history->isNotEmpty())
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: 0.875rem;">
                    <thead>
                        <tr style="border-bottom: 2px solid #e5e7eb; text-align: left;">
                            <th style="padding: 0.625rem 0.5rem; font-weight: 600;">Waktu</th>
                            <th style="padding: 0.625rem 0.5rem; font-weight: 600;">Nama Berkas</th>
                            <th style="padding: 0.625rem 0.5rem; font-weight: 600;">Diinput Oleh</th>
                            <th style="padding: 0.625rem 0.5rem; font-weight: 600; text-align: right;">Berhasil</th>
                            <th style="padding: 0.625rem 0.5rem; font-weight: 600; text-align: right;">Gagal</th>
                            <th style="padding: 0.625rem 0.5rem; font-weight: 600; text-align: right;">Maskapai Baru</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($history as $log)
                            <tr style="border-bottom: 1px solid #f3f4f6;">
                                <td style="padding: 0.625rem 0.5rem; white-space: nowrap;">
                                    {{ $log->created_at->format('d/m/Y H:i') }}
                                </td>
                                <td style="padding: 0.625rem 0.5rem;">
                                    {{ basename($log->properties['file'] ?? '-') }}
                                </td>
                                <td style="padding: 0.625rem 0.5rem;">
                                    {{ $log->causer?->name ?? 'System' }}
                                </td>
                                <td style="padding: 0.625rem 0.5rem; text-align: right;">
                                    <span style="display: inline-block; padding: 0.125rem 0.5rem; background: #d1fae5; color: #065f46; border-radius: 9999px; font-weight: 600; font-size: 0.75rem;">
                                        {{ $log->properties['success_count'] ?? 0 }}
                                    </span>
                                </td>
                                <td style="padding: 0.625rem 0.5rem; text-align: right;">
                                    @if (($log->properties['skip_count'] ?? 0) > 0)
                                        <span style="display: inline-block; padding: 0.125rem 0.5rem; background: #fee2e2; color: #991b1b; border-radius: 9999px; font-weight: 600; font-size: 0.75rem;">
                                            {{ $log->properties['skip_count'] }}
                                        </span>
                                    @else
                                        <span style="color: #9ca3af;">0</span>
                                    @endif
                                </td>
                                <td style="padding: 0.625rem 0.5rem; text-align: right;">
                                    @if (($log->properties['auto_created_count'] ?? 0) > 0)
                                        <span style="display: inline-block; padding: 0.125rem 0.5rem; background: #fef3c7; color: #92400e; border-radius: 9999px; font-weight: 600; font-size: 0.75rem;">
                                            {{ $log->properties['auto_created_count'] }}
                                        </span>
                                    @else
                                        <span style="color: #9ca3af;">0</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
