<template>
    <div style="max-width:360px; margin:40px auto;">
        <h2>Login</h2>
        <form @submit.prevent="submit">
            <div>
                <label>Email</label>
                <input v-model="email" type="email" required />
            </div>
            <div style="margin-top:8px;">
                <label>Password</label>
                <input v-model="password" type="password" required />
            </div>
            <div style="margin-top:12px;">
                <button :disabled="loading">{{ loading ? '...' : 'Login' }}</button>
            </div>
            <p v-if="error" style="color:red; margin-top:8px;">{{ error }}</p>
        </form>
    </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useUserStore } from '../stores/user'

const email = ref('')
const password = ref('')
const loading = ref(false)
const error = ref('')
const router = useRouter()
const user = useUserStore()

const submit = async () => {
    loading.value = true
    error.value = ''
    try {
        await user.login({ email: email.value, password: password.value })
        router.push({ name: 'dashboard' })
    } catch (e) {
        error.value = e.message
    } finally {
        loading.value = false
    }
}
</script>
