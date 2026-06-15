import { useState, useEffect } from 'react'
import { getComponents, postRecommendBuild, postRecommendBuildAi, postRecommendBuildTier } from '../../services/api'
import BuildCard from '../../components/BuildCard/BuildCard'
import { formatRupiah, RESOLUTIONS } from '../../utils/helpers'

const PRESETS = [
  { label: 'Budget',   amount: 5000000 },
  { label: 'Mid-Range', amount: 10000000 },
  { label: 'High-End', amount: 15000000 },
  { label: 'Ultra',    amount: 25000000 },
]

const TIERS = [
  { key: 'entry',       label: 'Entry Level',  desc: 'Hingga Rp 5 Juta',  color: 'border-emerald-500/30 text-emerald-400 bg-emerald-500/5 hover:bg-emerald-500/10' },
  { key: 'mainstream',  label: 'Mainstream',   desc: 'Hingga Rp 12 Juta', color: 'border-blue-500/30 text-blue-400 bg-blue-500/5 hover:bg-blue-500/10' },
  { key: 'enthusiast',  label: 'Enthusiast',   desc: 'Di atas Rp 20 Juta', color: 'border-purple-500/30 text-purple-400 bg-purple-500/5 hover:bg-purple-500/10' },
]

export default function BuildRecommendation() {
  const [games, setGames]       = useState([])
  const [form, setForm]         = useState({ budget: '', game_id: '', resolution: '1080p' })
  const [aiPrompt, setAiPrompt] = useState('')
  const [result, setResult]     = useState(null)
  const [error, setError]       = useState(null)
  const [loading, setLoading]   = useState(false)
  const [aiLoading, setAiLoading] = useState(false)
  const [fetching, setFetching] = useState(true)
  const [detectedInfo, setDetectedInfo] = useState(null)

  useEffect(() => {
    getComponents.games()
      .then((r) => {
        setGames(r.data.data)
        // Select first game by default to avoid empty selection issues
        if (r.data.data.length > 0) {
          setForm(f => ({ ...f, game_id: r.data.data[0].id }))
        }
      })
      .finally(() => setFetching(false))
  }, [])

  const handleSubmit = async (e) => {
    e.preventDefault()
    setLoading(true); setResult(null); setError(null); setDetectedInfo(null)
    try {
      const res = await postRecommendBuild({ ...form, budget: Number(form.budget) })
      setResult(res.data.data)
    } catch (err) {
      setError(err)
    } finally {
      setLoading(false)
    }
  }

  const handleAiSubmit = async (e) => {
    e.preventDefault()
    if (!aiPrompt.trim()) return
    setAiLoading(true); setResult(null); setError(null); setDetectedInfo(null)
    try {
      const res = await postRecommendBuildAi({ prompt: aiPrompt })
      setResult(res.data.data.recommendation)
      setDetectedInfo(res.data.data.parsed_prompt)
    } catch (err) {
      setError(err)
    } finally {
      setAiLoading(false)
    }
  }

  const handleTierClick = async (tierKey) => {
    setLoading(true); setResult(null); setError(null); setDetectedInfo(null)
    try {
      const res = await postRecommendBuildTier(tierKey)
      setResult(res.data.data)
    } catch (err) {
      setError(err)
    } finally {
      setLoading(false)
    }
  }

  const allFilled = form.budget && form.game_id && form.resolution

  return (
    <div className="max-w-4xl mx-auto px-4 sm:px-6 py-12">
      {/* Title Header */}
      <div className="mb-10 animate-fade-in">
        <p className="text-primary text-sm font-medium mb-2 uppercase tracking-wider">🏗️ Build Recommendation</p>
        <h1 class="text-4xl font-extrabold text-white mb-3 tracking-tight">Rekomendasi Rakitan PC</h1>
        <p className="text-gray-400">Pilih spesifikasi PC rakitan terbaik dengan cepat melalui AI, tier preset pintar, atau atur secara manual sesuai kebutuhan gaming dan budget Anda.</p>
      </div>

      {/* Section 1 — AI Prompt Input */}
      <div className="glass p-6 rounded-2xl mb-6 animate-slide-up border border-white/5 glow-cyan">
        <label className="block text-sm text-gray-300 font-semibold mb-2.5 flex items-center gap-2">
          <span>🤖 Gunakan AI untuk bantu rakitan kamu</span>
          <span className="bg-gradient-to-r from-primary to-accent text-[9px] uppercase font-black px-2 py-0.5 rounded text-white tracking-widest">Groq Powered</span>
        </label>
        <form onSubmit={handleAiSubmit} className="flex flex-col sm:flex-row gap-3">
          <input
            type="text"
            placeholder="Contoh: main GTA V 1080p, budget 10 juta, butuh RTX, 16GB RAM"
            value={aiPrompt}
            onChange={(e) => setAiPrompt(e.target.value)}
            disabled={aiLoading}
            className="flex-1 field"
          />
          <button
            type="submit"
            disabled={aiLoading || !aiPrompt.trim()}
            className="btn-primary py-3 px-6 whitespace-nowrap active:scale-[0.98] transition-transform flex items-center justify-center gap-2"
          >
            {aiLoading ? (
              <>
                <span className="spinner w-5 h-5 border border-white/30 border-t-white"></span>
                <span>Menganalisa...</span>
              </>
            ) : (
              <span>Build dengan AI</span>
            )}
          </button>
        </form>

        {detectedInfo && (
          <div className="mt-4 pt-3 border-t border-white/5 flex flex-wrap gap-2 text-xs text-gray-400 items-center">
            <span className="font-semibold text-gray-300">Hasil Deteksi AI:</span>
            <span className="bg-surface-700/40 px-2.5 py-1 rounded-md border border-white/5">
              Budget Maks: <strong className="text-primary">Rp {number_format(detectedInfo.budget_max ?? 0, 0, ',', '.')}</strong>
            </span>
            <span className="bg-surface-700/40 px-2.5 py-1 rounded-md border border-white/5">
              Resolusi: <strong className="text-accent">{detectedInfo.resolution ?? '-'}</strong>
            </span>
            {detectedInfo.gpu_preference && (
              <span className="bg-surface-700/40 px-2.5 py-1 rounded-md border border-white/5">
                GPU: <strong className="text-green-400">{detectedInfo.gpu_preference}</strong>
              </span>
            )}
          </div>
        )}
      </div>

      {/* Section 2 — Tier Cards */}
      <h2 className="text-sm font-bold text-gray-400 mb-3 uppercase tracking-wider">Pilih Berdasarkan Tier Budget</h2>
      <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
        {TIERS.map(({ key, label, desc, color }) => (
          <button
            key={key}
            onClick={() => handleTierClick(key)}
            disabled={loading}
            className={`w-full text-left p-6 rounded-2xl border transition-all duration-200 active:scale-[0.99] flex flex-col justify-between ${color}`}
          >
            <div>
              <div className="text-xs uppercase tracking-wider font-bold mb-1.5 opacity-80">{label}</div>
              <div className="text-white text-xl font-black mb-1">{desc}</div>
              <div className="text-gray-400 text-xs mb-4">Cocok untuk target {key === 'entry' ? '1080p' : (key === 'mainstream' ? '1440p' : '4K')}</div>
            </div>
            <div className="inline-flex items-center gap-1 text-xs font-bold mt-2 hover:underline">
              <span>Rakit Sekarang</span>
              <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M9 5l7 7-7 7"></path></svg>
            </div>
          </button>
        ))}
      </div>

      {/* Section 3 — Manual Form */}
      <div className="glass p-6 rounded-2xl mb-8 border border-white/5">
        <h2 className="text-lg font-bold text-white mb-5 flex items-center gap-2">
          <span className="w-1.5 h-4 bg-primary rounded-full"></span>
          <span>Atur Manual Rakitan</span>
        </h2>
        <form onSubmit={handleSubmit} className="space-y-5">
          {/* Budget */}
          <div>
            <label className="block text-sm font-semibold text-gray-300 mb-2">Budget (Rupiah)</label>
            <div className="flex flex-wrap gap-2 mb-3">
              {PRESETS.map(({ label, amount }) => (
                <button key={label} type="button"
                  onClick={() => setForm((f) => ({ ...f, budget: amount }))}
                  className={`px-4 py-2 rounded-xl text-sm font-medium transition-all duration-200 ${
                    Number(form.budget) === amount
                      ? 'bg-gradient-to-r from-primary/20 to-accent/20 text-primary border border-primary/30'
                      : 'bg-surface-700/40 text-gray-400 hover:text-white'
                  }`}>
                  {label}
                  <span className="ml-1.5 text-xs opacity-60">{formatRupiah(amount)}</span>
                </button>
              ))}
            </div>
            <input
              type="number" min="1000000" step="500000"
              value={form.budget} onChange={(e) => setForm((f) => ({ ...f, budget: e.target.value }))}
              placeholder="Contoh: 10000000" className="field font-mono"
            />
            {form.budget > 0 && (
              <p className="text-xs text-primary mt-1.5 font-medium">{formatRupiah(Number(form.budget))}</p>
            )}
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-5">
            {/* Game */}
            <div>
              <label className="block text-sm font-semibold text-gray-300 mb-1.5">Game Target</label>
              <select
                value={form.game_id}
                onChange={(e) => setForm((f) => ({ ...f, game_id: e.target.value }))}
                className="field select-custom"
                required
              >
                {fetching ? (
                  <option>Memuat game...</option>
                ) : (
                  games.map((g) => (
                    <option key={g.id} value={g.id}>
                      {g.name} (Min. VRAM: {g.min_vram}GB)
                    </option>
                  ))
                )}
              </select>
            </div>

            {/* Resolution */}
            <div>
              <label className="block text-sm font-semibold text-gray-300 mb-1.5">Resolusi Target</label>
              <div className="grid grid-cols-4 gap-2">
                {RESOLUTIONS.map((r) => (
                  <button key={r} type="button"
                    onClick={() => setForm((f) => ({ ...f, resolution: r }))}
                    className={`py-2.5 rounded-xl text-sm font-semibold transition-all duration-200 ${
                      form.resolution === r
                        ? 'bg-gradient-to-r from-primary to-accent text-white shadow-glow-primary'
                        : 'bg-surface-700/60 text-gray-400 hover:text-white'
                    }`}>
                    {r}
                  </button>
                ))}
              </div>
            </div>
          </div>

          <button type="submit" className="btn-primary w-full justify-center py-4 text-base mt-2" disabled={!allFilled || loading || fetching}>
            {loading ? (
              <>
                <span className="spinner w-5 h-5 border border-white/30 border-t-white"></span>
                <span>Mencari build terbaik...</span>
              </>
            ) : (
              <span>🏗️ Rekomendasikan Build</span>
            )}
          </button>
        </form>
      </div>

      {/* Error Display */}
      {error && (
        <div className="glass p-5 border border-danger/20 mb-6 animate-slide-up rounded-2xl flex items-start gap-4 text-red-400">
          <span className="text-2xl mt-0.5">⚠️</span>
          <div>
            <p className="font-extrabold text-white text-lg">Gagal Membuat Rekomendasi</p>
            <p className="text-gray-400 text-sm mt-1">{error.message || 'Budget tidak mencukupi untuk rakitan tersebut.'}</p>
            <p className="text-xs text-gray-500 mt-2">Coba tingkatkan budget atau pilih game/resolusi yang lebih ringan.</p>
          </div>
        </div>
      )}

      {/* Result Display */}
      {result && <BuildCard data={result} />}
    </div>
  )
}

// Helper function equivalent to PHP number_format
function number_format(number, decimals, dec_point, thousands_sep) {
  number = (number + '').replace(/[^0-9+\-Ee.]/g, '');
  var n = !isFinite(+number) ? 0 : +number,
    prec = !isFinite(+decimals) ? 0 : Math.abs(decimals),
    sep = (typeof thousands_sep === 'undefined') ? ',' : thousands_sep,
    dec = (typeof dec_point === 'undefined') ? '.' : dec_point,
    s = '',
    toFixedFix = function(n, prec) {
      var k = Math.pow(10, prec);
      return '' + (Math.round(n * k) / k).toFixed(prec);
    };
  s = (prec ? toFixedFix(n, prec) : '' + Math.round(n)).split('.');
  if (s[0].length > 3) {
    s[0] = s[0].replace(/\B(?=(?:\d{3})+(?!\d))/g, sep);
  }
  if ((s[1] || '').length < prec) {
    s[1] = s[1] || '';
    s[1] += new Array(prec - s[1].length + 1).join('0');
  }
  return s.join(dec);
}
