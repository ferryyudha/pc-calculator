<style>
[x-cloak] { display: none !important; }
.chat-bubble-assistant {
    background-color: #1e293b;
    color: #f8fafc;
    border: 1px solid #334155;
    border-radius: 1rem;
    border-top-left-radius: 0;
    padding: 0.75rem 1rem;
    font-size: 0.875rem;
    line-height: 1.625;
    max-width: 85%;
    text-align: left;
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px -1px rgba(0, 0, 0, 0.1);
}
.chat-bubble-user {
    background-color: #2563eb;
    color: #ffffff;
    border-radius: 1rem;
    border-top-right-radius: 0;
    padding: 0.75rem 1rem;
    font-size: 0.875rem;
    line-height: 1.625;
    max-width: 85%;
    text-align: left;
    display: inline-block;
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px -1px rgba(0, 0, 0, 0.1);
}
.chat-bubble-assistant strong {
    color: #22d3ee;
    font-weight: 600;
}
.chat-bubble-assistant ul {
    margin-top: 0.5rem;
    margin-bottom: 0.5rem;
    padding-left: 1.25rem;
    list-style-type: disc;
}
.chat-bubble-assistant li {
    margin-bottom: 0.375rem;
}
.chat-bubble-assistant p {
    margin-bottom: 0.5rem;
}
.chat-bubble-assistant p:last-child {
    margin-bottom: 0;
}
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
                    <div :class="msg.role === 'user' ? 'chat-bubble-user' : 'chat-bubble-assistant'"
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
                    class="flex-1 bg-slate-900 border border-slate-700 focus:border-blue-500 rounded-xl
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
            if (!text) return '';
            
            // Normalize newlines
            let lines = text.replace(/\r\n/g, '\n').replace(/\r/g, '\n').split('\n');
            let resultHtml = [];
            let inList = false;
            
            for (let line of lines) {
                let trimmed = line.trim();
                // Check if line starts with * or - for bullet points
                if (trimmed.startsWith('* ') || trimmed.startsWith('- ') || trimmed.startsWith('• ')) {
                    if (!inList) {
                        resultHtml.push('<ul class="list-disc pl-5 my-2 space-y-1">');
                        inList = true;
                    }
                    let content = trimmed.replace(/^[*•-]\s+/, '');
                    // Format bold tags inside the list item
                    content = content.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
                    resultHtml.push('<li>' + content + '</li>');
                } else {
                    if (inList) {
                        resultHtml.push('</ul>');
                        inList = false;
                    }
                    if (trimmed === '') {
                        resultHtml.push('<div class="h-2"></div>'); // Spacing for empty lines
                    } else {
                        // Format bold tags inside the paragraph
                        let content = line.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
                        resultHtml.push('<p class="mb-2 leading-relaxed">' + content + '</p>');
                    }
                }
            }
            if (inList) {
                resultHtml.push('</ul>');
            }
            return resultHtml.join('');
        }
    }
}
</script>
