@props(['value' => null, 'status' => null])
@php $level = max(0, min(100, (float) ($value ?? 0))); @endphp

<div class="gauge-wrap" data-gauge data-value="{{ $level }}" data-status="{{ $status }}">
    <svg class="size-36 sm:size-40" viewBox="0 0 120 120" role="img" aria-label="{{ __('ui.water.level') }} {{ $value === null ? __('ui.common.unavailable') : round($level).'%' }}">
        <circle class="gauge-track" cx="60" cy="60" r="48" />
        <circle class="gauge-progress" cx="60" cy="60" r="48" pathLength="100" stroke-dasharray="100" stroke-dashoffset="{{ 100 - $level }}" />
        <path class="gauge-waterline" d="M34 73c8-5 14 5 23 0s15 5 29 0" />
    </svg>
    <div class="absolute inset-0 grid place-content-center text-center">
        <strong class="text-3xl font-bold text-ink tabular-nums"><span data-gauge-value>{{ $value === null ? '—' : round($level) }}</span><span class="text-base">%</span></strong>
        <span class="mt-0.5 text-xs font-semibold text-muted">{{ __('ui.water.level') }}</span>
    </div>
</div>
