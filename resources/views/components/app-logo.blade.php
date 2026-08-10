@props(['compact' => false])

<div {{ $attributes->class(['flex items-center gap-3']) }}>
    <span class="grid size-11 shrink-0 place-items-center rounded-2xl bg-teal text-white shadow-soft" aria-hidden="true">
        <x-icon name="waves" class="size-6" />
    </span>
    @unless($compact)
        <span class="min-w-0">
            <span class="block text-base font-bold text-ink">J-SIAGA</span>
            <span class="block truncate text-xs text-muted">{{ __('ui.brand.tagline') }}</span>
        </span>
    @endunless
</div>
