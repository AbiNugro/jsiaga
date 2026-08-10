@props(['status' => null])
@php $status = $status ?: 'UNKNOWN'; @endphp
<span {{ $attributes->class(['status-badge', 'status-safe' => $status === 'SAFE', 'status-warning' => $status === 'WARNING', 'status-danger' => $status === 'DANGER', 'status-flood' => $status === 'FLOOD', 'status-empty' => !in_array($status, ['SAFE','WARNING','DANGER','FLOOD'])]) }}>
    <span class="size-2 rounded-full bg-current" aria-hidden="true"></span>
    <span data-status-badge-label>{{ $status === 'OFFLINE' ? __('ui.status.offline') : (in_array($status, ['SAFE', 'WARNING', 'DANGER', 'FLOOD']) ? $status : __('ui.status.empty')) }}</span>
</span>
