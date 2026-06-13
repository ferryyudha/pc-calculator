/**
 * CompatibilityCard — Blueprint §6.1
 * Shows each check individually with pass/fail state
 */
export default function CompatibilityCard({ checks, compatible }) {
  if (!checks) return null

  return (
    <div className="glass p-6 space-y-3 animate-slide-up">
      {/* Header */}
      <div className="flex items-center gap-3 pb-3 border-b border-white/5">
        <div className={`w-10 h-10 rounded-xl flex items-center justify-center ${
          compatible ? 'bg-success/15 text-success' : 'bg-danger/20 text-red-400'
        }`}>
          {compatible
            ? <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M5 13l4 4L19 7"/></svg>
            : <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M6 18L18 6M6 6l12 12"/></svg>
          }
        </div>
        <div>
          <p className={`font-semibold ${compatible ? 'text-success' : 'text-red-400'}`}>
            {compatible ? 'Semua Komponen Kompatibel ✓' : 'Ada Masalah Kompatibilitas'}
          </p>
          <p className="text-xs text-gray-500">{checks.length} pemeriksaan selesai</p>
        </div>
      </div>

      {/* Individual checks */}
      <div className="space-y-2">
        {checks.map((check, i) => (
          <div key={i} className={check.passed ? 'check-pass' : 'check-fail'}>
            <div className={`mt-0.5 flex-shrink-0 w-5 h-5 rounded-full flex items-center justify-center text-xs ${
              check.passed ? 'bg-success/20 text-success' : 'bg-danger/25 text-red-200'
            }`}>
              {check.passed
                ? <svg className="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd"/></svg>
                : <svg className="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fillRule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clipRule="evenodd"/></svg>
              }
            </div>
            <div className="flex-1 min-w-0">
              <p className="text-xs font-medium text-gray-400">{check.name}</p>
              <p className={`text-sm ${check.passed ? 'text-success' : 'text-red-400'}`}>{check.message}</p>
            </div>
          </div>
        ))}
      </div>
    </div>
  )
}
