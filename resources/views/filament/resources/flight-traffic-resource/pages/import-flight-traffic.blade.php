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
        </div>
    </div>
</x-filament-panels::page>
