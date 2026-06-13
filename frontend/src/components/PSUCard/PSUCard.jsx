import { formatRupiah } from '../../utils/helpers'

/**
 * PSUCard — Blueprint §6.1
 * Shows detailed power breakdown per component
 */
export default function PSUCard({ data }) {
  if (!data) return null
  const { breakdown, recommended_watt, recommended_psu, warning } = data

  const breakdownItems = [
    { label: 'CPU', value: breakdown.cpu, unit: 'W' },
    { label: 'GPU', value: breakdown.gpu, unit: 'W' },
    { label: 'Storage', value: breakdown.storage, unit: 'W' },
    { label: 'Case Fans', value: breakdown.fans, unit: 'W' },
    { label: 'Motherboard + RAM', value: breakdown.overhead, unit: 'W' },
  ]

  const totalPercent = (val) => Math.round((val / breakdown.total_draw) * 100)

  return (
    <div className="glass p-6 space-y-5 animate-slide-up">
      <div className="flex items-center gap-3">
        <div className="w-10 h-10 rounded-xl bg-accent/15 text-accent flex items-center justify-center">
          <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 10V3L4 14h7v7l9-11h-7z"/>
          </svg>
        </div>
        <div>
          <p className="font-bold text-lg text-white">{recommended_watt}W Direkomendasikan</p>
          <p className="text-xs text-gray-400">Total konsumsi: {breakdown.total_draw.toFixed(0)}W · Dengan headroom 30%</p>
        </div>
      </div>

      {warning && (
        <div className="flex items-center gap-2 p-3 rounded-xl bg-warning/10 border border-warning/20 text-warning text-sm">
          <svg className="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
          </svg>
          {warning}
        </div>
      )}

      {/* Breakdown bars */}
      <div className="space-y-2.5">
        <p className="text-xs font-medium text-gray-400 uppercase tracking-wider">Rincian Konsumsi</p>
        {breakdownItems.map(({ label, value, unit }) => (
          <div key={label} className="space-y-1">
            <div className="flex justify-between text-sm">
              <span className="text-gray-400">{label}</span>
              <span className="text-white font-medium">{value}{unit}</span>
            </div>
            <div className="h-1.5 bg-surface-600 rounded-full overflow-hidden">
              <div
                className="h-full bg-gradient-to-r from-primary to-accent rounded-full transition-all duration-500"
                style={{ width: `${totalPercent(value)}%` }}
              />
            </div>
          </div>
        ))}
        <div className="flex justify-between text-sm pt-2 border-t border-white/5">
          <span className="font-semibold text-white">Total</span>
          <span className="font-bold text-primary">{breakdown.total_draw.toFixed(0)}W</span>
        </div>
      </div>

      {/* Recommended PSU */}
      {recommended_psu && (
        <div className="p-4 rounded-xl bg-primary/8 border border-primary/20">
          <p className="text-xs text-gray-400 mb-1">PSU yang Disarankan</p>
          <p className="font-semibold text-white">{recommended_psu.name}</p>
          <div className="flex items-center gap-4 mt-2 text-sm">
            <span className="text-primary font-mono">{recommended_psu.watt}W</span>
            <span className="text-gray-400">{recommended_psu.certification}</span>
            <span className="text-accent font-semibold ml-auto">{formatRupiah(recommended_psu.price)}</span>
          </div>
        </div>
      )}
    </div>
  )
}
