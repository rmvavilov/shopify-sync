<template>
    <ProductDetailsDrawer
        v-model="open"
        mode="live"
        :product="product"
        :loading="loading"
        :error="error"
        :out-of-sync="false"
        @refresh="reload"
        @edit="editOpen = true"
        @delete="onDelete"
    />

    <ProductEditDialog
        v-model="editOpen"
        :product="product"
        @saved="onSave"
    />
</template>

<script setup>
import {ref, onMounted, watch} from 'vue'
import {useRoute, useRouter} from 'vue-router'
import {useProductDetails} from '@/composables/useProductDetails.js'
import ProductDetailsDrawer from '@/components/ProductDetailsDrawer.vue'
import ProductEditDialog from '@/components/ProductEditDialog.vue'

const route = useRoute()
const router = useRouter()
const id64 = route.params.id

const {product, loading, error, reload} = useProductDetails({mode: 'live', id64})

const open = ref(true)
watch(open, (v) => {
    if (!v) router.back()
})
onMounted(reload)

const editOpen = ref(false)

async function onSave(payload) {
    try {
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        const res = await fetch(`/api/shopify/products/live/${id64}/update`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(token ? {'X-CSRF-TOKEN': token} : {}),
            },
            body: JSON.stringify(payload),
        })
        if (res.status === 409) {
            const json = await res.json().catch(() => ({}))
            alert('The product has changed on Shopify. Please reload details.')
            return false
        }
        if (!res.ok) {
            const txt = await res.text()
            throw new Error(txt || `Save failed: ${res.status}`)
        }

        await reload()
        editOpen.value = false
        return false
    } catch (e) {
        console.error(e)
        alert(e.message || 'Save failed')
        return false
    }
}

async function onDelete() {
    if (!confirm('Delete this product on Shopify? This cannot be undone.')) return
    try {
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        const res = await fetch(`/api/shopify/products/live/${id64}`, {
            method: 'DELETE',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                ...(token ? {'X-CSRF-TOKEN': token} : {}),
            },
        })
        if (!res.ok) throw new Error(`Delete failed: ${res.status}`)

        open.value = false
    } catch (e) {
        console.error(e)
        alert(e.message || 'Delete failed')
    }
}
</script>
