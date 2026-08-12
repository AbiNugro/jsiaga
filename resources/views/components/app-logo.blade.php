@props(['compact' => false])

<div {{ $attributes->class(['flex items-center gap-3']) }}>
    <span class="flex size-16 shrink-0 items-center justify-center" aria-hidden="true">
        <img src="{{ asset('images/logo-jsiaga.png') }}" alt="" width="64" height="64" loading="eager" decoding="sync" fetchpriority="high" class="block size-full object-contain">
    </span>
    @unless($compact)
        <span class="min-w-0">
            <span class="block text-base font-bold text-ink">J-SIAGA</span>
            <span class="block text-xs leading-4 text-muted">{{ __('ui.brand.tagline') }}</span>
        </span>
    @endunless
</div>
