import { useState, useEffect } from 'react'
import { getComponents, postRecommendBuild, postRecommendBuildAi, postRecommendBuildTier, getPopularTags } from '../../services/api'
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

const PURPOSES = ['4k Gaming', 'Graphic Design', 'Home Office', 'Light Gaming', 'Streaming']
const BUDGET_RANGES = ['Rp 5-10 Juta', 'Rp 10-15 Juta', 'Rp 15-20 Juta', 'Rp 20-30 Juta', 'Rp 30-40 Juta', 'Rp 40-60 Juta']

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
  const [popularTags, setPopularTags] = useState({ cpus: [], gpus: [] })

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

    // Fetch popular tags from backend recommendations count
    getPopularTags()
      .then((r) => {
        if (r.data?.success && r.data?.data) {
          setPopularTags(r.data.data)
        }
      })
      .catch((err) => {
        console.error('Failed to load popular tags', err)
      })
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

  const handleTagClick = (type, value) => {
    const lowercasePrompt = aiPrompt.toLowerCase();

    // Determine currently active options in prompt
    const activePurpose = PURPOSES.find(p => lowercasePrompt.includes(p.toLowerCase()));
    const activeCpu = [...popularTags.cpus, 'i5', 'i7', 'i9', 'Ryzen 5', 'Ryzen 7', 'Ryzen 9', '245k', '265k', '285k']
      .find(c => lowercasePrompt.includes(c.toLowerCase()));
    const activeGpu = [...popularTags.gpus, 'RTX5090', 'RTX5080', 'RTX5070Ti', 'RTX5060Ti', 'RX7900XT', 'RX9070XT', 'RX9060XT']
      .find(g => lowercasePrompt.includes(g.toLowerCase()));
    const activeBudget = BUDGET_RANGES.find(b => lowercasePrompt.includes(b.toLowerCase()));

    let newPurpose = activePurpose || '';
    let newCpu = activeCpu || '';
    let newGpu = activeGpu || '';
    let newBudget = activeBudget || '';

    if (type === 'purpose') {
      newPurpose = activePurpose === value ? '' : value;
    } else if (type === 'cpu') {
      newCpu = activeCpu === value ? '' : value;
    } else if (type === 'gpu') {
      newGpu = activeGpu === value ? '' : value;
    } else if (type === 'budget') {
      newBudget = activeBudget === value ? '' : value;
    }

    const parts = [];
    if (newPurpose) {
      parts.push(`pc for ${newPurpose}`);
    }
    if (newCpu) {
      parts.push(`cpu: ${newCpu}`);
    }
    if (newGpu) {
      parts.push(`vga: ${newGpu}`);
    }
    if (newBudget) {
      parts.push(`budget: ${newBudget}`);
    }

    if (newPurpose) {
      const remaining = [];
      if (newCpu) remaining.push(`cpu: ${newCpu}`);
      if (newGpu) remaining.push(`vga: ${newGpu}`);
      if (newBudget) remaining.push(`budget: ${newBudget}`);
      setAiPrompt(`pc for ${newPurpose}` + (remaining.length > 0 ? ', ' + remaining.join(', ') : ''));
    } else {
      setAiPrompt(parts.join(', '));
    }
  }

  const isTagActive = (type, value) => {
    if (!aiPrompt) return false;
    return aiPrompt.toLowerCase().includes(value.toLowerCase());
  }

  const allFilled = form.budget && form.game_id && form.resolution

  return (
    <div className="max-w-4xl mx-auto px-4 sm:px-6 py-12">
      {/* Title Header */}
      <div className="mb-10 animate-fade-in">
        <p className="text-primary text-sm font-medium mb-2 uppercase tracking-wider">🏗️ Build Recommendation</p>
        <h1 className="text-4xl font-extrabold text-white mb-3 tracking-tight">Rekomendasi Rakitan PC</h1>
        <p className="text-gray-400">Pilih spesifikasi PC rakitan terbaik dengan cepat melalui AI, tier preset pintar, atau atur secara manual sesuai kebutuhan gaming dan budget Anda.</p>
      </div>

      {/* Section 1 — Premium AI Prompt Builder UI */}
      <div className="glass p-8 rounded-3xl mb-8 border border-white/10 relative overflow-hidden bg-gradient-to-b from-surface-800/85 to-surface-900/90 shadow-2xl animate-slide-up">
        {/* Background Ambient Glow */}
        <div className="absolute top-0 right-0 w-80 h-80 bg-primary/10 rounded-full blur-3xl -z-10 pointer-events-none"></div>
        <div className="absolute bottom-0 left-0 w-80 h-80 bg-accent/5 rounded-full blur-3xl -z-10 pointer-events-none"></div>

        {/* Title Header with BETA Badge */}
        <div className="flex items-center justify-between mb-6">
          <h2 className="text-xl font-bold text-white flex items-center gap-2.5">
            <span className="text-xl">🤖</span>
            <span>Use AI to help you with your PC build</span>
          </h2>
          <span className="bg-orange-600/90 text-white text-[10px] font-black px-2.5 py-0.5 rounded-full tracking-wider uppercase border border-orange-500/20 shadow-glow-primary">
            BETA
          </span>
        </div>

        {/* Dynamic Search Box */}
        <form onSubmit={handleAiSubmit} className="relative flex items-center mb-6">
          <div className="relative flex-1">
            <input
              type="text"
              placeholder="Contoh: pc for Graphic Design, cpu: Ryzen 5, vga: RTX5070Ti, budget: Rp 10-15 Juta"
              value={aiPrompt}
              onChange={(e) => setAiPrompt(e.target.value)}
              disabled={aiLoading}
              className="w-full bg-surface-950/60 border border-white/10 hover:border-white/20 focus:border-primary/50 text-white placeholder-gray-500 rounded-2xl py-4.5 pl-5 pr-44 text-sm font-medium focus:outline-none focus:ring-1 focus:ring-primary/30 transition-all duration-300 backdrop-blur-sm"
            />
            {/* BUILD WITH AI Button inside the search bar */}
            <div className="absolute right-2 top-2 bottom-2">
              <button
                type="submit"
                disabled={aiLoading || !aiPrompt.trim()}
                className="h-full bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 disabled:from-surface-800 disabled:to-surface-850 disabled:text-gray-500 text-white font-extrabold px-6 rounded-xl text-xs uppercase tracking-wider flex items-center gap-2 active:scale-[0.98] transition-all duration-200 border border-blue-500/30 shadow-lg shadow-blue-500/10 cursor-pointer"
              >
                {aiLoading ? (
                  <>
                    <span className="spinner w-3.5 h-3.5 border border-white/30 border-t-white"></span>
                    <span>Building...</span>
                  </>
                ) : (
                  <>
                    <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                      <path strokeLinecap="round" strokeLinejoin="round" d="M14 10l-2 1m0 0l-2-1m2 1v2.5M20 7l-2 1m2-1l-2-1m2 1v2.5M14 4l-2-1-2 1v2.5M4 7l2-1M4 7l2 1M4 7v2.5M10 16l-2 1M10 16v2.5M6 21l2-1-2-1v2.5" />
                    </svg>
                    <span>BUILD WITH AI</span>
                  </>
                )}
              </button>
            </div>
          </div>
        </form>

        {/* Dynamic Tag Builder Rows */}
        <div className="space-y-4">
          {/* PURPOSE ROW */}
          <div className="flex items-start sm:items-center gap-4 py-2 border-b border-white/5">
            <div className="w-8 shrink-0 flex justify-center pt-1 sm:pt-0" title="Target/Purpose">
              <svg className="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                <rect width="20" height="12" x="2" y="4" rx="2" />
                <path d="M12 16v4" />
                <path d="M8 20h8" />
              </svg>
            </div>
            <div className="flex flex-wrap gap-2">
              {PURPOSES.map((p) => {
                const active = isTagActive('purpose', p);
                return (
                  <button
                    key={p}
                    type="button"
                    onClick={() => handleTagClick('purpose', p)}
                    className={`px-3 py-1.5 rounded-xl text-xs font-semibold border transition-all duration-200 cursor-pointer ${
                      active
                        ? 'bg-blue-600 text-white border-blue-500 shadow-lg shadow-blue-500/25'
                        : 'bg-surface-800/40 text-gray-400 border-white/5 hover:text-white hover:bg-surface-800/60'
                    }`}
                  >
                    {p}
                  </button>
                );
              })}
            </div>
          </div>

          {/* CPU ROW */}
          <div className="flex items-start sm:items-center gap-4 py-2 border-b border-white/5">
            <div className="w-8 shrink-0 flex justify-center pt-1 sm:pt-0" title="CPU Preference">
              <svg className="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                <rect width="14" height="14" x="5" y="5" rx="2" />
                <path d="M9 1v4M15 1v4M9 19v4M15 19v4M1 9h4M1 15h4M19 9h4M19 15h4M9 9h6v6H9z" />
              </svg>
            </div>
            <div className="flex flex-wrap gap-2">
              {(popularTags.cpus.length > 0 ? popularTags.cpus : ['i5', 'i7', 'i9', 'Ryzen 5', 'Ryzen 7', 'Ryzen 9', '245k', '265k', '285k']).map((c) => {
                const active = isTagActive('cpu', c);
                return (
                  <button
                    key={c}
                    type="button"
                    onClick={() => handleTagClick('cpu', c)}
                    className={`px-3 py-1.5 rounded-xl text-xs font-semibold border transition-all duration-200 cursor-pointer ${
                      active
                        ? 'bg-blue-600 text-white border-blue-500 shadow-lg shadow-blue-500/25'
                        : 'bg-surface-800/40 text-gray-400 border-white/5 hover:text-white hover:bg-surface-800/60'
                    }`}
                  >
                    {c}
                  </button>
                );
              })}
            </div>
          </div>

          {/* GPU ROW */}
          <div className="flex items-start sm:items-center gap-4 py-2 border-b border-white/5">
            <div className="w-8 shrink-0 flex justify-center pt-1 sm:pt-0" title="GPU Preference">
              <svg className="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                <rect width="18" height="12" x="3" y="6" rx="2" />
                <circle cx="9" cy="12" r="3" />
                <path d="M17 9h2M17 12h2M17 15h2" />
              </svg>
            </div>
            <div className="flex flex-wrap gap-2">
              {(popularTags.gpus.length > 0 ? popularTags.gpus : ['RTX5090', 'RTX5080', 'RTX5070Ti', 'RTX5060Ti', 'RX7900XT', 'RX9070XT', 'RX9060XT']).map((g) => {
                const active = isTagActive('gpu', g);
                return (
                  <button
                    key={g}
                    type="button"
                    onClick={() => handleTagClick('gpu', g)}
                    className={`px-3 py-1.5 rounded-xl text-xs font-semibold border transition-all duration-200 cursor-pointer ${
                      active
                        ? 'bg-blue-600 text-white border-blue-500 shadow-lg shadow-blue-500/25'
                        : 'bg-surface-800/40 text-gray-400 border-white/5 hover:text-white hover:bg-surface-800/60'
                    }`}
                  >
                    {g}
                  </button>
                );
              })}
            </div>
          </div>

          {/* BUDGET ROW */}
          <div className="flex items-start sm:items-center gap-4 py-2">
            <div className="w-8 shrink-0 flex justify-center pt-1 sm:pt-0" title="Budget Range">
              <svg className="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                <circle cx="12" cy="12" r="10" />
                <path d="M12 6v12M15 9.5H12a2.5 2.5 0 0 0 0 5h3" />
              </svg>
            </div>
            <div className="flex flex-wrap gap-2">
              {BUDGET_RANGES.map((b) => {
                const active = isTagActive('budget', b);
                return (
                  <button
                    key={b}
                    type="button"
                    onClick={() => handleTagClick('budget', b)}
                    className={`px-3 py-1.5 rounded-xl text-xs font-semibold border transition-all duration-200 cursor-pointer ${
                      active
                        ? 'bg-blue-600 text-white border-blue-500 shadow-lg shadow-blue-500/25'
                        : 'bg-surface-800/40 text-gray-400 border-white/5 hover:text-white hover:bg-surface-800/60'
                    }`}
                  >
                    {b}
                  </button>
                );
              })}
            </div>
          </div>
        </div>

        {/* AI Detection Info */}
        {detectedInfo && (
          <div className="mt-6 pt-4 border-t border-white/5 flex flex-wrap gap-2.5 text-xs text-gray-400 items-center">
            <span className="font-semibold text-gray-300">Hasil Deteksi AI:</span>
            <span className="bg-surface-700/40 px-2.5 py-1 rounded-md border border-white/5">
              Budget Maks: <strong className="text-primary">{detectedInfo.budget_max ? `Rp ${number_format(detectedInfo.budget_max, 0, ',', '.')}` : 'Rp 10.000.000 (Default)'}</strong>
            </span>
            <span className="bg-surface-700/40 px-2.5 py-1 rounded-md border border-white/5">
              Resolusi: <strong className="text-accent">{detectedInfo.resolution ?? '-'}</strong>
            </span>
            {detectedInfo.gpu_preference && (
              <span className="bg-surface-700/40 px-2.5 py-1 rounded-md border border-white/5">
                GPU: <strong className="text-green-400">{detectedInfo.gpu_preference}</strong>
              </span>
            )}
            {detectedInfo.cpu_preference && (
              <span className="bg-surface-700/40 px-2.5 py-1 rounded-md border border-white/5">
                CPU: <strong className="text-indigo-400">{detectedInfo.cpu_preference}</strong>
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
