<template>
  <AdminLayout>
    <PageHeader title="Kasir" description="Cari siswa, pilih tagihan yang dibayar, terima uang, lalu cetak kuitansi." />

    <!-- Status kas: pembayaran hanya bisa dicatat saat kas dibuka. -->
    <section
      aria-labelledby="judul-status-kas"
      class="panel mb-6 flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between"
      :class="isSessionClosed ? 'border-amber bg-amber-muda' : ''"
    >
      <h2 id="judul-status-kas" class="sr-only">Status kas</h2>
      <p v-if="sessionLoading" class="text-tinta-2" role="status">Memeriksa status kas...</p>
      <template v-else-if="sessionCheckError">
        <p class="font-semibold text-galat" role="alert">{{ sessionCheckError }}</p>
        <button type="button" class="btn btn-secondary" @click="checkSession">Coba lagi</button>
      </template>
      <template v-else-if="activeSession">
        <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
          <StatusTag status="OPEN" />
          <p class="text-tinta-2">
            Dibuka pukul {{ formatTime(activeSession.start_time) }}, modal awal
            <span class="font-semibold text-tinta tabular-nums">{{ formatRupiah(activeSession.starting_balance) }}</span>
          </p>
        </div>
        <div class="flex flex-wrap gap-2">
          <router-link to="/kasir/riwayat" class="btn btn-ghost">Riwayat kas</router-link>
          <button type="button" class="btn btn-secondary" :disabled="isLoadingSummary" @click="fetchSessionSummary">
            {{ isLoadingSummary ? 'Menghitung kas...' : 'Tutup kas' }}
          </button>
        </div>
      </template>
      <template v-else>
        <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
          <StatusTag status="CLOSED" />
          <p class="font-medium text-tinta">Pembayaran baru bisa dicatat setelah kas dibuka.</p>
        </div>
        <div class="flex flex-wrap gap-2">
          <router-link to="/kasir/riwayat" class="btn btn-ghost">Riwayat kas</router-link>
          <button type="button" class="btn btn-primary" @click="openSessionModal">Buka kas</button>
        </div>
      </template>
    </section>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_23rem]">
      <div class="min-w-0 space-y-6">
        <!-- 1. Cari siswa -->
        <section v-if="!selectedStudent" class="panel p-5" aria-labelledby="judul-cari-siswa">
          <h2 id="judul-cari-siswa" class="text-xl font-bold">1. Cari siswa</h2>
          <label for="cari-siswa" class="field-label mt-3">Nama, NIS, atau NISN</label>
          <input
            id="cari-siswa"
            ref="searchInput"
            v-model="searchQuery"
            type="text"
            autocomplete="off"
            role="combobox"
            aria-autocomplete="list"
            aria-controls="hasil-cari-siswa"
            :aria-expanded="hasSearchResults ? 'true' : 'false'"
            :aria-activedescendant="activeOptionId"
            aria-describedby="cari-siswa-petunjuk"
            :disabled="!activeSession"
            class="field-input text-lg"
            placeholder="Nama, NIS, atau NISN siswa"
            @keydown="onSearchKeydown"
          />
          <p id="cari-siswa-petunjuk" class="field-hint">
            {{
              activeSession
                ? 'Hanya siswa aktif yang masih punya tagihan. Pilih dengan tombol panah atas dan bawah, lalu tekan Enter.'
                : 'Buka kas dulu untuk mulai mencatat pembayaran.'
            }}
          </p>

          <div v-if="searchQuery" class="mt-3">
            <p v-if="isSearching" class="text-tinta-2" role="status">Mencari siswa...</p>
            <p v-else-if="searchError" class="field-error" role="alert">{{ searchError }}</p>
            <p v-else-if="searchResults.length === 0" class="text-tinta-2" role="status">
              Tidak ada siswa aktif dengan tagihan belum lunas yang cocok dengan "{{ searchQuery }}". Periksa ejaan nama atau
              nomor induk.
            </p>
            <ul
              v-else
              id="hasil-cari-siswa"
              role="listbox"
              aria-label="Hasil pencarian siswa"
              class="divide-y divide-garis overflow-hidden rounded-[var(--radius-kontrol)] border border-garis"
            >
              <li
                v-for="(student, index) in searchResults"
                :id="`opsi-siswa-${student.id}`"
                :key="student.id"
                role="option"
                :aria-selected="index === highlightedIndex ? 'true' : 'false'"
                class="flex min-h-14 cursor-pointer flex-wrap items-center justify-between gap-x-4 gap-y-1 px-4 py-2.5"
                :class="index === highlightedIndex ? 'bg-amber-muda' : 'hover:bg-krem'"
                @click="selectStudent(student)"
                @mouseenter="highlightedIndex = index"
              >
                <span class="min-w-0">
                  <span class="block font-semibold">{{ student.name }}</span>
                  <span class="block text-sm text-tinta-2">NIS {{ student.nis || '-' }}, NISN {{ student.nisn || '-' }}</span>
                </span>
                <span v-if="student.class_room?.name" class="text-sm font-semibold text-tinta-2">{{ student.class_room.name }}</span>
              </li>
            </ul>
          </div>
        </section>

        <!-- Siswa terpilih -->
        <section v-else class="panel flex flex-col gap-4 p-5 sm:flex-row sm:items-start sm:justify-between" aria-labelledby="judul-siswa">
          <div class="min-w-0">
            <p class="text-sm font-semibold text-tinta-2">Siswa</p>
            <h2 id="judul-siswa" class="text-2xl font-bold leading-tight">{{ selectedStudent.name }}</h2>
            <p class="text-tinta-2">{{ studentDetail(selectedStudent) }}</p>
            <a
              :href="`/siswa/${selectedStudent.id}/rekap`"
              target="_blank"
              rel="noopener"
              class="link inline-flex min-h-11 items-center text-sm"
            >
              Lihat rekap pembayaran<span class="sr-only"> (tab baru)</span>
            </a>
          </div>
          <div class="flex flex-wrap items-end justify-between gap-4 sm:flex-col sm:items-end">
            <div v-if="!isLoadingInvoices && !invoicesError" class="sm:text-right">
              <p class="text-sm text-tinta-2">Total belum dibayar</p>
              <p class="font-display text-2xl font-bold text-belum tabular-nums">{{ formatRupiah(totalOutstanding) }}</p>
            </div>
            <button type="button" class="btn btn-secondary" @click="resetKasir">Ganti siswa</button>
          </div>
        </section>

        <!-- 2. Pilih tagihan -->
        <section v-if="selectedStudent" class="panel overflow-hidden" aria-labelledby="judul-tagihan">
          <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-garis px-5 py-4">
            <h2 id="judul-tagihan" ref="billsHeading" tabindex="-1" class="text-xl font-bold">2. Pilih tagihan yang dibayar</h2>
            <p v-if="allItems.length" class="text-sm text-tinta-2">
              {{ selectedItemList.length }} dari {{ allItems.length }} tagihan dipilih
            </p>
          </div>

          <DataState v-if="isLoadingInvoices" state="loading" message="Mengambil tagihan siswa." />
          <DataState v-else-if="invoicesError" state="error" :message="invoicesError">
            <button type="button" class="btn btn-secondary" @click="loadStudentInvoices(selectedStudent.id)">Coba lagi</button>
          </DataState>
          <DataState
            v-else-if="allItems.length === 0"
            state="empty"
            title="Tidak ada tagihan yang perlu dibayar."
            message="Semua tagihan siswa ini sudah lunas. Cari siswa lain untuk transaksi berikutnya."
          >
            <button type="button" class="btn btn-secondary" @click="resetKasir">Ganti siswa</button>
          </DataState>

          <div v-else>
            <template v-for="invoice in studentInvoices" :key="invoice.id">
              <div v-if="invoice.items.length" class="border-b border-garis last:border-b-0">
                <div class="flex flex-wrap items-center justify-between gap-2 bg-krem/60 px-5 py-1.5">
                  <p class="text-sm">
                    <span class="font-semibold">{{ invoice.invoice_number }}</span>
                    <span v-if="invoice.academic_year?.name" class="text-tinta-2"> Tahun pelajaran {{ invoice.academic_year.name }}</span>
                  </p>
                  <button type="button" class="btn btn-ghost btn-sm" @click="toggleInvoice(invoice)">
                    {{ isInvoiceFullySelected(invoice) ? 'Batalkan pilihan' : 'Pilih semua' }}
                    <span class="sr-only">tagihan {{ invoice.invoice_number }}</span>
                  </button>
                </div>

                <ul class="divide-y divide-garis">
                  <li
                    v-for="item in invoice.items"
                    :key="item.id"
                    class="px-5 py-3"
                    :class="selectedItems[item.id] ? 'bg-amber-muda/40' : ''"
                  >
                    <div class="flex flex-wrap items-start gap-x-4 gap-y-1 sm:flex-nowrap">
                      <label :for="`tagihan-${item.id}`" class="flex min-h-11 min-w-0 flex-1 cursor-pointer items-start gap-3">
                        <input
                          :id="`tagihan-${item.id}`"
                          type="checkbox"
                          class="field-check mt-1 shrink-0"
                          :checked="selectedItems[item.id]"
                          @change="toggleItem(item.id)"
                        />
                        <span class="min-w-0">
                          <span class="block font-semibold">{{ itemName(item) }}</span>
                          <span class="block text-sm text-tinta-2">
                            Tarif {{ formatRupiah(item.amount) }}
                            <template v-if="Number(item.paid_amount) > 0">(sudah dibayar {{ formatRupiah(item.paid_amount) }})</template>
                          </span>
                        </span>
                      </label>
                      <div class="ml-[1.875rem] sm:ml-0 sm:text-right">
                        <p class="text-sm text-tinta-2">Sisa</p>
                        <p class="font-semibold tabular-nums">{{ formatRupiah(item.remaining_amount) }}</p>
                      </div>
                    </div>

                    <div v-if="selectedItems[item.id]" class="mt-3 sm:ml-[1.875rem] sm:max-w-xs">
                      <label :for="`nominal-${item.id}`" class="field-label">
                        Dibayar sekarang<span class="sr-only"> untuk {{ itemName(item) }}</span>
                      </label>
                      <input
                        :id="`nominal-${item.id}`"
                        v-model.number="payAmounts[item.id]"
                        type="number"
                        min="1"
                        :max="item.remaining_amount"
                        step="1000"
                        inputmode="numeric"
                        class="field-input text-right tabular-nums"
                        :aria-invalid="itemErrors[item.id] ? 'true' : 'false'"
                        :aria-describedby="`nominal-${item.id}-info`"
                      />
                      <div class="mt-1.5 flex flex-wrap items-center justify-between gap-x-3 gap-y-1">
                        <p v-if="itemErrors[item.id]" :id="`nominal-${item.id}-info`" class="field-error !mt-0">
                          {{ itemErrors[item.id] }}
                        </p>
                        <p v-else :id="`nominal-${item.id}-info`" class="text-sm text-tinta-2">
                          {{ isPartial(item) ? 'Dibayar sebagian. Sisanya tetap tercatat sebagai tunggakan.' : 'Tagihan ini lunas setelah dibayar.' }}
                        </p>
                        <button
                          v-if="isPartial(item) || itemErrors[item.id]"
                          type="button"
                          class="btn btn-ghost btn-sm"
                          @click="payInFull(item)"
                        >
                          Bayar penuh<span class="sr-only"> {{ itemName(item) }}</span>
                        </button>
                      </div>
                    </div>
                  </li>
                </ul>
              </div>
            </template>
          </div>
        </section>
      </div>

      <!-- 3. Terima pembayaran -->
      <section class="panel self-start p-5" aria-labelledby="judul-pembayaran">
        <h2 id="judul-pembayaran" class="text-xl font-bold">3. Terima pembayaran</h2>

        <DataState
          v-if="selectedItemList.length === 0"
          state="empty"
          compact
          :title="selectedStudent ? 'Belum ada tagihan dipilih.' : 'Belum ada siswa dipilih.'"
          :message="
            selectedStudent
              ? 'Centang tagihan yang dibayar hari ini. Total dan pilihan metode muncul di sini.'
              : 'Cari siswa dulu, lalu pilih tagihan yang dibayar.'
          "
        >
          <button v-if="!selectedStudent && activeSession" type="button" class="btn btn-secondary" @click="focusSearch">
            Ke pencarian siswa
          </button>
        </DataState>

        <div v-else class="mt-4 space-y-5">
          <ul class="space-y-1" aria-label="Tagihan yang dibayar">
            <li v-for="item in selectedItemList" :key="item.id" class="flex items-start justify-between gap-3">
              <span class="min-w-0 pt-2">
                <span class="block">{{ itemName(item) }}</span>
                <span v-if="isPartial(item)" class="block text-sm text-tinta-2">Cicilan</span>
              </span>
              <span class="flex shrink-0 items-center gap-1">
                <span class="font-semibold tabular-nums">{{ formatRupiah(payAmounts[item.id]) }}</span>
                <button type="button" class="btn btn-ghost btn-sm" @click="toggleItem(item.id)">
                  Hapus<span class="sr-only"> {{ itemName(item) }} dari pembayaran</span>
                </button>
              </span>
            </li>
          </ul>

          <div class="flex flex-wrap items-end justify-between gap-x-3 gap-y-1 border-t border-garis pt-4">
            <span class="font-semibold">Total dibayar</span>
            <span class="font-display text-4xl font-bold leading-none tabular-nums">{{ formatRupiah(totalToPay) }}</span>
          </div>

          <fieldset>
            <legend class="field-label">Metode pembayaran</legend>
            <div v-if="methodsError" class="space-y-2">
              <p class="field-error !mt-0" role="alert">{{ methodsError }}</p>
              <button type="button" class="btn btn-secondary" @click="getPaymentMethods">Muat ulang metode</button>
            </div>
            <div v-else-if="paymentMethods.length === 0" class="space-y-2">
              <p class="text-sm text-tinta-2">Belum ada metode pembayaran yang aktif, jadi pembayaran belum bisa disimpan.</p>
              <button type="button" class="btn btn-secondary" @click="getPaymentMethods">Muat ulang metode</button>
            </div>
            <div v-else class="grid gap-2">
              <label
                v-for="method in paymentMethods"
                :key="method.id"
                :for="`metode-${method.id}`"
                class="flex min-h-11 cursor-pointer items-center gap-3 rounded-[var(--radius-kontrol)] border px-3"
                :class="selectedMethod === method.id ? 'border-bata-tua bg-krem font-semibold' : 'border-garis-kuat'"
              >
                <input
                  :id="`metode-${method.id}`"
                  v-model="selectedMethod"
                  type="radio"
                  name="metode-pembayaran"
                  class="field-check shrink-0"
                  :value="method.id"
                />
                {{ method.name }}
              </label>
            </div>
          </fieldset>

          <div v-if="isCash" class="space-y-3 rounded-[var(--radius-kontrol)] border border-garis bg-krem/60 p-4">
            <div>
              <label for="uang-diterima" class="field-label">Uang diterima</label>
              <input
                id="uang-diterima"
                v-model.number="cashReceived"
                type="number"
                min="0"
                step="1000"
                inputmode="numeric"
                class="field-input text-right text-lg tabular-nums"
                placeholder="Jumlah uang dari penyetor"
                :aria-invalid="cashReceived && change < 0 ? 'true' : 'false'"
                aria-describedby="uang-diterima-info"
              />
            </div>
            <div class="flex flex-wrap gap-2" role="group" aria-label="Isi cepat uang diterima">
              <button
                v-for="(amount, index) in cashSuggestions"
                :key="amount"
                type="button"
                class="btn btn-secondary px-3"
                :class="cashReceived === amount ? '!border-bata-tua !bg-amber-muda' : ''"
                :aria-pressed="cashReceived === amount ? 'true' : 'false'"
                @click="cashReceived = amount"
              >
                {{ index === 0 ? 'Uang pas' : formatRupiah(amount) }}
              </button>
            </div>
            <div id="uang-diterima-info" aria-live="polite">
              <div v-if="cashReceived" class="flex flex-wrap items-baseline justify-between gap-x-3">
                <span class="font-semibold" :class="change >= 0 ? 'text-tinta' : 'text-galat'">
                  {{ change >= 0 ? 'Kembalian' : 'Uang kurang' }}
                </span>
                <span class="font-display text-2xl font-bold tabular-nums" :class="change >= 0 ? 'text-lunas' : 'text-galat'">
                  {{ formatRupiah(Math.abs(change)) }}
                </span>
              </div>
            </div>
          </div>

          <p v-else-if="selectedMethodObj" class="rounded-[var(--radius-kontrol)] border border-garis bg-krem/60 p-3 text-sm">
            Pastikan dana sudah masuk ke rekening sekolah. Tulis nomor referensi di catatan.
          </p>

          <div>
            <label for="catatan-pembayaran" class="field-label">
              Catatan <span class="font-normal text-tinta-2">(tidak wajib)</span>
            </label>
            <textarea
              id="catatan-pembayaran"
              v-model="notes"
              rows="2"
              class="field-input"
              placeholder="Nomor referensi transfer atau nama penyetor"
            ></textarea>
          </div>

          <div>
            <button
              type="button"
              class="btn btn-primary w-full text-base"
              :disabled="!!blockingReason"
              :aria-describedby="blockingReason ? 'alasan-belum-bisa-bayar' : undefined"
              @click="openConfirm"
            >
              Proses pembayaran
            </button>
            <p v-if="blockingReason" id="alasan-belum-bisa-bayar" class="field-hint text-center">{{ blockingReason }}</p>
          </div>
        </div>
      </section>
    </div>

    <!-- Konfirmasi pembayaran -->
    <BaseModal
      :open="showConfirmModal"
      title="Konfirmasi pembayaran"
      description="Periksa sekali lagi. Pembayaran yang sudah disimpan hanya bisa dibatalkan dengan persetujuan Kepala Sekolah."
      @close="closeConfirm"
    >
      <div v-if="selectedStudent" class="space-y-4">
        <div>
          <p class="text-lg font-semibold">{{ selectedStudent.name }}</p>
          <p class="text-sm text-tinta-2">{{ studentDetail(selectedStudent) }}</p>
        </div>

        <ul class="divide-y divide-garis border-y border-garis">
          <li v-for="item in selectedItemList" :key="item.id" class="flex justify-between gap-3 py-2">
            <span>
              {{ itemName(item) }}<span v-if="isPartial(item)" class="text-tinta-2"> (cicilan)</span>
            </span>
            <span class="font-semibold whitespace-nowrap tabular-nums">{{ formatRupiah(payAmounts[item.id]) }}</span>
          </li>
        </ul>

        <dl class="space-y-2">
          <div class="flex justify-between gap-4">
            <dt class="text-tinta-2">Metode</dt>
            <dd class="font-semibold">{{ selectedMethodObj?.name }}</dd>
          </div>
          <div class="flex flex-wrap items-baseline justify-between gap-x-4">
            <dt class="font-semibold">Total dibayar</dt>
            <dd class="font-display text-3xl font-bold tabular-nums">{{ formatRupiah(totalToPay) }}</dd>
          </div>
          <template v-if="isCash">
            <div class="flex justify-between gap-4">
              <dt class="text-tinta-2">Uang diterima</dt>
              <dd class="tabular-nums">{{ formatRupiah(cashReceived) }}</dd>
            </div>
            <div class="flex justify-between gap-4">
              <dt class="text-tinta-2">Kembalian</dt>
              <dd class="font-semibold text-lunas tabular-nums">{{ formatRupiah(change) }}</dd>
            </div>
          </template>
          <div v-if="notes" class="flex justify-between gap-4">
            <dt class="text-tinta-2">Catatan</dt>
            <dd class="text-right">{{ notes }}</dd>
          </div>
        </dl>

        <label
          for="konfirmasi-uang-diterima"
          class="flex cursor-pointer items-start gap-3 rounded-[var(--radius-kontrol)] border p-3"
          :class="confirmChecked ? 'border-lunas bg-lunas-muda' : 'border-garis-kuat'"
        >
          <input id="konfirmasi-uang-diterima" v-model="confirmChecked" type="checkbox" class="field-check mt-0.5 shrink-0" />
          <span>
            <template v-if="isCash">
              Saya sudah menerima uang tunai <strong>{{ formatRupiah(cashReceived) }}</strong> dan menyerahkan kembalian
              <strong>{{ formatRupiah(change) }}</strong>.
            </template>
            <template v-else>
              Saya sudah memastikan dana <strong>{{ formatRupiah(totalToPay) }}</strong> diterima melalui {{ selectedMethodObj?.name }}.
            </template>
          </span>
        </label>

        <p
          v-if="paymentError"
          class="rounded-[var(--radius-kontrol)] bg-galat-muda px-3 py-2 text-sm font-semibold text-galat"
          role="alert"
        >
          {{ paymentError }}
        </p>
      </div>
      <template #footer>
        <button type="button" class="btn btn-secondary" :disabled="isProcessing" @click="closeConfirm">Periksa lagi</button>
        <button type="button" class="btn btn-primary" :disabled="!confirmChecked || isProcessing" @click="submitPayment">
          {{ isProcessing ? 'Menyimpan pembayaran...' : 'Simpan pembayaran' }}
        </button>
      </template>
    </BaseModal>

    <!-- Pembayaran tersimpan -->
    <BaseModal
      :open="showSuccessModal"
      title="Pembayaran tersimpan"
      :description="successPayment?.transaction_number ? `Nomor transaksi ${successPayment.transaction_number}` : ''"
      size="md"
      @close="resetKasir"
    >
      <div class="space-y-4 text-center">
        <div>
          <p class="text-sm text-tinta-2">Total diterima</p>
          <p class="font-display text-4xl font-bold tabular-nums">{{ formatRupiah(successPayment?.total_amount) }}</p>
        </div>
        <div v-if="successChange > 0" class="rounded-[var(--radius-kontrol)] border border-lunas bg-lunas-muda p-4 text-lunas">
          <p class="font-semibold">Serahkan kembalian</p>
          <p class="font-display text-3xl font-bold tabular-nums">{{ formatRupiah(successChange) }}</p>
        </div>
        <section class="rounded-[var(--radius-kontrol)] border border-garis p-4 text-left" aria-labelledby="catat-kartu">
          <h3 id="catat-kartu" class="font-sans text-base font-semibold">Tulis di kartu siswa, lalu paraf</h3>
          <p v-if="cardLinesError" class="mt-2 text-sm font-medium text-galat" role="alert">{{ cardLinesError }}</p>
          <p v-else-if="!cardLines.length" class="mt-2 text-sm text-tinta-2" role="status">Menyiapkan baris kartu...</p>
          <p v-else-if="!linesToWrite.length" class="mt-2 text-sm text-tinta-2">Tidak ada yang ditulis di kartu kali ini.</p>
          <table v-if="linesToWrite.length" class="mt-2 w-full text-sm">
            <thead>
              <tr class="text-left text-tinta-2">
                <th scope="col" class="py-1 font-semibold">Baris di kartu</th>
                <th scope="col" class="py-1 font-semibold">Tanggal</th>
                <th scope="col" class="py-1 text-right font-semibold">Jumlah</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="line in linesToWrite" :key="line.payment_item_id" class="border-t border-garis align-top">
                <td class="py-1.5 pr-2">
                  <span class="font-semibold">{{ line.side === 'bulanan' ? 'Bulanan' : 'Semester/tahunan' }} no {{ line.no }}</span>
                  <span class="block">{{ line.row }}</span>
                  <span class="block text-xs" :class="line.remaining_after > 0 ? 'text-belum' : 'text-lunas'">
                    <template v-if="line.remaining_after > 0">Cicilan, sisa {{ formatRupiah(line.remaining_after) }}</template>
                    <template v-else-if="line.side === 'bulanan' && line.card_amount > line.amount">Lunas, tulis total sebulan</template>
                    <template v-else>Lunas</template>
                  </span>
                </td>
                <td class="py-1.5 pr-2 tabular-nums">{{ formatShortDate(line.date) }}</td>
                <td class="py-1.5 text-right tabular-nums">{{ formatAngka(line.card_amount) }}</td>
              </tr>
            </tbody>
          </table>
          <div v-if="linesToHold.length" class="mt-3 rounded-[var(--radius-kontrol)] bg-amber-muda px-3 py-2 text-sm">
            <p class="font-semibold">Belum ditulis di kartu</p>
            <ul class="mt-1 space-y-1">
              <li v-for="line in linesToHold" :key="line.payment_item_id">
                {{ line.row }} baru dibayar sebagian, sisa {{ formatRupiah(line.remaining_after) }}. Tulis di baris
                {{ line.row }} saat lunas; kuitansi ini bukti cicilannya.
              </li>
            </ul>
          </div>
          <a
            v-if="selectedStudent"
            :href="`/siswa/${selectedStudent.id}/kartu?mode=terisi`"
            target="_blank"
            rel="noopener"
            class="link mt-2 inline-flex min-h-11 items-center text-sm"
          >
            Kartu hilang? Cetak kartu pengganti<span class="sr-only"> (tab baru)</span>
          </a>
        </section>
      </div>
      <template #footer>
        <button type="button" class="btn btn-secondary" @click="resetKasir">Transaksi baru</button>
        <button ref="printButton" type="button" class="btn btn-primary" @click="printReceipt">Cetak kuitansi</button>
      </template>
    </BaseModal>

    <!-- Buka kas -->
    <BaseModal
      :open="showOpenSessionModal"
      title="Buka kas"
      description="Hitung uang modal di laci, lalu masukkan jumlahnya sebagai saldo awal."
      size="sm"
      @close="showOpenSessionModal = false"
    >
      <form id="form-buka-kas" class="space-y-4" @submit.prevent="openSession">
        <p
          v-if="sessionError"
          class="rounded-[var(--radius-kontrol)] bg-galat-muda px-3 py-2 text-sm font-semibold text-galat"
          role="alert"
        >
          {{ sessionError }}
        </p>
        <div>
          <label for="saldo-awal" class="field-label">Saldo awal tunai (Rp)</label>
          <input
            id="saldo-awal"
            v-model.number="startingBalance"
            type="number"
            min="0"
            step="1000"
            inputmode="numeric"
            required
            class="field-input text-right text-lg tabular-nums"
            placeholder="Jumlah uang modal di laci"
          />
          <p class="field-hint">Isi 0 bila laci kosong.</p>
        </div>
      </form>
      <template #footer>
        <button type="button" class="btn btn-secondary" @click="showOpenSessionModal = false">Nanti saja</button>
        <button type="submit" form="form-buka-kas" class="btn btn-primary" :disabled="isSubmittingSession || !isStartingBalanceValid">
          {{ isSubmittingSession ? 'Membuka kas...' : 'Buka kas' }}
        </button>
      </template>
    </BaseModal>

    <!-- Tutup kas -->
    <BaseModal
      :open="showCloseSessionModal && !!sessionSummary"
      title="Tutup kas"
      description="Hitung uang fisik di laci dan cocokkan dengan catatan sistem."
      @close="showCloseSessionModal = false"
    >
      <form v-if="sessionSummary" id="form-tutup-kas" class="space-y-4" @submit.prevent="closeSession">
        <dl class="divide-y divide-garis rounded-[var(--radius-kontrol)] border border-garis">
          <div class="flex justify-between gap-4 px-4 py-2.5">
            <dt class="text-tinta-2">Saldo awal</dt>
            <dd class="tabular-nums">{{ formatRupiah(sessionSummary.session.starting_balance) }}</dd>
          </div>
          <div class="flex justify-between gap-4 px-4 py-2.5">
            <dt class="text-tinta-2">Penerimaan tunai</dt>
            <dd class="tabular-nums">+ {{ formatRupiah(sessionSummary.total_cash_payments) }}</dd>
          </div>
          <div class="flex flex-wrap items-baseline justify-between gap-x-4 px-4 py-2.5">
            <dt class="font-semibold">Seharusnya di laci</dt>
            <dd class="font-display text-2xl font-bold tabular-nums">{{ formatRupiah(sessionSummary.expected_balance) }}</dd>
          </div>
        </dl>

        <div>
          <label for="uang-fisik" class="field-label">Uang fisik di laci (Rp)</label>
          <input
            id="uang-fisik"
            v-model.number="actualBalance"
            type="number"
            min="0"
            step="1000"
            inputmode="numeric"
            required
            class="field-input text-right text-lg tabular-nums"
            placeholder="Hasil hitung uang di laci"
          />
        </div>

        <div v-if="typeof actualBalance === 'number'" aria-live="polite">
          <p v-if="closeDifference === 0" class="font-semibold text-lunas">Kas cocok, tidak ada selisih.</p>
          <div v-else class="space-y-2">
            <p class="font-semibold text-belum">
              Selisih kas: {{ closeDifference > 0 ? 'lebih' : 'kurang' }} {{ formatRupiah(Math.abs(closeDifference)) }}
            </p>
            <div>
              <label for="alasan-selisih" class="field-label">Alasan selisih</label>
              <textarea
                id="alasan-selisih"
                v-model="closeNotes"
                rows="2"
                required
                class="field-input"
                aria-describedby="alasan-selisih-info"
                placeholder="Penyebab uang di laci berbeda dari catatan"
              ></textarea>
              <p id="alasan-selisih-info" class="field-hint">Wajib diisi karena ada selisih.</p>
            </div>
          </div>
        </div>

        <p
          v-if="sessionError"
          class="rounded-[var(--radius-kontrol)] bg-galat-muda px-3 py-2 text-sm font-semibold text-galat"
          role="alert"
        >
          {{ sessionError }}
        </p>
      </form>
      <template #footer>
        <button type="button" class="btn btn-secondary" @click="showCloseSessionModal = false">Batal</button>
        <button
          type="submit"
          form="form-tutup-kas"
          class="btn btn-primary"
          :disabled="isSubmittingSession || typeof actualBalance !== 'number' || (closeDifference !== 0 && !closeNotes)"
        >
          {{ isSubmittingSession ? 'Menutup kas...' : 'Tutup kas' }}
        </button>
      </template>
    </BaseModal>
  </AdminLayout>
</template>

<script setup lang="ts">
import { ref, onMounted, computed, watch, nextTick } from 'vue'
import axios from 'axios'
import { useNotice } from '@/composables/useNotice'
import { errorMessage, formatAngka, formatRupiah, formatShortDate } from '@/utils/format'
import AdminLayout from '@/components/layout/AdminLayout.vue'
import PageHeader from '@/components/app/PageHeader.vue'
import DataState from '@/components/app/DataState.vue'
import StatusTag from '@/components/app/StatusTag.vue'
import BaseModal from '@/components/app/BaseModal.vue'

const notice = useNotice()

// Search state
const searchInput = ref<HTMLInputElement | null>(null)
const searchQuery = ref('')
const searchResults = ref<any[]>([])
const isSearching = ref(false)
const searchError = ref('')
const highlightedIndex = ref(0)

// Selection state
const selectedStudent = ref<any>(null)
const studentInvoices = ref<any[]>([])
const isLoadingInvoices = ref(false)
const invoicesError = ref('')
const billsHeading = ref<HTMLElement | null>(null)

// Payment state
const paymentMethods = ref<any[]>([])
const methodsError = ref('')
const selectedMethod = ref<number | null>(null)
const notes = ref('')
const cashReceived = ref<number | null>(null)
const payAmounts = ref<Record<number, number>>({}) // invoice_item_id -> amount
const selectedItems = ref<Record<number, boolean>>({}) // invoice_item_id -> isSelected

// Confirmation state
const showConfirmModal = ref(false)
const confirmChecked = ref(false)
const isProcessing = ref(false)
const paymentError = ref('')

// Success state
const successPayment = ref<any>(null)
const cardLines = ref<any[]>([])
const cardLinesError = ref('')
const linesToWrite = computed(() => cardLines.value.filter((line) => line.write_now))
const linesToHold = computed(() => cardLines.value.filter((line) => !line.write_now))

/** Rows the treasurer copies onto the student's physical card for this payment. */
const loadCardLines = async (paymentId: number) => {
  cardLines.value = []
  cardLinesError.value = ''
  try {
    const res = await axios.get(`/api/v1/payments/${paymentId}/receipt`)
    cardLines.value = res.data.card_lines || []
  } catch (err) {
    cardLinesError.value = 'Baris kartu gagal dimuat. Rinciannya tetap ada di kuitansi.'
  }
}
const successChange = ref(0)
const showSuccessModal = ref(false)
const printButton = ref<HTMLButtonElement | null>(null)

// Session state
const activeSession = ref<any>(null)
const sessionLoading = ref(true)
const sessionCheckError = ref('')
const showOpenSessionModal = ref(false)
const showCloseSessionModal = ref(false)
const startingBalance = ref<number | null>(null)
const actualBalance = ref<number | null>(null)
const closeNotes = ref('')
const sessionSummary = ref<any>(null)
const isLoadingSummary = ref(false)
const isSubmittingSession = ref(false)
const sessionError = ref('')

const formatTime = (value: string) => {
  return new Date(value).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })
}

const itemName = (item: any) => [item.payment_type?.name, item.period].filter(Boolean).join(', ')

const studentDetail = (student: any) =>
  [student.class_room?.name, student.class_room?.major?.name, `NIS ${student.nis || '-'}`].filter(Boolean).join(', ')

// ---------- Session ----------

const isSessionClosed = computed(() => !sessionLoading.value && !sessionCheckError.value && !activeSession.value)

const checkSession = async () => {
  sessionLoading.value = true
  sessionCheckError.value = ''
  try {
    const res = await axios.get('/api/v1/cashier-sessions/current')
    if (res.data.data) {
      activeSession.value = res.data.data
      focusSearch()
    } else {
      activeSession.value = null
      openSessionModal()
    }
  } catch (err) {
    sessionCheckError.value = errorMessage(err, 'Status kas gagal diperiksa. Periksa koneksi ke server, lalu coba lagi.')
  } finally {
    sessionLoading.value = false
  }
}

const openSessionModal = () => {
  sessionError.value = ''
  showOpenSessionModal.value = true
}

const isStartingBalanceValid = computed(() => typeof startingBalance.value === 'number' && startingBalance.value >= 0)

const openSession = async () => {
  if (!isStartingBalanceValid.value) return
  isSubmittingSession.value = true
  sessionError.value = ''
  try {
    const res = await axios.post('/api/v1/cashier-sessions/open', {
      starting_balance: startingBalance.value,
    })
    activeSession.value = res.data.data
    showOpenSessionModal.value = false
    startingBalance.value = null
    focusSearch()
  } catch (err: any) {
    sessionError.value = errorMessage(err, 'Kas gagal dibuka. Coba lagi.')
  } finally {
    isSubmittingSession.value = false
  }
}

const fetchSessionSummary = async () => {
  sessionError.value = ''
  actualBalance.value = null
  closeNotes.value = ''
  isLoadingSummary.value = true
  try {
    const res = await axios.get('/api/v1/cashier-sessions/summary')
    sessionSummary.value = res.data.data
    showCloseSessionModal.value = true
  } catch (err) {
    notice.error(errorMessage(err, 'Ringkasan kas gagal dimuat, jadi kas belum bisa ditutup. Coba lagi.'))
  } finally {
    isLoadingSummary.value = false
  }
}

const closeDifference = computed(() => {
  if (typeof actualBalance.value !== 'number' || !sessionSummary.value) return 0
  return actualBalance.value - Number(sessionSummary.value.expected_balance)
})

const closeSession = async () => {
  if (typeof actualBalance.value !== 'number') return
  if (closeDifference.value !== 0 && !closeNotes.value) return
  isSubmittingSession.value = true
  sessionError.value = ''
  try {
    await axios.post('/api/v1/cashier-sessions/close', {
      actual_balance: actualBalance.value,
      notes: closeNotes.value,
    })
    const difference = closeDifference.value
    showCloseSessionModal.value = false
    activeSession.value = null
    notice.success(
      difference === 0
        ? 'Kas ditutup, uang cocok. Tercatat di Riwayat Kas.'
        : `Kas ditutup dengan selisih ${difference > 0 ? 'lebih' : 'kurang'} ${formatRupiah(Math.abs(difference))}. Tercatat di Riwayat Kas beserta catatannya.`,
    )
    resetKasir()
  } catch (err: any) {
    sessionError.value = errorMessage(err, 'Kas gagal ditutup. Coba lagi.')
  } finally {
    isSubmittingSession.value = false
  }
}

// ---------- Student search ----------

const getPaymentMethods = async () => {
  methodsError.value = ''
  try {
    const res = await axios.get('/api/v1/payment-methods')
    paymentMethods.value = res.data.data
    const cash = paymentMethods.value.find((method) => method.code === 'CASH')
    selectedMethod.value = cash?.id ?? paymentMethods.value[0]?.id ?? null
  } catch (err) {
    methodsError.value = errorMessage(err, 'Metode pembayaran gagal dimuat.')
  }
}

const focusSearch = () => {
  nextTick(() => searchInput.value?.focus())
}

const hasSearchResults = computed(() => !!searchQuery.value && !isSearching.value && searchResults.value.length > 0)

const activeOptionId = computed(() => {
  const student = hasSearchResults.value ? searchResults.value[highlightedIndex.value] : null
  return student ? `opsi-siswa-${student.id}` : undefined
})

let searchTimeout: ReturnType<typeof setTimeout> | undefined
watch(searchQuery, (newVal) => {
  clearTimeout(searchTimeout)
  highlightedIndex.value = 0
  searchError.value = ''
  if (!newVal) {
    searchResults.value = []
    isSearching.value = false
    return
  }

  isSearching.value = true
  searchTimeout = setTimeout(async () => {
    try {
      const res = await axios.get('/api/v1/payments/students/search', { params: { q: newVal } })
      searchResults.value = res.data.data
    } catch (err) {
      searchResults.value = []
      searchError.value = errorMessage(err, 'Pencarian siswa gagal. Periksa koneksi, lalu ketik ulang.')
    } finally {
      isSearching.value = false
    }
  }, 300)
})

const onSearchKeydown = (event: KeyboardEvent) => {
  if (event.key === 'Escape') {
    searchQuery.value = ''
    return
  }

  if (!searchResults.value.length) return

  if (event.key === 'ArrowDown') {
    event.preventDefault()
    highlightedIndex.value = (highlightedIndex.value + 1) % searchResults.value.length
  } else if (event.key === 'ArrowUp') {
    event.preventDefault()
    highlightedIndex.value = (highlightedIndex.value - 1 + searchResults.value.length) % searchResults.value.length
  } else if (event.key === 'Enter') {
    event.preventDefault()
    selectStudent(searchResults.value[highlightedIndex.value])
  }
}

const selectStudent = async (student: any) => {
  selectedStudent.value = student
  searchQuery.value = ''
  searchResults.value = []
  cashReceived.value = null
  notes.value = ''

  nextTick(() => billsHeading.value?.focus())
  await loadStudentInvoices(student.id)
}

const loadStudentInvoices = async (studentId: number) => {
  isLoadingInvoices.value = true
  invoicesError.value = ''
  try {
    const res = await axios.get(`/api/v1/payments/student/${studentId}/invoices`)
    studentInvoices.value = res.data.data

    payAmounts.value = {}
    selectedItems.value = {}

    studentInvoices.value.forEach((invoice) => {
      invoice.items.forEach((item: any) => {
        payAmounts.value[item.id] = parseFloat(item.remaining_amount)
        selectedItems.value[item.id] = false
      })
    })
  } catch (err) {
    invoicesError.value = errorMessage(err, 'Tagihan siswa gagal dimuat. Periksa koneksi ke server, lalu coba lagi.')
  } finally {
    isLoadingInvoices.value = false
  }
}

// ---------- Bill selection ----------

const allItems = computed(() => studentInvoices.value.flatMap((invoice) => invoice.items))

const totalOutstanding = computed(() => allItems.value.reduce((sum: number, item: any) => sum + parseFloat(item.remaining_amount), 0))

const selectedItemList = computed(() => allItems.value.filter((item: any) => selectedItems.value[item.id]))

const itemErrors = computed(() => {
  const errors: Record<number, string> = {}
  selectedItemList.value.forEach((item: any) => {
    const amount = payAmounts.value[item.id]
    if (!amount || amount <= 0) {
      errors[item.id] = 'Nominal harus lebih dari Rp 0.'
    } else if (amount > parseFloat(item.remaining_amount)) {
      errors[item.id] = `Paling banyak ${formatRupiah(item.remaining_amount)}, sesuai sisa tagihan.`
    }
  })
  return errors
})

const isInvoiceFullySelected = (invoice: any) => invoice.items.length > 0 && invoice.items.every((item: any) => selectedItems.value[item.id])

const toggleInvoice = (invoice: any) => {
  const shouldSelect = !isInvoiceFullySelected(invoice)
  invoice.items.forEach((item: any) => {
    selectedItems.value[item.id] = shouldSelect
  })
}

const toggleItem = (itemId: number) => {
  selectedItems.value[itemId] = !selectedItems.value[itemId]
}

const payInFull = (item: any) => {
  payAmounts.value[item.id] = parseFloat(item.remaining_amount)
}

const isPartial = (item: any) => {
  const amount = payAmounts.value[item.id] || 0
  return amount > 0 && amount < parseFloat(item.remaining_amount)
}

// ---------- Payment ----------

const totalToPay = computed(() => selectedItemList.value.reduce((sum: number, item: any) => sum + (payAmounts.value[item.id] || 0), 0))

const selectedMethodObj = computed(() => paymentMethods.value.find((method) => method.id === selectedMethod.value))

const isCash = computed(() => selectedMethodObj.value?.code === 'CASH')

const change = computed(() => (cashReceived.value ?? 0) - totalToPay.value)

const cashSuggestions = computed(() => {
  const total = totalToPay.value
  if (total <= 0) return []
  const candidates = [total, Math.ceil(total / 10000) * 10000, Math.ceil(total / 50000) * 50000, Math.ceil(total / 100000) * 100000]
  return [...new Set(candidates)].slice(0, 4)
})

const blockingReason = computed(() => {
  if (selectedItemList.value.length === 0) return 'Pilih minimal satu tagihan.'
  if (Object.keys(itemErrors.value).length > 0) return 'Perbaiki nominal tagihan yang belum benar.'
  if (!selectedMethod.value) return 'Pilih metode pembayaran.'
  if (isCash.value && (cashReceived.value === null || cashReceived.value <= 0)) return 'Isi jumlah uang yang diterima.'
  if (isCash.value && change.value < 0) return `Uang diterima kurang ${formatRupiah(-change.value)}.`
  return null
})

const openConfirm = () => {
  if (blockingReason.value) return
  paymentError.value = ''
  confirmChecked.value = false
  showConfirmModal.value = true
}

const closeConfirm = () => {
  if (isProcessing.value) return
  showConfirmModal.value = false
}

const submitPayment = async () => {
  if (!confirmChecked.value || blockingReason.value) return

  isProcessing.value = true
  paymentError.value = ''
  try {
    const res = await axios.post('/api/v1/payments', {
      student_id: selectedStudent.value.id,
      payment_method_id: selectedMethod.value,
      notes: notes.value,
      items: selectedItemList.value.map((item: any) => ({
        invoice_item_id: item.id,
        amount: payAmounts.value[item.id],
      })),
    })

    successPayment.value = res.data.data
    successChange.value = isCash.value ? change.value : 0
    showConfirmModal.value = false
    showSuccessModal.value = true
    loadCardLines(res.data.data.id)
  } catch (err: any) {
    paymentError.value = errorMessage(err, 'Pembayaran gagal disimpan. Coba lagi.')
  } finally {
    isProcessing.value = false
  }
}

/** BaseModal focuses its first control after one tick; move focus to "Cetak kuitansi" right after it. */
watch(showSuccessModal, async (isOpen) => {
  if (!isOpen) return
  await nextTick()
  await nextTick()
  printButton.value?.focus()
})

const resetKasir = () => {
  showSuccessModal.value = false
  successPayment.value = null
  selectedStudent.value = null
  studentInvoices.value = []
  invoicesError.value = ''
  payAmounts.value = {}
  selectedItems.value = {}
  notes.value = ''
  cashReceived.value = null
  searchQuery.value = ''
  searchError.value = ''
  focusSearch()
}

const printReceipt = () => {
  if (successPayment.value) {
    window.open(`/pembayaran/${successPayment.value.id}/kuitansi`, '_blank')
  }
}

onMounted(() => {
  checkSession()
  getPaymentMethods()
})
</script>
