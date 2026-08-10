<x-layouts.app :title="__('ui.history.title')" :latest="$latest">
    <div data-page="history" data-range="{{ $range }}" class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <x-section-header :eyebrow="__('ui.history.eyebrow')" :title="__('ui.history.heading')" :description="__('ui.history.description')" />
            <button type="button" data-refresh-history class="secondary-button shrink-0"><x-icon name="refresh" class="size-4" /> {{ __('ui.common.refresh') }}</button>
        </div>

        <x-range-filter :active="$range" />

        @if($chartReadings->isEmpty())
            <x-empty-state />
        @else
            <section class="grid grid-cols-2 gap-3 lg:grid-cols-4" aria-label="{{ __('ui.history.summary_label') }}">
                @foreach([
                    ['label' => __('ui.history.highest'), 'value' => $summary['highest'], 'unit' => '%'],
                    ['label' => __('ui.history.average'), 'value' => $summary['average'], 'unit' => '%'],
                    ['label' => __('ui.history.warning_count'), 'value' => $summary['warning'], 'unit' => __('ui.common.times')],
                    ['label' => __('ui.history.danger_count'), 'value' => $summary['danger'], 'unit' => __('ui.common.times')],
                ] as $stat)
                    <article class="surface p-4 sm:p-5">
                        <p class="text-pretty text-xs font-semibold text-muted">{{ $stat['label'] }}</p>
                        <p class="mt-2 text-2xl font-bold text-ink tabular-nums">{{ $stat['value'] ?? '—' }} <span class="text-xs font-semibold text-muted">{{ $stat['unit'] }}</span></p>
                    </article>
                @endforeach
            </section>

            <article class="surface min-w-0 p-5 sm:p-6">
                <div class="mb-5 flex items-center justify-between gap-3">
                    <div><p class="text-xs font-bold uppercase text-teal">{{ __('ui.history.chart_eyebrow') }}</p><h2 class="mt-1 text-balance text-xl font-bold text-ink">{{ __('ui.history.chart_heading') }}</h2></div>
                    <span data-history-state class="text-xs font-medium text-muted" aria-live="polite">{{ __('ui.common.readings', ['count' => $chartReadings->count()]) }}</span>
                </div>
                <x-error-state data-history-error class="mb-4 hidden" />
                <div class="chart-container chart-container-large"><canvas id="historyChart" aria-label="{{ __('ui.history.chart_label') }}"></canvas></div>
            </article>

            <section aria-labelledby="reading-list-title">
                <h2 id="reading-list-title" class="mb-3 text-balance text-xl font-bold text-ink">{{ __('ui.history.list') }}</h2>
                <div class="space-y-3 lg:hidden">
                    @foreach($readings as $reading)
                        <article class="surface p-4">
                            <div class="flex items-start justify-between gap-3">
                                <time class="text-pretty text-sm font-semibold text-ink">{{ $reading->recorded_at->timezone('Asia/Jakarta')->translatedFormat('d M Y, H:i') }} WIB</time>
                                <x-status-badge :status="$reading->status" />
                            </div>
                            <dl class="mt-4 grid grid-cols-2 gap-x-3 gap-y-4 text-sm">
                                <div><dt class="text-xs text-muted">{{ __('ui.water.level') }}</dt><dd class="mt-1 font-bold tabular-nums">{{ $reading->water_level }}%</dd></div>
                                <div><dt class="text-xs text-muted">{{ __('ui.sensor.temperature') }}</dt><dd class="mt-1 font-bold tabular-nums">{{ $reading->temperature ?? '—' }}{{ $reading->temperature !== null ? '°C' : '' }}</dd></div>
                                <div><dt class="text-xs text-muted">{{ __('ui.sensor.humidity') }}</dt><dd class="mt-1 font-bold tabular-nums">{{ $reading->humidity ?? '—' }}{{ $reading->humidity !== null ? '%' : '' }}</dd></div>
                                @php $readingLightCondition = \App\Support\LightCondition::key($reading->light); @endphp
                                <div><dt class="text-xs text-muted">{{ __('ui.sensor.light') }}</dt><dd class="mt-1 font-bold">{{ $readingLightCondition ? __('ui.sensor.light_conditions.'.$readingLightCondition) : '—' }}</dd></div>
                            </dl>
                        </article>
                    @endforeach
                </div>

                <div class="surface hidden overflow-hidden lg:block">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[760px] text-left text-sm">
                            <thead class="bg-mist text-xs text-muted"><tr><th class="table-cell">{{ __('ui.history.time') }}</th><th class="table-cell">{{ __('ui.history.status') }}</th><th class="table-cell">{{ __('ui.water.level') }}</th><th class="table-cell">{{ __('ui.sensor.temperature') }}</th><th class="table-cell">{{ __('ui.sensor.humidity') }}</th><th class="table-cell">{{ __('ui.sensor.light') }}</th></tr></thead>
                            <tbody class="divide-y divide-navy/5">
                                @foreach($readings as $reading)
                                    @php $tableLightCondition = \App\Support\LightCondition::key($reading->light); @endphp
                                    <tr><td class="table-cell font-medium">{{ $reading->recorded_at->timezone('Asia/Jakarta')->format('d/m/Y H:i') }}</td><td class="table-cell"><x-status-badge :status="$reading->status" /></td><td class="table-cell tabular-nums">{{ $reading->water_level }}%</td><td class="table-cell tabular-nums">{{ $reading->temperature ?? '—' }}</td><td class="table-cell tabular-nums">{{ $reading->humidity ?? '—' }}</td><td class="table-cell font-semibold">{{ $tableLightCondition ? __('ui.sensor.light_conditions.'.$tableLightCondition) : '—' }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="mt-5">{{ $readings->links() }}</div>
            </section>
        @endif
    </div>

    @push('scripts')
        <script type="application/json" id="history-chart-data">{!! json_encode($chartReadings->map(fn($item) => ['recorded_at' => $item->recorded_at->toIso8601String(), 'water_level' => $item->water_level, 'status' => $item->status])) !!}</script>
    @endpush
</x-layouts.app>
