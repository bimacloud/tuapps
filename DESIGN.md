# DESIGN.md: Sistem Pembayaran SMK

Arah desain ini ditulis dari jawaban pemilik proyek (30 Oktober 2026). Agen memformat, bukan mengarang.

## Identitas

- Nama sekolah, alamat, kontak, dan logo diambil dari menu **Pengaturan** (`school_name`, `school_address`, `school_phone`, `school_email`, `school_logo`). Jangan pernah menulis nama sekolah atau logo secara hardcode.
- Sumber rasa visual: flyer PPDB sekolah. Oranye hangat, pita miring, huruf sans kondensed tebal.

## Pengguna dan nuansa

- Pengguna harian: staf TU / bendahara (kasir, tagihan, master data) dan kepala sekolah (pantau, setujui).
- Nuansa: **nyaman untuk TU**. Tenang, mudah dibaca seharian, angka rupiah jelas, tidak ramai.
- Tema: **terang saja**. Tidak ada mode gelap dan tidak ada tombol tema.

Dial: ENERGY 2 / RHYTHM 1 / MOTION 1

- ENERGY 2: warna sekolah terasa (sidebar cokelat, aksen oranye), tapi area kerja tetap tenang.
- RHYTHM 1: semua halaman memakai susunan yang sama (judul, alat, data). Disengaja, supaya TU tidak perlu belajar ulang tiap menu.
- MOTION 1: hanya transisi hover dan fokus (150ms). Tanpa animasi masuk.

## Palet (dari pemilik)

| Token | Hex | Peran |
|---|---|---|
| `krem` | `#FEF3E2` | Latar halaman |
| `amber` | `#F4AE52` | Penanda menu aktif (pita miring) dan sorotan, selalu dengan teks `tinta` |
| `bata` | `#D4621A` | Aksen besar saja: teks 18px+ tebal, garis fokus, elemen non-teks |
| `tinta` | `#2A1A0E` | Teks utama, latar sidebar |

Turunan untuk keterbacaan (bukan warna baru, hanya tingkat terang dari palet di atas):

| Token | Hex | Alasan |
|---|---|---|
| `bata-tua` | `#B04E14` | `bata` hanya 3,78:1 di atas putih. Tombol utama dan tautan butuh 4,5:1; `bata-tua` 5,32:1 |
| `tinta-2` | `#6B5444` | Teks sekunder. 6,43:1 di atas krem, 7,05:1 di atas putih |
| `tinta-3` | `#3D2817` | Hover di sidebar |
| `pasir` | `#C4AE93` | Teks redup di sidebar. 7,84:1 di atas `tinta` |
| `garis` | `#E8D9C3` | Pemisah dekoratif (bukan batas kontrol) |
| `garis-kuat` | `#8C7560` | Batas input dan kontrol. 4,35:1 di atas putih |
| `kertas` | `#FFFFFF` | Permukaan panel, tabel, form |

Warna status (fungsional, selalu bersama teks label):

| Status | Teks / latar | Kontras |
|---|---|---|
| Lunas | `#1E6B3A` / `#E4F2E7` | 5,64:1 |
| Belum lunas | `#8A3B0C` / `#FDEBDD` | 6,68:1 |
| Menunggu / sebagian | `#2A1A0E` / `#FBE3BF` | 13,45:1 |
| Dibatalkan / nonaktif | `#6B5444` / `#EFE6DA` | 5,71:1 |
| Galat | `#B3261E` / `#FCE8E6` | 5,55:1 |

## Tipografi

- Judul, nama sekolah, angka besar: **Barlow Condensed** (600/700). Alasan: flyer sekolah memakai sans kondensed tebal; ini membawa karakternya.
- Teks, tabel, form: **Barlow** (400/500/600). Alasan: satu keluarga dengan judul, jelas di ukuran kecil, angka tabular untuk rupiah (`tabular-nums`).
- Ukuran dasar 15px. Tidak ada huruf kapital dengan tracking lebar.

## Motif identitas

**Pita miring**: blok dengan sisi kanan terpotong miring, diambil dari pita label di flyer. Dipakai hanya di:

1. Item menu aktif di sidebar (amber di atas cokelat).
2. Penanda tahun pelajaran aktif di header.

Tidak dipakai sebagai hiasan di tempat lain.

## Bentuk

- Radius: 6px untuk tombol dan input, 10px untuk panel dan modal. Pita miring tanpa radius.
- Bayangan: tidak ada pada panel (cukup garis). Hanya modal dan menu tarik-turun yang terangkat.
- Ikon: hanya di menu sidebar untuk membantu memindai 10+ menu. Tombol cukup teks.

## Cetak

- Kertas: A4 (asumsi sementara, 31 Oktober 2026). Proporsi diambil dari kartu lama sekolah (dibuat di Letter): baris bulanan 10 mm, baris semester 9,4 mm, tabel sekitar 12 cm; semuanya muat di A4.
- Kartu dicetak kosong per kelas di awal tahun (nama, kelas, tahun pelajaran terisi). Saat siswa membayar, bendahara menulis tanggal dan jumlah dari layar kasir lalu memaraf. SPP yang dicicil ditulis sekali saat lunas dengan total sebulan; cicilan semester/tahunan ditulis per cicilan. Kuitansi dicetak setiap pembayaran, termasuk cicilan.
- Kartu bukti pembayaran: A4 landscape dibagi dua. Kiri **Administrasi Bulanan** (Juli s.d. Juni), kanan **Administrasi Semester dan Tahunan** dengan uraian tetap seperti contoh: Daftar Ulang (4 baris), LKS/Buku Semester 1 (2), LKS/Buku Semester 2 (2), ASTS 1, ASAS 1, ASTS 2, ASAT, PKL, Asesmen Akhir (2), baris 10 kosong.
- Terisi otomatis dari data pembayaran siswa. Kolom paraf dibiarkan kosong.
- Hitam putih, garis tipis, supaya hemat tinta dan mirip kartu fisik yang sudah dipakai.
