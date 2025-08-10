<template>
    <ProductDetailsDrawer
        v-model="open"
        mode="local"
        :product="product"
        :loading="loading"
        :error="error"
        :out-of-sync="outOfSync"
        @refresh="reload"
        @sync="onSync"
    />
</template>

<script setup>
import {ref, onMounted, watch} from 'vue'
import {useRoute, useRouter} from 'vue-router'
import {useProductDetails} from '@/composables/useProductDetails.js'
import ProductDetailsDrawer from '@/components/ProductDetailsDrawer.vue'

const route = useRoute()
const router = useRouter()
const id64 = route.params.id

const {product, loading, error, reload, outOfSync} = useProductDetails({mode: 'local', id64})

const open = ref(true)
watch(open, (v) => {
    if (!v) router.back()
})

onMounted(reload)

async function onSync() {
    try {
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        const res = await fetch(`/api/shopify/products/sync/${id64}`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                ...(token ? { 'X-CSRF-TOKEN': token } : {}),
            },
        })
        if (!res.ok) throw new Error(`Sync failed: ${res.status}`)

        const json = await res.json()
        if (json?.product) {
            await reload()
        }
    } catch (e) {
        console.error(e)
    }
}
</script>
