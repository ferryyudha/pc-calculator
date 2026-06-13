import { useState, useEffect } from 'react'
import { Link } from 'react-router-dom'
import { getStats } from '../../services/api'

const features = [
  {
    to: '/compatibility',
    icon: '🔗',
    color: 'from-blue-500/20 to-cyan-500/20 border-blue-500/20',
    glow: 'group-hover:shadow-[0_0_30px_rgba(59,130,246,0.2)]',
    badge: 'bg-blue-500/15 text-blue-400',
    title: 'Cek Kompatibilitas',
    desc: 'Validasi CPU, Motherboard, RAM, GPU, dan PSU dalam satu klik. Deteksi konflik socket, tipe DDR, dan kebutuhan watt secara otomatis.',
    tags: ['Socket Match', 'DDR4/DDR5', 'PSU Watt'],
  },
  {
    to: '/fps-calculator',
    icon: '🎮',
    color: 'from-purple-500/20 to-pink-500/20 border-purple-500/20',
    glow: 'group-hover:shadow-[0_0_30px_rgba(168,85,247,0.2)]',
    badge: 'bg-purple-500/15 text-purple-400',
    title: 'Kalkulator FPS',
    desc: 'Estimasi FPS untuk kombinasi CPU + GPU + Game + Resolusi dari data benchmark nyata. Fallback otomatis dengan label transparansi data.',
    tags: ['720p–4K', 'Data Terukur', 'Estimasi Cerdas'],
  },
  {
    to: '/psu-calculator',
    icon: '⚡',
    color: 'from-yellow-500/20 to-orange-500/20 border-yellow-500/20',
    glow: 'group-hover:shadow-[0_0_30px_rgba(234,179,8,0.2)]',
    badge: 'bg-yellow-500/15 text-yellow-400',
    title: 'Kalkulator PSU',
    desc: 'Hitung kebutuhan watt PSU dari semua komponen. Breakdown rinci per komponen dengan headroom 30% untuk efisiensi optimal.',
    tags: ['30% Headroom', 'Per-Komponen', 'SSD + HDD'],
  },
  {
    to: '/build',
    icon: '🏗️',
    color: 'from-green-500/20 to-emerald-500/20 border-green-500/20',
    glow: 'group-hover:shadow-[0_0_30px_rgba(34,197,94,0.2)]',
    badge: 'bg-green-500/15 text-green-400',
    title: 'Rekomendasi Rakitan',
    desc: 'Masukkan budget dan game target, dapatkan rekomendasi build optimal dengan algoritma dinamis yang mempertimbangkan semua komponen.',
    tags: ['Algoritma Dinamis', 'Budget Fleksibel', 'Estimasi FPS'],
  },
]

// Nilai default (fallback) yang ditampilkan jika request ke database gagal atau sedang loading
const defaultStats = {
  cpus: '6',
  gpus: '5',
  games: '5',
  benchmarks: '142+',
}

export default function Home() {
  // statsData menyimpan nilai counter yang akan ditampilkan di halaman utama
  const [statsData, setStatsData] = useState(defaultStats)
  // loading mengontrol apakah skeleton animasi pulse sedang aktif
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    // Ambil statistik terbaru dari backend saat komponen pertama kali dimuat
    getStats()
      .then((res) => {
        if (res.data?.success) {
          const { cpus, gpus, games, benchmarks } = res.data.data
          setStatsData({
            cpus: String(cpus),
            gpus: String(gpus),
            games: String(games),
            // Format angka benchmark dengan locale Indonesia (contoh: 78.720+)
            benchmarks: `${new Intl.NumberFormat('id-ID').format(benchmarks)}+`,
          })
        }
      })
      .catch((err) => {
        // Catat error ke konsol dan tampilkan nilai fallback default
        console.error('Gagal mengambil statistik:', err)
      })
      .finally(() => {
        setLoading(false)
      })
  }, [])

  // Array yang dipetakan untuk merender grid statistik di halaman utama.
  // CATATAN MAINTENANCE: Jika menambah item statistik baru dari respons /api/stats,
  // ekstrak dari res.data.data di atas, tambahkan ke state statsData, lalu daftarkan di sini.
  const displayStats = [
    { value: statsData.cpus, label: 'CPU Tersedia' },
    { value: statsData.gpus, label: 'GPU Tersedia' },
    { value: statsData.games, label: 'Game Benchmark' },
    { value: statsData.benchmarks, label: 'Entri Benchmark' },
  ]

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 py-12">

      {/* Hero */}
      <div className="text-center mb-20 animate-fade-in">
        <div className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-primary/10 border border-primary/20 text-primary text-xs font-medium mb-6">
          <span className="w-1.5 h-1.5 rounded-full bg-primary animate-pulse-slow"></span>
          Kalkulator PC & Estimasi FPS Gaming
        </div>
        <h1 className="text-5xl sm:text-7xl font-black mb-6 leading-tight">
          <span className="gradient-text">PC Calculator</span>
          <br />
          <span className="text-white/80 text-4xl sm:text-5xl font-bold">untuk Builder Indonesia</span>
        </h1>
        <p className="text-gray-400 text-lg max-w-2xl mx-auto mb-10">
          Cek kompatibilitas komponen, estimasi FPS gaming, hitung kebutuhan PSU
          serta dapatkan rekomendasi rakitan terbaik sesuai budget kamu.
        </p>
        <div className="flex flex-wrap gap-4 justify-center">
          <Link to="/build" className="btn-primary text-base px-8 py-4">
            <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 10V3L4 14h7v7l9-11h-7z"/>
            </svg>
            Mulai Rekomendasi Rakitan
          </Link>
          <Link to="/compatibility" className="btn-secondary text-base px-8 py-4">Cek Kompatibilitas →</Link>
        </div>
      </div>

      {/* Stats */}
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-20 animate-slide-up">
        {displayStats.map(({ value, label }) => (
          <div key={label} className="glass p-5 text-center hover:border-primary/20 transition-all duration-300 animate-fade-in">
            {loading ? (
              <div className="h-9 w-20 bg-white/10 rounded mx-auto mb-2 animate-pulse"></div>
            ) : (
              <p className="text-3xl font-black gradient-text mb-1">{value}</p>
            )}
            <p className="text-sm text-gray-400">{label}</p>
          </div>
        ))}
      </div>

      {/* Feature Cards */}
      <div className="grid sm:grid-cols-2 gap-5">
        {features.map(({ to, icon, color, glow, badge, title, desc, tags }) => (
          <Link key={to} to={to}
            className={`group glass bg-gradient-to-br ${color} p-6 hover:scale-[1.02] hover:-translate-y-1 transition-all duration-300 cursor-pointer ${glow}`}>
            <div className="flex items-start gap-4">
              <div className={`w-12 h-12 rounded-xl ${badge} flex items-center justify-center text-2xl flex-shrink-0`}>
                {icon}
              </div>
              <div className="flex-1">
                <h2 className="text-lg font-bold text-white mb-2">{title}</h2>
                <p className="text-gray-400 text-sm leading-relaxed mb-4">{desc}</p>
                <div className="flex flex-wrap gap-2">
                  {tags.map((tag) => (
                    <span key={tag} className="px-2 py-0.5 rounded-md bg-white/5 text-gray-400 text-xs">{tag}</span>
                  ))}
                </div>
              </div>
            </div>
          </Link>
        ))}
      </div>

      {/* Benchmark transparency note */}
      <div className="mt-16 glass p-6 flex items-start gap-4">
        <div className="w-10 h-10 rounded-xl bg-primary/10 text-primary flex items-center justify-center flex-shrink-0">
          <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
        </div>
        <div>
          <p className="font-semibold text-white mb-1">Transparansi Data Benchmark</p>
          <p className="text-sm text-gray-400">
            Setiap hasil FPS dilabeli <span className="badge-measured mx-1">Data Terukur</span> jika berasal dari benchmark nyata,
            atau <span className="badge-interpolated mx-1">Estimasi</span> jika dihitung dari data hardware serupa.
            Kami tidak menyembunyikan ketidakpastian data.
          </p>
        </div>
      </div>
    </div>
  )
}
