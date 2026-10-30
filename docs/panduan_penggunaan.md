# Panduan Penggunaan Aplikasi Manajemen Pembayaran SMKN

Aplikasi ini membagi hak akses menjadi **Bendahara** (operator teknis transaksi) dan **Kepala Sekolah** (pemantauan, persetujuan, & laporan).

---

## 👑 1. Panduan Untuk Bendahara (Kasir & Operator)
Bendahara bertugas mengatur data inti, membuat tagihan, dan melayani pembayaran siswa secara langsung.

### A. Mengatur Data Master & Tarif (Setup Awal)
1. Masuk ke menu **Data Master**. Di sini Anda bisa mengelola *Tahun Ajaran*, *Jurusan*, *Kelas*, dan *Siswa*.
2. Masuk ke menu **Jenis Pembayaran** untuk mendefinisikan biaya (contoh: SPP [Bulanan], Uang Gedung [Tahunan], Seragam [Khusus]).
3. Masuk ke menu **Tarif Pembayaran** untuk menentukan harga masing-masing jenis pembayaran per Tahun Ajaran.

### B. Menerbitkan Tagihan Secara Massal
1. Buka menu **Tagihan (Invoices)**.
2. Klik tombol **Buat Tagihan Massal** untuk membuat tagihan otomatis (misal: membebankan SPP ke seluruh kelas X sekaligus).

### C. Menerima Pembayaran (Offline / Kasir)
Sebelum melayani pembayaran dari siswa yang datang, Bendahara WAJIB membuka Sesi Kasir agar aliran dana jelas.
1. Klik **Sesi Kasir** (di pojok kanan atas atau dashboard). Masukkan *Modal Awal* (contoh: Rp500.000 untuk kembalian), lalu klik **Buka Kasir**.
2. Masuk ke menu **Pembayaran**. Cari siswa berdasarkan Nama, NIS, atau NISN.
3. Klik pada siswa yang dimaksud, pilih tagihan yang ingin dibayar (contoh: SPP Juli & Agustus).
4. Masukkan nominal uang yang diterima, lalu klik **Bayar**. Status tagihan akan otomatis berubah (LUNAS/SEBAGIAN).
5. Pada akhir jam kerja, jangan lupa **Tutup Sesi Kasir** dan setorkan *Total Saldo Akhir*.

### D. Membatalkan Transaksi (Salah Input)
Jika Anda salah menginput pembayaran, Anda tidak bisa langsung menghapusnya demi keamanan pembukuan.
1. Masuk ke riwayat pembayaran siswa terkait.
2. Klik tombol **Batalkan Pembayaran** (Cancel).
3. Masukkan alasan pembatalan. Request ini akan dikirim ke Kepala Sekolah untuk disetujui.

---

## 🎓 2. Panduan Untuk Kepala Sekolah (Pemantau & Pemberi Persetujuan)
Kepala Sekolah berfokus pada analisis data, evaluasi laporan, dan keamanan transaksi.

### A. Memantau Dashboard & Laporan
1. Saat login, Anda langsung melihat **Dashboard** yang berisi metrik ringkasan (Total Pemasukan, Tunggakan, Persentase Kelancaran).
2. Anda bisa membuka menu **Laporan** untuk melihat rekapitulasi harian (uang masuk per hari), bulanan, maupun laporan tunggakan siswa per kelas/jurusan untuk evaluasi.

### B. Menyetujui/Menolak Pembatalan Transaksi
1. Buka menu **Persetujuan (Approvals)**.
2. Jika ada bendahara yang salah input dan meminta pembatalan transaksi, daftarnya akan muncul di sini.
3. Anda bisa memeriksa riwayat transaksinya, lalu menekan **Setujui** (uang akan dikembalikan secara logis dan tagihan siswa kembali muncul) atau **Tolak** jika alasan bendahara tidak valid.

### C. Memantau Log Aktivitas (Audit Log)
1. Buka menu **Audit Logs**.
2. Anda bisa memantau seluruh aktivitas sistem (kapan bendahara login, kapan kasir ditutup, transaksi yang dibuat, dll) agar terhindar dari kecurangan/manipulasi data.

---

## 📱 3. Panduan Pembayaran Online (Untuk Siswa / Wali Murid)
Fitur ini diproses oleh sistem di latar belakang tanpa campur tangan Bendahara.

1. Siswa login melalui Aplikasi Mobile / Portal Siswa.
2. Siswa melihat rincian tagihannya dan mengklik **Bayar Online**.
3. Sistem akan memunculkan *QRIS* atau *Virtual Account (VA)* (Via Midtrans).
4. Ketika siswa selesai mentransfer melalui Mobile Banking mereka, sistem secara otomatis:
   - Mencatatnya sebagai Pembayaran yang berhasil (*Success*).
   - Memotong sisa tagihan menjadi LUNAS.
   - Mengirim notifikasi otomatis ke WhatsApp Siswa/Wali Murid.
5. Dana ini tercatat terpisah dan **tidak masuk** ke laci Sesi Kasir milik Bendahara.
