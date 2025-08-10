import { ref, reactive, watch } from 'vue'
import { allowedSortKeys } from '@/utils/productHelpers.js'

export function useProductsLive() {
    const items = ref([])
    const itemsLength = ref(0)
    const loading = ref(false)
    const q = ref('')

    const options = reactive({
        page: 1,
        itemsPerPage: 10,
        sortBy: [{ key: 'updated_at', order: 'desc' }],
    })

    const pageCursors = reactive({ 1: { start: null, end: null } })
    const lastLoadedPage = ref(1)

    const computeVirtualTotal = (page, per, pageInfo, countThisPage) =>
        (page - 1) * per + (pageInfo?.hasNextPage ? per + 1 : countThisPage)

    async function fetchLive(opts, nav = {}) {
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

            if (nav.direction === 'next' && nav.after) {
                params.set('after', nav.after)
            }
            if (nav.direction === 'prev' && nav.before) {
                params.set('before', nav.before)
                params.set('direction', 'prev')
            }

            const res = await fetch(`/api/shopify/products/proxy?${params}`, {
                credentials: 'same-origin',
            })
            if (!res.ok) throw new Error('Load failed')

            const data = await res.json()
            items.value = data.items || []
            const info = data.pageInfo || null
            pageCursors[opts.page] = {
                start: info?.startCursor || null,
                end: info?.endCursor || null,
            }
            itemsLength.value = computeVirtualTotal(
                opts.page,
                opts.itemsPerPage,
                info,
                items.value.length
            )
            lastLoadedPage.value = opts.page
        } catch (e) {
            console.error(e)
            items.value = []
            itemsLength.value = 0
        } finally {
            loading.value = false
        }
    }

    function onUpdateOptions(newOpts) {
        let nav = {}
        if (newOpts.page > lastLoadedPage.value) {
            nav = { direction: 'next', after: pageCursors[lastLoadedPage.value]?.end || null }
        } else if (newOpts.page < lastLoadedPage.value) {
            nav = {
                direction: 'prev',
                before:
                    pageCursors[newOpts.page]?.start ||
                    pageCursors[lastLoadedPage.value]?.start ||
                    null,
            }
        }
        fetchLive(newOpts, nav)
    }

    function reload() {
        Object.keys(pageCursors).forEach((k) => delete pageCursors[k])
        pageCursors[1] = { start: null, end: null }
        options.page = 1
        fetchLive(options, {})
    }

    watch(q, reload)

    return {
        // state
        items,
        itemsLength,
        loading,
        q,
        options,
        // actions
        reload,
        onUpdateOptions,
        // debug helpers (if needed)
        _pageCursors: pageCursors,
        _lastLoadedPage: lastLoadedPage,
    }
}
