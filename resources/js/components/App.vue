<template>
    <div>
        <header style="display:flex;gap:1rem;align-items:center; padding:12px; border-bottom:1px solid #eee;">
            <strong>Laravel + Vue SPA</strong>
            <nav style="display:flex; gap:0.5rem;">
                <RouterLink to="/dashboard">Dashboard</RouterLink>
                <RouterLink to="/products">Products</RouterLink>
            </nav>
            <div style="margin-left:auto;">
                <template v-if="userStore.isAuthenticated">
                    <span style="margin-right:8px;">{{ userStore.user?.name }}</span>
                    <button @click="userStore.logout()">Logout</button>
                </template>
                <template v-else>
                    <RouterLink to="/login">Login</RouterLink>
                </template>
            </div>
        </header>
        <main style="padding:16px;">
            <RouterView />
        </main>
    </div>
</template>

<script setup>
import { useUserStore } from '../stores/user'
const userStore = useUserStore()
</script>
