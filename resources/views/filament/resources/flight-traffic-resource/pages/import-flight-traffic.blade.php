<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Card Unggah -->
        <div class="bg-white dark:bg-gray-900 rounded-xl p-6 shadow-sm border border-gray-200 dark:border-gray-800">
            <form wire:submit="submit" class="space-y-4">
                {{ $this->form }}

                <div class="p-4 bg-blue-50 dark:bg-blue-950/40 rounded-lg text-xs text-blue-700 dark:text-blue-300 flex items-start gap-2">
                    <x-heroicon-m-information-circle class="w-5 h-5 shrink-0 text-blue-500" />
                    <div>
                        <strong>PANDUAN IMPORT EXCEL:</strong>
                        <p>Kolom yang tidak tersedia atau kosong pada file Anda akan otomatis diisi kosong/nol di dalam sistem tanpa membatalkan proses import.</p>
                    </div>
                </div>

                <div class="flex justify-end pt-2">
                    <x-filament::button type="submit" icon="heroicon-o-arrow-up-tray" color="primary">
                        Proses Import Berkas
                    </x-filament::button>
                </div>
            </form>
        </div>

        <!-- Card Riwayat Berkas Diimpor -->
        <div class="space-y-3">
            <h3 class="text-sm font-bold text-gray-700 dark:text-gray-300 flex items-center gap-2">
                <x-heroicon-o-clock class="w-4 h-4 text-gray-500" />
                Riwayat Berkas Diimpor
            </h3>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-4 flex flex-col justify-between shadow-sm">
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <x-heroicon-o-document-text class="w-7 h-7 text-emerald-600" />
                            <span class="px-2 py-0.5 bg-blue-100 text-blue-700 rounded text-[10px] font-bold">692 Baris</span>
                        </div>
                        <h4 class="font-bold text-sm text-gray-800 dark:text-gray-200">DATA_LLAU_PLW_August_2026.xlsx</h4>
                        <p class="text-[11px] text-gray-400 mt-1">Bulan 8/2026 · Diunggah oleh Administrator System</p>
                        <p class="text-[10px] text-gray-400">2026-08-24 15:00:58</p>
                    </div>
                    <div class="flex items-center justify-between mt-4 pt-3 border-t border-gray-100 dark:border-gray-800">
                        <span class="text-xs font-semibold text-gray-500">Batch #1</span>
                        <div class="flex gap-2">
                            <x-filament::button size="xs" color="gray" icon="heroicon-o-eye">
                                Lihat Data
                            </x-filament::button>
                            <x-filament::button size="xs" color="danger" icon="heroicon-o-trash">
                                Hapus
                            </x-filament::button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
