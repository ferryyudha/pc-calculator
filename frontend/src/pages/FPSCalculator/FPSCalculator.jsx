import { useState, useEffect, useMemo } from 'react'
import { getComponents, postFpsEstimateAll } from '../../services/api'
// No imports needed from helpers since they are not used here
import SearchableSelect from '../../components/SearchableSelect/SearchableSelect'

// ─── GPU Score (RTX 4070 = 150 sebagai baseline referensi) ───────────────────
// Sumber: benchmark TechPowerUp, Digital Foundry, GamersNexus
const getGpuScore = (name) => {
  const n = name.toLowerCase()
  // RTX 50 Series
  if (n.includes('5090'))    return 420
  if (n.includes('5080'))    return 320
  if (n.includes('5070 ti')) return 250
  if (n.includes('5070'))    return 215
  if (n.includes('5060 ti')) return 185
  if (n.includes('5060'))    return 170  // sedikit di atas RTX 4070
  // RTX 40 Series
  if (n.includes('4090'))    return 360
  if (n.includes('4080 super')) return 310
  if (n.includes('4080'))    return 285
  if (n.includes('4070 ti super')) return 220
  if (n.includes('4070 ti')) return 195
  if (n.includes('4070 super')) return 175
  if (n.includes('4070'))    return 150  // BASELINE REFERENSI
  if (n.includes('4060 ti')) return 130
  if (n.includes('4060'))    return 110
  if (n.includes('4050'))    return 90
  // RTX 30 Series
  if (n.includes('3090 ti')) return 230
  if (n.includes('3090'))    return 210
  if (n.includes('3080 ti')) return 200
  if (n.includes('3080'))    return 185
  if (n.includes('3070 ti')) return 165
  if (n.includes('3070'))    return 150
  if (n.includes('3060 ti')) return 130
  if (n.includes('3060'))    return 105
  if (n.includes('3050'))    return 80
  // RTX 20 Series
  if (n.includes('2080 ti')) return 185
  if (n.includes('2080'))    return 160
  if (n.includes('2070'))    return 135
  if (n.includes('2060 super')) return 120
  if (n.includes('2060'))    return 110
  // GTX 16 Series
  if (n.includes('1660 super')) return 90
  if (n.includes('1660 ti')) return 88
  if (n.includes('1660'))    return 80
  if (n.includes('1650 super')) return 72
  if (n.includes('1650'))    return 62
  // AMD RX 9000
  if (n.includes('rx 9070 xt')) return 210
  if (n.includes('rx 9070'))   return 175
  if (n.includes('rx 9060 xt')) return 160
  // AMD RX 7000
  if (n.includes('rx 7900 xtx')) return 295
  if (n.includes('rx 7900 xt')) return 265
  if (n.includes('rx 7900 gre')) return 220
  if (n.includes('rx 7800 xt')) return 170
  if (n.includes('rx 7700 xt')) return 145
  if (n.includes('rx 7600 xt')) return 120
  if (n.includes('rx 7600'))   return 110
  // AMD RX 6000
  if (n.includes('rx 6950 xt')) return 225
  if (n.includes('rx 6900 xt')) return 210
  if (n.includes('rx 6800 xt')) return 195
  if (n.includes('rx 6800'))   return 175
  if (n.includes('rx 6750 xt')) return 160
  if (n.includes('rx 6700 xt')) return 145
  if (n.includes('rx 6650 xt')) return 125
  if (n.includes('rx 6600 xt')) return 115
  if (n.includes('rx 6600'))   return 105
  if (n.includes('rx 6500 xt')) return 72
  // AMD RX 5000
  if (n.includes('rx 5700 xt')) return 130
  if (n.includes('rx 5700'))   return 115
  if (n.includes('rx 5600 xt')) return 100
  if (n.includes('rx 5500 xt')) return 78
  // AMD RX 500
  if (n.includes('rx 590'))    return 68
  if (n.includes('rx 580'))    return 62
  if (n.includes('rx 570'))    return 55
  if (n.includes('rx 560'))    return 40
  // Intel Arc
  if (n.includes('arc b580'))   return 120
  if (n.includes('arc b570'))   return 105
  if (n.includes('arc a770'))   return 130
  if (n.includes('arc a750'))   return 115
  // Old/Entry
  if (n.includes('gt 710'))    return 8
  if (n.includes('gt 730'))    return 12
  return 75 // fallback untuk GPU tidak dikenal
}

// ─── iGPU Score ───────────────────────────────────────────────────────────────
const getIGpuScore = (cpuName) => {
  const n = cpuName.toLowerCase()
  // AMD APU dengan grafis kuat
  if (n.includes('5700g'))  return 42
  if (n.includes('5600g'))  return 37
  if (n.includes('5500gt')) return 33
  if (n.includes('3400g'))  return 32
  if (n.includes('3200g'))  return 25
  if (n.includes('a6-9500')) return 8
  // AMD Ryzen 7000/9000 dengan Radeon 890M/780M
  if (n.includes('9700x') || n.includes('9600x')) return 20
  if (n.includes('7800x3d') || n.includes('9800x3d')) return 18
  if (n.includes('7700') || n.includes('9700')) return 20
  if (n.includes('7600') || n.includes('9600')) return 18
  // Intel 12th-14th Gen (UHD 770/770)
  if (n.includes('i9-13') || n.includes('i9-14')) return 22
  if (n.includes('i7-13') || n.includes('i7-14')) return 20
  if (n.includes('i5-13') || n.includes('i5-14')) return 18
  if (n.includes('i3-13') || n.includes('i3-14')) return 15
  // Intel generic
  if (n.includes('intel') || n.includes('core')) return 15
  return 15
}

// ─── CPU Multiplier ───────────────────────────────────────────────────────────
const getCpuMultiplier = (name) => {
  const n = name.toLowerCase()
  // AMD High-end
  if (n.includes('9950x') || n.includes('9900x'))  return 1.28
  if (n.includes('7950x') || n.includes('7900x'))  return 1.25
  if (n.includes('9800x3d') || n.includes('7800x3d')) return 1.30  // 3D V-Cache boost gaming
  // AMD Mainstream
  if (n.includes('9700x') || n.includes('7700x'))  return 1.15
  if (n.includes('9600x') || n.includes('7600x'))  return 1.10
  if (n.includes('ryzen 9'))   return 1.25
  if (n.includes('ryzen 7'))   return 1.15
  if (n.includes('ryzen 5'))   return 1.0
  if (n.includes('ryzen 3'))   return 0.82
  // Intel High-end
  if (n.includes('i9-14') || n.includes('i9-13')) return 1.25
  if (n.includes('i9'))        return 1.22
  if (n.includes('ultra 9'))   return 1.28
  // Intel Mainstream
  if (n.includes('i7-14') || n.includes('i7-13')) return 1.15
  if (n.includes('i7'))        return 1.12
  if (n.includes('ultra 7'))   return 1.18
  if (n.includes('i5-14') || n.includes('i5-13')) return 1.0
  if (n.includes('i5'))        return 1.0
  if (n.includes('ultra 5'))   return 1.03
  if (n.includes('i3'))        return 0.82
  // Old/budget
  if (n.includes('a6-9500'))   return 0.50
  if (n.includes('3200g'))     return 0.78
  return 0.90
}

// ─── Tabel FPS Per-Game (RTX 4070 + i5/Ryzen 5 + High Settings) ──────────────
// Data berdasarkan benchmark: TechPowerUp, GamersNexus, Digital Foundry, Jarrod's Tech
// Resolusi: { '720p', '1080p', '4K' }
const GAME_FPS_TABLE = {
  'valorant':             { '720p': 580, '1080p': 410, '4K': 135 },
  'league-of-legends':   { '720p': 420, '1080p': 300, '4K':  90 },
  'dota-2':              { '720p': 340, '1080p': 240, '4K':  75 },
  'cs2':                 { '720p': 290, '1080p': 205, '4K':  62 },
  'minecraft':           { '720p': 500, '1080p': 355, '4K': 110 },
  'fortnite':            { '720p': 205, '1080p': 148, '4K':  46 },
  'apex-legends':        { '720p': 220, '1080p': 158, '4K':  50 },
  'gta-v':               { '720p': 200, '1080p': 143, '4K':  44 },
  'pubg-battlegrounds':  { '720p': 185, '1080p': 133, '4K':  42 },
  'genshin-impact':      { '720p': 155, '1080p': 112, '4K':  36 },
  'red-dead-redemption-2': { '720p': 115, '1080p': 82, '4K': 26 },
  'elden-ring':          { '720p': 120, '1080p': 86, '4K':  27 },
  'hogwarts-legacy':     { '720p': 105, '1080p': 76, '4K':  24 },
  'cyberpunk-2077':      { '720p': 105, '1080p': 75, '4K':  23 },
  'black-myth-wukong':   { '720p':  92, '1080p': 66, '4K':  20 },
}
// GPU Reference Score untuk tabel di atas
const GPU_REFERENCE_SCORE = 150  // RTX 4070

// ─── Monitor Tier Helper ──────────────────────────────────────────────────────
// Memberi label sesuai target monitor yang umum di Indonesia
const getMonitorTier = (fps) => {
  if (fps >= 240) return { label: '240Hz+', color: 'text-cyan-400' }
  if (fps >= 144) return { label: '144Hz ✓', color: 'text-emerald-400' }
  if (fps >= 60)  return { label: '60Hz ✓',  color: 'text-yellow-400' }
  if (fps >= 30)  return { label: 'Playable', color: 'text-orange-400' }
  return              { label: 'Rendah',   color: 'text-red-400' }
}

// Helper to get background and text color based on FPS range
const getFpsBadgeClass = (fps) => {
  if (fps >= 144) return 'bg-emerald-600 text-white'
  if (fps >= 60)  return 'bg-blue-600 text-white'
  if (fps >= 30)  return 'bg-amber-600 text-white'
  return 'bg-rose-600 text-white'
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

    // Bersihkan context saat halaman ditinggalkan
    return () => { localStorage.removeItem('active_page_context') }
  }, [])

  // Sinkronisasi pilihan form ke localStorage agar Chat AI bisa membacanya
  useEffect(() => {
    if (form.cpu_id || form.gpu_id) {
      localStorage.setItem('active_page_context', JSON.stringify({
        page: 'fps_calculator',
        data: {
          cpu_id: form.cpu_id || null,
          gpu_id: form.gpu_id === 'igpu' ? 'igpu' : (form.gpu_id || null),
          ram_id: form.ram_id || null,
        },
        result: results ? results.map(r => ({
          game_name: r.game_name,
          weight_class: r.weight_class,
          fps: { '720p': r.fps['720p'], '1080p': r.fps['1080p'], '4K': r.fps['4K'] },
        })) : null,
      }))
    } else {
      localStorage.removeItem('active_page_context')
    }
  }, [form.cpu_id, form.gpu_id, form.ram_id, results])

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
        const cpuMultiplier = getCpuMultiplier(cpu ? cpu.name : '')

        let gpuScore = 0
        if (form.gpu_id === 'igpu') {
          gpuScore = getIGpuScore(cpu ? cpu.name : '')
        } else {
          const gpu = lists.gpus.find((g) => g.id == form.gpu_id)
          gpuScore = getGpuScore(gpu ? gpu.name : '')
        }

        const fps = { ...gameResult.fps }
        const gameBaseline = GAME_FPS_TABLE[gameResult.game_slug]

        resolutions.forEach((r) => {
          if (fps[r] === null) {
            if (gameBaseline && gameBaseline[r] != null) {
              // Gunakan tabel baseline per-game untuk akurasi lebih baik
              // Scale: (gpuScore / GPU_REFERENCE_SCORE) * cpuMultiplier * ramFactor
              const scaleFactor = (gpuScore / GPU_REFERENCE_SCORE) * cpuMultiplier * localRamFactor
              fps[r] = Math.max(1, Math.round(gameBaseline[r] * scaleFactor))
            } else {
              // Fallback: formula berbasis weight class (untuk game di luar tabel)
              const gameWeight = getGameWeight(gameResult.weight_class)
              let resolutionMultiplier = 1.0
              if (r === '720p')  resolutionMultiplier = 1.35
              if (r === '1080p') resolutionMultiplier = 1.0
              if (r === '4K')    resolutionMultiplier = 0.40
              const baseHighFps = (gpuScore * cpuMultiplier) / gameWeight
              fps[r] = Math.max(1, Math.round(baseHighFps * resolutionMultiplier * localRamFactor))
            }
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
  const selectedGpu = form.gpu_id === 'igpu' ? null : lists.gpus.find((g) => g.id == form.gpu_id)
  const isFormValid = form.cpu_id && form.gpu_id

  // Hitung analisis bottleneck dari hasil yang ada
  const performanceSummary = useMemo(() => {
    if (!results || results.length === 0) return null
    const gpuScore = form.gpu_id === 'igpu'
      ? getIGpuScore(selectedCpu?.name || '')
      : getGpuScore(selectedGpu?.name || '')
    const cpuMult = getCpuMultiplier(selectedCpu?.name || '')

    // Bottleneck ratio: >1.1 CPU bottleneck, <0.9 GPU bottleneck, else balanced
    // Di 1080p CPU lebih dominan, di 4K GPU lebih dominan
    const gpuNorm = gpuScore / GPU_REFERENCE_SCORE
    const calcBottleneck = (cpuWeight, gpuWeight) => {
      const ratio = (cpuMult * cpuWeight) / (gpuNorm * gpuWeight)
      const pct = Math.round(Math.abs(1 - ratio) * 100 * Math.min(cpuWeight, gpuWeight))
      if (ratio > 1.12)  return { type: 'CPU', pct: Math.min(pct, 45), color: 'text-orange-400', bg: 'bg-orange-500/10 border-orange-500/25' }
      if (ratio < 0.88)  return { type: 'GPU', pct: Math.min(pct, 45), color: 'text-blue-400',   bg: 'bg-blue-500/10 border-blue-500/25' }
      return                    { type: 'Seimbang', pct: 0,              color: 'text-emerald-400', bg: 'bg-emerald-500/10 border-emerald-500/25' }
    }

    const bottleneck = {
      '1080p': calcBottleneck(1.2, 0.8),  // 1080p lebih CPU-bound
      '1440p': calcBottleneck(1.0, 1.0),  // 1440p seimbang
      '4K':    calcBottleneck(0.6, 1.4),  // 4K lebih GPU-bound
    }

    // Persentase game yang bisa dimainkan di tiap FPS tier
    const total = results.length
    const countAbove = (res, threshold) => results.filter(r => (r.fps[res] || 0) >= threshold).length
    const pctOf = (n) => Math.round((n / total) * 100)

    const gameStats = {
      '1080p': {
        '60':  pctOf(countAbove('1080p', 60)),
        '90':  pctOf(countAbove('1080p', 90)),
        '144': pctOf(countAbove('1080p', 144)),
      },
      '4K': {
        '60':  pctOf(countAbove('4K', 60)),
        '90':  pctOf(countAbove('4K', 90)),
        '144': pctOf(countAbove('4K', 144)),
      },
    }

    // Rekomendasi resolusi berdasarkan keseimbangan performa
    let recommendedRes = '1080p'
    if (bottleneck['4K'].type === 'Seimbang' || (gpuNorm > 1.8 && cpuMult >= 1.0)) recommendedRes = '4K'
    else if (bottleneck['1440p'].type === 'Seimbang' || gpuNorm > 1.2) recommendedRes = '1440p'

    return { bottleneck, gameStats, recommendedRes, gpuNorm, cpuMult }
  }, [results, form.gpu_id, selectedCpu, selectedGpu])

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

          {/* ── Performance Summary Analysis ── */}
          {performanceSummary && (
            <div className="glass p-6 space-y-5">
              <h2 className="text-base font-bold text-white flex items-center gap-2">
                <span className="text-purple-400">📊</span> Ringkasan Analisis Performa
              </h2>

              {/* Bottleneck per resolusi */}
              <div>
                <p className="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Hambatan Komponen per Resolusi</p>
                <div className="overflow-x-auto">
                  <table className="w-full text-xs">
                    <thead>
                      <tr className="text-gray-500 border-b border-white/5">
                        <th className="text-left pb-2 font-medium">Resolusi</th>
                        <th className="text-center pb-2 font-medium">Hambatan (%)</th>
                        <th className="text-center pb-2 font-medium">Komponen Pembatas</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-white/5">
                      {[['1080p','1920×1080'],['1440p','2560×1440'],['4K','3840×2160']].map(([res, label]) => {
                        const b = performanceSummary.bottleneck[res]
                        const isRecommended = performanceSummary.recommendedRes === res
                        return (
                          <tr key={res} className={`${b.bg} border rounded-lg`}>
                            <td className="py-2 px-3 font-semibold text-white">
                              {label}
                              {isRecommended && <span className="ml-1.5 text-[9px] bg-primary/20 text-primary px-1.5 py-0.5 rounded-full">✓ Disarankan</span>}
                            </td>
                            <td className={`py-2 px-3 text-center font-bold ${b.color}`}>
                              {b.type === 'Seimbang' ? '~0%' : `${b.pct}%`}
                            </td>
                            <td className={`py-2 px-3 text-center font-semibold ${b.color}`}>{b.type}</td>
                          </tr>
                        )
                      })}
                    </tbody>
                  </table>
                </div>
                <p className="text-[10px] text-gray-500 mt-1.5">Hambatan tinggi = komponen tersebut jadi bottleneck yang membatasi performa.</p>
              </div>

              {/* % game yang bisa dimainkan */}
              <div>
                <p className="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">% Game yang Bisa Dimainkan di FPS Target</p>
                <div className="overflow-x-auto">
                  <table className="w-full text-xs">
                    <thead>
                      <tr className="text-gray-500 border-b border-white/5">
                        <th className="text-left pb-2 font-medium">Resolusi</th>
                        <th className="text-center pb-2 font-medium">60+ FPS</th>
                        <th className="text-center pb-2 font-medium">90+ FPS</th>
                        <th className="text-center pb-2 font-medium text-emerald-400">144+ FPS ✓</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-white/5">
                      {[['1080p','1920×1080'],['4K','3840×2160']].map(([res, label]) => {
                        const s = performanceSummary.gameStats[res]
                        const pctColor = (v) => v >= 80 ? 'text-emerald-400' : v >= 50 ? 'text-yellow-400' : v >= 20 ? 'text-orange-400' : 'text-red-400'
                        return (
                          <tr key={res}>
                            <td className="py-2 font-semibold text-gray-300">{label}</td>
                            <td className={`py-2 text-center font-bold ${pctColor(s['60'])}`}>{s['60']}%</td>
                            <td className={`py-2 text-center font-bold ${pctColor(s['90'])}`}>{s['90']}%</td>
                            <td className={`py-2 text-center font-bold ${pctColor(s['144'])}`}>{s['144']}%</td>
                          </tr>
                        )
                      })}
                    </tbody>
                  </table>
                </div>
                <p className="text-[10px] text-gray-500 mt-1.5">Dihitung dari {results.length} game populer dalam database menggunakan High Settings.</p>
              </div>

              {/* Resolusi yang disarankan */}
              <div className="p-3 rounded-xl bg-primary/8 border border-primary/20">
                <p className="text-xs font-semibold text-gray-400 mb-1">💡 Resolusi yang Disarankan</p>
                <p className="text-sm font-bold text-white">
                  {performanceSummary.recommendedRes === '4K' && '3840×2160 (4K) — GPU kamu cukup kuat untuk resolusi ini'}
                  {performanceSummary.recommendedRes === '1440p' && '2560×1440 (1440p) — sweet spot untuk setup kamu'}
                  {performanceSummary.recommendedRes === '1080p' && '1920×1080 (1080p) — optimal untuk performa tertinggi'}
                </p>
              </div>
            </div>
          )}

          <div className="text-center">
            <h2 className="text-xl font-bold text-white mb-1">Average FPS in popular games</h2>
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
                      <div className="flex items-center gap-2">
                        <span className={`text-[10px] font-semibold ${getMonitorTier(gameResult.fps['720p']).color}`}>
                          {getMonitorTier(gameResult.fps['720p']).label}
                        </span>
                        <span className={`px-3 py-1 rounded text-xs font-black min-w-[70px] text-center ${getFpsBadgeClass(gameResult.fps['720p'])}`}>
                          {gameResult.fps['720p']} FPS
                        </span>
                      </div>
                    </div>
                    {/* Table row 1080p */}
                    <div className="flex items-center justify-between border-b border-white/5 pb-1.5">
                      <span className="text-xs text-gray-400 flex items-center gap-1">
                        1080p
                        <span className="text-yellow-400">★</span>
                      </span>
                      <div className="flex items-center gap-2">
                        <span className={`text-[10px] font-semibold ${getMonitorTier(gameResult.fps['1080p']).color}`}>
                          {getMonitorTier(gameResult.fps['1080p']).label}
                        </span>
                        <span className={`px-3 py-1 rounded text-xs font-black min-w-[70px] text-center ${getFpsBadgeClass(gameResult.fps['1080p'])}`}>
                          {gameResult.fps['1080p']} FPS
                        </span>
                      </div>
                    </div>
                    {/* Table row 4K */}
                    <div className="flex items-center justify-between pb-0.5">
                      <span className="text-xs text-gray-400">4K</span>
                      <div className="flex items-center gap-2">
                        <span className={`text-[10px] font-semibold ${getMonitorTier(gameResult.fps['4K']).color}`}>
                          {getMonitorTier(gameResult.fps['4K']).label}
                        </span>
                        <span className={`px-3 py-1 rounded text-xs font-black min-w-[70px] text-center ${getFpsBadgeClass(gameResult.fps['4K'])}`}>
                          {gameResult.fps['4K']} FPS
                        </span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            ))}
          </div>
          {/* Legend monitor tier */}
          <div className="flex flex-wrap justify-center gap-3 mt-4 text-[10px]">
            <span className="text-cyan-400 font-semibold">■ 240Hz+</span>
            <span className="text-emerald-400 font-semibold">■ 144Hz ✓ (target monitor gaming Indonesia)</span>
            <span className="text-yellow-400 font-semibold">■ 60Hz ✓</span>
            <span className="text-orange-400 font-semibold">■ Playable ≥30fps</span>
            <span className="text-red-400 font-semibold">■ Rendah</span>
          </div>
          <p className="text-[11px] text-gray-500 text-center mt-3">
            Estimasi berdasarkan benchmark referensi (High Settings). Angka aktual bisa berbeda tergantung setting grafis, driver, dan kondisi sistem.
          </p>
        </div>
      )}
    </div>
  )
}
