<x-layouts.app :title="__('ui.recommendations.title')" :latest="$latest">
    @php $isOffline = $latest?->effectiveStatus() === 'OFFLINE'; @endphp
    <div data-page="recommendations" class="space-y-6">
        <x-section-header :eyebrow="__('ui.recommendations.eyebrow')" :title="__('ui.recommendations.heading')" :description="__('ui.recommendations.description')" />

        @if(!$latest)
            <x-empty-state />
        @else
            <section>
                <article class="surface p-5 sm:p-6">
                    <div class="flex items-center justify-between gap-3"><p class="text-xs font-bold uppercase text-teal">{{ __('ui.sensor.latest') }}</p><x-status-badge :status="$latest->effectiveStatus()" data-recommendation-status /></div>
                    <dl class="mt-5 grid grid-cols-2 gap-4">
                        <div><dt class="text-xs text-muted">{{ __('ui.water.level') }}</dt><dd data-recommendation-water-level class="mt-1 text-2xl font-bold tabular-nums">{{ $isOffline ? '-' : $latest->water_level.'%' }}</dd></div>
                        <div><dt class="text-xs text-muted">{{ __('ui.sensor.light') }}</dt><dd data-recommendation-light class="mt-1 text-2xl font-bold">{{ $isOffline ? '-' : ($lightCondition ? __('ui.sensor.light_conditions.'.$lightCondition) : __('ui.common.unavailable')) }}</dd></div>
                        <div class="col-span-2 border-t border-navy/5 pt-4"><dt class="text-xs text-muted">{{ __('ui.recommendations.last_update') }}</dt><dd data-recommendation-updated class="mt-1 text-sm font-semibold">{{ $latest->recorded_at->timezone('Asia/Jakarta')->translatedFormat('d M Y, H:i:s') }} WIB</dd></div>
                    </dl>
                </article>
            </section>

            <section class="surface p-5 sm:p-6" aria-labelledby="ai-title">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-start gap-3"><span class="grid size-11 shrink-0 place-items-center rounded-2xl bg-sky-soft text-teal"><x-icon name="sparkle" class="size-5" /></span><div><p class="text-xs font-bold uppercase text-teal">{{ __('ui.recommendations.ai_eyebrow') }}</p><h2 id="ai-title" class="mt-1 text-balance text-lg font-bold">{{ __('ui.recommendations.ai_heading') }}</h2><p class="mt-1 text-pretty text-sm text-muted">{{ __('ui.recommendations.ai_description') }}</p></div></div>
                    <button type="button" data-ai-explain class="secondary-button shrink-0" @disabled($latest->isStale())><x-icon name="sparkle" class="size-4" /> {{ __('ui.recommendations.ai_button') }}</button>
                </div>
                <div data-ai-result class="mt-4 hidden whitespace-pre-line rounded-2xl bg-mist p-4 text-pretty text-sm leading-6 text-ink" aria-live="polite"></div>
            </section>

            <section aria-labelledby="all-guides-title">
                <div class="mb-3"><p class="text-xs font-bold uppercase text-teal">{{ __('ui.recommendations.all_eyebrow') }}</p><h2 id="all-guides-title" class="mt-1 text-balance text-xl font-bold">{{ __('ui.recommendations.all_heading') }}</h2></div>
                <div class="grid gap-4 lg:grid-cols-3">
                    @foreach($allRecommendations as $status => $guide)
                        <x-recommendation-card :title="$status" :summary="$guide['summary']" :steps="$guide['steps']" :status="$status" />
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-layouts.app>
