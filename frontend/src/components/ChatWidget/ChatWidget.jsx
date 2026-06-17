import { useState, useRef, useEffect } from 'react'
import api from '../../services/api'

export default function ChatWidget() {
  const [open, setOpen] = useState(false)
  const [messages, setMessages] = useState([])
  const [inputText, setInputText] = useState('')
  const [loading, setLoading] = useState(false)

  const messagesEndRef = useRef(null)

  const scrollToBottom = () => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' })
  }

  useEffect(() => {
    if (open) {
      scrollToBottom()
    }
  }, [messages, open])

  const handleSend = async (e) => {
    e.preventDefault()
    const text = inputText.trim()
    if (!text) return

    const newMessages = [...messages, { role: 'user', content: text }]
    setMessages(newMessages)
    setInputText('')
    setLoading(true)

    try {
      const response = await api.post('/chat', {
        message: text,
        history: messages.slice(-6), // Kirim history SEBELUM pesan user saat ini, backend menambahkan sendiri
      })

      setMessages((prev) => [
        ...prev,
        { role: 'assistant', content: response.data.reply || 'Maaf, terjadi kesalahan.' },
      ])
    } catch (err) {
      setMessages((prev) => [
        ...prev,
        { role: 'assistant', content: 'Maaf, gagal menghubungi server. Coba lagi.' },
      ])

      // Catat error frontend ke server log (chat_errors.log)
      try {
        await api.post('/log-error', {
          type:    'NetworkError',
          message: err?.message || String(err),
          detail:  err?.code || null,
          url:     window.location.href,
        })
      } catch (_) {
        // Abaikan jika log juga gagal (server offline, dll)
      }
    } finally {
      setLoading(false)
    }
  }

  const formatMessage = (text) => {
    if (!text) return null;

    let lines = text.replace(/\r\n/g, '\n').replace(/\r/g, '\n').split('\n');
    let resultHtml = [];
    let inList = false;

    for (let line of lines) {
      let trimmed = line.trim();
      if (trimmed.startsWith('* ') || trimmed.startsWith('- ') || trimmed.startsWith('• ')) {
        if (!inList) {
          resultHtml.push('<ul class="list-disc pl-5 my-2 space-y-1">');
          inList = true;
        }
        let content = trimmed.replace(/^[*•-]\s+/, '');
        content = content.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
        resultHtml.push('<li>' + content + '</li>');
      } else {
        if (inList) {
          resultHtml.push('</ul>');
          inList = false;
        }
        if (trimmed === '') {
          resultHtml.push('<div class="h-2"></div>');
        } else {
          let content = line.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
          resultHtml.push('<p class="mb-2 leading-relaxed">' + content + '</p>');
        }
      }
    }
    if (inList) {
      resultHtml.push('</ul>');
    }

    const htmlString = resultHtml.join('');
    return <div dangerouslySetInnerHTML={{ __html: htmlString }} />;
  }

  return (
    <div className="relative z-50">
      {/* Floating Button */}
      <button
        onClick={() => setOpen(!open)}
        className="fixed bottom-6 right-6 bg-gradient-to-r from-cyan-500 to-purple-600
                   text-white rounded-full w-14 h-14 flex items-center justify-center
                   shadow-lg hover:scale-105 transition-transform duration-200 focus:outline-none cursor-pointer"
      >
        {!open ? <span className="text-2xl">💬</span> : <span className="text-2xl">✕</span>}
      </button>

      {/* Chat Panel */}
      {open && (
        <div
          className="fixed bottom-24 right-6 w-96 max-w-[calc(100vw-3rem)]
                     bg-surface-800 border border-surface-600 rounded-2xl shadow-2xl
                     flex flex-col h-[500px] text-slate-100 overflow-hidden animate-slide-up"
        >
          {/* Header */}
          <div className="bg-gradient-to-r from-cyan-500 to-purple-600 px-4 py-3 shrink-0">
            <h3 className="text-white font-bold text-sm">Asisten PC Calculator</h3>
            <p className="text-white/80 text-xs">Tanya apa saja soal rakitan PC & komponen</p>
          </div>

          {/* Messages */}
          <div className="flex-1 overflow-y-auto p-4 space-y-3">
            {messages.length === 0 ? (
              <div className="text-slate-400 text-sm text-center mt-12 px-4 leading-relaxed">
                👋 Halo! Saya asisten pintar PC Calculator.
                <br />
                <br />
                Tanya saya soal kompatibilitas komponen, estimasi FPS game, atau rekomendasi build sesuai budget kamu!
              </div>
            ) : (
              messages.map((msg, index) => (
                <div key={index} className={msg.role === 'user' ? 'text-right' : 'text-left'}>
                  <div
                    className={
                      msg.role === 'user'
                        ? 'inline-block bg-blue-600 text-white rounded-2xl rounded-tr-none px-3.5 py-2.5 text-sm max-w-[85%] text-left shadow-md'
                        : 'inline-block bg-surface-700 text-slate-100 rounded-2xl rounded-tl-none px-4 py-3 text-sm max-w-[85%] text-left shadow-md border border-surface-600 [&_strong]:text-cyan-400 [&_strong]:font-semibold [&_ul]:list-disc [&_ul]:pl-5 [&_ul]:my-2 [&_li]:mb-1'
                    }
                  >
                    {formatMessage(msg.content)}
                  </div>
                </div>
              ))
            )}

            {loading && (
              <div className="text-left">
                <div className="inline-block bg-slate-700/60 text-slate-400 rounded-2xl rounded-tl-none px-4 py-2.5 text-sm">
                  <span className="flex items-center gap-1.5">
                    <span className="animate-bounce">●</span>
                    <span className="animate-bounce" style={{ animationDelay: '0.2s' }}>●</span>
                    <span className="animate-bounce" style={{ animationDelay: '0.4s' }}>●</span>
                  </span>
                </div>
              </div>
            )}
            <div ref={messagesEndRef} />
          </div>

          {/* Input */}
          <div className="border-t border-surface-600 p-3 bg-surface-900/80 shrink-0">
            <form onSubmit={handleSend} className="flex gap-2">
              <input
                type="text"
                value={inputText}
                onChange={(e) => setInputText(e.target.value)}
                placeholder="Tulis pertanyaan..."
                disabled={loading}
                className="flex-1 bg-surface-900 border border-surface-600 focus:border-blue-500 rounded-xl
                           px-4 py-2.5 text-sm text-slate-100 focus:outline-none
                           disabled:opacity-50 transition-colors"
              />
              <button
                type="submit"
                disabled={loading || !inputText.trim()}
                className="bg-blue-600 hover:bg-blue-500 disabled:opacity-50 disabled:hover:bg-blue-600
                           text-white rounded-xl px-4 py-2.5 text-sm font-semibold transition active:scale-[0.97] cursor-pointer"
              >
                Kirim
              </button>
            </form>
          </div>
        </div>
      )}
    </div>
  )
}
