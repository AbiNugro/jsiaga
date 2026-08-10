@props(['lines' => 3])
<div {{ $attributes->class(['surface animate-pulse space-y-3 p-5']) }} role="status" aria-label="Memuat data">
    @for($i = 0; $i < $lines; $i++)
        <div @class(['h-3 rounded-full bg-navy/10', 'w-2/3' => $i % 2 === 0, 'w-full' => $i % 2 !== 0])></div>
    @endfor
</div>
