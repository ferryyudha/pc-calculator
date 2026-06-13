import { useState, useEffect, useMemo } from 'react'
import { getComponents, postFpsEstimateAll } from '../../services/api'
import { RESOLUTIONS, RESOLUTION_LABELS } from '../../utils/helpers'
import SearchableSelect from '../../components/SearchableSelect/SearchableSelect'

// Helper to get GPU score based on name
const getGpuScore = (name) => {
  const n = name.toLowerCase()
  if (n.includes('5090')) return 350
  if (n.includes('5080')) return 280
  if (n.includes('5070 ti')) return 220
  if (n.includes('5070')) return 180
  if (n.includes('5060 ti')) return 160
  if (n.includes('5060')) return 130
  if (n.includes('4090')) return 300
  if (n.includes('4080')) return 240
  if (n.includes('4070 super')) return 165
  if (n.includes('4070')) return 150
  if (n.includes('4060')) return 100
  if (n.includes('3090')) return 180
  if (n.includes('3080')) return 150
  if (n.includes('3070')) return 120
  if (n.includes('3060')) return 85
  if (n.includes('3050')) return 65
  if (n.includes('1660 super')) return 60
  if (n.includes('1660')) return 55
  if (n.includes('rx 9070')) return 150
  if (n.includes('rx 9060 xt')) return 130
  if (n.includes('rx 7800 xt')) return 135
  if (n.includes('rx 7700 xt')) return 110
  if (n.includes('rx 6900 xt')) return 160
  if (n.includes('rx 6700 xt')) return 105
  if (n.includes('rx 6600 xt')) return 90
  if (n.includes('rx 5600 xt')) return 75
  if (n.includes('rx 580')) return 50
  if (n.includes('rx 560')) return 35
  if (n.includes('gt 710')) return 10
  if (n.includes('arc b580')) return 90
  return 80 // fallback
}

// Helper to check if CPU has integrated graphics
const hasIntegratedGpu = (cpuName) => {
  if (!cpuName) return false
  const name = cpuName.toLowerCase()
  
  // Explicitly check F-series or KF-series which have no iGPU
  if (name.includes('f ') || name.includes('kf ') || name.includes('-f') || name.includes('-kf') || name.endsWith('f') || name.endsWith('kf')) {
    return false
  }
  // AMD F-series
  if (name.includes('7500f') || name.includes('8700f') || name.includes('8400f')) {
    return false
  }
  return true
}

// Helper to get integrated GPU score based on CPU name
const getIGpuScore = (cpuName) => {
  const n = cpuName.toLowerCase()
  if (n.includes('5700g') || n.includes('3400g')) return 40
  if (n.includes('5600g') || n.includes('5500gt')) return 35
  if (n.includes('3200g')) return 25
  if (n.includes('a6-9500')) return 8
  if (n.includes('ryzen') && (n.includes('7000') || n.includes('9000') || n.includes('7800x3d') || n.includes('9800x3d') || n.includes('9950x') || n.includes('7600') || n.includes('7700') || n.includes('9600x') || n.includes('9700x'))) {
    return 15
  }
  return 15
}

// Helper to get CPU multiplier based on name
const getCpuMultiplier = (name) => {
  const n = name.toLowerCase()
  if (n.includes('ryzen 9') || n.includes('core i9') || n.includes('9950x') || n.includes('9900x')) return 1.3
  if (n.includes('ryzen 7') || n.includes('core i7') || n.includes('ultra 7') || n.includes('9700x') || n.includes('7800x3d') || n.includes('9800x3d')) return 1.15
  if (n.includes('ryzen 5') || n.includes('core i5') || n.includes('ultra 5') || n.includes('7500f') || n.includes('7600') || n.includes('9600x') || n.includes('5600') || n.includes('5500')) return 1.0
  if (n.includes('ryzen 3') || n.includes('core i3') || n.includes('3200g')) return 0.8
  if (n.includes('a6-9500')) return 0.5
  return 0.9 // fallback
}

// Helper to get Game Weight based on weight class
const getGameWeight = (weightClass) => {
  switch (weightClass) {
    case 'light': return 0.8
    case 'medium': return 1.2
    case 'heavy': return 1.6
    case 'extreme': return 2.2
    default: return 1.2
  }
}

// Helper untuk mendapatkan URL cover art game dari folder lokal (public/images/games)
const getGameCoverUrl = (slug) => {
  const knownSlugs = [
    'valorant', 'cs2', 'gta-v', 'fortnite', 'cyberpunk-2077',
    'dota-2', 'league-of-legends', 'apex-legends', 'pubg-battlegrounds',
    'genshin-impact', 'minecraft', 'black-myth-wukong',
    'red-dead-redemption-2', 'elden-ring', 'hogwarts-legacy',
  ]
  if (knownSlugs.includes(slug)) {
    return `/images/games/${slug}.jpg`
  }
  return 'https://placehold.co/120x176/1e293b/64748b?text=Game'
}

// Helper to get background and text color based on FPS range
const getFpsBadgeClass = (fps) => {
  if (fps >= 60) return 'bg-emerald-600 text-white'
  if (fps >= 30) return 'bg-amber-600 text-white'
  return 'bg-rose-600 text-white'
}


export default function FPSCalculator() {
  const [lists, setLists]   = useState({ cpus: [], gpus: [], rams: [], games: [] })
  const [form, setForm]     = useState({ cpu_id: '', gpu_id: '', ram_id: '' })
  const [results, setResults] = useState(null)
  const [error, setError]   = useState(null)
  const [loading, setLoading] = useState(false)
  const [fetching, setFetching] = useState(true)
  const [ramAppliedInfo, setRamAppliedInfo] = useState({ applied: false, factor: 1 })

  useEffect(() => {
    Promise.all([getComponents.cpus(), getComponents.gpus(), getComponents.rams(), getComponents.games()])
      .then(([c, g, r, ga]) => setLists({ cpus: c.data.data, gpus: g.data.data, rams: r.data.data, games: ga.data.data }))
      .finally(() => setFetching(false))
  }, [])

  const setGpu = (gpuId) => setForm((f) => ({ ...f, gpu_id: gpuId }))

  const handleCpuChange = (cpuId) => {
    const cpu = lists.cpus.find((c) => c.id == cpuId)
    setForm((f) => {
      const nextGpuId = f.gpu_id === 'igpu' && (!cpu || !hasIntegratedGpu(cpu.name)) ? '' : f.gpu_id
      return { ...f, cpu_id: cpuId, gpu_id: nextGpuId }
    })
  }

  const handleCalculateFPS = async (e) => {
    if (e) e.preventDefault()
    if (!form.cpu_id || !form.gpu_id) return

    setLoading(true); setResults(null); setError(null)
    setRamAppliedInfo({ applied: false, factor: 1 })
    
    try {
      const payload = { cpu_id: form.cpu_id, gpu_id: form.gpu_id }
      if (form.ram_id) {
        payload.ram_id = form.ram_id
      }

      const res = await postFpsEstimateAll(payload)
      const cpu = lists.cpus.find((c) => c.id == form.cpu_id)
      const localRamFactor = res.data?.meta?.ram_factor || 1.0

      const mappedResults = res.data.data.map((gameResult) => {
        const resolutions = ['720p', '1080p', '4K']
        const gameWeight = getGameWeight(gameResult.weight_class)
        const cpuMultiplier = getCpuMultiplier(cpu ? cpu.name : '')

        let gpuScore = 0
        if (form.gpu_id === 'igpu') {
          gpuScore = getIGpuScore(cpu ? cpu.name : '')
        } else {
          const gpu = lists.gpus.find((g) => g.id == form.gpu_id)
          gpuScore = getGpuScore(gpu ? gpu.name : '')
        }

        const fps = { ...gameResult.fps }

        resolutions.forEach((r) => {
          if (fps[r] === null) {
            let resolutionMultiplier = 1.0
            if (r === '720p') resolutionMultiplier = 1.35
            if (r === '1080p') resolutionMultiplier = 1.0
            if (r === '1440p') resolutionMultiplier = 0.65
            if (r === '4K') resolutionMultiplier = 0.40

            const baseHighFps = (gpuScore * cpuMultiplier) / gameWeight
            fps[r] = Math.max(1, Math.round(baseHighFps * resolutionMultiplier * localRamFactor))
          }
        })

        return {
          ...gameResult,
          fps
        }
      })

      if (res.data?.meta?.ram_applied) {
        setRamAppliedInfo({
          applied: true,
          factor: res.data.meta.ram_factor || 1.0
        })
      }

      setResults(mappedResults)
    } catch (err) {
      setError(err)
    } finally {
      setLoading(false)
    }
  }

  const selectedCpu = lists.cpus.find((c) => c.id == form.cpu_id)
  const isFormValid = form.cpu_id && form.gpu_id

  const iGpuOption = useMemo(() => {
    if (selectedCpu && hasIntegratedGpu(selectedCpu.name)) {
      return {
        id: 'igpu',
        name: '[Integrated Graphics] Radeon / Intel UHD Graphics',
        isSpecial: true
      }
    }
    return null
  }, [selectedCpu])

  return (
    <div className="max-w-4xl mx-auto px-4 sm:px-6 py-12">
      <div className="mb-10 animate-fade-in">
        <p className="text-purple-400 text-sm font-medium mb-2">🎮 FPS Calculator</p>
        <h1 className="text-4xl font-black text-white mb-3">Estimasi FPS Gaming</h1>
        <p className="text-gray-400">Pilih CPU dan GPU untuk mendapatkan estimasi FPS di berbagai resolusi untuk game populer.</p>
      </div>

      <form onSubmit={handleCalculateFPS} className="glass px-6 sm:px-8 py-8 space-y-6 mb-6 animate-slide-up max-w-2xl mx-auto relative z-30">
        <div className="grid sm:grid-cols-2 gap-6">
          {/* CPU */}
          <SearchableSelect
            label="CPU"
            id="cpu"
            value={form.cpu_id}
            onChange={handleCpuChange}
            options={lists.cpus}
            placeholder="Pilih CPU"
            loading={fetching}
            formatLabel={(c) => `${c.name} · ${c.socket}`}
          />
          {/* GPU */}
          <SearchableSelect
            label="GPU"
            id="gpu"
            value={form.gpu_id}
            onChange={setGpu}
            options={lists.gpus}
            placeholder="Pilih GPU"
            loading={fetching}
            formatLabel={(g) => `${g.name} · ${g.vram}GB`}
            extraOption={iGpuOption}
          />
        </div>

        {/* RAM (Opsional) */}
        <div>
          <SearchableSelect
            label="RAM (Opsional)"
            id="ram"
            value={form.ram_id}
            onChange={(val) => setForm((f) => ({ ...f, ram_id: val }))}
            options={lists.rams}
            placeholder="-- Tidak dipilih (gunakan baseline) --"
            loading={fetching}
            formatLabel={(r) => `${r.capacity}GB ${r.ddr_version} ${r.speed}MHz ${r.sticks > 1 ? '(Dual Channel)' : '(Single Channel)'} — ${r.name}`}
            extraOption={{ id: '', name: '-- Tidak dipilih (gunakan baseline) --', isSpecial: true }}
          />
        </div>

        <button type="submit" className="btn-primary text-white w-full justify-center py-3.5" disabled={!isFormValid || loading || fetching}>
          {loading ? (
            <>
              <span className="spinner w-4 h-4 border border-white/30 border-t-white"></span>
              <span className="text-white">Menghitung...</span>
            </>
          ) : (
            <>
              <svg className="w-5 h-5 text-white animate-pulse-slow" fill="currentColor" viewBox="0 0 24 24">
                <path d="M21 6H3c-1.1 0-2 .9-2 2v8c0 1.1.9 2 2 2h18c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2zm-10 7H8v3H6v-3H3v-2h3V8h2v3h3v2zm4.5 2c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zm3.5-3c-.83 0-1.5-.67-1.5-1.5S20.17 9 21 9s1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/>
              </svg>
              <span className="text-white">Hitung FPS</span>
            </>
          )}
        </button>
      </form>

      {error && (
        <div className="glass p-5 border border-danger/20 mb-6 animate-slide-up max-w-2xl mx-auto">
          <div className="flex items-start gap-3">
            <div className="w-8 h-8 rounded-lg bg-danger/15 text-danger flex items-center justify-center flex-shrink-0">
              <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12"/></svg>
            </div>
            <div>
              <p className="font-semibold text-danger text-sm">{error.code || 'Error'}</p>
              <p className="text-gray-400 text-sm mt-0.5">{error.message}</p>
            </div>
          </div>
        </div>
      )}

      {results && (
        <div className="space-y-6 mt-8 animate-slide-up max-w-2xl mx-auto">
          <div className="text-center mb-4">
            <h2 className="text-xl font-bold text-white mb-1">
              Average FPS in popular games
            </h2>
            {ramAppliedInfo.applied && (
              <p className="text-xs text-purple-400 font-medium">
                * FPS sudah disesuaikan dengan RAM yang dipilih (faktor: {ramAppliedInfo.factor.toFixed(2)}x)
              </p>
            )}
          </div>
          <div className="space-y-4">
            {results.map((gameResult) => (
              <div key={gameResult.game_id} className="glass overflow-hidden flex bg-slate-900 border border-slate-800 rounded-2xl h-44 shadow-lg">
                {/* Cover Gambar (Kiri) */}
                <div className="w-28 relative flex-shrink-0 bg-slate-800">
                  <img
                    src={getGameCoverUrl(gameResult.game_slug)}
                    alt={gameResult.game_name}
                    className="w-full h-full object-cover"
                    onError={(e) => {
                      // Jika gambar lokal gagal load, tampilkan placeholder teks
                      e.target.onerror = null
                      e.target.src = `https://placehold.co/120x176/1e293b/64748b?text=${encodeURIComponent(gameResult.game_name)}`
                    }}
                  />
                </div>
                
                {/* Game FPS Table (Right) */}
                <div className="flex-1 p-4 flex flex-col justify-between">
                  <div className="flex items-center justify-between">
                    <h3 className="text-sm font-bold text-white tracking-wide">{gameResult.game_name}</h3>
                    <span className={`px-2 py-0.5 rounded-full text-[9px] font-bold uppercase tracking-wider ${
                      {
                        light: 'bg-green-500/15 text-green-400',
                        medium: 'bg-yellow-500/15 text-yellow-400',
                        heavy: 'bg-orange-500/15 text-orange-400',
                        extreme: 'bg-red-500/15 text-red-400',
                      }[gameResult.weight_class]
                    }`}>{gameResult.weight_class}</span>
                  </div>
                  
                  <div className="space-y-2 mt-2">
                    {/* Table row 720p */}
                    <div className="flex items-center justify-between border-b border-white/5 pb-1.5">
                      <span className="text-xs text-gray-400">720p</span>
                      <span className={`px-3 py-1 rounded text-xs font-black min-w-[70px] text-center ${
                        getFpsBadgeClass(gameResult.fps['720p'])
                      }`}>{gameResult.fps['720p']} FPS</span>
                    </div>
                    {/* Table row 1080p */}
                    <div className="flex items-center justify-between border-b border-white/5 pb-1.5">
                      <span className="text-xs text-gray-400 flex items-center gap-1">
                        1080p
                        <span className="text-yellow-400">★</span>
                      </span>
                      <span className={`px-3 py-1 rounded text-xs font-black min-w-[70px] text-center ${
                        getFpsBadgeClass(gameResult.fps['1080p'])
                      }`}>{gameResult.fps['1080p']} FPS</span>
                    </div>
                    {/* Table row 4K */}
                    <div className="flex items-center justify-between pb-0.5">
                      <span className="text-xs text-gray-400">4K</span>
                      <span className={`px-3 py-1 rounded text-xs font-black min-w-[70px] text-center ${
                        getFpsBadgeClass(gameResult.fps['4K'])
                      }`}>{gameResult.fps['4K']} FPS</span>
                    </div>
                  </div>
                </div>
              </div>
            ))}
          </div>
          <p className="text-[11px] text-gray-500 text-center mt-6">
            Estimasi FPS dihasilkan menggunakan AI dan bisa keliru. Harap periksa kembali respons.
          </p>
        </div>
      )}
    </div>
  )
}
