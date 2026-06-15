import { Outlet } from 'react-router-dom'
import Navbar from '../components/Navbar/Navbar'
import Footer from '../components/Footer/Footer'
import ChatWidget from '../components/ChatWidget/ChatWidget'

export default function MainLayout() {
  return (
    <div className="min-h-screen flex flex-col">
      <Navbar />
      <main className="flex-1 pt-20">
        <Outlet />
      </main>
      <Footer />
      <ChatWidget />
      
      {/* Disclaimer Banner */}
      <div className="w-full bg-[#f5f5f5] border-t border-[#e5e5e5] py-2.5 px-4 text-center">
        <p className="text-xs sm:text-sm text-[#737373] font-normal">
          PC Calculator adalah AI dan bisa keliru. Harap periksa kembali respons.
        </p>
      </div>
    </div>
  )
}
