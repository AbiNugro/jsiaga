@php
    $items = [
        ['route' => 'home', 'label' => __('ui.nav.home'), 'icon' => 'home'],
        ['route' => 'history', 'label' => __('ui.nav.history'), 'icon' => 'history'],
        ['route' => 'recommendations', 'label' => __('ui.nav.guide'), 'icon' => 'shield'],
        ['route' => 'chat', 'label' => __('ui.nav.chat'), 'icon' => 'chat'],
    ];
@endphp

<nav class="bottom-nav-wrap" aria-label="{{ __('ui.nav.bottom') }}">
    <div class="bottom-nav-shell">
        @foreach($items as $item)
            <a href="{{ route($item['route']) }}" @class(['bottom-nav-item', 'bottom-nav-item-active' => request()->routeIs($item['route'])]) aria-current="{{ request()->routeIs($item['route']) ? 'page' : 'false' }}">
                <span class="bottom-nav-icon"><x-icon :name="$item['icon']" class="size-6" /></span>
                <span>{{ $item['label'] }}</span>
            </a>
        @endforeach
    </div>
</nav>
