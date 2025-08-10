<template>
    <v-container fluid class="mt-12">
        <v-row justify="center">
            <v-col cols="12" sm="8" md="6" lg="5" xl="4">
                <v-card class="pa-6" width="100%" elevation="2">
                    <v-card-title class="text-h6">Login</v-card-title>
                    <v-card-text>
                        <v-form @submit.prevent="submit" v-model="valid">
                            <v-text-field
                                v-model="email"
                                label="Email"
                                type="email"
                                prepend-inner-icon="mdi-email"
                                :rules="[v => !!v || 'Required']"
                                required
                            />
                            <v-text-field
                                v-model="password"
                                label="Password"
                                type="password"
                                prepend-inner-icon="mdi-lock"
                                :rules="[v => !!v || 'Required']"
                                required
                            />
                            <v-alert v-if="error" type="error" density="compact" class="mt-2">{{ error }}</v-alert>
                            <v-btn :loading="loading" type="submit" color="primary" class="mt-4" block>Sign in</v-btn>
                        </v-form>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>
    </v-container>
</template>

<script setup>
import {ref} from 'vue'
import {useRouter} from 'vue-router'
import {useUserStore} from '@/stores/user'

const email = ref('')
const password = ref('')
const loading = ref(false)
const error = ref('')
const valid = ref(true)
const router = useRouter()
const user = useUserStore()

const submit = async () => {
    if (!valid.value) return
    loading.value = true
    error.value = ''
    try {
        await user.login({email: email.value, password: password.value})
        router.push({name: 'dashboard'})
    } catch (e) {
        error.value = e.message
    } finally {
        loading.value = false
    }
}
</script>
