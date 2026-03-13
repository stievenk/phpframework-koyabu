# Koyabu Webapi - Form Framework Documentation
[![Latest Stable Version](https://poser.pugx.org/koyabu/webapi/downloads)]([https://poser.pugx.org/koyabu/webapi/downloads](https://packagist.org/packages/koyabu/webapi))

## Installation
```composer require koyabu/webapi:^v8.2.0```

## composer.json
If your get error about minimum-stability, edit your ```composer.json```
```
{
    "minimum-stability": "dev",
    "prefer-stable": false
}
```

## Contoh Penggunaan
```php
use Koyabu\Webapi\Form;

$config = [
    'database' => [
        'driver' => 'mysqli',
        'host'   => 'localhost',
        'user'   => 'root',
        'pass'   => 'password',
        'name'   => 'database_name'
    ],
    'dropbox' => [
        'access_token' => 'YOUR_TOKEN',
        'home_dir' => 'uploads'
    ]
];

$form = new Form($config);

// Simpan Data
$id = $form->save(['username' => 'stieven', 'status' => 'active'], 'users');

// Ambil Data
$data = $form->get(['table' => 'users', 'data' => ['username' => 'stieven']]);

// Terbilang
echo $form->terbilang(1500.50); // seribu lima ratus koma lima

// Singkatan Angka
echo $form->numberShort(2500000, 'ID', 1, 'SHORT', 'Rp'); // Rp 2.5Jt

echo $form->formatWaktu(90061); // 1 Hari 1 Jam 1 Menit 1 Detik
```

**Koyabu Framework** adalah library PHP utilitas yang dirancang untuk menangani operasi database, pengolahan gambar, manajemen waktu, dan integrasi API pihak ketiga (Dropbox, QR Code, Google 2FA).

## Informasi Versi
* **Versi Core:** 8.2.2
* **Terakhir Diperbarui:** 13 Maret 2026
* **Kebutuhan Minimum:** PHP 8.1+
* **Database:** MariaDB 10+ atau MySQL 8+

---

## Fitur Utama

### 1. Database Wrapper
Menyediakan antarmuka yang seragam untuk berbagai driver database.
* **`get($params)`**: Mengambil satu baris data berdasarkan field atau kriteria array.
* **`save($data, $table, $method, $primary)`**: Menyimpan data dengan opsi `INSERT`, `UPDATE`, `REPLACE`, atau `ON DUPLICATE KEY UPDATE`.
* **`delete($params, $table)`**: Menghapus data berdasarkan kriteria tertentu.
* **`query($query)`**: Eksekusi query SQL mentah secara aman.

### 2. Utilitas Angka & Mata Uang
* **`terbilang($nilai)`**: Mengonversi angka menjadi teks bahasa Indonesia (Mendukung hingga Triliun, angka negatif, dan desimal).
* **`numberShort($num, $lan, $decnum, $tipe, $currency)`**: Menyingkat angka besar (contoh: 1.2jt) dengan dukungan bahasa Indonesia (ID) atau Inggris (EN).

### 3. Pengolahan Gambar & Filter
* **`resizeAndWatermarkImage($params)`**: Mengubah ukuran gambar, memberikan watermark dengan posisi fleksibel, dan menerapkan filter (Blur, Pixelate, Negatif, Colorize).

### 4. Manajemen Waktu
* **`formatWaktu($detik)`**: Format deskriptif "x Tahun x Bulan x Hari...".
* **`timeToShort($time)`**: Mengambil satuan waktu terdekat (Hari/Jam/Menit/Detik).
* **`umur($tgl)`**: Menghitung umur berdasarkan tanggal lahir.

### 5. Keamanan & Integrasi
* **QR Code:** Render QR Code ke Base64 atau file fisik.
* **Google 2FA:** Generasi Secret Key dan QR Code OTP.
* **Dropbox:** Upload (overwrite) dan delete file menggunakan Dropbox API.
* **Markdown Parser:** Konversi teks Markdown ke HTML dengan deteksi otomatis link WhatsApp, Telepon, Email, dan Tabel.

---

