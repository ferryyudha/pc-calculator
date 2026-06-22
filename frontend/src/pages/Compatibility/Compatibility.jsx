import { useState, useEffect } from 'react'
import { getComponents, postCompatibility } from '../../services/api'
import CompatibilityCard from '../../components/CompatibilityCard/CompatibilityCard'
import SearchableSelect from '../../components/SearchableSelect/SearchableSelect'


export default function Compatibility() {
  const [lists, setLists] = useState({ cpus: [], gpus: [], motherboards: [], rams: [], psus: [] })
  const [form, setForm]   = useState({ cpu_id: '', motherboard_id: '', ram_id: '', gpu_id: '', psu_id: '' })
  const [result, setResult] = useState(null)
  const [loading, setLoading] = useState(false)
  const [fetching, setFetching] = useState(true)

  useEffect(() => {
    Promise.all([
      getComponents.cpus(), getComponents.gpus(),
      getComponents.motherboards(), getComponents.rams(), getComponents.psus(),
    ]).then(([cpuRes, gpuRes, moboRes, ramRes, psuRes]) => {
      setLists({
        cpus: cpuRes.data.data, gpus: gpuRes.data.data,
        motherboards: moboRes.data.data, rams: ramRes.data.data, psus: psuRes.data.data,
      })
    }).finally(() => setFetching(false))

    // Bersihkan context saat halaman ditinggalkan
    return () => { localStorage.removeItem('active_page_context') }
  }, [])

  // Sinkronisasi pilihan form ke localStorage agar Chat AI bisa membacanya
  useEffect(() => {
    const hasAny = Object.values(form).some(Boolean)
    if (hasAny) {
      localStorage.setItem('active_page_context', JSON.stringify({
        page: 'compatibility_checker',
        data: {
          cpu_id: form.cpu_id || null,
          gpu_id: form.gpu_id || null,
          motherboard_id: form.motherboard_id || null,
          ram_id: form.ram_id || null,
          psu_id: form.psu_id || null,
        },
        result: result ? {
          compatible: result.compatible,
          checks: result.checks?.map(c => ({ name: c.name, passed: c.passed, message: c.message })),
        } : null,
      }))
    } else {
      localStorage.removeItem('active_page_context')
    }
  }, [form.cpu_id, form.gpu_id, form.motherboard_id, form.ram_id, form.psu_id, result])

  const set = (key) => (val) => setForm((f) => ({ ...f, [key]: val }))

  const handleSubmit = async (e) => {
    e.preventDefault()
    if (Object.values(form).some((v) => !v)) return
    setLoading(true); setResult(null)
    try {
      const res = await postCompatibility(form)
      setResult(res.data.data)
    } catch (err) {
      setResult(err.details || { compatible: false, checks: [], error: err.message })
    } finally {
      setLoading(false)
    }
  }

  const allFilled = Object.values(form).every(Boolean)

  return (
    <div className="max-w-4xl mx-auto px-4 sm:px-6 py-12">
      <div className="mb-10 animate-fade-in">
        <p className="text-primary text-sm font-medium mb-2">🔗 Compatibility Checker</p>
        <h1 className="text-4xl font-black text-white mb-3">Cek Kompatibilitas Komponen</h1>
        <p className="text-gray-400">Pilih 5 komponen dan sistem akan memvalidasi socket, tipe RAM, dan kebutuhan daya secara otomatis.</p>
      </div>

      <form onSubmit={handleSubmit} className="glass p-6 space-y-4 mb-6 animate-slide-up relative z-30">
        <div className="grid sm:grid-cols-2 gap-4">
          <SearchableSelect label="CPU" id="cpu" value={form.cpu_id} onChange={set('cpu_id')} options={lists.cpus} placeholder="Pilih CPU" loading={fetching}/>
          <SearchableSelect label="GPU" id="gpu" value={form.gpu_id} onChange={set('gpu_id')} options={lists.gpus} placeholder="Pilih GPU" loading={fetching}/>
          <SearchableSelect label="Motherboard" id="mobo" value={form.motherboard_id} onChange={set('motherboard_id')} options={lists.motherboards} placeholder="Pilih Motherboard" loading={fetching}/>
          <SearchableSelect label="RAM" id="ram" value={form.ram_id} onChange={set('ram_id')} options={lists.rams} placeholder="Pilih RAM" loading={fetching}/>
          <SearchableSelect label="PSU" id="psu" value={form.psu_id} onChange={set('psu_id')} options={lists.psus} placeholder="Pilih PSU" loading={fetching}/>
        </div>
        <button type="submit" className="btn-primary w-full justify-center py-3.5" disabled={!allFilled || loading || fetching}>
          {loading ? <><span className="spinner w-4 h-4 border border-white/30 border-t-white"></span> Memeriksa...</> : '🔍 Cek Kompatibilitas'}
        </button>
      </form>

      {result && (
        <div>
          <CompatibilityCard checks={result.checks} compatible={result.compatible}/>
        </div>
      )}

      {/* Info box */}
      <div className="mt-8 grid sm:grid-cols-2 gap-4 text-sm text-gray-400">
        {[
          ['Socket CPU ↔ Motherboard', 'AM4 hanya cocok dengan board AM4, LGA1700 hanya dengan board Intel.'],
          ['Tipe RAM ↔ CPU', 'CPU AM4 umumnya DDR4. CPU AM5 membutuhkan DDR5. Beberapa Intel mendukung keduanya.'],
          ['Tipe RAM ↔ Motherboard', 'Board dan RAM harus berbagi versi DDR yang sama.'],
          ['PSU Watt', 'Kalkulasi: (TDP CPU + Power GPU) × 1.2. Sisihkan 20% headroom minimum.'],
        ].map(([title, desc]) => (
          <div key={title} className="glass-sm p-4">
            <p className="font-semibold text-white text-xs mb-1">{title}</p>
            <p className="text-xs leading-relaxed">{desc}</p>
          </div>
        ))}
      </div>

      <p className="text-[11px] text-gray-500 text-center mt-8">
        Sistem ini menggunakan AI untuk estimasi data kompatibilitas & benchmark dan bisa keliru. Harap periksa kembali respons.
      </p>
    </div>
  )
}
