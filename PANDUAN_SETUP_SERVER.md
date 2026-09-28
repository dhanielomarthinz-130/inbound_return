# 📖 Panduan Lengkap Instalasi & Update Server Lokal (Inbound Return IEG)

Panduan ini ditujukan bagi Administrator atau Tim IT untuk melakukan instalasi awal dan pembaruan (*update*) sistem **Inbound Return IEG** pada PC Server Lokal (di kantor atau gudang) langsung dari GitHub.

---

## 📂 Folder Alat Server (`server-tools/`)

Seluruh file otomasi server telah dikelompokkan ke dalam folder **`server-tools/`**:

| Nama File | Fungsi | Kapan Digunakan? |
|---|---|---|
| **`1_INSTAL_SERVER_BARU.bat`** | Menginstal database, tabel, user, dan mengonfigurasi sistem pertama kali | Saat memasang aplikasi di PC Server baru |
| **`2_UPDATE_DARI_GITHUB.bat`** | Menarik update kode terbaru dari GitHub (`git pull`) & migrasi database | Setiap kali ada pembaruan fitur dari GitHub |
| **`3_JALANKAN_SERVER.bat`** | Memeriksa MySQL, mendeteksi IP LAN/Wi-Fi, dan membuka browser | Setiap hari saat PC Server dinyalakan |
| **`install-server.ps1`** | Script inti otomasi PowerShell | Dipanggil oleh file batch di atas |

---

## 🚀 Langkah 1: Persiapan PC Server

Sebelum menjalankan instalasi, pastikan hal-hal berikut sudah terpasang di PC Server:
1. **XAMPP** atau **Laragon**:
   - Pastikan service **Apache** dan **MySQL** sudah dalam status **RUNNING / START**.
2. **Git for Windows** *(Opsional tapi direkomendasikan)*:
   - Jika Git belum ada, script akan otomatis mengunduh versi ZIP dari GitHub.

---

## 🛠️ Langkah 2: Cara Instalasi di PC Server Baru

Pilih salah satu dari 2 cara termudah di bawah ini:

### Cara A: Cukup Double-Click (Jika File Sudah Di-download)
1. Buka folder proyek di Windows Explorer.
2. Masuk ke folder **`server-tools/`**.
3. Klik dua kali (**Double-click**) file:
   👉 **`1_INSTAL_SERVER_BARU.bat`**
4. Script akan otomatis:
   - Memeriksa koneksi PHP & MySQL.
   - Membuat database `inbound_return` jika belum ada.
   - Membuat semua tabel dan auto-patch kolom PIN.
   - Menambahkan 4 akun resmi default.
   - Menarik data master produk dari OCS WMS jika tabel produk masih kosong.

---

### Cara B: Menggunakan 1 Baris Perintah PowerShell (Dari Server Kosong)
Jika di PC Server Anda belum ada file proyek sama sekali, Anda bisa langsung menarik dan menginstalnya langsung dari GitHub:

1. Buka **PowerShell** (atau Command Prompt / CMD) di PC Server.
2. Copy dan paste perintah berikut, lalu tekan **Enter**:

```powershell
powershell -ExecutionPolicy Bypass -Command "iwr -useb https://raw.githubusercontent.com/dhanielomarthinz-130/inbound_return/main/install-server.ps1 | iex"
```

Script akan otomatis mengunduh source code ke folder web server (`C:\xampp\htdocs\inbound_return` atau `C:\laragon\www\inbound_return`), menyiapkan database, dan menyalakan sistem.

---

## 🔄 Langkah 3: Cara Update Sistem dari GitHub Kapan Saja

Ketika tim pengembang telah melakukan perubahan fitur dan push ke GitHub, Anda **TIDAK PERLU** menginstal ulang atau copy-paste file manual.

Cukup lakukan langkah berikut di PC Server lokal Anda:

1. Buka folder **`server-tools/`**.
2. Klik dua kali (**Double-click**) file:
   👉 **`2_UPDATE_DARI_GITHUB.bat`**
3. Sistem akan otomatis:
   - Mengambil update terbaru (`git pull origin main`).
   - Menyesuaikan struktur tabel database jika ada kolom baru.
   - Memastikan akun pengguna tetap aktif dan terverifikasi.
   - Menampilkan notifikasi hijau bahwa sistem sudah siap!

---

## 🌐 Langkah 4: Menghubungkan HP / Barcode Scanner di Gudang

Sistem ini didesain agar dapat diakses oleh banyak operator sekaligus melalui jaringan Wi-Fi / LAN yang sama:

1. Jalankan **`3_JALANKAN_SERVER.bat`**.
2. Script akan menampilkan **Alamat IP Server Lokal** Anda, misalnya: `192.168.1.50`.
3. Buka browser (Chrome / Safari / Edge) di HP atau Barcode Scanner nirkabel operator gudang, lalu akses:
   ```
   http://192.168.1.50/inbound_return/
   ```
   *(Ganti `192.168.1.50` dengan IP PC Server yang muncul di layar)*.
4. Operator dapat langsung melakukan pemindaian resi dan barang tanpa perlu kabel ke PC!

---

## 🔐 Informasi Akun Resmi Sistem

| No | Role / Hak Akses | Username | Password | PIN Operator | Keterangan |
|:---:|:---|:---|:---|:---:|:---|
| 1 | **Superadmin** | `Daniel` | `Dh@niel0` | - | Hak akses penuh: Master Ekspedisi, User, Mode Maintenance, Master Kondisi, Dashboard |
| 2 | **Admin** | `Admin` | `Password01` | - | Hak akses monitoring: Dashboard, Master Produk, Ekspor Excel |
| 3 | **Operator 1** | `Operator 1` | `Password01` | `123456` | Layar scanner barcode barang masuk & rekam video unboxing |
| 4 | **Operator 2** | `Operator 2` | `Password01` | `123456` | Layar scanner barcode barang masuk & rekam video unboxing |

> [!TIP]
> Pada halaman login operator, operator cukup memilih namanya dari menu dropdown **Pilih Operator Inbound** lalu memasukkan 6 angka PIN menggunakan tombol Numpad interaktif di layar.

---

## ❓ Pemecahan Masalah (Troubleshooting)

1. **MySQL Tidak Terhubung / Gagal Koneksi:**
   - Buka XAMPP Control Panel atau Laragon.
   - Pastikan modul **Apache** dan **MySQL** berwarna hijau (Running).
2. **Kamera Tidak Muncul di HP / Tablet Saat Mengakses via IP:**
   - Browser modern (seperti Chrome di Android/iOS) membatasi akses kamera belakang jika tidak melalui `https://` atau `localhost`.
   - **Solusi:** Di HP Android, buka Chrome lalu ketik `chrome://flags/#unsafely-treat-insecure-origin-as-secure`, masukkan URL IP server lokal (contoh: `http://192.168.1.50:8080`), pilih **Enable**, lalu restart Chrome. Kamera scanner akan langsung aktif normal!
3. **Firewall Windows Memblokir Akses dari Perangkat Lain:**
   - Pastikan profil jaringan di Windows diatur ke **Private Network**, bukan Public.
   - Berikan izin (*Allow access*) untuk Apache HTTP Server pada Windows Defender Firewall.
