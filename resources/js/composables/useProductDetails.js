import {ref, computed} from 'vue'

export function useProductDetails({mode, id64}) {
    const product = ref(null)
    const loading = ref(false)
    const error = ref(null)

    const endpoint = computed(() => {
        return mode === 'live'
            ? `/api/shopify/products/proxy/${id64}`
            : `/api/shopify/products/local/${id64}`
    })

    async function reload() {
        loading.value = true
        error.value = null
        try {
            const res = await fetch(endpoint.value, {credentials: 'same-origin'})
            if (!res.ok) throw new Error(`Failed to load: ${res.status}`)
            const json = await res.json()
            product.value = json.product ?? null
        } catch (e) {
            error.value = e?.message || 'Failed to load product'
            product.value = null
        } finally {
            loading.value = false
        }
    }

    const outOfSync = computed(() => {
        if (mode !== 'local') return false
        const u = product.value?.updatedAt
        const s = product.value?.lastSyncedAt
        if (!u || !s) return false
        return new Date(u).getTime() > new Date(s).getTime()
    })

    return {product, loading, error, reload, outOfSync}
}
