<x-layouts.app :title="__('ui.chat.title')" :latest="$latest">
    @php($chatOnline = $latest && $latest->effectiveStatus() !== 'OFFLINE')
    <div data-page="chat" class="chat-shell -mt-1 lg:-mt-3">
        <header class="flex items-center justify-start gap-3 border-b border-navy/5 px-1 pb-3">
            <span class="flex size-14 shrink-0 items-center justify-center" aria-hidden="true">
                <img src="{{ asset('images/icon-bot.png') }}" alt="" class="block size-full object-contain">
            </span>
            <div><h1 class="text-balance text-lg font-bold text-ink">J-SIAGA Assistant</h1><p data-chat-connection-status class="text-xs italic text-muted">{{ $chatOnline ? __('ui.chat.ready') : __('ui.chat.offline') }}</p></div>
        </header>

        <div id="chatMessages" class="chat-messages" aria-live="polite">
            <x-chat-bubble>{{ __('ui.chat.welcome') }}</x-chat-bubble>
        </div>

        <div class="border-t border-navy/5 bg-mist/95 pt-3">
            <div class="no-scrollbar flex gap-2 overflow-x-auto pb-3">
                <x-quick-question-chip :question="__('ui.chat.quick_status')" />
                <x-quick-question-chip :question="__('ui.chat.quick_data')" />
                <x-quick-question-chip :question="__('ui.chat.quick_action')" />
                <x-quick-question-chip :question="__('ui.chat.quick_level')" />
            </div>
            <form id="chatForm" class="chat-form">
                <label for="chatInput" class="sr-only">{{ __('ui.chat.label') }}</label>
                <textarea id="chatInput" name="message" rows="1" maxlength="500" placeholder="{{ __('ui.chat.placeholder') }}" class="chat-input"></textarea>
                <button type="submit" class="send-button" aria-label="{{ __('ui.chat.send') }}"><x-icon name="send" class="size-5" /></button>
            </form>
            <p id="chatError" class="mt-2 hidden text-pretty text-xs text-danger" role="alert"></p>
        </div>
    </div>
</x-layouts.app>
