import {defineStore} from 'pinia'
import router from '../router'

export const useUserStore = defineStore('user', {
    state: () => ({
        user: null,
        initialized: false,
    }),
    getters: {
        isAuthenticated: (s) => !!s.user,
    },
    actions: {
        async getCsrfToken() {
            const meta = document.querySelector('meta[name="csrf-token"]')
            return meta ? meta.getAttribute('content') : ''
        },
        async fetchMe() {
            try {
                const res = await fetch('/api/me', {
                    credentials: 'same-origin',
                })
                if (res.ok) {
                    this.user = await res.json()
                } else {
                    this.user = null
                }
            } catch {
                this.user = null
            } finally {
                this.initialized = true
            }
        },
        async login(payload) {
            const token = await this.getCsrfToken()
            const res = await fetch('/login', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token,
                },
                body: JSON.stringify(payload),
            })
            if (!res.ok) {
                const data = await res.json().catch(() => ({}))
                throw new Error(data.message || 'Login failed')
            }
            await this.fetchMe()
        },
        async logout() {
            const token = await this.getCsrfToken()
            const res = await fetch('/logout', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'X-CSRF-TOKEN': token,
                    'Accept': 'application/json',
                },
            })

            if (res.status === 419) {
                alert(1);
                this.user = null
                // window.location.href = '/login'
                await router.replace({name: 'login'})
                return
            }
            if (!res.ok && res.status !== 204) {
                alert(2);
                const data = await res.json().catch(() => ({}))
                throw new Error(data.message || 'Logout failed')
            }

            this.user = null
            this.initialized = true
            await router.replace({name: 'login'})
        },
    },
})
