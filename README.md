# Portal Poltekkes Kemenkes Manado

Portal layanan digital berbasis PHP dengan panel admin untuk mengelola kategori, aplikasi, pengumuman, logo, dan banner. Data disimpan dalam JSON tanpa database MySQL.

## Menjalankan secara lokal

Dari direktori repositori, jalankan:

```sh
php -S 127.0.0.1:8080 -t .
```

Buka http://127.0.0.1:8080. Panel admin tersedia di `/admin.php`.

- Diuji menggunakan PHP 8.4.25.
- Folder `data` dan `images` harus dapat ditulis oleh proses PHP.
- Bootstrap dan ikon menggunakan CDN, sehingga memerlukan internet.
- Akun bawaan aplikasi: `admin` / `admin123`, dikonfigurasi di `login.php`.
- Alamat layanan yang belum tersedia dapat dilengkapi melalui panel admin.

## Perbaikan yang disertakan

- Kompatibilitas data lama dengan kolom `url` dan `deskripsi_kategori`.
- Formulir URL opsional dan pencegahan pengiriman ulang saat refresh.
- Validasi input, tanggal, tautan, dan unggahan gambar.
- Penyimpanan JSON dengan penguncian transaksi dan penggantian file melalui file sementara.
- Penanganan data JSON rusak serta pengaturan yang belum lengkap.
- Perbaikan posisi modal edit dan pembaruan sesi login.

## Pengujian

Memerlukan Node.js. Jalankan:

```sh
node tests/smoke.mjs
```

Jika PHP belum tersedia di PATH, tentukan `PHP_BINARY`. Contoh PowerShell:

```powershell
$env:PHP_BINARY = 'C:\path\to\php.exe'
node tests/smoke.mjs
```

Rangkaian pengujian berisi 69 pemeriksaan: sintaks PHP, login/logout, operasi tambah/edit/hapus, redirect, unggahan, validasi, data rusak, dan penulisan bersamaan. Pengujian menggunakan salinan sementara tanpa mengubah data portal asli. Tampilan browser dan ketersediaan situs tujuan eksternal belum diuji.
