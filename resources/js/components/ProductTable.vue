<template>
    <v-data-table-server
        :headers="headersToUse"
        :items="items"
        :items-length="itemsLength"
        v-model:page="options.page"
        v-model:items-per-page="options.itemsPerPage"
        v-model:sort-by="options.sortBy"
        :loading="loading"
        hover
        class="elevation-1"
        @update:options="$emit('update:options', $event)"
    >
        <!-- Image -->
        <template #item.image="{ item }">
            <v-avatar size="48" rounded="lg">
                <v-img :src="item.image || item.imageVariant || placeholder" alt=""/>
            </v-avatar>
        </template>

        <!-- Title + handle -->
        <template #item.title="{ item }">
            <div class="d-flex flex-column">
                <span class="font-weight-medium">{{ item.title }}</span>
                <span class="text-caption text-medium-emphasis" v-if="item.handle"
                >/{{ item.handle }}</span
                >
            </div>
        </template>

        <!-- Category -->
        <template #item.category="{ item }">
            <v-chip v-if="item.category" size="small" variant="tonal">
                {{ item.category }}
            </v-chip>
            <span v-else>—</span>
        </template>

        <!-- Price -->
        <template #item.priceSort="{ item }">
            <template v-if="priceMin(item) != null">
                <v-chip color="primary" variant="flat" size="small">
                    {{ money(priceMin(item), currency(item)) }}
                    <template
                        v-if="
              priceMax(item) != null && priceMax(item) !== priceMin(item)
            "
                    >
                        — {{ money(priceMax(item), currency(item)) }}
                    </template>
                </v-chip>
            </template>
            <span v-else>—</span>
        </template>

        <!-- Total -->
        <template #item.total="{ item }">
            {{ item.total ?? '—' }}
        </template>

        <!-- Status -->
        <template #item.status="{ item }">
            <v-chip
                size="small"
                :color="statusColor(item.status)"
                :variant="item.status ? 'tonal' : 'text'"
            >
                {{ item.status || '—' }}
            </v-chip>
        </template>

        <!-- Actions -->
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
            <v-alert type="info" variant="tonal"
            >No products - change filter or reload.
            </v-alert
            >
        </template>
    </v-data-table-server>
</template>

<script setup>
import {computed} from 'vue';
import {
    placeholder,
    priceMin,
    priceMax,
    currency,
    money,
    publicUrl,
    statusColor,
} from '@/utils/productHelpers.js'

const props = defineProps({
    headers: {type: Array, default: () => null},
    items: {type: Array, default: () => []},
    itemsLength: {type: Number, default: 0},
    loading: {type: Boolean, default: false},
    options: {
        type: Object,
        required: true, // { page, itemsPerPage, sortBy }
    },
})

defineEmits(['update:options'])

const defaultHeaders = [
    {title: 'Image', key: 'image', sortable: false},
    {title: 'Title', key: 'title', sortable: true},
    {title: 'Category', key: 'category', sortable: true},
    {title: 'Price', key: 'priceSort', sortable: false},
    {title: 'Total', key: 'total', sortable: false},
    {title: 'Status', key: 'status', sortable: false},
    {title: 'Actions', key: 'actions', sortable: false},
]

const headersToUse = computed(() => props.headers ?? defaultHeaders)
</script>
