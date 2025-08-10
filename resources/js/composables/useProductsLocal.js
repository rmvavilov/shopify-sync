import {ref, reactive, watch} from 'vue'
import {allowedSortKeys} from '@/utils/productHelpers.js'

export function useProductsLocal() {
    const items = ref([])
    const itemsLength = ref(0)
    const loading = ref(false)
    const q = ref('')

    const options = reactive({
        page: 1,
        itemsPerPage: 10,
        sortBy: [{key: 'updated_at', order: 'desc'}],
    })

    async function fetchLocal(opts) {
        loading.value = true
        try {
            const params = new URLSearchParams()
            params.set('page', String(opts.page))
            params.set('itemsPerPage', String(opts.itemsPerPage))

            const s0 =
                opts.sortBy?.[0] && allowedSortKeys.has(opts.sortBy[0].key)
                    ? opts.sortBy[0]
                    : null
            if (s0) {
                params.append('sortBy[0][key]', s0.key)
                params.append('sortBy[0][order]', s0.order === 'asc' ? 'asc' : 'desc')
            }
            if (q.value) params.set('q', q.value)

            const res = await fetch(`/api/shopify/products/local?${params}`, {
                credentials: 'same-origin',
            })
            if (!res.ok) throw new Error('Load failed')
            const data = await res.json()

            // waiting { mode:'local', items:[], total }
            const a = Array.isArray(data) ? {items: data, total: data.length} : data
            items.value = a.items || []
            itemsLength.value = Number(a.total || 0)
        } catch (e) {
            console.error(e)
            items.value = []
            itemsLength.value = 0
        } finally {
            loading.value = false
        }
    }

    function onUpdateOptions(newOpts) {
        fetchLocal(newOpts)
    }

    function reload() {
        options.page = 1
        fetchLocal(options)
    }

    watch(q, reload)

    return {
        items,
        itemsLength,
        loading,
        q,
        options,
        reload,
        onUpdateOptions,
    }
}
