@props(['message' => 'Data tidak dapat dimuat. Coba lagi beberapa saat.'])
<div {{ $attributes->class(['rounded-2xl bg-danger-soft p-4 text-sm text-danger']) }} role="alert">
    <div class="flex items-start gap-3"><x-icon name="alert" class="mt-0.5 size-5 shrink-0" /><p class="text-pretty">{{ $message }}</p></div>
</div>
