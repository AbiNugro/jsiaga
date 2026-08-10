@props(['title', 'summary', 'steps' => [], 'status' => null, 'numbered' => false])
<article {{ $attributes->class(['surface overflow-hidden']) }}>
    <div class="flex items-start gap-3 p-5 sm:p-6">
        <span @class(['grid size-11 shrink-0 place-items-center rounded-2xl', 'bg-safe-soft text-safe' => $status === 'SAFE', 'bg-warning-soft text-warning-ink' => $status === 'WARNING', 'bg-danger-soft text-danger' => in_array($status, ['DANGER', 'FLOOD']), 'bg-sky-soft text-teal' => !$status])>
            <x-icon :name="in_array($status, ['DANGER', 'FLOOD']) ? 'alert' : 'shield'" class="size-5" />
        </span>
        <div class="min-w-0">
            <h2 class="text-balance text-lg font-bold text-ink">{{ $title }}</h2>
            <p class="mt-1 text-pretty text-sm leading-6 text-muted">{{ $summary }}</p>
        </div>
    </div>
    @if($steps)
        <ol class="border-t border-navy/5 px-5 py-4 sm:px-6">
            @foreach($steps as $index => $step)
                <li class="flex gap-3 py-2 text-sm leading-6 text-ink">
                    <span class="grid size-6 shrink-0 place-items-center rounded-full bg-mist text-xs font-bold text-teal">{{ $numbered ? $index + 1 : '✓' }}</span>
                    <span class="text-pretty">{{ $step }}</span>
                </li>
            @endforeach
        </ol>
    @endif
</article>
