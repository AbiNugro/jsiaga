@props(['eyebrow' => null, 'title', 'description' => null])

<div {{ $attributes->class(['min-w-0']) }}>
    @if($eyebrow)<p class="mb-1 text-xs font-bold uppercase text-teal">{{ $eyebrow }}</p>@endif
    <h1 class="text-balance text-2xl font-bold text-ink sm:text-3xl">{{ $title }}</h1>
    @if($description)<p class="mt-2 max-w-2xl text-pretty text-sm leading-6 text-muted sm:text-base">{{ $description }}</p>@endif
</div>
