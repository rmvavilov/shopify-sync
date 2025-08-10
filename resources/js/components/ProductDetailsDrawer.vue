<template>
    <v-navigation-drawer
        v-model="model"
        temporary
        location="right"
        width="560"
        class="pa-0"
    >
        <div class="d-flex align-center justify-space-between px-4 py-3">
            <div class="d-flex align-center" style="gap:8px;">
                <span class="text-h6">{{ product?.title || 'Product' }}</span>
                <v-chip
                    v-if="product?.status"
                    size="small"
                    :color="statusColor(product.status)"
                    variant="tonal"
                    label
                >{{ product.status }}
                </v-chip>
                <v-chip
                    v-if="mode === 'live'"
                    size="small"
                    color="red"
                    variant="tonal"
                    label
                >LIVE
                </v-chip>
                <v-chip
                    v-else
                    size="small"
                    color="blue"
                    variant="tonal"
                    label
                >LOCAL
                </v-chip>
            </div>
            <div class="d-flex align-center" style="gap:8px;">
                <v-btn size="small" variant="text" icon="mdi-refresh" :loading="loading" @click="$emit('refresh')"/>
                <v-btn size="small" variant="text" icon="mdi-close" @click="close"/>
            </div>
        </div>

        <v-divider/>

        <div class="px-4 py-3">
            <v-alert v-if="error" type="error" variant="tonal" class="mb-3">
                {{ error }}
            </v-alert>

            <v-skeleton-loader
                v-if="loading && !product"
                type="image, article, actions"
                class="mb-4"
            />

            <div v-else>
                <!-- Media -->
                <div class="d-flex align-center mb-4" style="gap:12px;">
                    <v-avatar size="96" rounded="lg">
                        <v-img :src="product?.image || product?.imageVariant || placeholder"/>
                    </v-avatar>
                    <div class="d-flex flex-column text-caption">
                        <div>
                            <strong>Shop Updated:</strong>
                            {{ formatDateTime(product?.updatedAt) }}
                        </div>
                        <div v-if="mode==='local'">
                            <strong>Synced (Local):</strong>
                            {{ formatDateTime(product?.lastSyncedAt) }}
                            <v-chip
                                v-if="outOfSync"
                                size="x-small"
                                color="warning"
                                variant="tonal"
                                class="ml-2"
                                label
                            >Out of sync
                            </v-chip>
                        </div>
                    </div>
                </div>

                <!-- Meta -->
                <v-table density="compact" class="mb-3">
                    <tbody>
                    <tr>
                        <td class="text-medium-emphasis">Handle</td>
                        <td>{{ product?.handle || '—' }}</td>
                    </tr>
                    <tr>
                        <td class="text-medium-emphasis">Category</td>
                        <td>{{ product?.category || '—' }}</td>
                    </tr>
                    <tr>
                        <td class="text-medium-emphasis">Inventory</td>
                        <td>{{ product?.total ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td class="text-medium-emphasis">Price</td>
                        <td>
                <span v-if="priceMin(product) != null">
                  {{ money(priceMin(product), currency(product)) }}
                  <template v-if="priceMax(product) != null && priceMax(product) !== priceMin(product)">
                    — {{ money(priceMax(product), currency(product)) }}
                  </template>
                </span>
                            <span v-else>—</span>
                        </td>
                    </tr>
                    <tr>
                        <td class="text-medium-emphasis">Public</td>
                        <td>
                            <v-btn
                                :href="publicUrl(product) || undefined"
                                :disabled="!publicUrl(product)"
                                target="_blank"
                                rel="noopener"
                                size="small"
                                variant="text"
                                prepend-icon="mdi-open-in-new"
                            >Open
                            </v-btn>
                        </td>
                    </tr>
                    </tbody>
                </v-table>

                <!-- Description -->
                <div v-if="product?.description" class="prose mb-4" v-html="product.description"></div>

                <!-- Actions -->
                <div class="d-flex flex-wrap" style="gap:8px;">
                    <v-btn
                        v-if="mode==='local'"
                        size="small"
                        color="primary"
                        variant="flat"
                        prepend-icon="mdi-sync"
                        @click="$emit('sync')"
                    >Sync now
                    </v-btn>

                    <v-btn
                        v-if="mode==='live'"
                        size="small"
                        color="secondary"
                        variant="tonal"
                        prepend-icon="mdi-pencil"
                        @click="$emit('edit')"
                    >Edit
                    </v-btn>

                    <v-btn
                        v-if="mode==='live'"
                        size="small"
                        color="error"
                        variant="tonal"
                        prepend-icon="mdi-delete"
                        @click="$emit('delete')"
                    >Delete
                    </v-btn>
                </div>
            </div>
        </div>
    </v-navigation-drawer>
</template>

<script setup>
import {computed, toRefs} from 'vue'
import {money, priceMin, priceMax, currency, publicUrl, statusColor, formatDateTime} from '@/utils/productHelpers.js'

const props = defineProps({
    modelValue: {type: Boolean, default: false},
    mode: {type: String, default: 'live'}, // 'live' | 'local'
    product: {type: Object, default: null},
    loading: {type: Boolean, default: false},
    error: {type: String, default: null},
    outOfSync: {type: Boolean, default: false},
})
const emit = defineEmits(['update:modelValue', 'refresh', 'edit', 'delete', 'sync'])

const model = computed({
    get: () => props.modelValue,
    set: (v) => emit('update:modelValue', v),
})

function close() {
    emit('update:modelValue', false)
}

const placeholder = 'https://via.placeholder.com/160x160?text=No+Image'
</script>

<style scoped>
.prose :deep(img) {
    max-width: 100%;
    height: auto;
}
</style>
