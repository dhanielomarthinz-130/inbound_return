<?php
/**
 * claim_dossier.php
 * Halaman Mandiri Berkas Bukti Klaim & Banding Ekspedisi
 * PT. IEG Inovasi Eka Gemilang
 */
require_once __DIR__ . '/config.php';

// Pastikan user login
$currentUser = requireLogin();

$query = trim($_GET['q'] ?? $_GET['query'] ?? $_GET['order_id'] ?? $_GET['tracking_number'] ?? '');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Berkas Klaim Ekspedisi - <?= htmlspecialchars($query ?: 'Pusat Klaim') ?> - IEG Inovasi Eka Gemilang</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .font-mono { font-family: 'JetBrains Mono', monospace; }
        @media print {
            @page { size: A4 portrait; margin: 8mm 10mm; }
            body { background: white !important; color: #0f172a !important; padding: 0 !important; }
            .no-print { display: none !important; }
            .print-break-inside-avoid { break-inside: avoid !important; page-break-inside: avoid !important; }
            .print-border { border: 1px solid #cbd5e1 !important; }
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        }
    </style>
</head>
<body class="bg-slate-100 min-h-screen text-slate-800 antialiased p-3 sm:p-6 lg:p-8">

    <div class="max-w-6xl mx-auto space-y-5">
        <!-- Top Toolbar / Action Buttons (No Print) -->
        <div class="bg-white rounded-2xl shadow-xs border border-slate-200 p-3 sm:p-4 flex flex-wrap items-center justify-between gap-3 no-print">
            <div class="flex items-center gap-3">
                <a href="admin.php#claims" class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition flex items-center gap-2">
                    <i class="fa-solid fa-arrow-left"></i>
                    <span>Kembali ke Admin</span>
                </a>
                <span class="text-xs font-bold text-slate-400">|</span>
                <span class="text-xs text-slate-500 font-medium">Halaman Berkas Klaim Resmi</span>
            </div>
            <div class="flex items-center gap-2">
                <button onclick="copyClaimSummaryText()" class="px-3.5 py-2 bg-slate-800 hover:bg-slate-700 text-white text-xs font-bold rounded-xl transition flex items-center gap-1.5 shadow-2xs">
                    <i class="fa-solid fa-copy"></i>
                    <span>Salin Bukti Klaim</span>
                </button>
                <button onclick="window.print()" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl transition flex items-center gap-1.5 shadow-sm shadow-emerald-600/20">
                    <i class="fa-solid fa-print"></i>
                    <span>Cetak Dokumen (A4)</span>
                </button>
                <button onclick="window.close()" class="px-3 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 text-xs font-bold rounded-xl transition">
                    <i class="fa-solid fa-xmark"></i>
                    <span>Tutup</span>
                </button>
            </div>
        </div>

        <!-- Loading State -->
        <div id="claimLoadingState" class="bg-white rounded-3xl shadow-sm border border-slate-200 p-12 text-center space-y-4">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-amber-50 text-amber-600 text-2xl animate-spin">
                <i class="fa-solid fa-spinner"></i>
            </div>
            <div>
                <h3 class="font-bold text-base text-slate-800">Menyusun Berkas Klaim...</h3>
                <p class="text-xs text-slate-400 mt-1">Mengambil data Orders OCS lokal, rekaman video unboxing, data receiving dan bukti foto.</p>
            </div>
        </div>

        <!-- Error State -->
        <div id="claimErrorState" class="hidden bg-white rounded-3xl shadow-sm border border-rose-200 p-12 text-center space-y-4">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-rose-50 text-rose-600 text-2xl">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>
            <div>
                <h3 id="claimErrorTitle" class="font-bold text-base text-rose-800">Data Klaim Tidak Ditemukan</h3>
                <p id="claimErrorMessage" class="text-xs text-slate-500 mt-1 max-w-md mx-auto">Nomor resi atau pesanan belum terdaftar di sistem unboxing maupun data orders OCS.</p>
            </div>
            <div class="pt-2">
                <a href="admin.php#claims" class="px-4 py-2 bg-slate-800 text-white text-xs font-bold rounded-xl inline-flex items-center gap-2">
                    <i class="fa-solid fa-arrow-left"></i> Kembali Cari Nomor Lain
                </a>
            </div>
        </div>

        <!-- Main Dossier Card (Printable) -->
        <div id="claimMainCard" class="hidden bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden print:border-none print:shadow-none print:rounded-none">
            
            <!-- KOP SURAT RESMI BERLOGO -->
            <div class="p-6 sm:p-8 border-b-2 border-slate-900 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-slate-50/50 print:bg-white">
                <div class="flex items-center gap-4">
                    <img src="assets/image/logo-IEG.png" alt="Logo IEG" class="h-16 w-auto object-contain shrink-0">
                    <div>
                        <h1 class="font-black text-lg sm:text-xl text-slate-900 tracking-tight leading-tight">IEG Inovasi Eka Gemilang</h1>
                        <p class="text-xs font-bold text-slate-600 uppercase tracking-wider">Warehouse Return &amp; Dispute</p>
                        <p class="text-[11px] text-slate-400">Divisi Penyelesaian Klaim &amp; Banding Asuransi Ekspedisi / Marketplace</p>
                    </div>
                </div>
                <div class="text-left sm:text-right shrink-0">
                    <div class="inline-block bg-slate-900 text-white font-black text-xs px-3 py-1 rounded tracking-wider uppercase mb-1 shadow-2xs">
                        BERITA ACARA KLAIM EKSPEDISI
                    </div>
                    <div class="font-mono font-bold text-xs text-slate-600" id="claimDocNumber">Ref: -</div>
                    <div class="text-[10px] text-slate-400">Tanggal Cetak: <b class="text-slate-700" id="claimPrintDate"><?= date('d/m/Y H:i') ?></b></div>
                </div>
            </div>

            <div class="p-6 sm:p-8 space-y-6">
                <!-- Status Banner Kelayakan Klaim -->
                <div id="claimStatusBanner" class="rounded-2xl p-4 sm:p-5 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border">
                    <div class="flex items-start gap-3.5">
                        <span id="claimStatusIcon" class="w-10 h-10 rounded-xl flex items-center justify-center text-lg font-bold shrink-0"></span>
                        <div>
                            <span id="claimStatusBadge" class="inline-block px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider mb-1"></span>
                            <h2 id="claimMainTitle" class="font-black text-base sm:text-lg tracking-tight text-slate-900 leading-tight">Memeriksa kelayakan klaim...</h2>
                            <p id="claimReasonText" class="text-xs mt-0.5 font-medium"></p>
                        </div>
                    </div>
                    <div class="text-left sm:text-right shrink-0 bg-white/70 p-3 rounded-xl border border-slate-200/80 shadow-2xs">
                        <span class="text-[10px] uppercase font-bold text-slate-500 block">Total Tuntutan Nilai Klaim:</span>
                        <span id="claimTotalAmountDisplay" class="font-mono font-black text-xl sm:text-2xl text-emerald-700">Rp 0</span>
                        <span id="claimPriceSubDisplay" class="text-[10px] text-slate-400 block font-mono">Nilai Barang: Rp 0</span>
                    </div>
                </div>

                <!-- 4 Indikator Kelengkapan Checklist Bukti -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-xs print-break-inside-avoid">
                    <div id="checkOrderBox" class="p-3 rounded-2xl border border-slate-200 bg-slate-50/60 flex items-center gap-2.5">
                        <i id="checkOrderIcon" class="fa-solid fa-circle-check text-emerald-500 text-lg"></i>
                        <div>
                            <span class="font-bold text-slate-800 block text-xs">Data Order OCS</span>
                            <span id="checkOrderLabel" class="text-[10px] text-slate-500">Tersinkronisasi</span>
                        </div>
                    </div>
                    <div id="checkRecBox" class="p-3 rounded-2xl border border-slate-200 bg-slate-50/60 flex items-center gap-2.5">
                        <i id="checkRecIcon" class="fa-solid fa-circle-check text-emerald-500 text-lg"></i>
                        <div>
                            <span class="font-bold text-slate-800 block text-xs">Serah Terima Kurir</span>
                            <span id="checkRecLabel" class="text-[10px] text-slate-500">Terverifikasi</span>
                        </div>
                    </div>
                    <div id="checkUnboxBox" class="p-3 rounded-2xl border border-slate-200 bg-slate-50/60 flex items-center gap-2.5">
                        <i id="checkUnboxIcon" class="fa-solid fa-circle-check text-emerald-500 text-lg"></i>
                        <div>
                            <span class="font-bold text-slate-800 block text-xs">Video Unboxing</span>
                            <span id="checkUnboxLabel" class="text-[10px] text-slate-500">Terekam di Stasiun</span>
                        </div>
                    </div>
                    <div id="checkPhotosBox" class="p-3 rounded-2xl border border-slate-200 bg-slate-50/60 flex items-center gap-2.5">
                        <i id="checkPhotosIcon" class="fa-solid fa-circle-check text-emerald-500 text-lg"></i>
                        <div>
                            <span class="font-bold text-slate-800 block text-xs">Foto Bukti Fisik</span>
                            <span id="checkPhotosLabel" class="text-[10px] text-slate-500">Tersimpan</span>
                        </div>
                    </div>
                </div>

                <!-- Tiga Kolom Cross-Reference (Orders OCS, Receiving, Unboxing) -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs print-break-inside-avoid">
                    <!-- Kolom 1: Data Pesanan OCS -->
                    <div class="border border-slate-200 rounded-2xl p-4 bg-slate-50/40 space-y-2.5">
                        <div class="flex items-center gap-2 pb-2 border-b border-slate-200 font-bold text-slate-800">
                            <i class="fa-solid fa-cart-flatbed text-blue-600"></i>
                            <span>1. Data Pesanan (OCS System)</span>
                        </div>
                        <div class="space-y-1.5">
                            <div>
                                <span class="text-[10px] text-slate-400 uppercase font-bold block">No. Pesanan (Order ID)</span>
                                <span id="colOrderId" class="font-mono font-bold text-slate-900 text-xs">-</span>
                            </div>
                            <div>
                                <span class="text-[10px] text-slate-400 uppercase font-bold block">No. Resi (Tracking AWB)</span>
                                <span id="colTrackingNumber" class="font-mono font-bold text-slate-900 text-xs bg-slate-100 px-2 py-0.5 rounded inline-block">-</span>
                            </div>
                            <div class="grid grid-cols-2 gap-1.5">
                                <div>
                                    <span class="text-[10px] text-slate-400 uppercase font-bold block">Platform</span>
                                    <span id="colPlatform" class="font-bold text-slate-800">-</span>
                                </div>
                                <div>
                                    <span class="text-[10px] text-slate-400 uppercase font-bold block">Toko / Shop</span>
                                    <span id="colShop" class="font-bold text-slate-800 truncate block">-</span>
                                </div>
                            </div>
                            <div>
                                <span class="text-[10px] text-slate-400 uppercase font-bold block">Customer / Penerima</span>
                                <span id="colCustomer" class="text-slate-700">-</span>
                            </div>
                            <div>
                                <span class="text-[10px] text-slate-400 uppercase font-bold block">Rincian SKU &amp; Produk</span>
                                <div id="colProductList" class="bg-white p-2 rounded-xl border border-slate-200 text-slate-800 max-h-28 overflow-y-auto space-y-1 font-medium">-</div>
                            </div>
                        </div>
                    </div>

                    <!-- Kolom 2: Data Serah Terima Ekspedisi (Receiving) -->
                    <div class="border border-slate-200 rounded-2xl p-4 bg-slate-50/40 space-y-2.5">
                        <div class="flex items-center gap-2 pb-2 border-b border-slate-200 font-bold text-slate-800">
                            <i class="fa-solid fa-truck-ramp-box text-emerald-600"></i>
                            <span>2. Penerimaan Fisik (Receiving)</span>
                        </div>
                        <div class="space-y-1.5">
                            <div>
                                <span class="text-[10px] text-slate-400 uppercase font-bold block">Jasa Ekspedisi Pengirim</span>
                                <span id="colExpedition" class="font-bold text-indigo-700 text-xs">-</span>
                            </div>
                            <div>
                                <span class="text-[10px] text-slate-400 uppercase font-bold block">Kurir / Driver Pengantar</span>
                                <span id="colCourier" class="font-bold text-slate-800">-</span>
                            </div>
                            <div>
                                <span class="text-[10px] text-slate-400 uppercase font-bold block">Nomor Surat Jalan / Tanda Terima</span>
                                <span id="colReceiptNo" class="font-mono font-bold text-emerald-700">-</span>
                            </div>
                            <div class="grid grid-cols-2 gap-1.5">
                                <div>
                                    <span class="text-[10px] text-slate-400 uppercase font-bold block">Nomor Karung / Bag</span>
                                    <span id="colSackNumber" class="font-mono font-bold text-amber-800">-</span>
                                </div>
                                <div>
                                    <span class="text-[10px] text-slate-400 uppercase font-bold block">Petugas Penerima</span>
                                    <span id="colRecOperator" class="font-semibold text-slate-800">-</span>
                                </div>
                            </div>
                            <div>
                                <span class="text-[10px] text-slate-400 uppercase font-bold block">Waktu Diterima di Gudang</span>
                                <span id="colRecTime" class="text-slate-700 font-mono text-[11px]">-</span>
                            </div>
                            <div id="colCourierPhotoWrapper" class="hidden pt-1">
                                <span class="text-[10px] text-slate-400 uppercase font-bold block mb-1">Foto Bukti Driver / Kurir:</span>
                                <img id="colCourierPhotoImg" src="" alt="Foto Kurir" class="w-14 h-14 rounded-lg object-cover border border-slate-300 cursor-pointer shadow-2xs" onclick="previewImageDirect(this.src)">
                            </div>
                        </div>
                    </div>

                    <!-- Kolom 3: Data Unboxing Retur & Kerusakan -->
                    <div class="border border-slate-200 rounded-2xl p-4 bg-slate-50/40 space-y-2.5">
                        <div class="flex items-center gap-2 pb-2 border-b border-slate-200 font-bold text-slate-800">
                            <i class="fa-solid fa-box-open text-amber-600"></i>
                            <span>3. Hasil Pemeriksaan (Unboxing)</span>
                        </div>
                        <div class="space-y-1.5">
                            <div>
                                <span class="text-[10px] text-slate-400 uppercase font-bold block">Status &amp; Kondisi Fisik</span>
                                <span id="colConditionBadge" class="font-bold inline-block px-2 py-0.5 rounded text-xs">-</span>
                            </div>
                            <div>
                                <span class="text-[10px] text-slate-400 uppercase font-bold block">Kuantitas Rusak / Cacat</span>
                                <span id="colDamagedQty" class="font-mono font-bold text-rose-700 text-xs">0 Item</span>
                            </div>
                            <div>
                                <span class="text-[10px] text-slate-400 uppercase font-bold block">Catatan &amp; Alasan Kerusakan</span>
                                <div id="colConditionNotes" class="bg-amber-50/80 p-2 rounded-xl border border-amber-200 text-slate-800 text-[11px] font-semibold max-h-24 overflow-y-auto leading-tight">-</div>
                            </div>
                            <div>
                                <span class="text-[10px] text-slate-400 uppercase font-bold block">Daftar Produk Diperiksa &amp; Bukti Foto</span>
                                <div id="colUnboxItemsList" class="bg-white p-2 rounded-xl border border-slate-200 text-slate-800 text-[11px] max-h-36 overflow-y-auto space-y-1 mt-0.5">-</div>
                            </div>
                            <div class="grid grid-cols-2 gap-1.5">
                                <div>
                                    <span class="text-[10px] text-slate-400 uppercase font-bold block">Operator Unboxing</span>
                                    <span id="colUnboxOperator" class="font-semibold text-slate-800">-</span>
                                </div>
                                <div>
                                    <span class="text-[10px] text-slate-400 uppercase font-bold block">Waktu Unboxing</span>
                                    <span id="colUnboxTime" class="text-slate-700 font-mono text-[11px]">-</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bagian Video & Galeri Foto Bukti (No Print untuk Video) -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Video Unboxing Retur -->
                    <div class="border border-slate-200 rounded-2xl overflow-hidden bg-white shadow-2xs no-print">
                        <div class="p-3 bg-slate-900 text-white flex items-center justify-between text-xs">
                            <div class="flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                <span class="font-bold">Video Unboxing Retur (Gudang)</span>
                            </div>
                            <span class="text-[10px] text-slate-400">Bukti Rekaman Fisik</span>
                        </div>
                        <div class="relative bg-black aspect-video flex items-center justify-center">
                            <video id="dossierUnboxingVideo" controls class="w-full h-full object-contain hidden"></video>
                            <div id="noDossierUnboxingVideo" class="text-center p-6 text-slate-400">
                                <i class="fa-solid fa-video-slash text-3xl mb-2 text-slate-600 block"></i>
                                <span class="text-xs">Video unboxing belum tersedia untuk resi ini.</span>
                            </div>
                        </div>
                    </div>

                    <!-- Video Packing NAS -->
                    <div class="border border-slate-200 rounded-2xl overflow-hidden bg-white shadow-2xs no-print">
                        <div class="p-3 bg-slate-900 text-white flex items-center justify-between text-xs">
                            <div class="flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
                                <span class="font-bold">Video Packing Pengiriman (NAS Synology)</span>
                            </div>
                            <span class="text-[10px] text-slate-400">Bukti Packer Awal</span>
                        </div>
                        <div class="relative bg-black aspect-video flex items-center justify-center">
                            <video id="dossierPackingVideo" controls class="w-full h-full object-contain hidden"></video>
                            <div id="noDossierPackingVideo" class="text-center p-6 text-slate-400">
                                <i class="fa-solid fa-box-open text-3xl mb-2 text-slate-600 block"></i>
                                <span id="nasStatusText" class="text-xs block text-slate-400">Mencari rekaman packing di NAS...</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Galeri Foto Bukti Fisik Barang Rusak / Cacat -->
                <div class="border border-slate-200 rounded-2xl overflow-hidden bg-white shadow-2xs print-break-inside-avoid">
                    <div class="p-3.5 bg-slate-900 text-white flex items-center justify-between text-xs">
                        <div class="flex items-center gap-2">
                            <i class="fa-solid fa-camera text-emerald-400"></i>
                            <span class="font-bold">Galeri Foto Bukti Fisik Kerusakan / Kondisi Barang</span>
                        </div>
                        <span id="dossierPhotoCount" class="text-[10px] font-mono bg-white/10 px-2 py-0.5 rounded">0 Foto Tersimpan</span>
                    </div>
                    <div class="p-4 bg-slate-50/50">
                        <div id="dossierPhotoGrid" class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                            <!-- Foto diinject via JS -->
                        </div>
                        <div id="noDossierPhotos" class="text-center py-8 text-slate-400 hidden">
                            <i class="fa-solid fa-images text-3xl text-slate-300 mb-2 block"></i>
                            <span class="text-xs">Tidak ada foto bukti unboxing untuk paket ini.</span>
                        </div>
                    </div>
                </div>

                <!-- Tanda Tangan Dokumen Berita Acara (Untuk Cetak Fisik) -->
                <div class="pt-6 border-t border-slate-300 print-break-inside-avoid">
                    <div class="grid grid-cols-3 gap-4 text-center">
                        <div class="border border-slate-200 rounded-xl p-3 bg-slate-50/60 print:bg-white">
                            <p class="text-[9px] text-slate-500 font-bold uppercase mb-14">Petugas Pemeriksa Unboxing</p>
                            <p id="signUnboxOperator" class="text-xs font-bold text-slate-800 border-t border-slate-400 mx-2 pt-1">-</p>
                            <p class="text-[8px] text-slate-400">Operator Inbound Gudang</p>
                        </div>
                        <div class="border border-slate-200 rounded-xl p-3 bg-slate-50/60 print:bg-white">
                            <p class="text-[9px] text-slate-500 font-bold uppercase mb-14">Mengetahui / Supervisor</p>
                            <p class="text-xs font-bold text-slate-800 border-t border-slate-400 mx-2 pt-1">( .................................... )</p>
                            <p class="text-[8px] text-slate-400">Warehouse Lead / Supervisor</p>
                        </div>
                        <div class="border border-slate-200 rounded-xl p-3 bg-slate-50/60 print:bg-white">
                            <p class="text-[9px] text-slate-500 font-bold uppercase mb-14">Pihak Ekspedisi / Kurir</p>
                            <p class="text-xs font-bold text-slate-800 border-t border-slate-400 mx-2 pt-1">( .................................... )</p>
                            <p class="text-[8px] text-slate-400">Perwakilan Ekspedisi Terkait</p>
                        </div>
                    </div>
                    <p class="text-[9px] text-slate-400 italic text-center mt-3 leading-tight">
                        * Berkas Berita Acara ini diterbitkan secara resmi oleh IEG Inovasi Eka Gemilang berdasarkan rekaman sistem cross-reference penerimaan, unboxing fisik dan data pesanan OCS.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Lightbox Preview Gambar -->
    <div id="imageLightboxModal" class="hidden fixed inset-0 z-50 bg-black/90 backdrop-blur-md flex items-center justify-center p-4" onclick="closeLightboxModal()">
        <div class="relative max-w-4xl max-h-[90vh] flex flex-col items-center" onclick="event.stopPropagation()">
            <img id="lightboxImg" src="" alt="Preview" class="max-w-full max-h-[85vh] object-contain rounded-2xl shadow-2xl border border-white/20">
            <button onclick="closeLightboxModal()" class="absolute -top-10 right-0 text-white hover:text-rose-400 text-xl font-bold transition">
                <i class="fa-solid fa-xmark"></i> Tutup
            </button>
        </div>
    </div>

    <script>
        const initialQuery = <?= json_encode($query) ?>;
        let currentDossierData = null;

        document.addEventListener('DOMContentLoaded', () => {
            if (initialQuery) {
                loadClaimDossier(initialQuery);
            } else {
                showError('Parameter Tidak Lengkap', 'Silakan masukkan nomor resi atau Order ID melalui URL atau Pusat Klaim.');
            }
        });

        async function loadClaimDossier(q) {
            document.getElementById('claimLoadingState').classList.remove('hidden');
            document.getElementById('claimErrorState').classList.add('hidden');
            document.getElementById('claimMainCard').classList.add('hidden');

            try {
                const res = await fetch(`api/ocs_lookup.php?q=${encodeURIComponent(q)}`);
                const data = await res.json();

                if (!data || !data.success) {
                    showError('Data Klaim Tidak Ditemukan', data?.message || 'Nomor resi atau pesanan tidak ditemukan di sistem unboxing maupun data orders OCS.');
                    return;
                }

                currentDossierData = data;
                renderDossier(data);

                // Otomatis Cetak jika dipanggil dari tombol Cetak PDF
                const urlParams = new URLSearchParams(window.location.search);
                if (urlParams.get('autoprint') === '1' || urlParams.get('print') === '1') {
                    setTimeout(() => {
                        window.print();
                    }, 650);
                }

                // Tarik video NAS Synology di background
                fetchNasVideo(data, q);
            } catch (err) {
                showError('Terjadi Kesalahan', err.message);
            } finally {
                document.getElementById('claimLoadingState').classList.add('hidden');
            }
        }

        async function fetchNasVideo(data, q) {
            try {
                const nasParams = new URLSearchParams({ action: 'search', q: q });
                if (data.order?.Id) nasParams.append('order_id', data.order.Id);
                if (data.order?.TrackingNumber) nasParams.append('tracking_number', data.order.TrackingNumber);
                if (data.reception?.package_barcode) nasParams.append('package_barcode', data.reception.package_barcode);
                if (data.unboxing?.invoice_number) nasParams.append('alt_query', data.unboxing.invoice_number);

                const nasRes = await fetch(`api/nas_video.php?${nasParams.toString()}`);
                const nasData = await nasRes.json();

                const vidPlayer = document.getElementById('dossierPackingVideo');
                const noVid = document.getElementById('noDossierPackingVideo');
                const nasText = document.getElementById('nasStatusText');

                if (nasData && nasData.success && nasData.video_url) {
                    vidPlayer.src = nasData.video_url;
                    vidPlayer.classList.remove('hidden');
                    noVid.classList.add('hidden');
                } else {
                    if (nasText) nasText.innerText = nasData?.message || 'Video packing belum ditemukan di NAS.';
                }
            } catch (e) {
                console.warn('Gagal memuat video NAS:', e);
            }
        }

        function renderDossier(d) {
            const ord = d.order || {};
            const rec = d.reception || {};
            const unb = d.unboxing || {};
            const readiness = d.claim_readiness || {};
            const photos = d.photos || [];

            document.getElementById('claimMainCard').classList.remove('hidden');
            document.title = `Berkas Klaim - ${ord.TrackingNumber || unb.invoice_number || d.query} - IEG Inovasi Eka Gemilang`;

            // Ref Number
            document.getElementById('claimDocNumber').innerText = `Ref: CLM-${ord.Id || unb.invoice_number || ord.TrackingNumber || 'DOC'}`;

            // Banner Kelayakan
            const banner = document.getElementById('claimStatusBanner');
            const icon = document.getElementById('claimStatusIcon');
            const badge = document.getElementById('claimStatusBadge');
            const title = document.getElementById('claimMainTitle');
            const reason = document.getElementById('claimReasonText');

            if (d.is_claimable) {
                banner.className = 'rounded-2xl p-4 sm:p-5 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border bg-rose-50/80 border-rose-200 text-rose-900 shadow-xs';
                icon.className = 'w-10 h-10 rounded-xl flex items-center justify-center text-lg font-bold shrink-0 bg-rose-500 text-white';
                icon.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i>';
                badge.className = 'inline-block px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider mb-1 bg-rose-200 text-rose-800';
                badge.innerText = 'PAKET LAYAK KLAIM / BANDING EKSPEDISI';
                title.innerText = 'Kerusakan / Cacat Fisik Terverifikasi';
                reason.innerText = d.claim_eligibility_reason || 'Kondisi barang rusak/cacat saat unboxing retur di gudang.';
            } else if (!unb || !unb.id) {
                banner.className = 'rounded-2xl p-4 sm:p-5 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border bg-amber-50/80 border-amber-200 text-amber-900 shadow-xs';
                icon.className = 'w-10 h-10 rounded-xl flex items-center justify-center text-lg font-bold shrink-0 bg-amber-500 text-white';
                icon.innerHTML = '<i class="fa-solid fa-clock"></i>';
                badge.className = 'inline-block px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider mb-1 bg-amber-200 text-amber-800';
                badge.innerText = 'BELUM DI-UNBOXING DI GUDANG';
                title.innerText = 'Menunggu Pemeriksaan Fisik Barang';
                reason.innerText = 'Paket belum melalui stasiun unboxing untuk verifikasi fisik.';
            } else {
                banner.className = 'rounded-2xl p-4 sm:p-5 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border bg-emerald-50/80 border-emerald-200 text-emerald-900 shadow-xs';
                icon.className = 'w-10 h-10 rounded-xl flex items-center justify-center text-lg font-bold shrink-0 bg-emerald-500 text-white';
                icon.innerHTML = '<i class="fa-solid fa-circle-check"></i>';
                badge.className = 'inline-block px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider mb-1 bg-emerald-200 text-emerald-800';
                badge.innerText = 'KONDISI BARANG BAIK (GOOD)';
                title.innerText = 'Tidak Ditemukan Kerusakan Fisik';
                reason.innerText = 'Pemeriksaan unboxing mencatat barang dalam keadaan baik.';
            }

            // Nilai Finansial
            const totalClaimFmt = ord.TotalClaimAmountFormatted || (ord.PackagePriceFormatted || 'Rp 0');
            document.getElementById('claimTotalAmountDisplay').innerText = totalClaimFmt;
            document.getElementById('claimPriceSubDisplay').innerText = `Nilai Barang: ${ord.PackagePriceFormatted || 'Rp 0'}`;

            // Checklist
            setCheckStatus('checkOrder', readiness.order_data);
            setCheckStatus('checkRec', readiness.reception_data);
            setCheckStatus('checkUnbox', readiness.unboxing_video);
            setCheckStatus('checkPhotos', readiness.photos_available);

            // Kolom 1 (Orders OCS)
            document.getElementById('colOrderId').innerText = ord.Id || '-';
            document.getElementById('colTrackingNumber').innerText = ord.TrackingNumber || unb.invoice_number || '-';
            document.getElementById('colPlatform').innerText = ord.CommercePlatform || 'OCS';
            document.getElementById('colShop').innerText = ord.ShopName || '-';
            document.getElementById('colCustomer').innerText = ord.Customer?.Name ? `${ord.Customer.Name} (${ord.Customer.PhoneNumber || ''})` : '-';
            
            // Rincian SKU
            const prodListEl = document.getElementById('colProductList');
            let prodHtml = '';
            if (Array.isArray(ord.Items) && ord.Items.length > 0) {
                ord.Items.forEach(it => {
                    prodHtml += `<div class="border-b border-slate-100 pb-1 last:border-none"><b>${escapeHtml(it.ProductName || it.Sku || 'Produk')}</b> <span class="text-slate-500 font-mono">(x${it.Quantity || 1})</span> - <span class="font-mono text-emerald-700">Rp ${Number(it.Price || 0).toLocaleString('id-ID')}</span></div>`;
                });
            } else if (ord.ProductName) {
                prodHtml = `<div><b>${escapeHtml(ord.ProductName)}</b> (x${ord.TotalQtyOrder || 1}) - <span class="font-mono text-emerald-700">${ord.PackagePriceFormatted || 'Rp 0'}</span></div>`;
            } else {
                prodHtml = `<span class="text-slate-400 italic">Rincian produk tidak tersedia</span>`;
            }
            prodListEl.innerHTML = prodHtml;

            // Kolom 2 (Receiving)
            document.getElementById('colExpedition').innerText = rec.expedition || ord.ShippingProvider || unb.expedition || '-';
            document.getElementById('colCourier').innerText = rec.courier_name || '-';
            document.getElementById('colReceiptNo').innerText = rec.receipt_number || '-';
            document.getElementById('colSackNumber').innerText = rec.sack_number || '-';
            document.getElementById('colRecOperator').innerText = rec.operator_name || '-';
            document.getElementById('colRecTime').innerText = rec.received_at || '-';

            if (rec.courier_photo) {
                document.getElementById('colCourierPhotoWrapper').classList.remove('hidden');
                document.getElementById('colCourierPhotoImg').src = rec.courier_photo;
            }

            // Kolom 3 (Unboxing)
            const condBadge = document.getElementById('colConditionBadge');
            const condText = unb.damaged_reasons || unb.condition || unb.status || 'BELUM UNBOXING';
            condBadge.innerText = condText;
            if (d.is_claimable) {
                condBadge.className = 'font-bold inline-block px-2 py-0.5 rounded text-xs bg-rose-100 text-rose-800 border border-rose-200';
            } else {
                condBadge.className = 'font-bold inline-block px-2 py-0.5 rounded text-xs bg-emerald-100 text-emerald-800 border border-emerald-200';
            }

            document.getElementById('colDamagedQty').innerText = `${unb.total_damaged || unb.damaged_qty_sum || (d.is_claimable ? 1 : 0)} Item`;
            document.getElementById('colConditionNotes').innerText = unb.damaged_reasons || unb.notes || (d.is_claimable ? 'Barang rusak saat unboxing' : 'Tidak ada catatan cacat');
            document.getElementById('colUnboxOperator').innerText = unb.operator_name || '-';
            document.getElementById('colUnboxTime').innerText = unb.unboxed_at || unb.created_at || '-';
            document.getElementById('signUnboxOperator').innerText = unb.operator_name || 'Operator Gudang';

            // Rincian Item Unboxing & Bukti Foto Tiap Item
            const unboxItemsEl = document.getElementById('colUnboxItemsList');
            if (unboxItemsEl) {
                if (Array.isArray(unb.items) && unb.items.length > 0) {
                    let uHtml = '';
                    unb.items.forEach(it => {
                        const itC = (it.type || it.condition || 'GOOD').toUpperCase().trim();
                        const isDmg = (itC !== 'GOOD' && itC !== 'BAGUS' && itC !== 'LAYAK');
                        const bColor = isDmg ? 'bg-rose-100 text-rose-800 border-rose-300' : 'bg-emerald-100 text-emerald-800 border-emerald-300';
                        const photoBtn = it.photo_path ? 
                            `<button type="button" onclick="previewImageDirect('${escapeHtml(it.photo_path)}')" class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-rose-600 hover:bg-rose-700 text-white text-[9px] font-bold shadow-2xs transition shrink-0 ml-auto cursor-pointer" title="Lihat Foto Bukti Rusak"><i class="fa-solid fa-camera"></i> Foto Rusak</button>` : 
                            (isDmg ? `<span class="text-[9px] text-rose-600 font-bold shrink-0 ml-auto">⚠️ Tanpa Foto</span>` : '');
                        
                        uHtml += `
                            <div class="flex items-center justify-between gap-1.5 p-1.5 rounded-lg border border-slate-100 hover:bg-slate-50 transition">
                                <div class="min-w-0 flex-1">
                                    <div class="font-bold text-slate-800 truncate">${escapeHtml(it.product_name || it.barcode || 'Produk')}</div>
                                    <div class="text-[10px] text-slate-500 font-mono mt-0.5">Qty: <b>${it.qty || 1}</b> • <span class="px-1 py-0.2 rounded font-bold border ${bColor}">${itC}</span></div>
                                </div>
                                ${photoBtn}
                            </div>
                        `;
                    });
                    unboxItemsEl.innerHTML = uHtml;
                } else {
                    unboxItemsEl.innerHTML = `<span class="text-slate-400 italic">Belum ada rincian item unboxing</span>`;
                }
            }

            // Video Unboxing
            const unboxVideo = document.getElementById('dossierUnboxingVideo');
            const noUnboxVid = document.getElementById('noDossierUnboxingVideo');
            if (unb.video_url || unb.video_path) {
                unboxVideo.src = unb.video_url || unb.video_path;
                unboxVideo.classList.remove('hidden');
                noUnboxVid.classList.add('hidden');
            } else {
                unboxVideo.classList.add('hidden');
                noUnboxVid.classList.remove('hidden');
            }

            // Galeri Foto Bukti
            const photoGrid = document.getElementById('dossierPhotoGrid');
            const noPhotos = document.getElementById('noDossierPhotos');
            const photoCount = document.getElementById('dossierPhotoCount');

            if (photos && photos.length > 0) {
                photoCount.innerText = `${photos.length} Foto Tersimpan`;
                noPhotos.classList.add('hidden');
                let phtml = '';
                photos.forEach(p => {
                    const purl = p.url || p.file_path || p;
                    const pcap = p.caption || p.title || p.type || 'Bukti Barang';
                    const isDamaged = p.is_damaged || p.type === 'damaged' || (p.badge === 'Barang Rusak') || String(pcap).toLowerCase().includes('rusak');
                    phtml += `
                        <div class="border ${isDamaged ? 'border-rose-500 ring-2 ring-rose-200' : 'border-slate-200'} rounded-xl p-1.5 bg-white shadow-2xs text-center relative group">
                            ${isDamaged ? `<span class="absolute top-2 left-2 bg-rose-600 text-white font-mono font-bold text-[9px] px-1.5 py-0.5 rounded shadow-xs z-10">⚠️ RUSAK</span>` : ''}
                            <img src="${escapeHtml(purl)}" alt="${escapeHtml(pcap)}" class="w-full h-24 sm:h-28 object-cover rounded-lg cursor-pointer hover:opacity-95 transition" onclick="previewImageDirect('${escapeHtml(purl)}')">
                            <span class="text-[9px] font-bold ${isDamaged ? 'text-rose-700' : 'text-slate-700'} block mt-1 truncate" title="${escapeHtml(pcap)}">${escapeHtml(pcap)}</span>
                        </div>
                    `;
                });
                photoGrid.innerHTML = phtml;
            } else {
                photoCount.innerText = '0 Foto';
                photoGrid.innerHTML = '';
                noPhotos.classList.remove('hidden');
            }
        }

        function setCheckStatus(id, ok) {
            const icon = document.getElementById(id + 'Icon');
            const label = document.getElementById(id + 'Label');
            if (!icon || !label) return;
            if (ok) {
                icon.className = 'fa-solid fa-circle-check text-emerald-500 text-lg';
                label.className = 'text-[10px] text-emerald-700 font-semibold';
                label.innerText = 'Terverifikasi Lengkap';
            } else {
                icon.className = 'fa-solid fa-circle-xmark text-slate-300 text-lg';
                label.className = 'text-[10px] text-slate-400 font-normal';
                label.innerText = 'Belum Ada';
            }
        }

        function showError(title, msg) {
            document.getElementById('claimLoadingState').classList.add('hidden');
            document.getElementById('claimMainCard').classList.add('hidden');
            document.getElementById('claimErrorState').classList.remove('hidden');
            document.getElementById('claimErrorTitle').innerText = title;
            document.getElementById('claimErrorMessage').innerText = msg;
        }

        function previewImageDirect(src) {
            const modal = document.getElementById('imageLightboxModal');
            const img = document.getElementById('lightboxImg');
            if (modal && img) {
                img.src = src;
                modal.classList.remove('hidden');
            }
        }

        function closeLightboxModal() {
            const modal = document.getElementById('imageLightboxModal');
            if (modal) modal.classList.add('hidden');
        }

        function copyClaimSummaryText() {
            if (!currentDossierData) return;
            const d = currentDossierData;
            const ord = d.order || {};
            const unb = d.unboxing || {};
            const rec = d.reception || {};

            const text = `*BERKAS KLAIM EKSPEDISI - IEG INOVASI EKA GEMILANG*
----------------------------------------
No. Resi: ${ord.TrackingNumber || unb.invoice_number || '-'}
No. Order: ${ord.Id || '-'}
Toko / Shop: ${ord.ShopName || '-'} (${ord.CommercePlatform || 'OCS'})
Ekspedisi: ${rec.expedition || ord.ShippingProvider || unb.expedition || '-'}
Status Barang: ${d.is_claimable ? 'RUSAK / CACAT (LAYAK KLAIM)' : 'BAIK'}
Kerusakan: ${unb.damaged_reasons || unb.notes || '-'}
Total Nilai Klaim: ${ord.TotalClaimAmountFormatted || ord.PackagePriceFormatted || 'Rp 0'}
Waktu Unboxing: ${unb.unboxed_at || unb.created_at || '-'} (Operator: ${unb.operator_name || '-'})
Diverifikasi Sistem: IEG Return Inbound Management
----------------------------------------`;

            navigator.clipboard.writeText(text).then(() => {
                alert('Teks ringkasan berkas klaim berhasil disalin ke clipboard!');
            }).catch(() => {
                prompt('Salin teks berkas klaim berikut:', text);
            });
        }

        function escapeHtml(str) {
            if (!str) return '';
            return String(str).replace(/[&<>"']/g, function(m) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m];
            });
        }
    </script>
</body>
</html>
