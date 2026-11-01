<template>
  <admin-layout>
    <section v-if="loadingStudent || studentError" class="panel">
      <DataState v-if="loadingStudent" state="loading" message="Mengambil data siswa." />
      <DataState v-else state="error" :message="studentError">
        <button type="button" class="btn btn-secondary" @click="loadAll">Coba lagi</button>
        <router-link to="/master/students" class="btn btn-ghost">Kembali ke data siswa</router-link>
      </DataState>
    </section>

    <template v-else-if="student">
      <PageHeader :title="student.name" :description="studentLine">
        <template #actions>
          <router-link to="/master/students" class="btn btn-secondary">Kembali ke data siswa</router-link>
          <router-link :to="`/siswa/${studentId}/kartu`" class="btn btn-primary">Cetak kartu pembayaran</router-link>
        </template>
      </PageHeader>

      <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
        <section class="panel overflow-hidden" aria-labelledby="rekap-bulanan">
          <h2 id="rekap-bulanan" class="border-b border-garis px-5 py-3.5 text-xl font-bold">Administrasi bulanan</h2>
          <RecapTable
            :items="bulanan"
            :loading="loadingRecap"
            :error="recapError"
            label-column="Bulan"
            empty-message="Belum ada tagihan bulanan untuk siswa ini."
            :label-of="(item) => item.period"
            @retry="fetchRecap"
          />
        </section>

        <section class="panel overflow-hidden" aria-labelledby="rekap-tahunan">
          <h2 id="rekap-tahunan" class="border-b border-garis px-5 py-3.5 text-xl font-bold">Administrasi semester dan tahunan</h2>
          <RecapTable
            :items="tahunan"
            :loading="loadingRecap"
            :error="recapError"
            label-column="Jenis tagihan"
            empty-message="Belum ada tagihan semester atau tahunan untuk siswa ini."
            :label-of="(item) => [item.payment_type?.name, item.period].filter(Boolean).join(' ')"
            @retry="fetchRecap"
          />
        </section>
      </div>
    </template>
  </admin-layout>
</template>

<script setup lang="ts">
import { computed, defineComponent, h, onMounted, ref, type PropType } from 'vue'
import { useRoute } from 'vue-router'
import axios from 'axios'
import AdminLayout from '@/components/layout/AdminLayout.vue'
import PageHeader from '@/components/app/PageHeader.vue'
import DataState from '@/components/app/DataState.vue'
import StatusTag from '@/components/app/StatusTag.vue'
import { errorMessage, formatRupiah } from '@/utils/format'

const route = useRoute()
const studentId = route.params.id as string

const student = ref<any>(null)
const bulanan = ref<any[]>([])
const tahunan = ref<any[]>([])
const loadingStudent = ref(true)
const loadingRecap = ref(true)
const studentError = ref('')
const recapError = ref('')

const studentLine = computed(() => {
  if (!student.value) return ''
  const parts = [
    `NIS ${student.value.nis || '-'}`,
    student.value.nisn ? `NISN ${student.value.nisn}` : null,
    student.value.class_room?.name ? `Kelas ${student.value.class_room.name}` : null,
    student.value.class_room?.major?.name || null,
  ]
  return parts.filter(Boolean).join(' · ')
})

const fetchStudent = async () => {
  loadingStudent.value = true
  studentError.value = ''
  try {
    const res = await axios.get(`/api/v1/students/${studentId}`)
    student.value = res.data.data
  } catch (error) {
    studentError.value = errorMessage(error, 'Data siswa tidak ditemukan atau gagal dimuat.')
  } finally {
    loadingStudent.value = false
  }
}

const fetchRecap = async () => {
  loadingRecap.value = true
  recapError.value = ''
  try {
    const res = await axios.get(`/api/v1/payments/student/${studentId}/recap`)
    bulanan.value = res.data.data.bulanan
    tahunan.value = res.data.data.tahunan
  } catch (error) {
    recapError.value = errorMessage(error, 'Rekap tagihan gagal dimuat.')
  } finally {
    loadingRecap.value = false
  }
}

const loadAll = async () => {
  await fetchStudent()
  if (student.value) await fetchRecap()
}

/** One table per section; both sections share the same columns and states. */
const RecapTable = defineComponent({
  props: {
    items: { type: Array as PropType<any[]>, required: true },
    loading: Boolean,
    error: { type: String, default: '' },
    labelColumn: { type: String, required: true },
    emptyMessage: { type: String, required: true },
    labelOf: { type: Function as PropType<(item: any) => string>, required: true },
  },
  emits: ['retry'],
  setup(props, { emit }) {
    return () => {
      if (props.loading) return h(DataState, { state: 'loading', compact: true })
      if (props.error) {
        return h(DataState, { state: 'error', compact: true, message: props.error }, () =>
          h('button', { type: 'button', class: 'btn btn-secondary', onClick: () => emit('retry') }, 'Coba lagi'),
        )
      }
      if (!props.items.length) {
        return h(DataState, {
          state: 'empty',
          compact: true,
          title: props.emptyMessage,
          message: 'Tagihan dibuat dari menu Tagihan, lalu muncul di sini.',
        })
      }

      return h('div', { class: 'overflow-x-auto' }, [
        h('table', { class: 'table-data' }, [
          h('thead', [
            h('tr', [
              h('th', { scope: 'col' }, props.labelColumn),
              h('th', { scope: 'col', class: 'num' }, 'Tagihan'),
              h('th', { scope: 'col', class: 'num' }, 'Sisa'),
              h('th', { scope: 'col' }, 'Status'),
            ]),
          ]),
          h(
            'tbody',
            props.items.map((item) =>
              h('tr', { key: item.id }, [
                h('td', { class: 'font-medium' }, props.labelOf(item)),
                h('td', { class: 'num' }, formatRupiah(item.amount)),
                h('td', { class: ['num', Number(item.remaining_amount) > 0 ? 'font-semibold text-belum' : ''] }, formatRupiah(item.remaining_amount)),
                h('td', [h(StatusTag, { status: item.status })]),
              ]),
            ),
          ),
        ]),
      ])
    }
  },
})

onMounted(loadAll)
</script>
