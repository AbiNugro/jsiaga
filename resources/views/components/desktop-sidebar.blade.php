@php
    $items = [
        ['route' => 'home', 'label' => __('ui.nav.home'), 'icon' => 'home'],
        ['route' => 'history', 'label' => __('ui.nav.history'), 'icon' => 'history'],
        ['route' => 'recommendations', 'label' => __('ui.nav.recommendations'), 'icon' => 'shield'],
        ['route' => 'chat', 'label' => __('ui.nav.chat'), 'icon' => 'chat'],
    ];
@endphp

<aside class="sticky top-0 hidden h-dvh border-r border-navy/5 bg-white px-5 py-7 lg:flex lg:flex-col">
    <x-app-logo class="px-2" />
    <nav class="mt-10 space-y-2" aria-label="{{ __('ui.nav.main') }}">
        @foreach($items as $item)
            <a href="{{ route($item['route']) }}" @class(['nav-item', 'nav-item-active' => request()->routeIs($item['route'])])>
                <x-icon :name="$item['icon']" class="size-5" />
                <span>{{ $item['label'] }}</span>
            </a>
        @endforeach
    </nav>
    <div class="language-selector-up mt-auto mb-4 flex items-center gap-2">
        <x-language-selector />
        <button type="button" data-enable-alerts class="alert-icon-button" aria-label="{{ __('ui.alerts.enable') }}" title="{{ __('ui.alerts.enable') }}" aria-pressed="false">
            <x-icon name="bell" class="size-5" />
            <span data-alert-button-label class="sr-only">{{ __('ui.alerts.enable') }}</span>
        </button>
    </div>
    <div class="rounded-3xl bg-mist p-4 text-sm text-muted shadow-inset">
        <div class="mb-2 flex items-center gap-2 font-semibold text-ink">
            <x-icon name="signal" class="size-4 text-teal" />
            {{ __('ui.sidebar.active') }}
        </div>
        <p class="text-pretty text-xs leading-5">{{ __('ui.sidebar.description') }}</p>
    </div>
</aside>
