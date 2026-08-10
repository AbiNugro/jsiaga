<x-layouts.app :title="__('ui.chat.title')" :latest="$latest">
    <div data-page="chat" class="chat-shell">
        <header class="flex items-center gap-3 border-b border-navy/5 px-1 pb-4">
            <span class="grid size-11 shrink-0 place-items-center rounded-2xl bg-teal text-white"><x-icon name="bot" class="size-6" /></span>
            <div><h1 class="text-balance text-lg font-bold text-ink">J-SIAGA Assistant</h1><p class="flex items-center gap-1.5 text-xs text-muted"><span class="size-2 rounded-full bg-safe"></span> {{ __('ui.chat.ready') }}</p></div>
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
