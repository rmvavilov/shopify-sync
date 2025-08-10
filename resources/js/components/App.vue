<template>
    <v-app>
        <v-app-bar flat>
            <v-app-bar-title>Laravel/Vue/Vuetify/Shopify</v-app-bar-title>

            <template v-if="user.isAuthenticated">
                <v-btn variant="text" :to="{ name: 'dashboard' }">Dashboard</v-btn>
                <v-btn variant="text" :to="{ name: 'products' }">Products</v-btn>
            </template>


            <v-spacer />

            <template v-if="user.isAuthenticated">
                <v-chip class="mr-2" prepend-icon="mdi-account" color="primary" variant="tonal">
                    {{ user.user?.name }}
                </v-chip>
                <v-btn @click="onLogout" color="primary" prepend-icon="mdi-logout">Logout</v-btn>
            </template>
            <template v-else>
                <v-btn :to="{ name: 'login' }" color="primary" prepend-icon="mdi-login">Login</v-btn>
            </template>
        </v-app-bar>

        <v-main>
            <v-container class="py-6">
                <RouterView />
            </v-container>
        </v-main>
    </v-app>
</template>

<script setup>
import { useUserStore } from '../stores/user'
const user = useUserStore()

const onLogout = async () => {
    try {
        await user.logout()
    } catch (e) {
        console.error(e)
    }
}
</script>
