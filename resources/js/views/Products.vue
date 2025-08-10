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

        <v-data-table
            :headers="headers"
            :items="filteredItems"
            :loading="loading"
            :items-per-page="10"
            class="elevation-1"
            hover
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
        </v-data-table>
    </div>
</template>

<script setup>
import {ref, computed, onMounted} from 'vue'

const items = ref([])
const loading = ref(false)
const q = ref('')
const mode = ref('proxy') // TODO: remove hardcode fetch from backend
const placeholder = 'https://via.placeholder.com/120x120?text=No+Image'

const headers = [
    {title: 'Image', key: 'image', sortable: false},
    {title: 'Title', key: 'title'},
    {title: 'Category', key: 'category'},
    {title: 'Price', key: 'priceSort'},
    {title: 'Total', key: 'total'},
    {title: 'Status', key: 'status'},
    {title: 'Actions', key: 'actions', sortable: false},
]

const filteredItems = computed(() => {
    const needle = q.value.trim().toLowerCase()
    if (!needle) return withComputed(items.value)
    return withComputed(items.value).filter(p =>
        (p.title || '').toLowerCase().includes(needle) ||
        (p.category || '').toLowerCase().includes(needle) ||
        (p.handle || '').toLowerCase().includes(needle) ||
        (p.status || '').toLowerCase().includes(needle)
    )
})

function withComputed(arr) {
    return arr.map(p => ({
        ...p,
        // priceSort: min price if exists; else variant.price; else null
        priceSort: priceMin(p) ?? null,
    }))
}

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

const load = async () => {
    loading.value = true
    try {
        const res = await fetch('/api/shopify/products', {credentials: 'same-origin'})
        if (!res.ok) throw new Error('Failed to load products')
        const data = await res.json()
        const a = Array.isArray(data) ? data : []
        console.log(a)
        items.value = a
    } catch (e) {
        console.error(e)
        items.value = []
    } finally {
        loading.value = false
    }
}

const sync = async () => {
    //TODO: add sync shopify command trigger
    syncing.value = true
    try {
        await load()
    } finally {
        syncing.value = false
    }
}

const remove = async (p) => {
    //TODO: add delete product
}

onMounted(load)
</script>
