export function formatRupiah(amount) {
  return 'Rp ' + new Intl.NumberFormat('id-ID').format(amount)
}

export function formatFpsNumber(n) {
  return n?.toLocaleString() ?? '—'
}

export const RESOLUTIONS = ['720p', '1080p', '1440p', '4K']

export const RESOLUTION_LABELS = {
  '720p': 'HD (720p)',
  '1080p': 'Full HD (1080p)',
  '1440p': '2K (1440p)',
  '4K': '4K Ultra HD',
}

export const FPS_QUALITY_COLOR = (fps) => {
  if (fps >= 144) return 'text-success'
  if (fps >= 60)  return 'text-primary'
  if (fps >= 30)  return 'text-warning'
  return 'text-danger'
}

export const FPS_QUALITY_LABEL = (fps) => {
  if (fps >= 144) return 'Excellent'
  if (fps >= 60)  return 'Smooth'
  if (fps >= 30)  return 'Playable'
  return 'Unplayable'
}
