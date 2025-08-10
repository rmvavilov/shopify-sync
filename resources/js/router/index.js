import { createRouter, createWebHistory } from 'vue-router'
import { useUserStore } from '../stores/user'
import Login from '../views/Login.vue'
import Dashboard from '../views/Dashboard.vue'
import Products from '../views/Products.vue'

const routes = [
    { path: '/login', name: 'login', component: Login, meta: { guestOnly: true } },
    { path: '/dashboard', name: 'dashboard', component: Dashboard, meta: { requiresAuth: true } },
    { path: '/products', name: 'products', component: Products, meta: { requiresAuth: true } },
    { path: '/:pathMatch(.*)*', redirect: '/dashboard' },
]

const router = createRouter({
    history: createWebHistory(),
    routes,
})

router.beforeEach(async (to, from) => {
    const user = useUserStore()
    if (!user.initialized) {
        await user.fetchMe()
    }

    if (to.meta.requiresAuth && !user.isAuthenticated) {
        return { name: 'login' }
    }
    if (to.meta.guestOnly && user.isAuthenticated) {
        return { name: 'dashboard' }
    }
})

export default router
