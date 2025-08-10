<template>
    <v-dialog v-model="model" max-width="720">
        <v-card>
            <v-card-title class="d-flex align-center justify-space-between">
                <span>Edit product</span>
                <v-btn icon="mdi-close" variant="text" @click="close"/>
            </v-card-title>
            <v-divider/>
            <v-card-text>
                <v-alert v-if="error" type="error" variant="tonal" class="mb-4">{{ error }}</v-alert>

                <v-text-field v-model="form.title" label="Title"/>
                <v-text-field v-model="form.handle" label="Handle"/>
                <v-select
                    v-model="form.status"
                    label="Status"
                    :items="['ACTIVE','DRAFT','ARCHIVED']"
                    clearable
                />
                <v-text-field v-model="form.productType" label="Category (productType)"/>
                <v-textarea v-model="form.descriptionHtml" label="Description (HTML)"
                            rows="6" auto-grow/>

                <v-alert
                    v-if="conflict"
                    type="warning"
                    variant="tonal"
                    class="mt-4"
                >
                    This product was changed on Shopify since you opened it. Please reload details and try again.
                </v-alert>
            </v-card-text>
            <v-card-actions class="justify-end">
                <v-btn variant="text" @click="close">Cancel</v-btn>
                <v-btn :loading="saving" color="primary" @click="save">Save</v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script setup>
import {computed, reactive, ref, watch} from 'vue'

const props = defineProps({
    modelValue: {type: Boolean, default: false},
    product: {type: Object, default: null},
})
const emit = defineEmits(['update:modelValue', 'saved'])

const model = computed({
    get: () => props.modelValue,
    set: (v) => emit('update:modelValue', v),
})

const saving = ref(false)
const error = ref(null)
const conflict = ref(false)

const form = reactive({
    title: '',
    handle: '',
    status: '',
    productType: '',
    descriptionHtml: '',
    expectedUpdatedAt: '',
})

watch(() => props.product, (p) => {
    if (!p) return
    form.title = p.title || ''
    form.handle = p.handle || ''
    form.status = p.status || ''
    form.productType = p.category || ''
    form.descriptionHtml = p.description || ''
    form.expectedUpdatedAt = p.updatedAt || ''
}, {immediate: true})

function close() {
    emit('update:modelValue', false)
}

async function save() {
    if (!props.product?.id) return
    saving.value = true
    error.value = null
    conflict.value = false
    try {
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        const res = await emit('saved', {...form})
        if (res === false) {}
    } catch (e) {
        error.value = e?.message || 'Save failed'
    } finally {
        saving.value = false
    }
}
</script>
