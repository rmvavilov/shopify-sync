<template>
    <v-app>
        <v-app-bar flat>
            <v-app-bar-title>
                Shopify Sync
                <v-chip class="ml-3" size="small" variant="tonal">v{{ appVersion }}</v-chip>
            </v-app-bar-title>

            <template v-if="user.isAuthenticated">
                <v-btn variant="text" :to="{ name: 'dashboard' }">Dashboard</v-btn>
                <v-btn
                    :to="{ name: 'products-live' }"
                    variant="outlined"
                    color="red"
                    rounded="lg"
                    class="mr-2"
                >
                    Products
                    <v-chip color="red" size="small" variant="tonal" class="ml-2" label>live</v-chip>
                </v-btn>
                <v-btn
                    variant="outlined"
                    color="blue"
                    rounded="lg"
                    class="mr-2"
                    :to="{ name: 'products-local' }"
                >
                    Products
                    <v-chip color="blue" size="small" variant="tonal" class="ml-2" label>local</v-chip>
                </v-btn>
            </template>


            <v-spacer/>

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
                <RouterView/>
            </v-container>
        </v-main>
    </v-app>
</template>

<script setup>
import {useUserStore} from '@/stores/user'

const appVersion = document
    .querySelector('meta[name="app-version"]')
    ?.getAttribute('content') || 'dev'

const user = useUserStore()

const onLogout = async () => {
    try {
        await user.logout()
    } catch (e) {
        console.error(e)
    }
}
</script>
