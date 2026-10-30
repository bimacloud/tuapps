import { defineStore } from 'pinia'
import axios from 'axios'

/**
 * School identity (from Pengaturan) and the active academic year, shared by the layout and print pages.
 */
export const useSchoolStore = defineStore('school', {
  state: () => ({
    settings: {},
    profileLoaded: false,
    activeYear: null,
    years: [],
  }),

  getters: {
    name: (state) => state.settings.school_name || '',
    logo: (state) => state.settings.school_logo || '',
    address: (state) => state.settings.school_address || '',

    /**
     * Reminder (never an automatic switch) when the active year is behind the calendar:
     * a newer year has already started, or the active one has ended with no next year yet.
     */
    yearReminder: (state) => {
      const active = state.years.find((year) => year.is_active)
      if (!active) return null

      const now = new Date()
      const today = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`

      const startedNewer = state.years
        .filter((year) => !year.is_active && year.start_date && year.start_date <= today && year.start_date > (active.start_date || ''))
        .sort((a, b) => (a.start_date < b.start_date ? 1 : -1))[0]

      if (startedNewer) {
        return { kind: 'newer', active, next: startedNewer }
      }
      if (active.end_date && active.end_date < today) {
        return { kind: 'ended', active, next: null }
      }
      return null
    },
  },

  actions: {
    async loadProfile(force = false) {
      if (this.profileLoaded && !force) return

      try {
        const response = await axios.get('/api/v1/school-profile')
        this.settings = response.data.data || {}
      } catch (error) {
        // Identity is decorative for the shell; screens fall back to a generic name.
      } finally {
        this.profileLoaded = true
      }
    },

    async loadActiveYear() {
      try {
        const response = await axios.get('/api/v1/academic-years')
        this.years = response.data.data || []
        this.activeYear = this.years.find((year) => year.is_active) || null
      } catch (error) {
        this.years = []
        this.activeYear = null
      }
    },
  },
})
