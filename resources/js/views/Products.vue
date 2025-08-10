<template>
    <div>
        <h2>Products</h2>
        <div style="display:flex; gap:8px; margin-bottom:12px;">
            <button @click="load" :disabled="loading">{{ loading ? 'Loading...' : 'Reload' }}</button>
            <button v-if="mode==='local'" @click="sync" :disabled="syncing">{{ syncing ? 'Syncing...' : 'Sync from Shopify' }}</button>
        </div>
        <p v-if="error" style="color:red;">{{ error }}</p>

        <ul v-if="items.length">
            <li v-for="p in items" :key="p.id" style="padding:8px 0; border-bottom:1px solid #eee;">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <strong>{{ p.title }}</strong>
                        <div v-html="p.description" />
                        <small>Category: {{ p.category || '—' }}</small>
                    </div>
                    <div style="display:flex; gap:8px;">
                        <button @click="remove(p)" v-if="mode==='local'">Delete (local)</button>
                    </div>
                </div>
            </li>
        </ul>

        <p v-else>No Data. Please try again.</p>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'

const items = ref([])
const error = ref('')
const loading = ref(false)
const syncing = ref(false)
const mode = ref('proxy') // TODO: remove hardcode fetch from backend

const load = async () => {
    loading.value = true
    error.value = ''
    try {
        const res = await fetch('/api/shopify/products', { credentials: 'same-origin' })
        if (!res.ok) throw new Error('Failed to load products')
        items.value = await res.json()
    } catch (e) {
        error.value = e.message
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
