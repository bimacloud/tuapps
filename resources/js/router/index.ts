import { createRouter, createWebHistory } from 'vue-router'

const router = createRouter({
  history: createWebHistory('/'),
  scrollBehavior(to, from, savedPosition) {
    return savedPosition || { left: 0, top: 0 }
  },
  routes: [
    {
      path: '/',
      name: 'Dashboard',
      component: () => import('../views/Dashboard.vue'),
      meta: { title: 'Beranda' },
    },
    {
      path: '/master/academic-years',
      name: 'MasterAcademicYears',
      component: () => import('../views/MasterData/AcademicYears/Index.vue'),
      meta: { title: 'Tahun Pelajaran' },
    },
    {
      path: '/master/majors',
      name: 'MasterMajors',
      component: () => import('../views/MasterData/Majors/Index.vue'),
      meta: { title: 'Jurusan' },
    },
    {
      path: '/master/classes',
      name: 'MasterClasses',
      component: () => import('../views/MasterData/Classes/Index.vue'),
      meta: { title: 'Kelas' },
    },
    {
      path: '/master/students',
      name: 'MasterStudents',
      component: () => import('../views/MasterData/Students/Index.vue'),
      meta: { title: 'Data Siswa' },
    },
    {
      path: '/billing/payment-types',
      name: 'BillingPaymentTypes',
      component: () => import('../views/Billing/PaymentTypes/Index.vue'),
      meta: { title: 'Jenis Pembayaran' },
    },
    {
      path: '/billing/payment-rates',
      name: 'BillingPaymentRates',
      component: () => import('../views/Billing/PaymentRates/Index.vue'),
      meta: { title: 'Tarif Pembayaran' },
    },
    {
      path: '/invoices',
      name: 'InvoicesIndex',
      component: () => import('../views/Billing/Invoices/Index.vue'),
      meta: { title: 'Daftar Tagihan' },
    },
    {
      path: '/billing/invoices/generate',
      name: 'Generate Invoices',
      component: () => import('../views/Billing/Invoices/Generate.vue'),
      meta: { title: 'Buat Tagihan' },
    },
    {
      path: '/kasir',
      name: 'Kasir Pembayaran',
      component: () => import('../views/Billing/Cashier/Index.vue'),
      meta: { title: 'Kasir' },
    },
    {
      path: '/kasir/riwayat',
      name: 'Riwayat Kas',
      component: () => import('../views/Billing/Cashier/Sessions.vue'),
      meta: { title: 'Riwayat Kas' },
    },
    {
      path: '/pembayaran',
      name: 'Riwayat Pembayaran',
      component: () => import('../views/Billing/Payments/History.vue'),
      meta: { title: 'Riwayat Pembayaran' },
    },
    {
      path: '/pembayaran/:id/kuitansi',
      name: 'Cetak Kuitansi',
      component: () => import('../views/Billing/Payments/Receipt.vue'),
      meta: { title: 'Kuitansi' },
    },
    {
      path: '/siswa/:id/rekap',
      name: 'Rekap Pembayaran Siswa',
      component: () => import('../views/Billing/Students/Recap.vue'),
      meta: { title: 'Rekap Pembayaran Siswa' },
    },
    {
      path: '/kartu-pembayaran',
      name: 'Cetak Kartu Pembayaran',
      component: () => import('../views/Billing/Cards/Index.vue'),
      meta: { title: 'Kartu Pembayaran' },
    },
    {
      path: '/siswa/:id/kartu',
      name: 'Kartu Pembayaran',
      component: () => import('../views/Billing/Students/PaymentCard.vue'),
      meta: { title: 'Kartu Bukti Pembayaran' },
    },
    {
      path: '/reports',
      component: () => import('../views/Reports/Index.vue'),
      meta: { title: 'Laporan' },
      children: [
        {
          path: 'harian',
          name: 'Laporan Harian',
          component: () => import('../views/Reports/Daily.vue'),
          meta: { title: 'Laporan Harian' },
        },
        {
          path: 'pemasukan',
          name: 'Rekap Pemasukan',
          component: () => import('../views/Reports/Income.vue'),
          meta: { title: 'Rekap Pemasukan' },
        },
        {
          path: 'rekap-kelas',
          name: 'Rekap Kelas',
          component: () => import('../views/Reports/ClassRecap.vue'),
          meta: { title: 'Rekap Kelas' },
        },
        {
          path: 'tunggakan',
          name: 'Laporan Tunggakan',
          component: () => import('../views/Reports/Arrears.vue'),
          meta: { title: 'Laporan Tunggakan' },
        },
        {
          path: 'aggregated',
          name: 'Laporan Rekapitulasi',
          component: () => import('../views/Reports/Aggregated.vue'),
          meta: { title: 'Laporan Rekapitulasi' },
        },
      ],
    },
    {
      path: '/approvals',
      name: 'Persetujuan (Approval)',
      component: () => import('../views/Approvals/Index.vue'),
      meta: { title: 'Persetujuan' },
    },
    {
      path: '/audit-logs',
      name: 'Audit Logs',
      component: () => import('../views/AuditLogs/Index.vue'),
      meta: { title: 'Audit Log' },
    },
    {
      path: '/setup-awal',
      name: 'Setup Awal',
      component: () => import('../views/Setup/Index.vue'),
      meta: { title: 'Setup Awal' },
    },
    {
      path: '/settings',
      name: 'Pengaturan Aplikasi',
      component: () => import('../views/Settings/Index.vue'),
      meta: { title: 'Pengaturan' },
    },
    {
      path: '/signin',
      name: 'Signin',
      component: () => import('../views/Auth/Signin.vue'),
      meta: { title: 'Masuk' },
    },
    {
      path: '/:pathMatch(.*)*',
      name: 'NotFound',
      component: () => import('../views/Errors/FourZeroFour.vue'),
      meta: { title: 'Halaman tidak ditemukan' },
    },
  ],
})

import { useAuthStore } from '../stores/auth'
import { useSchoolStore } from '../stores/school'

export default router

router.beforeEach(async (to, from, next) => {
  const authStore = useAuthStore()

  if (authStore.token && !authStore.user) {
    await authStore.fetchUser()
  }

  const isAuthenticated = authStore.isAuthenticated

  if (to.name !== 'Signin' && !isAuthenticated) {
    next({ name: 'Signin' })
  } else if (to.name === 'Signin' && isAuthenticated) {
    next({ name: 'Dashboard' })
  } else {
    next()
  }
})

/**
 * After a new build, an open tab still points at old page chunks that no longer exist.
 * Instead of silently staying on the current page, load the target page fresh.
 */
router.onError((error, to) => {
  const isStaleChunk = /Failed to fetch dynamically imported module|Importing a module script failed|error loading dynamically imported module/i.test(
    String(error?.message ?? error),
  )
  if (isStaleChunk && to?.fullPath) {
    window.location.assign(to.fullPath)
  }
})

router.afterEach((to) => {
  const schoolStore = useSchoolStore()
  const appName = schoolStore.settings.school_name || 'Pembayaran Sekolah'
  document.title = `${to.meta.title ?? ''} | ${appName}`
})
