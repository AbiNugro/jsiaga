@props(['role' => 'assistant'])
<div @class(['flex', 'justify-end' => $role === 'user', 'justify-start' => $role !== 'user'])>
    <div @class(['chat-bubble', 'chat-bubble-user' => $role === 'user', 'chat-bubble-assistant' => $role !== 'user'])>{{ $slot }}</div>
</div>
