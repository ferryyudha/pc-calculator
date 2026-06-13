/**
 * DataSourceBadge — Blueprint §6.1
 * Shows "Data Terukur" or "Estimasi" badge next to FPS results
 */
export default function DataSourceBadge({ source, note }) {
  if (!source) return null

  if (source === 'smart_estimate') {
    return (
      <span className="badge-interpolated" title={note || 'Kalkulasi cerdas berdasarkan spesifikasi hardware'}>
        <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0012 18.75c-.718 0-1.402-.224-1.975-.608l-.548-.547z"/>
        </svg>
        Estimasi Cerdas
      </span>
    )
  }

  if (source === 'measured') {
    return (
      <span className="badge-measured" title="Data dari benchmark nyata">
        <svg className="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
          <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd"/>
        </svg>
        Data Terukur
      </span>
    )
  }

  return (
    <span className="badge-interpolated" title={note || 'FPS adalah estimasi berdasarkan hardware serupa'}>
      <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
      </svg>
      Estimasi
    </span>
  )
}
