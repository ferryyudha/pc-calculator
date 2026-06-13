import React, { useState, useEffect } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import { getDeviceDetail, getDeviceLogs, getDevicePower } from '../../services/api'
import HistoryCharts from './HistoryCharts'

export default function DeviceDetailsPage() {
  const { id } = useParams()
  const navigate = useNavigate()
  
  const [device, setDevice] = useState(null)
  const [logs, setLogs] = useState([])
  const [power, setPower] = useState(null)
  const [timeframe, setTimeframe] = useState('1h')
  const [detailTab, setDetailTab] = useState('charts') // charts, storage, battery, power
  const [loading, setLoading] = useState(true)
  
  const [plnRate] = useState(() => {
    const saved = localStorage.getItem('pln_rate')
    return saved ? parseFloat(saved) : 1699.53
  })

  // Load telemetry logs and details from API
  const fetchTelemetry = async () => {
    try {
      setLoading(true)
      
      // 1. Get device specs
      const detailRes = await getDeviceDetail(id)
      if (detailRes.data.status === 'success') {
        setDevice(detailRes.data.data)
      }

      // 2. Get history logs
      const logsRes = await getDeviceLogs(id, timeframe)
      if (logsRes.data.status === 'success') {
        setLogs(logsRes.data.data)
      }

      // 3. Get PLN cost metrics
      const powerRes = await getDevicePower(id, plnRate)
      if (powerRes.data.status === 'success') {
        setPower(powerRes.data.data)
      }
    } catch (e) {
      console.error('Gagal mengambil detail perangkat telemetri.', e)
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    fetchTelemetry()
  }, [id, timeframe, plnRate])

  if (loading && !device) {
    return (
      <div className="max-w-7xl mx-auto px-4 py-20 flex flex-col items-center justify-center text-center">
        <div className="spinner border-t-primary mb-4"></div>
        <p className="text-gray-400 text-sm">Memuat data spesifikasi telemetri PC...</p>
      </div>
    )
  }

  if (!device) {
    return (
      <div className="max-w-xl mx-auto px-4 py-20 text-center animate-fade-in">
        <div className="text-5xl mb-4">🔍</div>
        <h3 className="text-xl font-bold text-white mb-2">Perangkat Tidak Ditemukan</h3>
        <p className="text-gray-400 text-sm mb-6">Device ID #{id} tidak terdaftar di sistem monitor.</p>
        <button onClick={() => navigate('/monitoring')} className="btn-primary">
          Kembali ke Dashboard
        </button>
      </div>
    )
  }

  const log = device.latest_log
  const isOnline = device.status === 'online'

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 py-10">
      {/* Back Header */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8 border-b border-white/5 pb-6 animate-fade-in">
        <div className="flex items-center gap-4">
          <button
            onClick={() => navigate('/monitoring')}
            className="btn-secondary py-2 px-4 text-xs font-bold flex items-center gap-1.5"
          >
            &larr; Kembali
          </button>
          <div>
            <div className="flex items-center gap-2.5">
              <h1 className="text-2xl sm:text-3xl font-black text-white">{device.device_code}</h1>
              <span className={`px-2.5 py-0.5 rounded-full text-xs font-black border uppercase ${
                isOnline 
                  ? 'bg-success/15 text-success border-success/30' 
                  : 'bg-danger/15 text-danger border-danger/30'
              }`}>
                {device.status}
              </span>
            </div>
            <p className="text-xs text-gray-500 font-medium mt-1">
              Hostname: <span className="text-gray-300 font-mono">{device.hostname || '—'}</span> · OS: <span className="text-gray-300">{device.os || '—'}</span> · IP: <span className="text-gray-300 font-mono">{device.ip_address || '—'}</span>
            </p>
          </div>
        </div>

        {isOnline && (
          <div className="flex items-center gap-2 bg-success/10 border border-success/20 px-3.5 py-2 rounded-xl self-start md:self-center">
            <span className="w-2.5 h-2.5 rounded-full bg-success animate-pulse-slow"></span>
            <span className="text-xs font-bold text-success uppercase">Mengirim Telemetri</span>
          </div>
        )}
      </div>

      {/* Tabs Row */}
      <div className="flex flex-col md:flex-row md:items-center justify-between border-b border-white/5 pb-4 mb-8 gap-4">
        <div className="flex flex-wrap gap-2 p-1 bg-surface-800/60 rounded-xl border border-white/5">
          {[
            { id: 'charts', label: '📈 Grafik & Tren' },
            { id: 'storage', label: `💾 Penyimpanan (${device.storage_devices?.length || 0})` },
            { id: 'battery', label: '🔋 Kesehatan Baterai' },
            { id: 'power', label: '⚡ Biaya & Konsumsi Daya' }
          ].map(tab => (
            <button
              key={tab.id}
              onClick={() => setDetailTab(tab.id)}
              className={`px-4 py-2 rounded-lg text-xs sm:text-sm font-semibold transition-all duration-200 ${
                detailTab === tab.id
                  ? 'bg-primary text-surface-900 shadow-glow-primary'
                  : 'text-gray-400 hover:text-white hover:bg-white/5'
              }`}
            >
              {tab.label}
            </button>
          ))}
        </div>

        {detailTab === 'charts' && (
          <div className="flex gap-1.5 bg-surface-850 p-1 rounded-lg border border-white/5">
            {['1h', '24h', '7d', '30d'].map(tf => (
              <button
                key={tf}
                onClick={() => setTimeframe(tf)}
                className={`px-3 py-1 text-xs font-bold rounded transition-all duration-200 ${
                  timeframe === tf
                    ? 'bg-surface-650 text-white border border-white/10'
                    : 'text-gray-500 hover:text-gray-300'
                }`}
              >
                {tf.toUpperCase()}
              </button>
            ))}
          </div>
        )}
      </div>

      {/* Tab Contents */}
      <div className="animate-slide-up">
        {/* Charts & Graphs Tab */}
        {detailTab === 'charts' && (
          <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div className="glass p-5 border border-white/5 flex flex-col gap-2">
              <HistoryCharts logs={logs} metric="cpu_temp" label="Suhu CPU (°C)" color="#EF4444" />
            </div>
            <div className="glass p-5 border border-white/5 flex flex-col gap-2">
              <HistoryCharts logs={logs} metric="gpu_temp" label="Suhu GPU (°C)" color="#F59E0B" />
            </div>
            <div className="glass p-5 border border-white/5 flex flex-col gap-2">
              <HistoryCharts logs={logs} metric="cpu_usage" label="Beban Kerja CPU (%)" color="#00D4FF" />
            </div>
            <div className="glass p-5 border border-white/5 flex flex-col gap-2">
              <HistoryCharts logs={logs} metric="gpu_usage" label="Beban Kerja GPU (%)" color="#7C3AED" />
            </div>
            <div className="glass p-5 border border-white/5 flex flex-col gap-2">
              <HistoryCharts logs={logs} metric="ram_usage" label="Konsumsi RAM (GB)" color="#EC4899" />
            </div>
            <div className="glass p-5 border border-white/5 flex flex-col gap-2">
              <HistoryCharts logs={logs} metric="power_usage" label="Konsumsi Daya Real-time (W)" color="#10B981" />
            </div>
          </div>
        )}

        {/* Storage Devices Tab */}
        {detailTab === 'storage' && (
          <div className="glass p-6 border border-white/5">
            <h3 className="text-lg font-bold text-white mb-5">Media Penyimpanan Terdeteksi</h3>
            
            {device.storage_devices && device.storage_devices.length > 0 ? (
              <div className="overflow-x-auto">
                <table className="w-full text-left text-sm text-gray-300">
                  <thead>
                    <tr className="border-b border-white/5 text-gray-500 font-bold text-xs uppercase tracking-wider">
                      <th className="pb-3 px-4">Nama Drive</th>
                      <th className="pb-3 px-4">Kapasitas</th>
                      <th className="pb-3 px-4">Suhu Kerja</th>
                      <th className="pb-3 px-4">Kesehatan Media</th>
                      <th className="pb-3 px-4">Jam Pemakaian (POH)</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-white/5">
                    {device.storage_devices.map((drive, idx) => (
                      <tr key={idx} className="hover:bg-white/2 transition-colors">
                        <td className="py-4 px-4 font-bold text-white">{drive.name}</td>
                        <td className="py-4 px-4 font-mono text-xs">
                          {drive.capacity >= 1024 * 1024
                            ? `${(drive.capacity / (1024 * 1024)).toFixed(1)} TB`
                            : `${(drive.capacity / 1024).toFixed(0)} GB`}
                        </td>
                        <td className="py-4 px-4 font-mono text-xs text-gray-200">
                          {drive.temperature ? `${drive.temperature}°C` : '—'}
                        </td>
                        <td className="py-4 px-4">
                          <div className="flex items-center gap-2.5">
                            <span className={`font-black font-mono text-xs ${
                              drive.health >= 90 ? 'text-success' : drive.health >= 70 ? 'text-warning' : 'text-danger'
                            }`}>
                              {drive.health}%
                            </span>
                            <div className="w-16 h-1.5 bg-surface-700 rounded-full overflow-hidden border border-white/5">
                              <div
                                className={`h-full rounded-full ${
                                  drive.health >= 90 ? 'bg-success' : drive.health >= 70 ? 'bg-warning' : 'bg-danger'
                                }`}
                                style={{ width: `${drive.health}%` }}
                              ></div>
                            </div>
                          </div>
                        </td>
                        <td className="py-4 px-4 font-mono text-xs text-gray-400">
                          {drive.power_on_hours ? `${drive.power_on_hours.toLocaleString('id-ID')} Jam` : '—'}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            ) : (
              <div className="py-12 text-center text-gray-500">
                <span className="text-4xl block mb-2">💾</span>
                <h4 className="font-bold text-white text-base">Tidak Ada Informasi Penyimpanan</h4>
                <p className="text-xs text-gray-400 mt-1">Perangkat agent belum melaporkan data kesehatan penyimpanan.</p>
              </div>
            )}
          </div>
        )}

        {/* Battery Health Tab */}
        {detailTab === 'battery' && (
          <div className="glass p-6 border border-white/5">
            <h3 className="text-lg font-bold text-white mb-5">Kondisi Baterai (Laptop)</h3>
            
            {device.battery ? (
              <div className="grid grid-cols-2 md:grid-cols-4 gap-6">
                {[
                  {
                    title: 'Design Capacity',
                    val: device.battery.design_capacity ? `${(device.battery.design_capacity / 1000).toFixed(1)} Wh` : '—',
                    sub: `${device.battery.design_capacity} mWh`
                  },
                  {
                    title: 'Full Charge Capacity',
                    val: device.battery.full_charge_capacity ? `${(device.battery.full_charge_capacity / 1000).toFixed(1)} Wh` : '—',
                    sub: `${device.battery.full_charge_capacity} mWh`
                  },
                  {
                    title: 'Kesehatan Baterai',
                    val: device.battery.health_percentage ? `${device.battery.health_percentage.toFixed(1)}%` : '—',
                    sub: 'Penurunan kapasitas',
                    color: device.battery.health_percentage >= 80 ? 'text-success' : 'text-warning'
                  },
                  {
                    title: 'Cycle Count',
                    val: device.battery.cycle_count || '0',
                    sub: 'Total siklus isi ulang'
                  }
                ].map((item, idx) => (
                  <div key={idx} className="bg-surface-700/30 p-4.5 rounded-xl border border-white/5 space-y-1.5">
                    <span className="text-[10px] text-gray-500 font-bold uppercase tracking-wider block">{item.title}</span>
                    <span className={`text-xl sm:text-2xl font-black font-mono block ${item.color || 'text-white'}`}>
                      {item.val}
                    </span>
                    <span className="text-[10px] text-gray-400 block font-medium">{item.sub}</span>
                  </div>
                ))}
              </div>
            ) : (
              <div className="py-12 text-center text-gray-500">
                <span className="text-4xl block mb-2">🔋</span>
                <h4 className="font-bold text-white text-base">Tidak Ada Informasi Baterai</h4>
                <p className="text-xs text-gray-400 mt-1">Komputer tipe Desktop (PC Desktop) tidak memiliki perangkat penampung daya baterai.</p>
              </div>
            )}
          </div>
        )}

        {/* Power Draw & Costs Tab */}
        {detailTab === 'power' && (
          <div className="space-y-6 animate-slide-up">
            {/* Real-time power allocations */}
            <div className="glass p-6 border border-white/5">
              <h3 className="text-lg font-bold text-white mb-5">Alokasi Beban Daya Aktif</h3>
              {log ? (
                <div className="grid grid-cols-2 md:grid-cols-6 gap-4">
                  {[
                    { label: 'Beban CPU', val: `${(log.cpu_power || 0).toFixed(1)} W`, sub: 'Sensor Real-time' },
                    { label: 'Beban GPU', val: `${(log.gpu_power || 0).toFixed(1)} W`, sub: 'Sensor Real-time' },
                    { label: 'Baseline RAM', val: '10.0 W', sub: 'Pendekatan Tetap' },
                    { label: 'Baseline SSD', val: '5.0 W', sub: 'Pendekatan Tetap' },
                    { label: 'Baseline Motherboard', val: '20.0 W', sub: 'Pendekatan Tetap' },
                    { label: 'Total Pemakaian', val: `${(log.power_usage || 0).toFixed(1)} W`, sub: 'Akumulasi Daya', color: 'bg-success/10 border-success/30 text-success font-black text-base' }
                  ].map((pow, idx) => (
                    <div key={idx} className={`p-4 rounded-xl border border-white/5 space-y-1 ${pow.color || 'bg-surface-700/10'}`}>
                      <span className="text-[10px] text-gray-500 font-bold block uppercase">{pow.label}</span>
                      <span className="text-base font-black font-mono block text-white">{pow.val}</span>
                      <span className="text-[9px] text-gray-400 block">{pow.sub}</span>
                    </div>
                  ))}
                </div>
              ) : (
                <div className="text-center text-gray-500 py-6">Perangkat belum aktif atau tidak mengirimkan data log telemetri.</div>
              )}
            </div>

            {/* Calculations Breakdown */}
            {power && (
              <div className="grid md:grid-cols-2 gap-6">
                {/* Cost Projections */}
                <div className="glass p-6 border border-emerald-500/10 space-y-4 shadow-[0_4px_30px_rgba(16,185,129,0.02)]">
                  <h4 className="text-base font-bold text-white flex items-center gap-1.5">
                    💰 Perkiraan Tarif PLN Rumah Tangga
                  </h4>
                  
                  <div className="space-y-3.5 text-sm text-gray-400">
                    <div className="flex justify-between items-center pb-2 border-b border-white/5">
                      <span>Daya Telemetri</span>
                      <span className="text-white font-bold font-mono">{power.latest_power_w} Watt</span>
                    </div>
                    <div className="flex justify-between items-center pb-2 border-b border-white/5">
                      <span>Durasi Kerja Harian</span>
                      <span className="text-white font-medium">8 Jam per Hari (Baseline standard)</span>
                    </div>
                    <div className="flex justify-between items-center pb-2 border-b border-white/5">
                      <span>Konsumsi Energi Harian</span>
                      <span className="text-success font-bold font-mono">{power.kwh_per_day} kWh</span>
                    </div>
                    <div className="flex justify-between items-center pb-2 border-b border-white/5">
                      <span>Tarif Golongan PLN</span>
                      <span className="text-gray-200 font-medium">Rp {power.pln_rate.toLocaleString('id-ID')}/kWh</span>
                    </div>
                    <div className="flex justify-between items-center pt-2.5 font-bold border-t border-white/5 text-base">
                      <span className="text-gray-300">Biaya / Hari</span>
                      <span className="text-success font-mono">Rp {power.cost_per_day.toLocaleString('id-ID')}</span>
                    </div>
                    <div className="flex justify-between items-center pt-1 font-bold text-base">
                      <span className="text-gray-300">Biaya / Bulan (30d)</span>
                      <span className="text-success font-mono">Rp {power.cost_per_month.toLocaleString('id-ID')}</span>
                    </div>
                    <div className="flex justify-between items-center pt-1 font-bold text-base">
                      <span className="text-gray-300">Biaya / Tahun (365d)</span>
                      <span className="text-success font-mono">Rp {power.cost_per_year.toLocaleString('id-ID')}</span>
                    </div>
                  </div>
                </div>

                {/* Last 24 hours consumption */}
                <div className="glass p-6 border border-white/5 space-y-4 flex flex-col justify-between">
                  <div>
                    <h4 className="text-base font-bold text-white mb-4 flex items-center gap-1.5">
                      📊 Akumulasi Pemakaian 24 Jam Terakhir
                    </h4>

                    <div className="space-y-3.5 text-sm text-gray-400 mb-5">
                      <div className="flex justify-between items-center pb-2 border-b border-white/5">
                        <span>Rata-rata Beban Daya</span>
                        <span className="text-white font-bold font-mono">{power.historical_24h.avg_power_w} Watt</span>
                      </div>
                      <div className="flex justify-between items-center pb-2 border-b border-white/5">
                        <span>Energi Terpakai (24h)</span>
                        <span className="text-success font-bold font-mono">{power.historical_24h.kwh_24h} kWh</span>
                      </div>
                      <div className="flex justify-between items-center pt-2.5 font-bold border-t border-white/5 text-base">
                        <span className="text-gray-300">Tagihan Riwayat (24h)</span>
                        <span className="text-success font-mono">Rp {power.historical_24h.cost_24h.toLocaleString('id-ID')}</span>
                      </div>
                    </div>
                  </div>

                  <div className="bg-surface-700/30 p-3 rounded-lg text-xs text-gray-400 border border-white/5 leading-relaxed">
                    💡 **Informasi Tambahan**: Proyeksi biaya bulanan dihitung konstan berdasarkan pemakaian stabil 8 jam, sedangkan tagihan riwayat 24 jam dihitung berdasarkan rata-rata log data telemetry yang dikirim agen secara kumulatif selama seharian penuh.
                  </div>
                </div>
              </div>
            )}
          </div>
        )}
      </div>
    </div>
  )
}
