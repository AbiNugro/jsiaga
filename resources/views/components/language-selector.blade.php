@php
    $languages = [
        'id' => ['code' => 'ID', 'label' => 'Indonesia'],
        'en' => ['code' => 'EN', 'label' => 'English'],
        'ko' => ['code' => 'KO', 'label' => '한국어'],
    ];
    $current = app()->getLocale();
@endphp

<details class="language-selector">
    <summary class="language-trigger" aria-label="{{ __('ui.language.choose') }}">
        <x-icon name="globe" class="size-5" />
        <span>{{ $languages[$current]['code'] ?? 'ID' }}</span>
        <x-icon name="chevron" class="language-chevron size-4" />
    </summary>
    <div class="language-menu" role="menu" aria-label="{{ __('ui.language.choose') }}">
        @foreach($languages as $locale => $language)
            <form method="POST" action="{{ route('language.switch', $locale) }}">
                @csrf
                <button type="submit" @class(['language-option', 'language-option-active' => $current === $locale]) role="menuitem" aria-current="{{ $current === $locale ? 'true' : 'false' }}">
                    <span class="language-code">{{ $language['code'] }}</span>
                    <span>{{ $language['label'] }}</span>
                    @if($current === $locale)<x-icon name="check" class="ml-auto size-4" />@endif
                </button>
            </form>
        @endforeach
    </div>
</details>
