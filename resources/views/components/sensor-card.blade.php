@props(['icon', 'label', 'value' => null, 'displayValue' => null, 'unit' => '', 'key' => ''])

<article class="surface sensor-card">
    <div class="sensor-icon"><x-icon :name="$icon" class="size-5" /></div>
    <p class="mt-5 text-sm font-medium text-muted">{{ $label }}</p>
    <p class="mt-1 flex min-h-9 items-baseline gap-1 text-2xl font-bold text-ink tabular-nums sm:text-3xl">
        <span data-sensor-value="{{ $key }}">{{ $value === null ? '-' : ($displayValue ?? rtrim(rtrim(number_format($value, 1, ',', '.'), '0'), ',')) }}</span>
        @if($unit)<span data-sensor-unit @class(['text-sm font-semibold text-muted', 'hidden' => $value === null])>{{ $unit }}</span>@endif
    </p>
</article>
