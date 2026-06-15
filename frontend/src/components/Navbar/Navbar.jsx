import { NavLink, Link } from 'react-router-dom'
import { useState } from 'react'

const navItems = [
  { to: '/',                 label: 'Beranda' },
  { to: '/compatibility',    label: 'Kompatibilitas' },
  { to: '/fps-calculator',   label: 'Kalkulator FPS' },
  { to: '/psu-calculator',   label: 'Kalkulator PSU' },
  { to: '/build',            label: 'Rekomendasi Rakitan' },
  { to: '/components',       label: 'Komponen' },
]

export default function Navbar() {
  const [open, setOpen] = useState(false)

  return (
    <nav className="fixed top-0 left-0 right-0 z-50 bg-surface-900/80 backdrop-blur-md border-b border-white/5">
      <div className="max-w-7xl mx-auto px-4 sm:px-6">
        <div className="flex items-center justify-between h-16">
          {/* Logo */}
          <Link to="/" className="flex items-center gap-2.5 group">
            <div className="w-8 h-8 rounded-lg bg-gradient-to-br from-primary to-accent flex items-center justify-center shadow-glow-primary group-hover:scale-110 transition-transform duration-200">
              <svg className="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v18m0 0h10a2 2 0 002-2V9M9 21H5a2 2 0 01-2-2V9m0 0h18"/>
              </svg>
            </div>
            <span className="font-bold text-lg tracking-tight gradient-text">PC Calculator</span>
          </Link>

          {/* Desktop nav */}
          <div className="hidden lg:flex items-center gap-1">
            {navItems.map(({ to, label }) => (
              <NavLink key={to} to={to} end={to === '/'}
                className={({ isActive }) =>
                  `px-3 py-1.5 rounded-lg text-sm font-medium transition-all duration-200 ${
                    isActive
                      ? 'text-primary bg-primary/10 border border-primary/20'
                      : 'text-gray-400 hover:text-white hover:bg-white/5'
                  }`
                }>
                {label}
              </NavLink>
            ))}
          </div>

          {/* Mobile hamburger */}
          <button onClick={() => setOpen(!open)} className="lg:hidden btn-ghost p-2">
            <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              {open
                ? <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12"/>
                : <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16"/>
              }
            </svg>
          </button>
        </div>

        {/* Mobile menu */}
        {open && (
          <div className="lg:hidden pb-4 space-y-1 animate-slide-up">
            {navItems.map(({ to, label }) => (
              <NavLink key={to} to={to} end={to === '/'} onClick={() => setOpen(false)}
                className={({ isActive }) =>
                  `block px-4 py-2.5 rounded-xl text-sm font-medium transition-colors ${
                    isActive ? 'text-primary bg-primary/10' : 'text-gray-400 hover:text-white hover:bg-white/5'
                  }`
                }>
                {label}
              </NavLink>
            ))}
          </div>
        )}
      </div>
    </nav>
  )
}
