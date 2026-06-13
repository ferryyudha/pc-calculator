import { useState, useEffect } from 'react'
import { getComponents, postRecommendBuild } from '../../services/api'
import BuildCard from '../../components/BuildCard/BuildCard'
import { formatRupiah, RESOLUTIONS } from '../../utils/helpers'

const PRESETS = [
  { label: 'Budget',   amount: 5000000 },
  { label: 'Mid-Range', amount: 10000000 },
  { label: 'High-End', amount: 15000000 },
  { label: 'Ultra',    amount: 25000000 },
]

export default function BuildRecommendation() {
  const [games, setGames]     = useState([])
  const [form, setForm]       = useState({ budget: '', game_id: '', resolution: '1080p' })
  const [result, setResult]   = useState(null)
  const [error, setError]     = useState(null)
  const [loading, setLoading] = useState(false)
  const [fetching, setFetching] = useState(true)

  useEffect(() => {
    getComponents.games().then((r) => setGames(r.data.data)).finally(() => setFetching(false))
  }, [])

  const handleSubmit = async (e) => {
    e.preventDefault()
    setLoading(true); setResult(null); setError(null)
    try {
      const res = await postRecommendBuild({ ...form, budget: Number(form.budget) })
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
      <div className="mb-10 animate-fade-in">
        <p className="text-green-400 text-sm font-medium mb-2">🏗️ Build Recommendation</p>
        <h1 className="text-4xl font-black text-white mb-3">Rekomendasi Build PC</h1>
        <p className="text-gray-400">Masukkan budget dan game target. Algoritma akan mencari kombinasi CPU + GPU + Motherboard + RAM + PSU terbaik dalam budget kamu.</p>
      </div>

      <form onSubmit={handleSubmit} className="glass p-6 space-y-5 mb-6 animate-slide-up">
        {/* Budget */}
        <div>
          <label className="block text-sm font-medium text-gray-300 mb-2">Budget (Rupiah)</label>
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

        {/* Game */}
        <div>
          <label className="block text-sm font-medium text-gray-300 mb-1.5">Game Target</label>
          <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-2">
            {fetching
              ? <p className="text-gray-500 text-sm">Memuat game...</p>
              : games.map((g) => (
                <button key={g.id} type="button"
                  onClick={() => setForm((f) => ({ ...f, game_id: g.id }))}
                  className={`flex items-center gap-2.5 p-3 rounded-xl text-left transition-all duration-200 border ${
                    form.game_id == g.id
                      ? 'border-accent/50 bg-accent/10 text-white'
                      : 'border-white/5 bg-surface-700/30 text-gray-400 hover:border-white/10 hover:text-white'
                  }`}>
                  <div className={`w-2 h-2 rounded-full flex-shrink-0 ${
                    {light:'bg-green-400', medium:'bg-yellow-400', heavy:'bg-orange-400', extreme:'bg-red-400'}[g.weight_class]
                  }`}/>
                  <span className="text-sm font-medium truncate">{g.name}</span>
                  <span className="ml-auto text-xs text-gray-500">{g.min_vram}GB</span>
                </button>
              ))
            }
          </div>
        </div>

        {/* Resolution */}
        <div>
          <label className="block text-sm font-medium text-gray-300 mb-2">Resolusi Target</label>
          <div className="grid grid-cols-4 gap-2">
            {RESOLUTIONS.map((r) => (
              <button key={r} type="button"
                onClick={() => setForm((f) => ({ ...f, resolution: r }))}
                className={`py-3 rounded-xl text-sm font-semibold transition-all duration-200 ${
                  form.resolution === r
                    ? 'bg-gradient-to-r from-primary to-accent text-white shadow-glow-primary'
                    : 'bg-surface-700/60 text-gray-400 hover:text-white'
                }`}>
                {r}
              </button>
            ))}
          </div>
        </div>

        <button type="submit" className="btn-primary w-full justify-center py-4 text-base" disabled={!allFilled || loading || fetching}>
          {loading
            ? <><span className="spinner w-5 h-5 border border-white/30 border-t-white"></span> Mencari build terbaik...</>
            : '🏗️ Rekomendasikan Build'
          }
        </button>
      </form>

      {error && (
        <div className="glass p-5 border border-danger/20 mb-6 animate-slide-up">
          <div className="flex items-start gap-3">
            <div className="w-8 h-8 rounded-lg bg-danger/15 text-danger flex items-center justify-center flex-shrink-0 text-lg">💸</div>
            <div>
              <p className="font-semibold text-danger text-sm">{error.code || 'Budget Tidak Cukup'}</p>
              <p className="text-gray-400 text-sm mt-0.5">{error.message}</p>
              <p className="text-xs text-gray-500 mt-2">Coba tingkatkan budget atau pilih game/resolusi yang lebih ringan.</p>
            </div>
          </div>
        </div>
      )}

      {result && <BuildCard data={result} />}
    </div>
  )
}
