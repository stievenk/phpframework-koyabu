
# Koyabu Webapi - Form Framework Documentation
[![Latest Stable Version](https://poser.pugx.org/koyabu/webapi/downloads)](https://packagist.org/packages/koyabu/webapi)

**Koyabu Framework** adalah library PHP utilitas yang dirancang untuk mempercepat pengembangan aplikasi web melalui abstraksi database, pengolahan gambar, manajemen waktu, dan integrasi API pihak ketiga seperti Dropbox, QR Code, dan Google 2FA.

## Informasi Versi
* **Versi Core**: 8.2.2
* **Terakhir Diperbarui**: 14 April 2026
* **Kebutuhan Minimum**: PHP 8.3+
* **Rekomendasi Database**: MariaDB 10+ atau MySQL 8+

---

## Fitur Utama

### 1. Database Wrapper (Multi-Driver)
Mendukung driver `mysql`, `mysqli`, `pdo`, dan `odbc` secara otomatis melalui konfigurasi.
* **`get($params)`**: Mengambil satu baris data berdasarkan kriteria field tunggal atau array menggunakan operasi AND.
* **`saveTable($params)`**: Otomatis memfilter data berdasarkan struktur tabel yang ada. Mendukung metode `INSERT`, `UPDATE`, `REPLACE`, dan `DUPLICATEUPDATE` (`ON DUPLICATE KEY UPDATE`).
* **`delete($params, $table)`**: Menghapus data berdasarkan kriteria array atau query SQL mentah.
* **Transaction Support**: Dilengkapi dengan metode `start_transaction()`, `commit_transaction()`, dan `rollback_transaction()`.

### 2. Utilitas Angka & Lokalisasi (ID/EN)
* **`terbilang($nilai)`**: Konversi angka ke teks bahasa Indonesia, mendukung nilai negatif dan angka desimal (koma).
* **`numberShort($num, $lan, ...)`**: Menyingkat angka besar (contoh: 1.5M / 1.5Jt) dengan dukungan satuan dari Ribuan hingga Kuintiliun dalam bahasa Indonesia atau Inggris.

### 3. Pengolahan Gambar & Filter Visual
* **`resizeAndWatermarkImage($params)`**:
    * Mengubah ukuran gambar secara proporsional sesuai rasio aspek.
    * Mendukung 9 posisi watermark (seperti `top-right`, `center`, `bottom-left`).
    * **Filter Visual**: Pixelate, Negatif, Smooth, Colorize, Gaussian Blur, dan Selective Blur.

### 4. Markdown & Teks Parser
* **`markdownToHtml($markdown)`**: Konverter Markdown ke HTML yang mendukung:
    * Blok kode, tabel, daftar (list), dan kutipan (blockquote).
    * **Auto-Link Detection**: Otomatis mendeteksi URL, Email, dan nomor telepon.
    * **WhatsApp Integration**: Otomatis mendeteksi nomor telepon Indonesia dan mengarahkannya ke link `wa.me`.

### 5. Keamanan & Integrasi API
* **QR Code**: Generate QR Code ke format Base64 atau file fisik, serta fitur `QRcodeRead` untuk membaca isi file QR.
* **Google 2FA**: Membangun sistem otentikasi dua faktor termasuk pembuatan Secret Key dan validasi OTP.
* **Dropbox Storage**: Integrasi upload (overwrite) dan delete file dengan pembuatan shared link secara otomatis.
* **Logging System**: Mencatat log aktivitas atau error secara otomatis ke database tabel `z_debug` dan file fisik.

---

## Contoh Penggunaan

### Inisialisasi & Simpan Data
```php
use Koyabu\Webapi\Form;

$config = [
    'database' => [
        'driver' => 'mysqli',
        'host'   => 'localhost',
        'user'   => 'root',
        'pass'   => 'password',
        'name'   => 'nama_database'
    ]
];

$form = new Form($config);

// Menyimpan data dengan auto-filter field tabel
$id = $form->save(['username' => 'stieven', 'status' => 'aktif'], 'users');
```

### Konversi Angka & Waktu
```php
// Hasil: seratus lima puluh ribu koma lima
echo $form->terbilang(150000.50);

// Hasil: Rp 2.5Jt
echo $form->numberShort(2500000, 'ID', 1, 'SHORT', 'Rp');

// Hasil: 1 Hari 1 Jam 1 Menit 1 Detik
echo $form->formatWaktu(90061);
```

---

## Instalasi
```bash
composer require koyabu/webapi:^v8.2.2
```

### composer.json
```json
{
    "minimum-stability": "dev",
    "prefer-stable": false
}
```

**Author**: stieven.kalengkian@gmail.com
```