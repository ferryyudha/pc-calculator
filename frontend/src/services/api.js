import axios from 'axios'

export const BACKEND_URL = (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1')
  ? `http://${window.location.hostname}:8000`
  : 'https://pc-calculator-production.up.railway.app'

const api = axios.create({
  baseURL: `${BACKEND_URL}/api`,
  headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
  timeout: 15000,
})

// Interceptor: bongkar envelope { success, data } dari setiap response API
api.interceptors.response.use(
  (res) => res,
  (err) => {
    const msg = err.response?.data?.error?.message || err.message || 'Terjadi kesalahan'
    return Promise.reject({ ...err.response?.data?.error, message: msg, status: err.response?.status })
  }
)

export const getComponents = {
  cpus: (params) => api.get('/cpus', { params }),
  gpus: (params) => api.get('/gpus', { params }),
  motherboards: (params) => api.get('/motherboards', { params }),
  rams: (params) => api.get('/rams', { params }),
  ssds: (params) => api.get('/ssds', { params }),
  hdds: () => api.get('/hdds'),
  psus: (params) => api.get('/psus', { params }),
  games: () => api.get('/games'),
}

// Ambil jumlah data dinamis (CPU, GPU, game, benchmark) dari database untuk ditampilkan di halaman utama
export const getStats = () => api.get('/stats')

export const postCompatibility = (data) => api.post('/check-compatibility', data)
export const postFpsEstimate = (data) => api.post('/fps-estimate', data)
export const postFpsEstimateAll = (data) => api.post('/fps-estimate-all', data)
export const postPsuCalculate = (data) => api.post('/psu-calculate', data)
export const postRecommendBuild = (data) => api.post('/recommend-build', data)

// Monitoring PC Endpoints
export const getDevices = () => api.get('/devices')
export const getDeviceDetail = (id) => api.get(`/devices/${id}`)
export const getDeviceLogs = (id, timeframe) => api.get(`/devices/${id}/logs`, { params: { timeframe } })
export const getDevicePower = (id, rate) => api.get(`/devices/${id}/power`, { params: { rate } })
export const getAlerts = () => api.get('/alerts')
export const patchAlert = (id, status) => api.patch(`/alerts/${id}`, { status })

export default api

