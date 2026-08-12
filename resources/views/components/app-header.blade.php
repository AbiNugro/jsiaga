@props(['latest' => null])

<header class="sticky top-0 z-30 border-b border-navy/5 bg-mist/95 px-4 py-3 backdrop-blur-sm sm:px-6 lg:hidden">
    <div class="mx-auto flex max-w-6xl items-center justify-between gap-3">
        <x-app-logo class="app-logo-mobile" />
        <div class="flex items-center gap-2">
            <button type="button" data-enable-alerts class="alert-icon-button" aria-label="{{ __('ui.alerts.enable') }}" title="{{ __('ui.alerts.enable') }}" aria-pressed="false">
                <x-icon name="bell" class="size-5" />
                <span data-alert-button-label class="sr-only">{{ __('ui.alerts.enable') }}</span>
            </button>
            <x-language-selector />
        </div>
    </div>
</header>
