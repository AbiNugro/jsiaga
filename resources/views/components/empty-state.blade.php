@props(['title' => null, 'description' => null])
<div {{ $attributes->class(['surface flex flex-col items-center px-6 py-10 text-center']) }}>
    <span class="grid size-12 place-items-center rounded-2xl bg-sky-soft text-teal"><x-icon name="database" class="size-6" /></span>
    <h2 class="mt-4 text-balance text-lg font-bold text-ink">{{ $title ?? __('ui.empty.title') }}</h2>
    <p class="mt-2 max-w-md text-pretty text-sm leading-6 text-muted">{{ $description ?? __('ui.empty.description') }}</p>
    <code class="mt-4 rounded-xl bg-mist px-3 py-2 text-xs font-semibold text-ink">php artisan jsiaga:seed-demo</code>
</div>
