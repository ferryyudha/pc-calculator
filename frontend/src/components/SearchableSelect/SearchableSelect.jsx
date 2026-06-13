import { useState, useEffect, useRef, useMemo } from 'react'

export default function SearchableSelect({
  label,
  id,
  value,
  onChange,
  options = [],
  placeholder,
  loading,
  formatLabel,
  extraOption
}) {
  const [isOpen, setIsOpen] = useState(false)
  const [search, setSearch] = useState('')
  const containerRef = useRef(null)

  useEffect(() => {
    function handleClickOutside(event) {
      if (containerRef.current && !containerRef.current.contains(event.target)) {
        setIsOpen(false)
      }
    }
    document.addEventListener('mousedown', handleClickOutside)
    return () => document.removeEventListener('mousedown', handleClickOutside)
  }, [])

  useEffect(() => {
    if (!isOpen) {
      setSearch('')
    }
  }, [isOpen])

  const allOptions = useMemo(() => {
    const list = [...options]
    if (extraOption) {
      list.unshift(extraOption)
    }
    return list
  }, [options, extraOption])

  const selectedOption = allOptions.find((o) => String(o.id) === String(value))

  // Default formatter for compatibility with general component schemas
  const defaultFormatLabel = (o) => {
    if (o.isSpecial) return o.name
    return `${o.name}${o.socket ? ` · ${o.socket}` : ''}${o.ram_type ? ` · ${o.ram_type}` : ''}${o.ddr_version ? ` · ${o.ddr_version}` : ''}${o.watt ? ` · ${o.watt}W` : ''}`
  }

  const getOptionText = (o) => {
    if (o.isSpecial) return o.name
    return formatLabel ? formatLabel(o) : defaultFormatLabel(o)
  }

  const filteredOptions = allOptions.filter((o) => {
    const text = getOptionText(o)
    return text.toLowerCase().includes(search.toLowerCase())
  })

  return (
    <div className={`relative ${isOpen ? 'z-50' : 'z-10'}`} ref={containerRef}>
      <label className="block text-sm font-medium text-gray-300 mb-1.5">{label}</label>
      
      {/* Trigger Button */}
      <button
        id={id}
        type="button"
        disabled={loading}
        onClick={() => setIsOpen(!isOpen)}
        className={`w-full px-4 py-3 rounded-xl bg-surface-700/80 border border-white/8 text-sm flex justify-between items-center text-left transition-all duration-300 focus:outline-none focus:border-primary focus:ring-4 focus:ring-primary/25 ${
          selectedOption ? 'text-white' : 'text-gray-400'
        }`}
      >
        <span className="truncate">
          {loading ? 'Memuat...' : selectedOption ? getOptionText(selectedOption) : placeholder}
        </span>
        <svg
          className={`w-4 h-4 text-gray-500 transition-transform duration-250 ml-2 ${isOpen ? 'transform rotate-180' : ''}`}
          fill="none"
          viewBox="0 0 24 24"
          stroke="currentColor"
          strokeWidth="2.5"
        >
          <path strokeLinecap="round" strokeLinejoin="round" d="M19 9l-7 7-7-7" />
        </svg>
      </button>

      {/* Dropdown Panel */}
      {isOpen && (
        <div className="absolute z-50 w-full mt-2 bg-surface-800 border border-white/8 rounded-xl shadow-glass overflow-hidden animate-fade-in">
          {/* Search Box */}
          <div className="p-2 border-b border-white/5 bg-surface-900 flex items-center gap-2">
            <svg className="w-4 h-4 text-gray-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <input
              type="text"
              placeholder={`Cari ${label}...`}
              className="w-full bg-transparent text-sm placeholder-gray-500 text-white focus:outline-none py-1"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              autoFocus
            />
            {search && (
              <button
                type="button"
                onClick={() => setSearch('')}
                className="text-gray-500 hover:text-white"
              >
                <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={3}>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            )}
          </div>

          {/* Options List */}
          <div className="max-h-60 overflow-y-auto divide-y divide-white/5 bg-surface-800">
            {filteredOptions.length > 0 ? (
              filteredOptions.map((o) => (
                <button
                  key={o.id}
                  type="button"
                  onClick={() => {
                    onChange(o.id)
                    setIsOpen(false)
                  }}
                  className={`w-full px-4 py-2.5 text-xs text-left transition-colors block ${
                    o.isSpecial 
                      ? 'text-yellow-400 font-semibold hover:bg-yellow-400/10'
                      : String(o.id) === String(value)
                      ? 'bg-primary/5 text-primary font-bold'
                      : 'text-gray-300 hover:bg-primary/10 hover:text-white'
                  }`}
                >
                  {getOptionText(o)}
                </button>
              ))
            ) : (
              <div className="px-4 py-3 text-xs text-gray-500 text-center">Tidak ditemukan komponen cocok</div>
            )}
          </div>
        </div>
      )}
    </div>
  )
}
