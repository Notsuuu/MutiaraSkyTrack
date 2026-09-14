<x-filament-widgets::widget>
    <div class="sk-stats-grid">
        @foreach ($this->getStats() as $stat)
            @if (isset($stat['percent']))
                {{-- Layout horizontal (Delay / Cancel) --}}
                <div class="sk-stat-card sk-stat-card-horizontal sk-stat-{{ $stat['color'] }}">
                    <div class="sk-stat-icon-box">
                        <x-dynamic-component :component="$stat['icon']" class="sk-stat-icon" />
                    </div>
                    <div class="sk-stat-body">
                        <div class="sk-stat-label">{{ $stat['label'] }}</div>
                        <div class="sk-stat-value-row">
                            <span class="sk-stat-value">{{ $stat['value'] }}</span>
                            <span class="sk-stat-percent sk-stat-percent-{{ $stat['color'] }}">
                                ({{ number_format($stat['percent'], 1) }}%)
                            </span>
                        </div>
                    </div>
                </div>
            @else
                {{-- Layout vertikal (4 card atas) --}}
                <div class="sk-stat-card sk-stat-{{ $stat['color'] }}">
                    <div class="sk-stat-body">
                        <div class="sk-stat-label">{{ $stat['label'] }}</div>
                        <div class="sk-stat-value">{{ $stat['value'] }}</div>
                        @if (! empty($stat['unit']))
                            <div class="sk-stat-desc">
                                <span class="sk-stat-unit">
                                    @if ($stat['unit_icon'])
                                        <x-dynamic-component :component="$stat['unit_icon']" class="sk-stat-unit-icon" />
                                    @endif
                                    {{ $stat['unit'] }}
                                </span>
                            </div>
                        @endif
                    </div>
                    <div class="sk-stat-icon-box">
                        <x-dynamic-component :component="$stat['icon']" class="sk-stat-icon" />
                    </div>
                </div>
            @endif
        @endforeach
    </div>
</x-filament-widgets::widget>
