<x-filament-panels::page>
    <div class="sk-dashboard-actions">
        <div class="sk-dashboard-filters">
            {{ $this->filtersForm }}
        </div>

        <div class="sk-dashboard-export-wrap">
            <x-filament::button
                tag="button"
                icon="heroicon-o-arrow-down-tray"
                color="primary"
                onclick="window.print()"
                class="sk-dashboard-export-btn"
            >
                Ekspor Dashboard ke Gambar
            </x-filament::button>
        </div>
    </div>

    <x-filament-widgets::widgets
        :columns="$this->getColumns()"
        :widgets="$this->getVisibleWidgets()"
    />
</x-filament-panels::page>
