# Inbound Return Scan System (Laragon Version)

Sistem Inbound Return berbasis Barcode/QR Scanner dengan auto camera, input multi-produk (Barcode, No. Batch, Exp Date, Qty, Type), deteksi otomatis kurir/ekspedisi (SPX Shopee, GTL GoTo, J&T, SiCepat, dll), dan panel admin monitoring dengan CRUD Master Ekspedisi.

> **Spesifikasi & Lingkungan:**
> - Berjalan **native menggunakan Laragon** (Apache + PHP 8.x + MySQL).
> - **Tidak membutuhkan Docker** dan **tidak membutuhkan Node / node_modules**.
> - Dilengkapi fitur **Auto-Migration**: Database, tabel, dan data master langsung terbuat otomatis saat pertama kali dibuka.

---

## 🖥️ Perintah Pasang & Jalankan di PC Server Baru

### 1. Di PC Server, Install Laragon:
1. Unduh dan pasang [Laragon](https://laragon.org/download/).
2. Buka Laragon lalu klik **Start All** (Apache & MySQL aktif).

### 2. Clone Repository dari GitHub ke Folder Web Laragon:
Buka Terminal / PowerShell di folder root Laragon (`C:\laragon\www\`):
```bash
git clone https://github.com/dhanielomarthinz-130/inbound_return.git retrun.inboud
```

### 3. Masuk ke Folder dan Jalankan:
```bash
cd retrun.inboud
```
Cukup klik ganda (double-click) file **`jalankan-server.bat`**, atau buka langsung melalui browser:

- **Halaman Scanner Operator**: [http://localhost/retrun.inboud/](http://localhost/retrun.inboud/)
- **Halaman Admin Monitoring**: [http://localhost/retrun.inboud/admin.php](http://localhost/retrun.inboud/admin.php)

---

## 🌐 Cara Akses dari PC / HP Operator Gudang Lain (Jaringan LAN / WiFi):
1. Cek alamat IP lokal PC Server Anda (di CMD ketik `ipconfig`), misalnya IP: `192.168.1.100`.
2. Dari PC Operator / Barcode Scanner Gun yang terhubung ke Wi-Fi / kabel LAN yang sama, buka:
   - **Operator Scanner**: `http://192.168.1.100/retrun.inboud/`
   - **Admin Monitoring**: `http://192.168.1.100/retrun.inboud/admin.php`

---

## 🗄️ Database & Konfigurasi (`config.php`):
- **Host**: `127.0.0.1` / `localhost`
- **User**: `root`
- **Password**: *(kosong)*
- **Database**: `inbound_return`
- Database dan seluruh tabel berikut dibuat otomatis (*auto-migration*):
  1. `master_products`: Data produk, SKU, barcode, dan satuan.
  2. `master_expeditions`: Data armada/kurir dengan prefix auto-detect (SPX, GTL, JNT, dll).
  3. `return_sessions`: Data transaksi sesi return (invoice, kurir/ekspedisi, operator, total barang).
  4. `return_items`: Rincian per invoice (barcode, nama produk, batch, exp date, qty, tipe kondisi).

---

## 🚚 Deteksi Ekspedisi Otomatis (Auto-Detect):
Saat operator menembakkan barcode resi/invoice menggunakan Barcode Scanner Gun, sistem otomatis mengenali ekspedisi berdasarkan prefix:
- `SPXID...`, `SPX...`, `ID...` $\rightarrow$ **Shopee Xpress (SPX)**
- `GTL...`, `TKP...`, `GOTO...` $\rightarrow$ **GoTo Logistics (GTL)**
- `JP...`, `JX...`, `JT...` $\rightarrow$ **J&T Express**
- `00...`, `SC...` $\rightarrow$ **SiCepat Ekspres**
- `JNE...`, `01...` $\rightarrow$ **JNE Express**
- `100...`, `AP...` $\rightarrow$ **AnterAja**

*(Daftar awalan prefix dapat ditambah dan diubah secara bebas melalui menu **Master Ekspedisi** di halaman Admin).*

---

## 🏷️ Barcode Dummy untuk Pengujian Cepat:
- `8991001` - Kipas Angin Portable USB
- `8991002` - Earphone TWS Bluetooth 5.3
- `8991003` - Powerbank 10.000mAh Fast Charging
- `8991004` - Adaptor Charger 20W Type C
- `8991005` - Smart Lampu Bohlam LED 9W
