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
        description="{{ $history->isEmpty() ? 'Belum ada riwayat import. Riwayat akan muncul setelah Anda melakukan import.' : 'Setiap baris mewakili 1 batch import. Klik Hapus untuk menghapus seluruh data dari batch tersebut.' }}"
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
                            <th style="padding: 0.625rem 0.5rem; font-weight: 600; text-align: center;">Aksi</th>
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
                                <td style="padding: 0.625rem 0.5rem; text-align: center;">
                                    @if (!empty($log->properties['batch_id']))
                                        <button
                                            type="button"
                                            wire:click="deleteBatch({{ $log->id }})"
                                            wire:confirm="Yakin ingin menghapus SEMUA data penerbangan dari batch ini? Tindakan ini tidak dapat dibatalkan."
                                            wire:loading.attr="disabled"
                                            style="display: inline-flex; align-items: center; gap: 0.3rem; padding: 0.35rem 0.7rem; background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; border-radius: 8px; font-size: 0.75rem; font-weight: 600; cursor: pointer; transition: all 0.15s ease;"
                                            onmouseover="this.style.background='#fee2e2'"
                                            onmouseout="this.style.background='#fef2f2'"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width: 14px; height: 14px;">
                                                <path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 0 0 6 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 1 0 .23 1.482l.149-.022.841 10.518A2.75 2.75 0 0 0 7.596 19h4.807a2.75 2.75 0 0 0 2.742-2.53l.841-10.52.149.023a.75.75 0 0 0 .23-1.482A41.03 41.03 0 0 0 14 4.193V3.75A2.75 2.75 0 0 0 11.25 1h-2.5ZM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4ZM8.58 7.72a.75.75 0 0 0-1.5.06l.3 7.5a.75.75 0 1 0 1.5-.06l-.3-7.5Zm4.34.06a.75.75 0 1 0-1.5-.06l-.3 7.5a.75.75 0 1 0 1.5.06l.3-7.5Z" clip-rule="evenodd" />
                                            </svg>
                                            Hapus Batch
                                        </button>
                                    @else
                                        <span style="font-size: 0.7rem; color: #9ca3af;">—</span>
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
