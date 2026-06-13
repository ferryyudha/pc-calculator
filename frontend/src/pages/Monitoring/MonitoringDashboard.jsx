import React, { useState, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import { getDevices, getAlerts, patchAlert, BACKEND_URL } from '../../services/api'

export default function MonitoringDashboard() {
  const navigate = useNavigate()
  const [activeTab, setActiveTab] = useState('dashboard')
  const [devices, setDevices] = useState([])
  const [alerts, setAlerts] = useState([])
  const [loading, setLoading] = useState(true)
  const [plnRate, setPlnRate] = useState(() => {
    const saved = localStorage.getItem('pln_rate')
    return saved ? parseFloat(saved) : 1699.53
  })
  const [rateInput, setRateInput] = useState(plnRate.toString())
  const [settingsSaved, setSettingsSaved] = useState(false)

  // Fetch all devices and alerts from api
  const fetchData = async () => {
    try {
      const devRes = await getDevices()
      if (devRes.data.status === 'success') {
        setDevices(devRes.data.data)
      }

      const alertRes = await getAlerts()
      if (alertRes.data.status === 'success') {
        setAlerts(alertRes.data.data)
      }
    } catch (e) {
      console.error('Gagal mengambil data dari API backend.', e)
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    fetchData()
    // Poll telemetry data every 5 seconds
    const interval = setInterval(fetchData, 5000)
    return () => clearInterval(interval)
  }, [])

  const handleSaveRate = (e) => {
    e.preventDefault()
    const rate = parseFloat(rateInput)
    if (!isNaN(rate) && rate > 0) {
      setPlnRate(rate)
      localStorage.setItem('pln_rate', rate.toString())
      setSettingsSaved(true)
      setTimeout(() => setSettingsSaved(false), 3000)
    }
  }

  const handleUpdateAlertStatus = async (id, status) => {
    try {
      const res = await patchAlert(id, status)
      if (res.data.status === 'success') {
        fetchData()
      }
    } catch (e) {
      console.error('Gagal memperbarui status peringatan.', e)
    }
  }

  const activeAlertsCount = alerts.filter(a => a.status === 'active').length

  // Statistics summaries
  const totalCount = devices.length
  const onlineDevices = devices.filter(d => d.status === 'online')
  const onlineCount = onlineDevices.length

  const avgCpuTemp = onlineDevices.length > 0
    ? Math.round(onlineDevices.reduce((sum, d) => sum + (d.latest_log?.cpu_temp || 0), 0) / onlineDevices.length)
    : 0

  const avgGpuTemp = onlineDevices.length > 0
    ? Math.round(onlineDevices.reduce((sum, d) => sum + (d.latest_log?.gpu_temp || 0), 0) / onlineDevices.length)
    : 0

  const totalPower = onlineDevices.reduce((sum, d) => sum + (d.latest_log?.power_usage || 0), 0)

  // Last seen formatter
  const formatLastSeen = (timestamp) => {
    if (!timestamp) return 'Tidak pernah'
    const date = new Date(timestamp)
    const diffMs = new Date() - date
    const diffMins = Math.floor(diffMs / 60000)
    
    if (diffMins < 1) return 'Baru saja'
    if (diffMins < 60) return `${diffMins}m yang lalu`
    const diffHours = Math.floor(diffMins / 60)
    if (diffHours < 24) return `${diffHours}j yang lalu`
    return date.toLocaleDateString('id-ID')
  }

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 py-10">
      {/* Title Header */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8 animate-fade-in">
        <div>
          <p className="text-primary text-sm font-medium mb-1.5">🖥️ Real-time Telemetry Monitor</p>
          <h1 className="text-3xl sm:text-4xl font-black text-white mb-2">PC Monitoring & Telemetry</h1>
          <p className="text-gray-400 text-sm max-w-2xl">
            Pantau suhu komponen, beban pemrosesan, efisiensi energi, dan PLN billing rate di seluruh workstations Anda secara real-time.
          </p>
        </div>
        
        <div className="flex items-center gap-2.5 bg-surface-800/80 border border-white/5 px-4 py-2.5 rounded-xl self-start md:self-center">
          <span className="w-2.5 h-2.5 rounded-full bg-success animate-pulse-slow"></span>
          <span className="text-xs font-semibold text-success">Auto-Refresh Aktif</span>
          <span className="text-[10px] text-gray-500 font-mono">(5s)</span>
        </div>
      </div>

      {/* Tabs Layout */}
      <div className="flex flex-wrap items-center justify-between border-b border-white/5 pb-4 mb-8 gap-4">
        <div className="flex gap-2 p-1 bg-surface-800/60 rounded-xl border border-white/5">
          {[
            { id: 'dashboard', label: '📊 Ringkasan' },
            { id: 'devices', label: '💻 Semua PC' },
            { id: 'alerts', label: '⚠️ Peringatan', count: activeAlertsCount },
            { id: 'settings', label: '⚙️ Pengaturan' }
          ].map(tab => (
            <button
              key={tab.id}
              onClick={() => setActiveTab(tab.id)}
              className={`px-4 py-2 rounded-lg text-xs sm:text-sm font-semibold transition-all duration-200 flex items-center gap-2 ${
                activeTab === tab.id
                  ? 'bg-primary text-surface-900 shadow-glow-primary'
                  : 'text-gray-400 hover:text-white hover:bg-white/5'
              }`}
            >
              {tab.label}
              {tab.count !== undefined && tab.count > 0 && (
                <span className="bg-danger text-white text-[10px] px-1.5 py-0.5 rounded-full font-mono font-bold">
                  {tab.count}
                </span>
              )}
            </button>
          ))}
        </div>

        <a
          href={`${BACKEND_URL}/api/download-agent`}
          download
          className="btn-primary py-2 px-4 text-xs font-bold flex items-center gap-1.5"
        >
          <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
          </svg>
          Unduh Agent Client
        </a>
      </div>

      {/* Loading State */}
      {loading && devices.length === 0 && (
        <div className="flex flex-col items-center justify-center py-20 text-center">
          <div className="spinner border-t-primary mb-4"></div>
          <p className="text-gray-400 text-sm">Menghubungkan ke server telemetri...</p>
        </div>
      )}

      {/* Main Contents based on Tab */}
      {!loading && (
        <div className="animate-slide-up">
          {/* Dashboard Tab */}
          {activeTab === 'dashboard' && (
            <div className="space-y-8">
              {/* Metrics cards grid */}
              <div className="grid grid-cols-2 md:grid-cols-5 gap-4">
                {[
                  {
                    title: 'PC Aktif',
                    val: `${onlineCount} / ${totalCount}`,
                    sub: `${totalCount - onlineCount} offline`,
                    color: 'text-primary',
                    borderColor: 'border-primary/20'
                  },
                  {
                    title: 'Rata-rata Suhu CPU',
                    val: `${avgCpuTemp}°C`,
                    sub: avgCpuTemp > 80 ? '⚠️ Sangat Panas' : 'Dalam batas normal',
                    color: avgCpuTemp > 80 ? 'text-danger' : 'text-primary-light',
                    borderColor: avgCpuTemp > 80 ? 'border-danger/30' : 'border-white/5'
                  },
                  {
                    title: 'Rata-rata Suhu GPU',
                    val: `${avgGpuTemp}°C`,
                    sub: avgGpuTemp > 75 ? '⚠️ Sangat Panas' : 'Dalam batas normal',
                    color: avgGpuTemp > 75 ? 'text-danger' : 'text-primary-light',
                    borderColor: avgGpuTemp > 75 ? 'border-danger/30' : 'border-white/5'
                  },
                  {
                    title: 'Total Daya Aktif',
                    val: `${Math.round(totalPower)} W`,
                    sub: `Dari ${onlineCount} PC online`,
                    color: 'text-success',
                    borderColor: 'border-success/20'
                  },
                  {
                    title: 'Total Peringatan',
                    val: activeAlertsCount,
                    sub: activeAlertsCount > 0 ? '⚠️ Butuh perhatian' : 'Semua sistem sehat',
                    color: activeAlertsCount > 0 ? 'text-danger animate-pulse' : 'text-success',
                    borderColor: activeAlertsCount > 0 ? 'border-danger/30' : 'border-white/5'
                  }
                ].map((card, idx) => (
                  <div key={idx} className={`glass p-4 flex flex-col justify-between border ${card.borderColor}`}>
                    <span className="text-[11px] font-bold text-gray-500 uppercase tracking-wider">{card.title}</span>
                    <div className="my-2.5">
                      <span className={`text-2xl sm:text-3xl font-black ${card.color}`}>{card.val}</span>
                    </div>
                    <span className="text-[10px] text-gray-400 font-medium">{card.sub}</span>
                  </div>
                ))}
              </div>

              {/* Devices grid */}
              <div className="grid lg:grid-cols-3 gap-6">
                <div className="lg:col-span-2 space-y-4">
                  <div className="flex justify-between items-center border-b border-white/5 pb-2">
                    <h2 className="text-lg font-bold text-white">Daftar Perangkat Aktif ({onlineCount})</h2>
                    <button onClick={() => setActiveTab('devices')} className="text-primary hover:text-primary-light text-xs font-semibold">
                      Lihat Semua PC &rarr;
                    </button>
                  </div>

                  {onlineCount > 0 ? (
                    <div className="grid md:grid-cols-2 gap-4">
                      {onlineDevices.map((device) => {
                        const log = device.latest_log
                        const ramVal = log ? parseFloat(log.ram_usage) : 0
                        const ramPercent = Math.min(100, Math.round((ramVal / 16) * 100))

                        return (
                          <div key={device.id} className="glass p-5 border border-white/5 flex flex-col justify-between hover:border-primary/20 transition-all duration-300">
                            <div className="flex justify-between items-start mb-4">
                              <div>
                                <h3 className="font-bold text-white text-base">{device.device_code}</h3>
                                <p className="text-[11px] text-gray-500 font-mono mt-0.5">{device.hostname || 'Tanpa Hostname'}</p>
                              </div>
                              <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-success/10 text-success border border-success/20">
                                ONLINE
                              </span>
                            </div>

                            <div className="space-y-3.5 mb-5">
                              {/* CPU & GPU Stats */}
                              <div className="grid grid-cols-2 gap-3.5">
                                <div className="bg-surface-700/40 p-2.5 rounded-xl border border-white/5">
                                  <span className="text-[10px] text-gray-500 font-semibold block uppercase">CPU Temp</span>
                                  <span className={`text-base font-black font-mono block mt-0.5 ${log?.cpu_temp > 80 ? 'text-danger' : 'text-white'}`}>
                                    {log?.cpu_temp ? `${Math.round(log.cpu_temp)}°C` : '—'}
                                  </span>
                                  <span className="text-[10px] text-gray-400">Load: {log?.cpu_usage ? `${Math.round(log.cpu_usage)}%` : '—'}</span>
                                </div>
                                <div className="bg-surface-700/40 p-2.5 rounded-xl border border-white/5">
                                  <span className="text-[10px] text-gray-500 font-semibold block uppercase">GPU Temp</span>
                                  <span className={`text-base font-black font-mono block mt-0.5 ${log?.gpu_temp > 75 ? 'text-danger' : 'text-white'}`}>
                                    {log?.gpu_temp ? `${Math.round(log.gpu_temp)}°C` : '—'}
                                  </span>
                                  <span className="text-[10px] text-gray-400">Load: {log?.gpu_usage ? `${Math.round(log.gpu_usage)}%` : '—'}</span>
                                </div>
                              </div>

                              {/* RAM Usage */}
                              <div>
                                <div className="flex justify-between text-xs text-gray-400 mb-1">
                                  <span>Beban RAM</span>
                                  <span className="font-mono font-semibold text-gray-200">{ramVal > 0 ? `${ramVal.toFixed(1)} GB` : '—'}</span>
                                </div>
                                <div className="w-full bg-surface-700 rounded-full h-2 overflow-hidden border border-white/5">
                                  <div
                                    className={`h-full rounded-full transition-all duration-300 ${
                                      ramPercent > 85 ? 'bg-danger' : ramPercent > 70 ? 'bg-warning' : 'bg-primary'
                                    }`}
                                    style={{ width: `${ramPercent}%` }}
                                  ></div>
                                </div>
                              </div>

                              {/* Power Draw */}
                              <div className="flex justify-between items-center text-xs border-t border-white/5 pt-2.5">
                                <span className="text-gray-400">Konsumsi Daya:</span>
                                <span className="font-bold text-success font-mono text-sm">{log?.power_usage ? `${Math.round(log.power_usage)} Watt` : '—'}</span>
                              </div>
                            </div>

                            <div className="flex justify-between items-center text-xs text-gray-500 border-t border-white/5 pt-3">
                              <span>Seen: {formatLastSeen(device.last_seen)}</span>
                              <button
                                onClick={() => navigate(`/monitoring/device/${device.id}`)}
                                className="btn-secondary py-1.5 px-3.5 text-[11px] rounded-lg"
                              >
                                Detail PC &rarr;
                              </button>
                            </div>
                          </div>
                        )
                      })}
                    </div>
                  ) : (
                    <div className="glass p-12 text-center text-gray-500 border border-white/5">
                      Belum ada perangkat yang online. Aktifkan agent client pada komputer yang dipantau.
                    </div>
                  )}
                </div>

                {/* Sidebar Quick Setup */}
                <div className="space-y-6">
                  <h2 className="text-lg font-bold text-white border-b border-white/5 pb-2">Instalasi Agen PC</h2>
                  <div className="glass p-5 border border-white/5 space-y-4">
                    <div className="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center border border-primary/20 text-primary">
                      <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
                      </svg>
                    </div>
                    <div>
                      <h4 className="font-bold text-white text-sm mb-1">Cara Menghubungkan PC Anda</h4>
                      <p className="text-xs text-gray-400 leading-relaxed">
                        Agar telemetri PC Anda terdeteksi di dashboard ini, unduh program agen client, ekstrak dan jalankan di perangkat Windows Anda.
                      </p>
                    </div>

                    <ol className="text-xs text-gray-400 space-y-2 list-decimal list-inside bg-surface-700/30 p-3 rounded-lg border border-white/5">
                      <li>Unduh file ZIP dengan tombol di atas.</li>
                      <li>Ekstrak isi folder ZIP tersebut.</li>
                      <li>
                        Edit berkas <code className="text-primary font-mono">config.json</code> untuk menyesuaikan URL API:
                        <pre className="mt-1 text-[10px] text-gray-400 bg-surface-900 p-1.5 rounded font-mono overflow-x-auto">
                          {`{\n  "api_url": "${BACKEND_URL}"\n}`}
                        </pre>
                      </li>
                      <li>Jalankan <code className="text-primary font-mono">ScanDevice.exe</code> sebagai Administrator.</li>
                    </ol>

                    <div className="p-3 bg-surface-700/50 rounded-lg text-[11px] text-gray-400 border border-white/5 leading-relaxed">
                      💡 **Daya Baseline**: Estimasi biaya PLN didasarkan pada konsumsi daya aktual sensor PC ditambah baseline RAM, SSD, dan Motherboard (±35 Watt).
                    </div>
                  </div>
                </div>
              </div>
            </div>
          )}

          {/* Devices Tab */}
          {activeTab === 'devices' && (
            <div className="space-y-4">
              <h2 className="text-lg font-bold text-white border-b border-white/5 pb-2">Semua Perangkat Terdaftar ({totalCount})</h2>

              {devices.length > 0 ? (
                <div className="grid md:grid-cols-3 gap-6">
                  {devices.map((device) => {
                    const isOnline = device.status === 'online'
                    const log = device.latest_log

                    return (
                      <div key={device.id} className={`glass p-5 border flex flex-col justify-between hover:border-primary/20 transition-all duration-300 ${isOnline ? 'border-primary/10' : 'border-white/5'}`}>
                        <div className="flex justify-between items-start mb-4">
                          <div>
                            <h3 className="font-bold text-white text-base">{device.device_code}</h3>
                            <p className="text-[11px] text-gray-500 font-mono mt-0.5">{device.hostname || 'Tanpa Hostname'}</p>
                          </div>
                          <span className={`px-2 py-0.5 rounded-full text-[10px] font-bold border ${
                            isOnline 
                              ? 'bg-success/10 text-success border-success/20' 
                              : 'bg-danger/10 text-danger border-danger/20'
                          }`}>
                            {device.status.toUpperCase()}
                          </span>
                        </div>

                        <div className="text-xs text-gray-400 space-y-1.5 mb-5 bg-surface-700/20 p-3 rounded-lg border border-white/5">
                          <div className="flex justify-between"><span>Sistem Operasi:</span><span className="text-gray-200">{device.os || '—'}</span></div>
                          <div className="flex justify-between"><span>Alamat IP:</span><span className="text-gray-200 font-mono">{device.ip_address || '—'}</span></div>
                          <div className="flex justify-between"><span>Daya Terakhir:</span><span className="text-gray-200">{log?.power_usage ? `${Math.round(log.power_usage)} Watt` : '—'}</span></div>
                          <div className="flex justify-between"><span>Terakhir Aktif:</span><span className="text-gray-200">{formatLastSeen(device.last_seen)}</span></div>
                        </div>

                        <button
                          onClick={() => navigate(`/monitoring/device/${device.id}`)}
                          className="btn-secondary w-full justify-center py-2"
                        >
                          Lihat Detail Telemetri &rarr;
                        </button>
                      </div>
                    )
                  })}
                </div>
              ) : (
                <div className="glass p-12 text-center text-gray-500 border border-white/5">
                  Belum ada perangkat yang terdaftar di sistem.
                </div>
              )}
            </div>
          )}

          {/* Alerts Tab */}
          {activeTab === 'alerts' && (
            <div className="glass p-6 border border-white/5">
              <div className="flex justify-between items-center mb-6">
                <h3 className="text-lg font-bold text-white">Log Peringatan Hardware</h3>
                <span className="text-xs text-gray-400 bg-surface-700/50 border border-white/5 px-2.5 py-1 rounded-lg">
                  Total: {alerts.length} Peringatan
                </span>
              </div>

              {alerts.length > 0 ? (
                <div className="overflow-x-auto">
                  <table className="w-full text-left text-sm text-gray-300">
                    <thead>
                      <tr className="border-b border-white/5 text-gray-500 font-bold text-xs uppercase tracking-wider">
                        <th className="pb-3 text-center" style={{ width: '50px' }}>Tipe</th>
                        <th className="pb-3 px-4">Perangkat</th>
                        <th className="pb-3 px-4">Pesan Masalah</th>
                        <th className="pb-3 px-4">Status</th>
                        <th className="pb-3 px-4">Waktu Pemicu</th>
                        <th className="pb-3 text-right">Aksi</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-white/5">
                      {alerts.map((alert) => (
                        <tr key={alert.id} className="hover:bg-white/2 transition-colors">
                          <td className="py-4 text-center">
                            <div className="w-8 h-8 rounded-lg bg-surface-700/80 border border-white/5 flex items-center justify-center mx-auto">
                              {alert.type.includes('cpu') || alert.type.includes('gpu') ? (
                                <span className="text-danger text-sm">🔥</span>
                              ) : (
                                <span className="text-warning text-sm">⚠️</span>
                              )}
                            </div>
                          </td>
                          <td className="py-4 px-4 font-bold text-white">
                            {alert.device?.device_code || '—'}
                          </td>
                          <td className="py-4 px-4 text-gray-300 max-w-md">
                            {alert.source === 'diagnostic' && (
                              <span className="inline-block bg-primary/10 text-primary border border-primary/20 text-[9px] font-black px-1.5 py-0.5 rounded mr-1.5 uppercase tracking-wide">
                                Diagnostic
                              </span>
                            )}
                            <span className="align-middle">{alert.message}</span>
                          </td>
                          <td className="py-4 px-4">
                            <span className={`px-2 py-0.5 rounded text-[10px] font-bold border uppercase ${
                              alert.status === 'resolved'
                                ? 'bg-success/15 text-success border-success/30'
                                : alert.status === 'acknowledged'
                                ? 'bg-warning/15 text-warning border-warning/30'
                                : 'bg-danger/15 text-danger border-danger/30'
                            }`}>
                              {alert.status}
                            </span>
                          </td>
                          <td className="py-4 px-4 text-xs text-gray-400 font-medium">
                            {new Date(alert.created_at).toLocaleString('id-ID')}
                          </td>
                          <td className="py-4 text-right space-x-2">
                            {alert.status === 'active' && (
                              <button
                                onClick={() => handleUpdateAlertStatus(alert.id, 'acknowledged')}
                                className="px-2.5 py-1 rounded text-xs border border-warning/40 text-warning hover:bg-warning/10 transition-colors"
                              >
                                Tinjau
                              </button>
                            )}
                            {alert.status !== 'resolved' && (
                              <button
                                onClick={() => handleUpdateAlertStatus(alert.id, 'resolved')}
                                className="px-2.5 py-1 rounded text-xs bg-success text-surface-900 font-bold hover:bg-success/80 transition-colors"
                              >
                                Selesai
                              </button>
                            )}
                            {alert.status === 'resolved' && (
                              <span className="text-xs text-gray-500 font-semibold px-2">Diselesaikan ✓</span>
                            )}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              ) : (
                <div className="py-12 text-center text-gray-500">
                  <span className="text-4xl block mb-2">🎉</span>
                  <h4 className="font-bold text-white text-base">Tidak Ada Peringatan Hardware</h4>
                  <p className="text-xs text-gray-400 mt-1">Suhu, daya, dan kesehatan media penyimpanan di semua PC berada dalam kondisi aman.</p>
                </div>
              )}
            </div>
          )}

          {/* Settings Tab */}
          {activeTab === 'settings' && (
            <div className="grid md:grid-cols-2 gap-8">
              <div className="glass p-6 border border-white/5">
                <h3 className="text-lg font-bold text-white border-b border-white/5 pb-2 mb-5">Pengaturan Parameter Sistem</h3>
                
                <form onSubmit={handleSaveRate} className="space-y-5">
                  <div>
                    <label className="block text-sm font-semibold text-gray-300 mb-2" htmlFor="pln-rate">
                      Tarif Listrik PLN (Rupiah per kWh)
                    </label>
                    <div className="flex items-center gap-3">
                      <span className="text-gray-400 font-bold text-base">Rp</span>
                      <input
                        id="pln-rate"
                        type="number"
                        step="0.01"
                        className="field py-2.5 flex-1 text-white"
                        value={rateInput}
                        onChange={(e) => setRateInput(e.target.value)}
                        required
                      />
                    </div>
                    <span className="text-[11px] text-gray-500 mt-2 block leading-relaxed">
                      Tarif default PLN untuk golongan Rumah Tangga R-1/TR 1.300 VA saat ini adalah **Rp 1.699,53** per kWh. Nilai ini digunakan untuk kalkulasi perkiraan biaya listrik bulanan pada detail PC.
                    </span>
                  </div>

                  <div className="flex items-center gap-4 border-t border-white/5 pt-4">
                    <button type="submit" className="btn-primary py-2 px-5 text-xs font-bold">
                      Simpan Pengaturan
                    </button>
                    {settingsSaved && (
                      <span className="text-success text-xs font-semibold animate-fade-in">
                        ✓ Pengaturan berhasil disimpan!
                      </span>
                    )}
                  </div>
                </form>
              </div>

              {/* References Panel */}
              <div className="glass p-6 border border-white/5 space-y-4">
                <h3 className="text-lg font-bold text-white border-b border-white/5 pb-2">Referensi Tarif Listrik PLN (2026)</h3>
                
                <div className="space-y-3.5 text-xs text-gray-400">
                  <div className="flex justify-between items-center p-3 bg-surface-700/20 rounded-xl border border-white/5">
                    <span>R-1/TR (900 VA - Bersubsidi)</span>
                    <span className="text-white font-bold font-mono">Rp 605,00 / kWh</span>
                  </div>
                  <div className="flex justify-between items-center p-3 bg-surface-700/20 rounded-xl border border-white/5">
                    <span>R-1/TR (1.300 VA)</span>
                    <span className="text-white font-bold font-mono">Rp 1.699,53 / kWh</span>
                  </div>
                  <div className="flex justify-between items-center p-3 bg-surface-700/20 rounded-xl border border-white/5">
                    <span>R-1/TR (2.200 VA)</span>
                    <span className="text-white font-bold font-mono">Rp 1.699,53 / kWh</span>
                  </div>
                  <div className="flex justify-between items-center p-3 bg-surface-700/20 rounded-xl border border-white/5">
                    <span>R-2/TR (3.500 VA s.d. 5.500 VA)</span>
                    <span className="text-white font-bold font-mono">Rp 1.699,53 / kWh</span>
                  </div>
                </div>
              </div>
            </div>
          )}
        </div>
      )}
    </div>
  )
}
