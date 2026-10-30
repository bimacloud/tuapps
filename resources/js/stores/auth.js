import { defineStore } from 'pinia'
import axios from 'axios'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    token: localStorage.getItem('auth_token') || null,
    roles: [],
    permissions: []
  }),

  getters: {
    isAuthenticated: (state) => !!state.token,
    hasRole: (state) => (role) => state.roles.includes(role),
    hasPermission: (state) => (permission) => state.permissions.includes(permission),
  },

  actions: {
    async login(credentials) {
      try {
        const response = await axios.post('/api/v1/login', credentials)
        if (response.data.success) {
          this.token = response.data.data.token
          this.user = response.data.data.user
          this.roles = response.data.data.roles
          this.permissions = response.data.data.permissions
          localStorage.setItem('auth_token', this.token)
          
          // Set axios default header
          axios.defaults.headers.common['Authorization'] = `Bearer ${this.token}`
          return true
        }
      } catch (error) {
        throw error
      }
      return false
    },

    async fetchUser() {
      if (!this.token) return false
      
      try {
        axios.defaults.headers.common['Authorization'] = `Bearer ${this.token}`
        const response = await axios.get('/api/v1/user')
        if (response.data.success) {
          this.user = response.data.data.user
          this.roles = response.data.data.roles
          this.permissions = response.data.data.permissions
          return true
        }
      } catch (error) {
        this.logout()
      }
      return false
    },

    async logout() {
      try {
        if (this.token) {
          await axios.post('/api/v1/logout')
        }
      } catch (e) {
        // ignore error on logout
      } finally {
        this.user = null
        this.token = null
        this.roles = []
        this.permissions = []
        localStorage.removeItem('auth_token')
        delete axios.defaults.headers.common['Authorization']
      }
    }
  }
})
