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
        history: newMessages.slice(-6),
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
    } finally {
      setLoading(false)
    }
  }

  const formatMessage = (text) => {
    // Basic markdown support: bold and line breaks
    return text
      .split('\n')
      .map((line, i) => {
        // Convert bold markdown **text** to strong html
        let formattedLine = line.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
        // Convert list markdown * text or - text to list element display
        if (formattedLine.startsWith('* ')) {
          formattedLine = `• ${formattedLine.substring(2)}`
        } else if (formattedLine.startsWith('- ')) {
          formattedLine = `• ${formattedLine.substring(2)}`
        }
        return <span key={i} dangerouslySetInnerHTML={{ __html: formattedLine }} className="block min-h-[1em]" />
      })
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
                     bg-slate-800 border border-slate-700 rounded-2xl shadow-2xl
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
                        ? 'inline-block bg-blue-600 text-white rounded-2xl rounded-tr-none px-3.5 py-2 text-sm max-w-[85%] text-left shadow-sm'
                        : 'inline-block bg-slate-700 text-slate-100 rounded-2xl rounded-tl-none px-3.5 py-2 text-sm max-w-[85%] text-left shadow-sm border border-slate-650'
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
          <div className="border-t border-slate-700/60 p-3 bg-slate-800/80 shrink-0">
            <form onSubmit={handleSend} className="flex gap-2">
              <input
                type="text"
                value={inputText}
                onChange={(e) => setInputText(e.target.value)}
                placeholder="Tulis pertanyaan..."
                disabled={loading}
                className="flex-1 bg-slate-900 border border-slate-700 focus:border-blue-500 rounded-xl
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
