import { formatRupiah } from '../../utils/helpers'
import DataSourceBadge from '../DataSourceBadge/DataSourceBadge'

/**
 * BuildCard — Blueprint §6.1
 * Shows recommended build components, total price, remaining budget, and FPS
 */
export default function BuildCard({ data }) {
  if (!data) return null
  const { build, total_price, remaining_budget, estimated_fps, fps_source } = data

  const components = [
    { label: 'CPU',         icon: '🔲', item: build.cpu,         extra: `${build.cpu?.cores}C/${build.cpu?.threads}T · ${build.cpu?.tdp}W TDP` },
    { label: 'GPU',         icon: '🎮', item: build.gpu,         extra: `${build.gpu?.vram}GB ${build.gpu?.memory_type}` },
    { label: 'Motherboard', icon: '🔌', item: build.motherboard, extra: `${build.motherboard?.socket} · ${build.motherboard?.ram_type}` },
    { label: 'RAM',         icon: '💾', item: build.ram,         extra: `${build.ram?.capacity}GB ${build.ram?.ddr_version} ${build.ram?.speed}MHz` },
    { label: 'SSD',         icon: '💿', item: build.ssd,         extra: `${build.ssd?.capacity}GB · ${build.ssd?.type}` },
    { label: 'PSU',         icon: '⚡', item: build.psu,         extra: `${build.psu?.watt}W · ${build.psu?.certification}` },
  ]

  return (
    <div className="glass p-6 space-y-5 animate-slide-up">
      {/* Header */}
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <p className="text-xs text-gray-400 uppercase tracking-wider mb-1">Build yang Direkomendasikan</p>
          <p className="text-2xl font-bold text-white">{formatRupiah(total_price)}</p>
          <p className="text-sm text-success mt-0.5">Sisa budget: {formatRupiah(remaining_budget)}</p>
        </div>
        {estimated_fps && (
          <div className="text-right">
            <div className="flex items-center gap-2 justify-end mb-1">
              <p className="text-xs text-gray-400">Estimasi FPS</p>
              <DataSourceBadge source={fps_source} />
            </div>
            <p className="text-3xl font-bold text-primary font-mono">{estimated_fps.high}</p>
            <p className="text-xs text-gray-500">@ High Settings</p>
          </div>
        )}
      </div>

      {/* Components */}
      <div className="space-y-2">
        {components.map(({ label, icon, item, extra }) => item && (
          <div key={label} className="flex items-center gap-3 p-3 rounded-xl bg-surface-700/40 hover:bg-surface-700/60 transition-colors">
            <span className="text-lg w-8 text-center">{icon}</span>
            <div className="flex-1 min-w-0">
              <p className="text-xs text-gray-400">{label}</p>
              <p className="text-sm font-medium text-white truncate">{item.name}</p>
              <p className="text-xs text-gray-500">{extra}</p>
            </div>
            <p className="text-sm font-semibold text-accent whitespace-nowrap">{formatRupiah(item.price)}</p>
          </div>
        ))}
      </div>

      {/* All 4 quality settings FPS */}
      {estimated_fps && (
        <div className="p-4 rounded-xl bg-surface-700/40 border border-primary/10">
          <p className="text-xs text-gray-400 mb-3 uppercase tracking-wider">Perkiraan FPS per Setting</p>
          <div className="grid grid-cols-4 gap-2 text-center">
            {[['Low', estimated_fps.low], ['Medium', estimated_fps.medium], ['High', estimated_fps.high], ['Ultra', estimated_fps.ultra]].map(([q, fps]) => (
              <div key={q}>
                <p className="text-lg font-bold font-mono text-primary">{fps}</p>
                <p className="text-xs text-gray-500">{q}</p>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  )
}
