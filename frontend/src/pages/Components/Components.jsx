import { useState, useEffect } from 'react'
import { getComponents } from '../../services/api'
import { formatRupiah } from '../../utils/helpers'

const TABS = [
  { key: 'cpus',         label: 'CPU',         icon: '🔲' },
  { key: 'gpus',         label: 'GPU',         icon: '🎮' },
  { key: 'motherboards', label: 'Motherboard', icon: '🔌' },
  { key: 'rams',         label: 'RAM',         icon: '💾' },
  { key: 'ssds',         label: 'SSD',         icon: '💿' },
  { key: 'hdds',         label: 'HDD',         icon: '🗄️' },
  { key: 'psus',         label: 'PSU',         icon: '⚡' },
]

const COLUMNS = {
  cpus:         [['Nama','name'],['Brand','brand'],['Socket','socket'],['RAM Type','ram_type'],['Cores','cores'],['Boost','boost_clock','GHz'],['TDP','tdp','W'],['Harga','price','rupiah']],
  gpus:         [['Nama','name'],['Brand','brand'],['VRAM','vram','GB'],['Mem Type','memory_type'],['Power','power_draw','W'],['Harga','price','rupiah']],
  motherboards: [['Nama','name'],['Brand','brand'],['Socket','socket'],['Chipset','chipset'],['RAM Type','ram_type'],['Max RAM','max_ram','GB'],['Harga','price','rupiah']],
  rams:         [['Nama','name'],['DDR','ddr_version'],['Kapasitas','capacity','GB'],['Speed','speed','MHz'],['Harga','price','rupiah']],
  ssds:         [['Nama','name'],['Tipe','type'],['Kapasitas','capacity','GB'],['Power','power_draw','W'],['Harga','price','rupiah']],
  hdds:         [['Nama','name'],['Kapasitas','capacity','GB'],['Power','power_draw','W'],['Harga','price','rupiah']],
  psus:         [['Nama','name'],['Watt','watt','W'],['Sertifikasi','certification'],['Harga','price','rupiah']],
}

function CellValue({ value, suffix }) {
  if (suffix === 'rupiah') return <span className="font-semibold text-accent">{formatRupiah(value)}</span>
  if (suffix) return <span className="font-mono text-primary">{value}{suffix}</span>
  return <span>{value}</span>
}

export default function Components() {
  const [active, setActive] = useState('cpus')
  const [data, setData]     = useState({})
  const [loading, setLoading] = useState(false)

  useEffect(() => {
    if (data[active]) return
    setLoading(true)
    getComponents[active]?.()
      .then((r) => setData((d) => ({ ...d, [active]: r.data.data })))
      .finally(() => setLoading(false))
  }, [active])

  const rows = data[active] || []
  const cols = COLUMNS[active] || []

  return (
    <div className="max-w-6xl mx-auto px-4 sm:px-6 py-12">
      <div className="mb-10 animate-fade-in">
        <p className="text-gray-400 text-sm font-medium mb-2">🗂️ Database Komponen</p>
        <h1 className="text-4xl font-black text-white mb-3">Daftar Komponen</h1>
        <p className="text-gray-400">Semua komponen yang tersedia di database PC Calculator. Data diambil berdasarkan website www.enterkomputer.com</p>
      </div>

      {/* Tab bar */}
      <div className="flex flex-wrap gap-2 mb-6">
        {TABS.map(({ key, label, icon }) => (
          <button key={key} onClick={() => setActive(key)}
            className={`flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-medium transition-all duration-200 ${
              active === key
                ? 'bg-gradient-to-r from-primary/20 to-accent/20 text-primary border border-primary/30'
                : 'glass-sm text-gray-400 hover:text-white'
            }`}>
            <span>{icon}</span>
            {label}
          </button>
        ))}
      </div>

      {/* Table */}
      <div className="glass overflow-hidden">
        {loading ? (
          <div className="flex items-center justify-center h-40">
            <span className="spinner"></span>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-white/5">
                  {cols.map(([label]) => (
                    <th key={label} className="px-4 py-3.5 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider">
                      {label}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {rows.map((row, i) => (
                  <tr key={row.id} className={`border-b border-white/5 hover:bg-white/2 transition-colors ${i % 2 === 0 ? '' : 'bg-white/1'}`}>
                    {cols.map(([label, field, suffix]) => (
                      <td key={field} className="px-4 py-3 text-gray-300">
                        <CellValue value={row[field]} suffix={suffix}/>
                      </td>
                    ))}
                  </tr>
                ))}
                {rows.length === 0 && !loading && (
                  <tr>
                    <td colSpan={cols.length} className="text-center py-12 text-gray-500">Tidak ada data</td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        )}
      </div>

      <p className="text-xs text-gray-500 mt-4 text-center">{rows.length} komponen ditemukan</p>
    </div>
  )
}
