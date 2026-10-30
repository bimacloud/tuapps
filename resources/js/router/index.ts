import { createRouter, createWebHistory } from 'vue-router'

const router = createRouter({
  history: createWebHistory('/'),
  scrollBehavior(to, from, savedPosition) {
    return savedPosition || { left: 0, top: 0 }
  },
  routes: [
    {
      path: '/',
      name: 'Ecommerce',
      component: () => import('../views/Ecommerce.vue'),
      meta: {
        title: 'eCommerce Dashboard',
      },
    },
    {
      path: '/calendar',
      name: 'Calendar',
      component: () => import('../views/Others/Calendar.vue'),
      meta: {
        title: 'Calendar',
      },
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
      meta: {
        title: 'Generate Tagihan',
        requiresAuth: true
      }
    },
    {
      path: '/kasir',
      name: 'Kasir Pembayaran',
      component: () => import('../views/Billing/Cashier/Index.vue'),
      meta: {
        title: 'Kasir',
        requiresAuth: true
      }
    },
    {
      path: '/pembayaran',
      name: 'Riwayat Pembayaran',
      component: () => import('../views/Billing/Payments/History.vue'),
      meta: {
        title: 'Riwayat Pembayaran',
        requiresAuth: true
      }
    },
    {
      path: '/pembayaran/:id/kuitansi',
      name: 'Cetak Kuitansi',
      component: () => import('../views/Billing/Payments/Receipt.vue'),
      meta: {
        title: 'Kuitansi',
        requiresAuth: true,
        hideSidebar: true
      }
    },
    {
      path: '/siswa/:id/rekap',
      name: 'Rekap Pembayaran Siswa',
      component: () => import('../views/Billing/Students/Recap.vue'),
      meta: {
        title: 'Rekap Pembayaran Siswa',
        requiresAuth: true
      }
    },
    {
      path: '/profile',
      name: 'profile',
      component: () => import('../views/Others/UserProfile.vue'),
      meta: {
        title: 'Profile',
        requiresAuth: true
      }
    },
    {
      path: '/reports',
      component: () => import('../views/Reports/Index.vue'),
      meta: {
        title: 'Laporan Keuangan',
        requiresAuth: true
      },
      children: [
        {
          path: 'harian',
          name: 'Laporan Harian',
          component: () => import('../views/Reports/Daily.vue'),
        },
        {
          path: 'tunggakan',
          name: 'Laporan Tunggakan',
          component: () => import('../views/Reports/Arrears.vue'),
        },
        {
          path: 'aggregated',
          name: 'Laporan Rekapitulasi',
          component: () => import('../views/Reports/Aggregated.vue'),
        }
      ]
    },
    {
      path: '/approvals',
      name: 'Persetujuan (Approval)',
      component: () => import('../views/Approvals/Index.vue'),
      meta: {
        title: 'Persetujuan',
        requiresAuth: true
      }
    },
    {
      path: '/audit-logs',
      name: 'Audit Logs',
      component: () => import('../views/AuditLogs/Index.vue'),
      meta: {
        title: 'Audit Logs',
        requiresAuth: true
      }
    },
    {
      path: '/settings',
      name: 'Pengaturan Aplikasi',
      component: () => import('../views/Settings/Index.vue'),
      meta: {
        title: 'Pengaturan Aplikasi',
        requiresAuth: true
      }
    },
    {
      path: '/form-elements',
      name: 'Form Elements',
      component: () => import('../views/Forms/FormElements.vue'),
      meta: {
        title: 'Form Elements',
      },
    },
    {
      path: '/basic-tables',
      name: 'Basic Tables',
      component: () => import('../views/Tables/BasicTables.vue'),
      meta: {
        title: 'Basic Tables',
      },
    },
    {
      path: '/line-chart',
      name: 'Line Chart',
      component: () => import('../views/Chart/LineChart/LineChart.vue'),
    },
    {
      path: '/bar-chart',
      name: 'Bar Chart',
      component: () => import('../views/Chart/BarChart/BarChart.vue'),
    },
    {
      path: '/alerts',
      name: 'Alerts',
      component: () => import('../views/UiElements/Alerts.vue'),
      meta: {
        title: 'Alerts',
      },
    },
    {
      path: '/avatars',
      name: 'Avatars',
      component: () => import('../views/UiElements/Avatars.vue'),
      meta: {
        title: 'Avatars',
      },
    },
    {
      path: '/badge',
      name: 'Badge',
      component: () => import('../views/UiElements/Badges.vue'),
      meta: {
        title: 'Badge',
      },
    },

    {
      path: '/buttons',
      name: 'Buttons',
      component: () => import('../views/UiElements/Buttons.vue'),
      meta: {
        title: 'Buttons',
      },
    },

    {
      path: '/images',
      name: 'Images',
      component: () => import('../views/UiElements/Images.vue'),
      meta: {
        title: 'Images',
      },
    },
    {
      path: '/videos',
      name: 'Videos',
      component: () => import('../views/UiElements/Videos.vue'),
      meta: {
        title: 'Videos',
      },
    },
    {
      path: '/blank',
      name: 'Blank',
      component: () => import('../views/Pages/BlankPage.vue'),
      meta: {
        title: 'Blank',
      },
    },

    {
      path: '/error-404',
      name: '404 Error',
      component: () => import('../views/Errors/FourZeroFour.vue'),
      meta: {
        title: '404 Error',
      },
    },

    {
      path: '/signin',
      name: 'Signin',
      component: () => import('../views/Auth/Signin.vue'),
      meta: {
        title: 'Signin',
      },
    },
    {
      path: '/signup',
      name: 'Signup',
      component: () => import('../views/Auth/Signup.vue'),
      meta: {
        title: 'Signup',
      },
    },
  ],
})

import { useAuthStore } from '../stores/auth'

export default router

router.beforeEach(async (to, from, next) => {
  document.title = `Sistem Pembayaran SMK | ${to.meta.title}`
  
  const authStore = useAuthStore()
  
  // Try to fetch user if token exists but user is not loaded
  if (authStore.token && !authStore.user) {
    await authStore.fetchUser()
  }

  const isAuthenticated = authStore.isAuthenticated

  if (to.name !== 'Signin' && !isAuthenticated) {
    next({ name: 'Signin' })
  } else if (to.name === 'Signin' && isAuthenticated) {
    next({ name: 'Ecommerce' })
  } else {
    next()
  }
})
