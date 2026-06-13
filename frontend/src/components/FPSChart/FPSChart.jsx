import { BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer } from 'recharts'
import DataSourceBadge from '../DataSourceBadge/DataSourceBadge'

const CustomTooltip = ({ active, payload, label }) => {
  if (!active || !payload?.length) return null
  return (
    <div className="glass p-3 text-sm">
      <p className="font-semibold text-white mb-1">{label} Settings</p>
      {payload.map((p) => (
        <p key={p.name} style={{ color: p.fill }}>{p.name}: <span className="font-mono font-bold">{p.value} FPS</span></p>
      ))}
    </div>
  )
}

export default function FPSChart({ fps, source, note, gameName, resolution }) {
  if (!fps) return null

  const data = [
    { setting: 'Low',    FPS: fps.low },
    { setting: 'Medium', FPS: fps.medium },
    { setting: 'High',   FPS: fps.high },
    { setting: 'Ultra',  FPS: fps.ultra },
  ]

  return (
    <div className="glass p-6 space-y-4 animate-slide-up">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <p className="text-xs text-gray-400 uppercase tracking-wider">Hasil FPS</p>
          <p className="font-bold text-white">{gameName} @ {resolution}</p>
        </div>
        <DataSourceBadge source={source} note={note} />
      </div>

      {note && (
        <div className="flex items-start gap-2 p-2.5 rounded-lg bg-warning/8 border border-warning/15 text-warning text-xs">
          <svg className="w-3.5 h-3.5 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
          {note}
        </div>
      )}

      {/* FPS numbers */}
      <div className="grid grid-cols-4 gap-3">
        {data.map(({ setting, FPS }) => (
          <div key={setting} className="text-center p-3 rounded-xl bg-surface-700/50">
            <p className={`text-2xl font-bold font-mono ${
              FPS >= 144 ? 'text-success' : FPS >= 60 ? 'text-primary' : FPS >= 30 ? 'text-warning' : 'text-danger'
            }`}>{FPS}</p>
            <p className="text-xs text-gray-500 mt-0.5">{setting}</p>
          </div>
        ))}
      </div>

      {/* Bar chart */}
      <div className="h-48">
        <ResponsiveContainer width="100%" height="100%">
          <BarChart data={data} margin={{ top: 4, right: 4, left: -20, bottom: 4 }}>
            <CartesianGrid strokeDasharray="3 3" stroke="rgba(255,255,255,0.05)" />
            <XAxis dataKey="setting" tick={{ fill: '#9CA3AF', fontSize: 11 }} axisLine={false} tickLine={false}/>
            <YAxis tick={{ fill: '#9CA3AF', fontSize: 11 }} axisLine={false} tickLine={false}/>
            <Tooltip content={<CustomTooltip />} cursor={{ fill: 'rgba(255,255,255,0.03)' }}/>
            <Bar dataKey="FPS" fill="url(#fpsGrad)" radius={[6,6,0,0]}/>
            <defs>
              <linearGradient id="fpsGrad" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stopColor="#00D4FF" stopOpacity={0.9}/>
                <stop offset="100%" stopColor="#7C3AED" stopOpacity={0.7}/>
              </linearGradient>
            </defs>
          </BarChart>
        </ResponsiveContainer>
      </div>
    </div>
  )
}
