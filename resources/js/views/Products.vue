<template>
    <div>
        <div class="d-flex align-center mb-4" style="gap: 8px;">
            <v-btn color="primary" @click="load" :loading="loading" prepend-icon="mdi-refresh">Reload</v-btn>
            <v-spacer/>
            <v-text-field
                v-model="q"
                density="compact"
                variant="outlined"
                prepend-inner-icon="mdi-magnify"
                label="Search"
                clearable
                hide-details
                style="max-width: 320px"
            />
        </div>

        <v-data-table-server
            :headers="headers"
            :items="items"
            :items-length="itemsLength"
            v-model:page="options.page"
            v-model:items-per-page="options.itemsPerPage"
            v-model:sort-by="options.sortBy"
            :loading="loading"
            hover
            class="elevation-1"
            @update:options="onUpdateOptions"
        >
            <template #item.image="{ item }">
                <v-avatar size="48" rounded="lg">
                    <v-img :src="item.image || item.imageVariant || placeholder" alt=""/>
                </v-avatar>
            </template>

            <template #item.title="{ item }">
                <div class="d-flex flex-column">
                    <span class="font-weight-medium">{{ item.title }}</span>
                    <span class="text-caption text-medium-emphasis" v-if="item.handle">/{{ item.handle }}</span>
                </div>
            </template>

            <template #item.category="{ item }">
                <v-chip v-if="item.category" size="small" variant="tonal">{{ item.category }}</v-chip>
                <span v-else>—</span>
            </template>

            <template #item.priceSort="{ item }">
                <template v-if="priceMin(item) != null">
                    <v-chip color="primary" variant="flat" size="small">
                        {{ money(priceMin(item), currency(item)) }}
                        <template v-if="priceMax(item) != null && priceMax(item) !== priceMin(item)">
                            — {{ money(priceMax(item), currency(item)) }}
                        </template>
                    </v-chip>
                </template>
                <span v-else>—</span>
            </template>

            <template #item.total="{ item }">
                {{ item.total ?? '—' }}
            </template>

            <template #item.status="{ item }">
                <v-chip
                    size="small"
                    :color="statusColor(item.status)"
                    :variant="item.status ? 'tonal' : 'text'"
                >
                    {{ item.status || '—' }}
                </v-chip>
            </template>

            <template #item.actions="{ item }">
                <v-btn
                    :href="publicUrl(item) || '#'"
                    :disabled="!publicUrl(item)"
                    target="_blank"
                    rel="noopener"
                    variant="text"
                    size="small"
                    prepend-icon="mdi-open-in-new"
                >
                    Open
                </v-btn>
            </template>

            <template #loading>
                <v-skeleton-loader class="mx-2 my-4" type="table"/>
            </template>

            <template #no-data>
                <v-alert type="info" variant="tonal">No products - reload.</v-alert>
            </template>
        </v-data-table-server>
    </div>
</template>

<script setup>
import {ref, reactive, watch, onMounted} from 'vue'

const items = ref([])
const itemsLength = ref(0)
const loading = ref(false)
const q = ref('')
const mode = ref('proxy') // TODO: remove hardcode fetch from backend
const placeholder = 'https://via.placeholder.com/120x120?text=No+Image'

const headers = [
    {title: 'Image', key: 'image', sortable: false},
    {title: 'Title', key: 'title', sortable: true},
    {title: 'Category', key: 'category', sortable: true},
    {title: 'Price', key: 'priceSort', sortable: false},
    {title: 'Total', key: 'total', sortable: false},
    {title: 'Status', key: 'status', sortable: false},
    {title: 'Actions', key: 'actions', sortable: false},
]

const options = reactive({
    page: 1,
    itemsPerPage: 10,
    sortBy: [{key: 'updated_at', order: 'desc'}],
})

const pageCursors = reactive({1: {start: null, end: null}})
const lastLoadedPage = ref(1)

const priceMin = (p) => {
    if (p?.priceMin != null) return Number(p.priceMin)
    if (p?.variant?.price != null) return Number(p.variant.price)
    return null
}
const priceMax = (p) => (p?.priceMax != null ? Number(p.priceMax) : null)
const currency = (p) => p?.currency || p?.variant?.currency || undefined
const money = (amount, curr) => {
    if (amount == null) return '—'
    try {
        return curr
            ? new Intl.NumberFormat(undefined, {style: 'currency', currency: curr}).format(Number(amount))
            : new Intl.NumberFormat(undefined, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(Number(amount))
    } catch {
        return String(amount)
    }
}
const publicUrl = (p) => p?.onlineStoreUrl || p?.onlineStorePreviewUrl || null
const statusColor = (s) => {
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

function computeVirtualTotal(page, perPage, pageInfo, countThisPage) {
    return (page - 1) * perPage + (pageInfo?.hasNextPage ? perPage + 1 : countThisPage)
}

async function fetchServerData(opts, nav = {}) {
    loading.value = true
    try {
        const params = new URLSearchParams()
        params.set('page', String(opts.page))
        params.set('itemsPerPage', String(opts.itemsPerPage))

        const allowedSortKeys = new Set(['title', 'category', 'created_at', 'updated_at', 'id'])
        const sort0 = (opts.sortBy?.[0] && allowedSortKeys.has(opts.sortBy[0].key)) ? opts.sortBy[0] : null
        if (sort0) {
            params.append('sortBy[0][key]', sort0.key)
            params.append('sortBy[0][order]', sort0.order === 'asc' ? 'asc' : 'desc')
        }

        if (q.value) params.set('q', q.value)

        if (mode.value === 'proxy') {
            if (nav.direction === 'next' && nav.after) {
                params.set('after', nav.after)
            }
            if (nav.direction === 'prev' && nav.before) {
                params.set('before', nav.before)
                params.set('direction', 'prev')
            }
        }

        const res = await fetch(`/api/shopify/products?${params.toString()}`, {credentials: 'same-origin'})
        if (!res.ok) throw new Error('Failed to load products')
        const data = await res.json()

        if (Array.isArray(data)) {
            mode.value = 'legacy'
            items.value = data
            itemsLength.value = data.length
            lastLoadedPage.value = opts.page
            return
        }

        mode.value = data.mode || 'proxy'
        items.value = data.items || []

        if (mode.value === 'local') {
            itemsLength.value = Number(data.total || 0)
        } else {
            const info = data.pageInfo || null

            pageCursors[opts.page] = {
                start: info?.startCursor || null,
                end: info?.endCursor || null,
            }
            itemsLength.value = computeVirtualTotal(opts.page, opts.itemsPerPage, info, items.value.length)
        }

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
    if (mode.value === 'proxy') {
        if (newOpts.page > lastLoadedPage.value) {
            nav = {direction: 'next', after: pageCursors[lastLoadedPage.value]?.end || null}
        } else if (newOpts.page < lastLoadedPage.value) {
            nav = {
                direction: 'prev',
                before: pageCursors[newOpts.page]?.start || pageCursors[lastLoadedPage.value]?.start || null
            }
        }
    }
    fetchServerData(newOpts, nav)
}

function reload() {
    Object.keys(pageCursors).forEach(k => delete pageCursors[k])
    pageCursors[1] = {start: null, end: null}
    options.page = 1
    fetchServerData(options, {})
}

const load = () => reload()

watch(q, () => {
    reload()
})

onMounted(() => {
    fetchServerData(options, {})
})
</script>
