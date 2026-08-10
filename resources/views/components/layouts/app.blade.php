<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" class="antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#f4f8fa">
    <title>{{ $title ?? 'J-SIAGA' }} · {{ __('ui.brand.tagline') }}</title>
    <script type="application/json" id="ui-translations">{!! json_encode([
        'locale' => app()->getLocale(),
        'status' => [
            'SAFE' => __('ui.status.safe'),
            'WARNING' => __('ui.status.warning'),
            'DANGER' => __('ui.status.danger'),
            'FLOOD' => __('ui.status.flood'),
            'OFFLINE' => __('ui.status.offline'),
        ],
        'offlineSeconds' => (int) config('services.jsiaga.offline_seconds', 15),
        'initialStatus' => isset($latest) && $latest ? $latest->effectiveStatus() : 'OFFLINE',
        'common' => [
            'loading' => __('ui.common.loading'),
            'failed' => __('ui.common.failed'),
            'secondsAgo' => __('ui.common.seconds_ago'),
            'readings' => __('ui.common.readings', ['count' => ':count']),
            'dataOnline' => __('ui.alerts.data_online'),
            'dataOffline' => __('ui.alerts.data_offline'),
            'noData' => __('ui.alerts.no_data'),
        ],
        'chart' => [
            'level' => __('ui.water.level'),
            'status' => __('ui.history.status'),
        ],
        'lightConditions' => [
            'dark' => __('ui.sensor.light_conditions.dark'),
            'dim' => __('ui.sensor.light_conditions.dim'),
            'cloudy' => __('ui.sensor.light_conditions.cloudy'),
            'bright' => __('ui.sensor.light_conditions.bright'),
        ],
        'recommendations' => [
            'SAFE' => [__('ui.recommendations.status.safe.title'), __('ui.recommendations.status.safe.summary')],
            'WARNING' => [__('ui.recommendations.status.warning.title'), __('ui.recommendations.status.warning.summary')],
            'DANGER' => [__('ui.recommendations.status.danger.title'), __('ui.recommendations.status.danger.summary')],
            'FLOOD' => [__('ui.recommendations.status.flood.title'), __('ui.recommendations.status.flood.summary')],
            'OFFLINE' => [__('ui.recommendations.status.offline.title'), __('ui.recommendations.status.offline.summary')],
        ],
        'alerts' => [
            'enable' => __('ui.alerts.enable'),
            'enabled' => __('ui.alerts.enabled'),
            'unsupported' => __('ui.alerts.unsupported'),
            'denied' => __('ui.alerts.denied'),
            'soundOnly' => __('ui.alerts.sound_only'),
            'infoEnabled' => __('ui.alerts.info_enabled'),
            'infoDisabled' => __('ui.alerts.info_disabled'),
            'SAFE' => [__('ui.alerts.safe.title'), __('ui.alerts.safe.body')],
            'WARNING' => [__('ui.alerts.warning.title'), __('ui.alerts.warning.body')],
            'DANGER' => [__('ui.alerts.danger.title'), __('ui.alerts.danger.body')],
            'FLOOD' => [__('ui.alerts.flood.title'), __('ui.alerts.flood.body')],
            'OFFLINE' => [__('ui.alerts.offline.title'), __('ui.alerts.offline.body')],
            'voice' => [
                'SAFE' => __('ui.alerts.voice.safe'),
                'WARNING' => __('ui.alerts.voice.warning'),
                'DANGER' => __('ui.alerts.voice.danger'),
                'FLOOD' => __('ui.alerts.voice.flood'),
                'OFFLINE' => __('ui.alerts.voice.offline'),
            ],
        ],
        'chat' => [
            'typing' => __('ui.chat.typing'),
            'sendError' => __('ui.chat.send_error'),
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-dvh bg-mist text-ink">
    <div data-alert-feedback class="alert-feedback hidden" role="status" aria-live="polite">
        <span class="alert-feedback-icon"><x-icon name="bell" class="size-5" /></span>
        <p data-alert-feedback-text></p>
    </div>
    <div class="min-h-dvh lg:grid lg:grid-cols-[248px_minmax(0,1fr)]">
        <x-desktop-sidebar />
        <div class="min-w-0">
            <x-app-header :latest="$latest ?? null" />
            <main class="mx-auto w-full max-w-6xl px-4 pb-28 pt-5 sm:px-6 lg:px-8 lg:pb-10 lg:pt-8">
                {{ $slot }}
            </main>
        </div>
    </div>
    <x-bottom-navigation />
    @stack('scripts')
</body>
</html>
