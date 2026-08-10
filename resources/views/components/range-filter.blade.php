@props(['active' => '1h'])
@php $ranges = ['1h' => __('ui.range.1h'), '6h' => __('ui.range.6h'), '24h' => __('ui.range.24h'), '7d' => __('ui.range.7d')]; @endphp
<div class="segmented-control" role="group" aria-label="{{ __('ui.range.label') }}">
    @foreach($ranges as $value => $label)
        <a href="{{ route('history', ['range' => $value]) }}" @class(['segment', 'segment-active' => $active === $value]) aria-current="{{ $active === $value ? 'true' : 'false' }}">{{ $label }}</a>
    @endforeach
</div>
