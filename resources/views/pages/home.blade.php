<x-layouts.app :title="__('ui.home.title')" :latest="$latest">
    <div data-page="home" class="space-y-6">
        <x-section-header :eyebrow="__('ui.home.eyebrow')" :title="__('ui.home.heading')" :description="__('ui.home.description')" />

        @php
            $effectiveStatus = $latest?->effectiveStatus();
            $isOffline = $effectiveStatus === 'OFFLINE';
        @endphp
        <div data-safety-alert data-state="{{ $effectiveStatus }}" role="alert" aria-live="assertive" @class(['safety-alert', 'safety-alert-warning' => $effectiveStatus === 'WARNING', 'safety-alert-danger' => $effectiveStatus === 'DANGER', 'safety-alert-flood' => $effectiveStatus === 'FLOOD', 'safety-alert-offline' => $effectiveStatus === 'OFFLINE', 'hidden' => !in_array($effectiveStatus, ['WARNING', 'DANGER', 'FLOOD', 'OFFLINE'])])>
            <x-icon name="alert" class="size-5 shrink-0" />
            <div><strong data-safety-alert-title>{{ $effectiveStatus ? __('ui.alerts.'.strtolower($effectiveStatus).'.title') : '' }}</strong><p data-safety-alert-body class="mt-1 text-sm">{{ $effectiveStatus ? __('ui.alerts.'.strtolower($effectiveStatus).'.body') : '' }}</p></div>
        </div>

        @if(!$latest)
            <x-empty-state />
        @else
            <section class="grid gap-4 md:grid-cols-[minmax(0,1.35fr)_minmax(260px,.65fr)]" aria-label="{{ __('ui.status.current') }}">
                <x-flood-status-card :reading="$latest" />
                <article class="surface flex min-h-56 items-center justify-between gap-3 p-5 sm:p-6 md:flex-col md:justify-center">
                    <div>
                        <p class="text-sm font-semibold text-muted">{{ __('ui.water.level') }}</p>
                        <p class="mt-1 text-xl font-bold text-ink tabular-nums"><span data-current-water-level>{{ $isOffline ? '-' : round($latest->water_level) }}</span><span data-current-water-level-unit @class(['hidden' => $isOffline])>%</span></p>
                        <p class="mt-3 max-w-32 text-pretty text-xs leading-5 text-muted">{{ __('ui.water.explanation') }}</p>
                    </div>
                    <x-water-level-gauge :value="$isOffline ? null : $latest->water_level" :status="$effectiveStatus" />
                </article>
            </section>

            <section aria-labelledby="sensor-title">
                <div class="mb-3 flex items-end justify-between gap-3">
                    <div><p class="text-xs font-bold uppercase text-teal">{{ __('ui.home.section_sensor') }}</p><h2 id="sensor-title" class="mt-1 text-balance text-xl font-bold text-ink">{{ __('ui.home.latest_reading') }}</h2></div>
                    <span class="text-xs text-muted">{{ __('ui.home.refresh_note') }}</span>
                </div>
                <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <x-sensor-card icon="waves" :label="__('ui.water.level')" :value="$isOffline ? null : $latest->water_level" unit="%" key="water_level" />
                    <x-sensor-card icon="thermometer" :label="__('ui.sensor.temperature')" :value="$isOffline ? null : $latest->temperature" unit="°C" key="temperature" />
                    <x-sensor-card icon="droplet" :label="__('ui.sensor.humidity')" :value="$isOffline ? null : $latest->humidity" unit="%" key="humidity" />
                    <x-sensor-card icon="sun" :label="__('ui.sensor.light')" :value="$isOffline ? null : $latest->light" :display-value="!$isOffline && $lightCondition ? __('ui.sensor.light_conditions.'.$lightCondition) : null" key="light" />
                </div>
            </section>

            <section class="grid gap-4 lg:grid-cols-[minmax(0,1.35fr)_minmax(300px,.65fr)]">
                <article class="surface min-w-0 p-5 sm:p-6">
                    <div class="mb-4 flex items-start justify-between gap-3">
                        <div><p class="text-xs font-bold uppercase text-teal">{{ __('ui.home.trend') }}</p><h2 class="mt-1 text-balance text-xl font-bold text-ink">{{ __('ui.home.latest_level') }}</h2></div>
                        <a href="{{ route('history') }}" class="link-button">{{ __('ui.nav.history') }} <x-icon name="arrow" class="size-4" /></a>
                    </div>
                    <div class="chart-container"><canvas id="homeChart" aria-label="{{ __('ui.home.chart_label') }}"></canvas></div>
                </article>
                <article class="surface flex flex-col p-5 sm:p-6">
                    <span class="grid size-11 place-items-center rounded-2xl bg-sky-soft text-teal"><x-icon name="shield" class="size-5" /></span>
                    <p class="mt-4 text-xs font-bold uppercase text-teal">{{ __('ui.home.system_recommendation') }}</p>
                    <h2 class="mt-1 text-balance text-xl font-bold text-ink" data-recommendation-title>{{ $recommendation['title'] }}</h2>
                    <p class="mt-2 flex-1 text-pretty text-sm leading-6 text-muted" data-recommendation-summary>{{ $recommendation['summary'] }}</p>
                    <a href="{{ route('recommendations') }}" class="primary-button mt-5">{{ __('ui.home.view_guide') }} <x-icon name="arrow" class="size-4" /></a>
                </article>
            </section>
        @endif
    </div>

    @push('scripts')
        <script type="application/json" id="home-chart-data">{!! json_encode($recent->map(fn($item) => ['recorded_at' => $item->recorded_at->toIso8601String(), 'water_level' => $item->water_level])) !!}</script>
    @endpush
</x-layouts.app>
