import { Link } from 'react-router-dom'

export default function Footer() {
  return (
    <footer className="mt-24 border-t border-white/5 bg-surface-900/50">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 py-10">
        <div className="flex flex-col md:flex-row items-center justify-between gap-4">
          <div className="flex items-center gap-2.5">
            <div className="w-7 h-7 rounded-lg bg-gradient-to-br from-primary to-accent flex items-center justify-center">
              <svg className="w-3.5 h-3.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v18m0 0h10a2 2 0 002-2V9M9 21H5a2 2 0 01-2-2V9m0 0h18"/>
              </svg>
            </div>
            <span className="font-semibold text-sm gradient-text">PC Calculator</span>
          </div>
          <div className="text-center">
            <p className="text-gray-500 text-xs">
              Data komponen diambil berdasarkan website www.enterkomputer.com
            </p>
            <p className="text-[10px] text-gray-600 mt-1 max-w-md mx-auto">
              PC Calculator menggunakan AI untuk estimasi data benchmark & kompatibilitas dan bisa keliru. Harap periksa kembali respons.
            </p>
          </div>
          <div className="flex gap-4 text-xs text-gray-500">
            <Link to="/components" className="hover:text-primary transition-colors">Komponen</Link>
            <Link to="/compatibility" className="hover:text-primary transition-colors">Cek Kompatibilitas</Link>
          </div>
        </div>
      </div>
    </footer>
  )
}
