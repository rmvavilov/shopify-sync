import {defineStore} from 'pinia'
import router from '@/router'

function getCookie(name) {
    const m = document.cookie.split('; ').find(r => r.startsWith(name + '='))
    return m ? decodeURIComponent(m.split('=')[1]) : ''
}

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
                const res = await fetch('/api/me', {credentials: 'same-origin'})
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
            const postLogout = async () => {
                const xsrf = getCookie('XSRF-TOKEN')
                return fetch('/logout', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-XSRF-TOKEN': xsrf,
                        'Accept': 'application/json',
                    },
                })
            }

            let res = await postLogout()

            if (res.status === 419) {
                try {
                    await fetch('/', {credentials: 'same-origin', headers: {'X-Requested-With': 'XMLHttpRequest'}})
                } catch {
                }
                res = await postLogout()
            }

            if (!res.ok && res.status !== 204) {
                const data = await res.json().catch(() => ({}))
                throw new Error(data.message || `Logout failed (${res.status})`)
            }

            this.user = null
            this.initialized = true
            window.location.assign('/login')
        },
    },
})
