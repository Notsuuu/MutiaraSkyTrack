@php
    $filterState = property_exists($this, 'filters') ? ($this->filters ?? []) : [];
    $chartKey = 'sk-chart-' . md5(get_class($this) . '|' . json_encode($filterState));
@endphp

<x-filament-widgets::widget>
    <x-filament::section>
        @if ($heading = $this->getHeading())
            <x-slot name="heading">{{ $heading }}</x-slot>
        @endif

        {{-- ⚡ wire:key berubah saat filter berubah → Livewire destroy element lama → Alpine rebuild → chart instance baru → animation replay --}}
        <div
            wire:key="{{ $chartKey }}"
            x-data="skyChart({
                type: @js($this->getType()),
                data: @js($this->getData()),
                options: @js($this->getOptions()),
            })"
            style="position: relative; height: 280px; width: 100%;"
        >
            <canvas x-ref="canvas"></canvas>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
