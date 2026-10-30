const rupiah = new Intl.NumberFormat('id-ID', {
  style: 'currency',
  currency: 'IDR',
  minimumFractionDigits: 0,
  maximumFractionDigits: 0,
})

const tanggal = new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
const tanggalJam = new Intl.DateTimeFormat('id-ID', {
  day: 'numeric',
  month: 'short',
  year: 'numeric',
  hour: '2-digit',
  minute: '2-digit',
})

export const formatRupiah = (value: number | string | null | undefined): string => rupiah.format(Number(value ?? 0))

/** Number without currency sign, for narrow print columns: 150.000 */
export const formatAngka = (value: number | string | null | undefined): string =>
  new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(Number(value ?? 0))

export const formatDate = (value: string | Date | null | undefined): string =>
  value ? tanggal.format(new Date(value)) : '-'

export const formatDateTime = (value: string | Date | null | undefined): string =>
  value ? tanggalJam.format(new Date(value)) : '-'

/** dd/mm/yy, for the narrow TANGGAL column on the printed card. */
export const formatShortDate = (value: string | null | undefined): string => {
  if (!value) return ''
  const [year, month, day] = value.slice(0, 10).split('-')
  return `${day}/${month}/${year.slice(2)}`
}

/** First error message from a Laravel JSON error response. */
export const errorMessage = (error: any, fallback = 'Terjadi kesalahan. Coba lagi.'): string => {
  const data = error?.response?.data
  if (data?.errors) {
    const first = Object.values(data.errors)[0] as string[] | undefined
    if (first?.length) return first[0]
  }
  return data?.message || fallback
}
