# Inbound Return Scan System (Laragon Version)

Sistem Inbound Return berbasis Barcode/QR Scanner dengan auto camera, input multi-produk, kategorisasi kondisi (Good / Rusak), dan panel monitoring admin.

> **Catatan Lingkungan:**
> Aplikasi ini berjalan **native menggunakan Laragon** (Apache + PHP 8.x + MySQL). **Tidak membutuhkan Docker dan tidak memerlukan Node server**.

---

## 🚀 Cara Menjalankan dengan Laragon:

1. Buka aplikasi **Laragon**.
2. Klik tombol **Start All** (Apache & MySQL aktif).
3. Buka browser dan akses:
   - **Operator Inbound Scanner**: [http://localhost/retrun.inboud/](http://localhost/retrun.inboud/) (atau `http://retrun.inboud.test/`)
   - **Admin Monitoring Dashboard**: [http://localhost/retrun.inboud/admin.php](http://localhost/retrun.inboud/admin.php) (atau `http://retrun.inboud.test/admin.php`)

---

## 🗄️ Database & Konfigurasi:

- **Host**: `127.0.0.1` / `localhost`
- **User**: `root`
- **Password**: *(kosong)*
- **Database**: `inbound_return`
- Database dan tabel dibuat secara otomatis (*auto-migration*) saat aplikasi pertama kali dibuka melalui `config.php`.

### Struktur Tabel:
1. `master_products`: Data master barang & barcode.
2. `return_sessions`: Header sesi retur invoice.
3. `return_items`: Rincian produk per invoice dengan status kondisi (GOOD / RUSAK) & catatan kerusakan.

---

## 🏷️ Barcode Dummy untuk Pengujian:

- `8991001` - Kipas Angin Portable USB
- `8991002` - Earphone TWS Bluetooth 5.3
- `8991003` - Powerbank 10.000mAh Fast Charging
- `8991004` - Adaptor Charger 20W Type C
- `8991005` - Smart Lampu Bohlam LED 9W

---

## ✨ Fitur Utama:
1. **Auto Open Camera**: Kamera langsung aktif untuk scan barcode/QR.
2. **Scan Invoice**: Mengunci sesi invoice pengembalian.
3. **Scan Barcode Multi-Product**: Menambahkan banyak barang dalam satu sesi invoice.
4. **Kondisi GOOD & RUSAK**: Pemisahan barang layak restock vs rusak/afkir.
5. **Admin Monitoring & Export CSV**: Realtime KPI summary, chart kualitas rasio, filter tanggal, dan export laporan CSV.
