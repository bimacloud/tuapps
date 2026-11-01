<template>
  <article class="kartu" :aria-label="`Kartu bukti pembayaran ${studentName}`">
    <section class="kartu-half">
      <h2 class="kartu-title">Bukti Pembayaran<br />Administrasi Bulanan</h2>

      <dl class="kartu-identitas">
        <dt>Nama</dt>
        <dd>{{ studentName }}</dd>
        <dt>Kelas</dt>
        <dd>{{ className }}</dd>
        <dt>Th. Pelajaran</dt>
        <dd>{{ yearName }}</dd>
      </dl>

      <table class="kartu-table">
        <colgroup>
          <col class="w-no" />
          <col />
          <col class="w-tgl" />
          <col class="w-jml" />
          <col class="w-paraf" />
        </colgroup>
        <thead>
          <tr>
            <th scope="col">No</th>
            <th scope="col">Bulan</th>
            <th scope="col">Tanggal</th>
            <th scope="col">Jumlah</th>
            <th scope="col">Paraf</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in monthly" :key="row.month">
            <td class="c">{{ row.no }}</td>
            <td>{{ row.month.toUpperCase() }}</td>
            <td class="c">{{ monthWritten(row) ? formatShortDate(row.date) : '' }}</td>
            <td class="r">{{ monthWritten(row) ? formatAngka(row.paid) : '' }}</td>
            <td></td>
          </tr>
        </tbody>
      </table>
    </section>

    <section class="kartu-half">
      <h2 class="kartu-title">Bukti Pembayaran<br />Administrasi Semester dan Tahunan</h2>

      <table class="kartu-table">
        <colgroup>
          <col class="w-no" />
          <col />
          <col class="w-tgl" />
          <col class="w-jml" />
          <col class="w-paraf" />
        </colgroup>
        <thead>
          <tr>
            <th scope="col">No</th>
            <th scope="col">Uraian</th>
            <th scope="col">Tanggal</th>
            <th scope="col">Jumlah</th>
            <th scope="col">Paraf</th>
          </tr>
        </thead>
        <tbody v-for="slot in annual" :key="slot.key">
          <tr v-for="(line, index) in slotLines(slot)" :key="index">
            <template v-if="index === 0">
              <td class="c" :rowspan="slotLines(slot).length">{{ slot.no }}</td>
              <td class="uraian" :rowspan="slotLines(slot).length">{{ slotLabel(slot).toUpperCase() }}</td>
            </template>
            <td class="c">{{ formatShortDate(line?.date) }}</td>
            <td class="r">{{ line ? formatAngka(line.amount) : '' }}</td>
            <td></td>
          </tr>
        </tbody>
      </table>
    </section>
  </article>
</template>

<script setup lang="ts">
import { formatAngka, formatShortDate } from '@/utils/format'

export interface CardLine {
  date: string
  amount: number
}

export interface CardMonth {
  no: number
  month: string
  paid: number
  date: string | null
  status?: string | null
}

export interface CardSlot {
  no: number
  key: string
  label: string
  rows: CardLine[]
  min_rows: number
  items: string[]
}

/**
 * One A4-landscape sheet: monthly card on the left, semester/annual card on the right.
 * In blank mode only the identity is printed; dates, amounts and paraf are written by hand.
 */
const props = withDefaults(
  defineProps<{
    studentName: string
    className?: string | null
    yearName?: string | null
    monthly: CardMonth[]
    annual: CardSlot[]
    blank?: boolean
  }>(),
  { className: '', yearName: '', blank: false },
)

/** A month has one row, written by hand only once it is paid off; the filled card follows the same rule. */
const monthWritten = (row: CardMonth) => !props.blank && row.status === 'LUNAS' && row.paid > 0

/** Printed lines for one slot: every payment (unless blank), padded with empty lines up to the slot's fixed height. */
const slotLines = (slot: CardSlot): (CardLine | null)[] => {
  const rows = props.blank ? [] : slot.rows
  const blanks = Math.max(slot.min_rows - rows.length, 0)
  return [...rows, ...Array(blanks).fill(null)]
}

const slotLabel = (slot: CardSlot) => slot.label || (props.blank ? '' : slot.items.join(', '))
</script>

<style scoped>
/*
 * Physical card: A4 landscape split into two halves, black on white, thin rules.
 * Arial matches the card the school already prints, so staff and parents recognise it.
 */
.kartu {
  display: grid;
  grid-template-columns: 1fr 1fr;
  width: 297mm;
  height: 210mm;
  margin: 0 auto;
  background: #fff;
  color: #000;
  font-family: Arial, Helvetica, sans-serif;
  outline: 1px solid var(--color-garis);
  break-after: page;
  page: kartu;
}

/* Named page: only card sheets print landscape, receipts and reports keep their own page size. */
@page kartu {
  size: A4 landscape;
  margin: 0;
}

/* Proportions measured from the school's existing card (letter landscape), which fit A4 unchanged. */
.kartu-half {
  display: flex;
  flex-direction: column;
  padding: 13mm 13mm 8mm;
  overflow: hidden;
}

.kartu-half + .kartu-half {
  border-left: 1px dashed #666;
}

.kartu-title {
  margin-bottom: 7mm;
  font-family: Arial, Helvetica, sans-serif;
  font-size: 11pt;
  font-weight: 700;
  line-height: 1.4;
  text-align: center;
  text-transform: uppercase;
  letter-spacing: 0;
}

.kartu-identitas {
  display: grid;
  grid-template-columns: 42mm 1fr;
  row-gap: 2.4mm;
  margin-bottom: 6mm;
  font-size: 10.5pt;
  text-transform: uppercase;
}

.kartu-identitas dt {
  white-space: nowrap;
}

.kartu-identitas dt::after {
  content: ':';
  float: right;
  padding-right: 2mm;
}

.kartu-identitas dd {
  min-height: 5mm;
  border-bottom: 1px solid #000;
  padding-left: 1mm;
  text-transform: none;
  font-weight: 700;
}

.kartu-table {
  width: 100%;
  border-collapse: collapse;
  table-layout: fixed;
  font-size: 10pt;
}

.kartu-table th,
.kartu-table td {
  height: 10mm;
  padding: 0 1.5mm;
  border: 1px solid #000;
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
}

/* Semester side has 16 lines, so they are slightly shorter, as on the original card. */
.kartu-half + .kartu-half .kartu-table td {
  height: 9.4mm;
}

.kartu-table th {
  font-weight: 700;
  text-align: center;
  text-transform: uppercase;
}

.kartu-table .c {
  text-align: center;
}

.kartu-table .r {
  text-align: right;
  font-variant-numeric: tabular-nums;
}

.kartu-table .uraian {
  white-space: normal;
  line-height: 1.2;
  vertical-align: middle;
}

.kartu-table td[rowspan] {
  vertical-align: middle;
}

.w-no {
  width: 11.5mm;
}
.w-tgl {
  width: 27.5mm;
}
.w-jml {
  width: 28mm;
}
.w-paraf {
  width: 22mm;
}

@media print {
  .kartu {
    outline: none;
    margin: 0;
  }
}
</style>
