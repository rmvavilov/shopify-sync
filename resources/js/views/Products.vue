<template>
    <div>
        <h2>Products</h2>
        <button @click="load">Load Products</button>
        <p v-if="error" style="color:red;">{{ error }}</p>
        <ul v-if="items.length">
            <li v-for="p in items" :key="p.id">
                <strong>{{ p.title }}</strong>
                <div v-html="p.description"></div>
                <small>Category: {{ p.category || '—' }}</small>
            </li>
        </ul>
    </div>
</template>

<script setup>
import { ref } from 'vue'

const items = ref([])
const error = ref('')

const load = async () => {
    error.value = ''
    try {
        const res = await fetch('/api/shopify/products', { credentials: 'same-origin' })
        if (!res.ok) throw new Error('Failed to load')
        items.value = await res.json()
    } catch (e) {
        error.value = e.message
    }
}
</script>
