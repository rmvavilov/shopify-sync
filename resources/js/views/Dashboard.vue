<template>
    <div class="d-flex align-center" style="gap:8px;">
        <v-btn
            color="primary"
            :loading="syncing"
            prepend-icon="mdi-cloud-sync"
            @click="syncAll"
        >
            Sync all (Shopify)
        </v-btn>

        <v-chip v-if="lastRequestedAt" size="small" variant="tonal">
            Last requested: {{ fmt(lastRequestedAt) }}
        </v-chip>
    </div>
</template>

<script setup>
import {ref, onMounted} from 'vue'

const syncing = ref(false)
const lastRequestedAt = ref(null)

function fmt(v) {
    if (!v) return '—'
    const d = new Date(v)
    return Number.isNaN(d.getTime()) ? v : d.toLocaleString()
}

async function syncAll() {
    syncing.value = true
    try {
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        const res = await fetch('/api/shopify/sync-all', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                ...(token ? {'X-CSRF-TOKEN': token} : {}),
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({first: 100})
        })
        if (!res.ok) throw new Error(`Failed: ${res.status}`)
        const json = await res.json()
        lastRequestedAt.value = new Date().toISOString()
        // TODO: add Sync started/Sync Finished message
    } catch (e) {
        console.error(e)
        alert(e.message || 'Failed to start sync')
    } finally {
        syncing.value = false
    }
}

onMounted(() => {
    //TODO: call last_requested_at from cache
    //TODO: refetching result of syncing
})
</script>
