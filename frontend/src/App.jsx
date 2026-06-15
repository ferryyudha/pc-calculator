import { BrowserRouter, Routes, Route } from 'react-router-dom'
import MainLayout from './layouts/MainLayout'
import Home from './pages/Home/Home'
import Compatibility from './pages/Compatibility/Compatibility'
import FPSCalculator from './pages/FPSCalculator/FPSCalculator'
import PSUCalculator from './pages/PSUCalculator/PSUCalculator'
import BuildRecommendation from './pages/BuildRecommendation/BuildRecommendation'
import Components from './pages/Components/Components'

export default function App() {
  return (
    <BrowserRouter>
      <Routes>
        <Route element={<MainLayout />}>
          <Route path="/"                 element={<Home />} />
          <Route path="/compatibility"    element={<Compatibility />} />
          <Route path="/fps-calculator"   element={<FPSCalculator />} />
          <Route path="/psu-calculator"   element={<PSUCalculator />} />
          <Route path="/build"            element={<BuildRecommendation />} />
          <Route path="/components"       element={<Components />} />
        </Route>
      </Routes>
    </BrowserRouter>
  )
}
