<style>
[x-cloak] { display: none !important; }
</style>

<div x-data="chatWidget()" x-cloak>

    {{-- Floating Button --}}
    <button @click="open = !open"
        class="fixed bottom-6 right-6 z-50 bg-gradient-to-r from-cyan-500 to-purple-600
               text-white rounded-full w-14 h-14 flex items-center justify-center
               shadow-lg hover:scale-105 transition focus:outline-none">
        <span x-show="!open" class="text-2xl">💬</span>
        <span x-show="open" class="text-2xl">✕</span>
    </button>

    {{-- Chat Panel --}}
    <div x-show="open" x-transition
        class="fixed bottom-24 right-6 z-50 w-96 max-w-[calc(100vw-3rem)]
               bg-slate-800 border border-slate-700 rounded-2xl shadow-2xl
               flex flex-col h-[500px] text-slate-100 overflow-hidden">

        {{-- Header --}}
        <div class="bg-gradient-to-r from-cyan-500 to-purple-600 px-4 py-3 shrink-0">
            <h3 class="text-white font-bold text-sm">Asisten PC Calculator</h3>
            <p class="text-white/80 text-xs">Tanya apa saja soal rakitan PC & komponen</p>
        </div>

        {{-- Messages --}}
        <div class="flex-1 overflow-y-auto p-4 space-y-3" x-ref="messagesContainer">
            <template x-if="messages.length === 0">
                <div class="text-slate-400 text-sm text-center mt-12 px-4 leading-relaxed">
                    👋 Halo! Saya asisten pintar PC Calculator.<br><br>
                    Tanya saya soal kompatibilitas komponen, estimasi FPS game, atau rekomendasi build sesuai budget kamu!
                </div>
            </template>

            <template x-for="(msg, index) in messages" :key="index">
                <div :class="msg.role === 'user' ? 'text-right' : 'text-left'">
                    <div :class="msg.role === 'user'
                        ? 'inline-block bg-blue-600 text-white rounded-2xl rounded-tr-none px-3.5 py-2 text-sm max-w-[85%] text-left shadow-sm'
                        : 'inline-block bg-slate-750 text-slate-100 rounded-2xl rounded-tl-none px-3.5 py-2 text-sm max-w-[85%] text-left shadow-sm border border-slate-700/50'"
                        x-html="formatMessage(msg.content)">
                    </div>
                </div>
            </template>

            <template x-if="loading">
                <div class="text-left">
                    <div class="inline-block bg-slate-700/60 text-slate-400 rounded-2xl rounded-tl-none px-4 py-2.5 text-sm">
                        <span class="flex items-center gap-1.5">
                            <span class="animate-bounce">●</span>
                            <span class="animate-bounce" style="animation-delay: 0.2s">●</span>
                            <span class="animate-bounce" style="animation-delay: 0.4s">●</span>
                        </span>
                    </div>
                </div>
            </template>
        </div>

        {{-- Input --}}
        <div class="border-t border-slate-700/60 p-3 bg-slate-800/80 shrink-0">
            <form @submit.prevent="sendMessage" class="flex gap-2">
                <input type="text" x-model="inputText"
                    placeholder="Tulis pertanyaan..."
                    :disabled="loading"
                    class="flex-1 bg-slate-900 border border-slate-750 focus:border-blue-500 rounded-xl
                           px-4 py-2.5 text-sm text-slate-100 focus:outline-none
                           disabled:opacity-50 transition-colors">
                <button type="submit" :disabled="loading || !inputText.trim()"
                    class="bg-blue-600 hover:bg-blue-500 disabled:opacity-50 disabled:hover:bg-blue-600
                           text-white rounded-xl px-4 py-2.5 text-sm font-semibold transition active:scale-[0.97]">
                    Kirim
                </button>
            </form>
        </div>
    </div>
</div>

<script>
function chatWidget() {
    return {
        open: false,
        loading: false,
        inputText: '',
        messages: [],

        async sendMessage() {
            const text = this.inputText.trim();
            if (!text) return;

            this.messages.push({ role: 'user', content: text });
            this.inputText = '';
            this.loading = true;
            this.scrollToBottom();

            try {
                const response = await fetch('{{ route("chat.send") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({
                        message: text,
                        history: this.messages.slice(-6),
                    }),
                });

                const data = await response.json();
                this.messages.push({
                    role: 'assistant',
                    content: data.reply || 'Maaf, terjadi kesalahan.',
                });
            } catch (e) {
                this.messages.push({
                    role: 'assistant',
                    content: 'Maaf, gagal menghubungi server. Coba lagi.',
                });
            }

            this.loading = false;
            this.scrollToBottom();
        },

        scrollToBottom() {
            this.$nextTick(() => {
                const container = this.$refs.messagesContainer;
                if (container) container.scrollTop = container.scrollHeight;
            });
        },

        formatMessage(text) {
            // Basic markdown: bold, list item, dan line break
            return text
                .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                .replace(/^\*\s(.*)/gm, '• $1')
                .replace(/\n/g, '<br>');
        }
    }
}
</script>
