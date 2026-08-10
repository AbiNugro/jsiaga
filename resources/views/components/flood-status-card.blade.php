@props(['reading' => null])
@php
    $status = $reading?->effectiveStatus();
    $label = match ($status) {
        'OFFLINE' => __('ui.status.offline'),
        null => __('ui.status.waiting'),
        default => $status,
    };
@endphp
<article data-status-card data-status="{{ $status }}" @class(['status-card', 'status-card-safe' => $status === 'SAFE', 'status-card-warning' => $status === 'WARNING', 'status-card-danger' => $status === 'DANGER', 'status-card-flood' => $status === 'FLOOD', 'status-card-empty' => !$status || $status === 'OFFLINE'])>
    <header class="status-card-header">
        <p class="status-card-kicker"><span class="status-card-dot" aria-hidden="true"></span>{{ __('ui.status.current') }}</p>
        <p class="status-card-time">
            <x-icon name="refresh" class="size-4" />
            <span class="sr-only">{{ __('ui.status.updated') }}</span>
            <time data-recorded-at>{{ $reading?->recorded_at?->timezone('Asia/Jakarta')->diffForHumans() ?? __('ui.common.unavailable') }}</time>
        </p>
    </header>

    <div class="status-card-main">
        <div class="min-w-0">
            <p class="status-card-label" data-status-label>{{ $label }}</p>
        </div>
        <span class="status-card-icon" aria-hidden="true">
            <span class="status-icon-state status-icon-safe"><x-icon name="shield" class="size-6" /></span>
            <span class="status-icon-state status-icon-warning"><x-icon name="info" class="size-6" /></span>
            <span class="status-icon-state status-icon-danger"><x-icon name="alert" class="size-6" /></span>
            <span class="status-icon-state status-icon-flood"><x-icon name="alert" class="size-6" /></span>
            <span class="status-icon-state status-icon-empty"><x-icon name="signal" class="size-6" /></span>
        </span>
    </div>

    <div class="status-scale" aria-label="{{ __('ui.status.current') }}">
        <div class="status-scale-item status-scale-safe"><span class="status-scale-bar"></span><span>SAFE</span></div>
        <div class="status-scale-item status-scale-warning"><span class="status-scale-bar"></span><span>WARNING</span></div>
        <div class="status-scale-item status-scale-danger"><span class="status-scale-bar"></span><span>DANGER</span></div>
        <div class="status-scale-item status-scale-flood"><span class="status-scale-bar"></span><span>FLOOD</span></div>
    </div>
</article>
