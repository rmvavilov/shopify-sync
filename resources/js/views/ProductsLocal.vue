<template>
    <div>
        <div class="d-flex align-center mb-4" style="gap: 8px;">
            <v-btn color="primary" @click="reload" :loading="loading" prepend-icon="mdi-refresh">Reload</v-btn>
            <v-spacer/>
            <v-text-field
                v-model="q"
                density="compact"
                variant="outlined"
                prepend-inner-icon="mdi-magnify"
                label="Search (Local DB)"
                clearable
                hide-details
                style="max-width: 320px"
            />
        </div>

        <ProductTable
            :items="items"
            :items-length="itemsLength"
            :loading="loading"
            :options="options"
            @update:options="onUpdateOptions"
            :headers="headersLocal"
        />
    </div>
</template>

<script setup>
import ProductTable from '@/components/ProductTable.vue';
import {useProductsLocal} from '@/composables/useProductsLocal.js';
import {computed, onMounted} from 'vue';

const headersLocal = computed(() => [
    {title: 'Title', key: 'title', sortable: true},
    {title: 'Category', key: 'category', sortable: true},
    {title: 'Price', key: 'priceSort', sortable: false},
])

const {items, itemsLength, loading, q, options, reload, onUpdateOptions} = useProductsLocal()

onMounted(() => reload())
</script>
