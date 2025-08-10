export const placeholder =
    'https://via.placeholder.com/120x120?text=No+Image'

export const priceMin = (p) => {
    if (p?.priceMin != null) return Number(p.priceMin)
    if (p?.variant?.price != null) return Number(p.variant.price)
    return null
}

export const priceMax = (p) =>
    p?.priceMax != null ? Number(p.priceMax) : null

export const currency = (p) =>
    p?.currency || p?.variant?.currency || undefined

export const money = (amount, curr) => {
    if (amount == null) return '—'
    try {
        return curr
            ? new Intl.NumberFormat(undefined, {style: 'currency', currency: curr})
                .format(Number(amount))
            : new Intl.NumberFormat(undefined, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            }).format(Number(amount))
    } catch {
        return String(amount)
    }
}

export const publicUrl = (p) =>
    p?.onlineStoreUrl || p?.onlineStorePreviewUrl || null

export const statusColor = (s) => {
    switch ((s || '').toUpperCase()) {
        case 'ACTIVE':
            return 'success'
        case 'DRAFT':
            return 'warning'
        case 'ARCHIVED':
            return 'grey'
        default:
            return 'secondary'
    }
}

export const formatDateTime = (v) => {
    if (!v) return '—'
    const d = new Date(v)
    if (Number.isNaN(d.getTime())) return v
    return d.toLocaleString()
}

export const allowedSortKeys = new Set([
    'title',
    'category',
    'created_at',
    'updated_at',
    'id',
])
