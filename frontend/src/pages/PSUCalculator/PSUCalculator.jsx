import { useState, useEffect } from 'react'
import { getComponents, postPsuCalculate } from '../../services/api'
import PSUCard from '../../components/PSUCard/PSUCard'
import SearchableSelect from '../../components/SearchableSelect/SearchableSelect'

export default function PSUCalculator() {
  const [lists, setLists] = useState({ cpus: [], gpus: [], ssds: [], hdds: [] })
  const [form, setForm]   = useState({
    cpu_id: '', gpu_id: '', fans: 3,
    mode: 'count', // 'count' or 'specific'
    ssd_count: 1, hdd_count: 0,
    ssd_ids: [], hdd_ids: [],
  })
  const [result, setResult] = useState(null)
  const [loading, setLoading] = useState(false)
  const [fetching, setFetching] = useState(true)
  const [ssdSearch, setSsdSearch] = useState('')
  const [hddSearch, setHddSearch] = useState('')

  const filteredSsds = lists.ssds.filter((s) => s.name.toLowerCase().includes(ssdSearch.toLowerCase()))
  const filteredHdds = lists.hdds.filter((h) => h.name.toLowerCase().includes(hddSearch.toLowerCase()))

  useEffect(() => {
    Promise.all([getComponents.cpus(), getComponents.gpus(), getComponents.ssds(), getComponents.hdds()])
      .then(([c, g, s, h]) => setLists({ cpus: c.data.data, gpus: g.data.data, ssds: s.data.data, hdds: h.data.data }))
      .finally(() => setFetching(false))

    // Bersihkan context saat halaman ditinggalkan
    return () => { localStorage.removeItem('active_page_context') }
  }, [])

  // Sinkronisasi pilihan form ke localStorage agar Chat AI bisa membacanya
  useEffect(() => {
    if (form.cpu_id || form.gpu_id) {
      localStorage.setItem('active_page_context', JSON.stringify({
        page: 'psu_calculator',
        data: {
          cpu_id: form.cpu_id || null,
          gpu_id: form.gpu_id || null,
          fans: form.fans,
          ssd_count: form.ssd_count,
          hdd_count: form.hdd_count,
        },
        result: result ? {
          recommended_watt: result.recommended_watt,
          total_draw: result.breakdown?.total_draw,
          recommended_psu_name: result.recommended_psu?.name,
          recommended_psu_watt: result.recommended_psu?.watt,
          recommended_psu_price: result.recommended_psu?.price,
        } : null,
      }))
    } else {
      localStorage.removeItem('active_page_context')
    }
  }, [form.cpu_id, form.gpu_id, form.fans, form.ssd_count, form.hdd_count, result])

  const setNum = (key) => (e) => setForm((f) => ({ ...f, [key]: Number(e.target.value) }))

  const toggleId = (key, id) => {
    setForm((f) => ({
      ...f,
      [key]: f[key].includes(id) ? f[key].filter((x) => x !== id) : [...f[key], id],
    }))
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    setLoading(true); setResult(null)
    try {
      const payload = { cpu_id: form.cpu_id, gpu_id: form.gpu_id, fans: form.fans }
      if (form.mode === 'specific') {
        payload.ssd_ids = form.ssd_ids
        payload.hdd_ids = form.hdd_ids
      } else {
        payload.ssd_count = form.ssd_count
        payload.hdd_count = form.hdd_count
      }
      const res = await postPsuCalculate(payload)
      setResult(res.data.data)
    } catch (err) {
      console.error(err)
    } finally {
      setLoading(false)
    }
  }

  const allFilled = form.cpu_id && form.gpu_id

  return (
    <div className="max-w-4xl mx-auto px-4 sm:px-6 py-12">
      <div className="mb-10 animate-fade-in">
        <p className="text-yellow-400 text-sm font-medium mb-2">⚡ PSU Calculator</p>
        <h1 className="text-4xl font-black text-white mb-3">Kalkulator Kebutuhan PSU</h1>
        <p className="text-gray-400">Hitung watt PSU yang dibutuhkan berdasarkan semua komponen yang kamu pilih, dengan headroom 30% untuk efisiensi dan umur PSU.</p>
      </div>

      <form onSubmit={handleSubmit} className="glass p-6 space-y-5 mb-6 animate-slide-up relative z-30">
        {/* CPU + GPU */}
        <div className="grid sm:grid-cols-2 gap-4">
          <SearchableSelect
            label="CPU"
            id="cpu"
            value={form.cpu_id}
            onChange={(val) => setForm((f) => ({ ...f, cpu_id: val }))}
            options={lists.cpus}
            placeholder="Pilih CPU"
            loading={fetching}
            formatLabel={(c) => `${c.name} · ${c.tdp}W TDP`}
          />
          <SearchableSelect
            label="GPU"
            id="gpu"
            value={form.gpu_id}
            onChange={(val) => setForm((f) => ({ ...f, gpu_id: val }))}
            options={lists.gpus}
            placeholder="Pilih GPU"
            loading={fetching}
            formatLabel={(g) => `${g.name} · ${g.power_draw}W`}
          />
        </div>

        {/* Storage mode toggle */}
        <div>
          <label className="block text-sm font-medium text-gray-300 mb-2">Storage</label>
          <div className="flex gap-2 mb-3">
            {[['count', 'Jumlah Saja'], ['specific', 'Pilih Spesifik']].map(([val, lbl]) => (
              <button key={val} type="button"
                onClick={() => setForm((f) => ({ ...f, mode: val }))}
                className={`px-4 py-2 rounded-xl text-sm font-medium transition-all duration-200 ${
                  form.mode === val ? 'bg-accent/20 text-accent border border-accent/30' : 'bg-surface-700/40 text-gray-400 hover:text-white'
                }`}>
                {lbl}
              </button>
            ))}
          </div>

          {form.mode === 'count' ? (
            <div className="grid grid-cols-2 gap-4">
              {[['ssd_count', 'Jumlah SSD (default 5W/unit)'], ['hdd_count', 'Jumlah HDD (default 8W/unit)']].map(([key, lbl]) => (
                <div key={key}>
                  <label className="block text-xs text-gray-400 mb-1">{lbl}</label>
                  <input type="number" min="0" max="10" value={form[key]} onChange={setNum(key)} className="field"/>
                </div>
              ))}
            </div>
          ) : (
            <div className="space-y-4">
              <div>
                <div className="flex items-center justify-between mb-2">
                  <p className="text-xs text-gray-400">SSD (pilih satu atau lebih)</p>
                  <input
                    type="text"
                    placeholder="Cari SSD..."
                    value={ssdSearch}
                    onChange={(e) => setSsdSearch(e.target.value)}
                    className="px-3 py-1.5 text-xs rounded-xl bg-surface-700/50 border border-white/5 focus:outline-none focus:border-primary text-white w-48 transition-all duration-200"
                  />
                </div>
                <div className="max-h-52 overflow-y-auto space-y-1.5 pr-2 border border-white/5 p-2 rounded-xl bg-surface-900/40 custom-scrollbar">
                  {filteredSsds.length > 0 ? (
                    filteredSsds.map((s) => (
                      <label key={s.id} className="flex items-center gap-3 p-2.5 rounded-lg bg-surface-700/30 cursor-pointer hover:bg-surface-700/50 transition-colors">
                        <input type="checkbox" checked={form.ssd_ids.includes(s.id)} onChange={() => toggleId('ssd_ids', s.id)} className="accent-primary"/>
                        <span className="text-sm text-gray-300">{s.name}</span>
                        <span className="ml-auto text-xs text-yellow-400">{s.power_draw}W</span>
                      </label>
                    ))
                  ) : (
                    <p className="text-xs text-gray-500 text-center py-4">Tidak ada SSD ditemukan</p>
                  )}
                </div>
              </div>
              <div>
                <div className="flex items-center justify-between mb-2">
                  <p className="text-xs text-gray-400">HDD (pilih satu atau lebih)</p>
                  <input
                    type="text"
                    placeholder="Cari HDD..."
                    value={hddSearch}
                    onChange={(e) => setHddSearch(e.target.value)}
                    className="px-3 py-1.5 text-xs rounded-xl bg-surface-700/50 border border-white/5 focus:outline-none focus:border-primary text-white w-48 transition-all duration-200"
                  />
                </div>
                <div className="max-h-52 overflow-y-auto space-y-1.5 pr-2 border border-white/5 p-2 rounded-xl bg-surface-900/40 custom-scrollbar">
                  {filteredHdds.length > 0 ? (
                    filteredHdds.map((h) => (
                      <label key={h.id} className="flex items-center gap-3 p-2.5 rounded-lg bg-surface-700/30 cursor-pointer hover:bg-surface-700/50 transition-colors">
                        <input type="checkbox" checked={form.hdd_ids.includes(h.id)} onChange={() => toggleId('hdd_ids', h.id)} className="accent-primary"/>
                        <span className="text-sm text-gray-300">{h.name}</span>
                        <span className="ml-auto text-xs text-yellow-400">{h.power_draw}W</span>
                      </label>
                    ))
                  ) : (
                    <p className="text-xs text-gray-500 text-center py-4">Tidak ada HDD ditemukan</p>
                  )}
                </div>
              </div>
            </div>
          )}
        </div>

        {/* Fans */}
        <div>
          <label className="block text-sm font-medium text-gray-300 mb-1.5">
            Jumlah Case Fan: <span className="text-primary font-mono">{form.fans}</span> fan ({form.fans * 3}W)
          </label>
          <input type="range" min="0" max="12" value={form.fans} onChange={setNum('fans')}
            className="w-full accent-primary"/>
          <div className="flex justify-between text-xs text-gray-500 mt-0.5">
            <span>0</span><span>6</span><span>12</span>
          </div>
        </div>

        <button type="submit" className="btn-primary w-full justify-center py-3.5" disabled={!allFilled || loading || fetching}>
          {loading ? <><span className="spinner w-4 h-4 border border-white/30 border-t-white"></span> Menghitung...</> : '⚡ Hitung PSU'}
        </button>
      </form>

      {result && <PSUCard data={result} />}
    </div>
  )
}
