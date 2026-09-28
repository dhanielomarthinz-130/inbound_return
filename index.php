<?php
require_once __DIR__ . '/config.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inbound Return Station - Laragon Native</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- HTML5-QRCode Scanner Library -->
    <script src="https://unpkg.com/html5-qrcode"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/custom.css">
</head>
<body class="bg-slate-100 min-h-screen text-slate-800 antialiased flex flex-col justify-between">

    <!-- Top Navbar -->
    <header class="bg-slate-900 text-white shadow-md sticky top-0 z-30 w-full">
        <div class="w-full px-4 md:px-8 py-3 flex justify-between items-center">
            <div class="flex items-center space-x-3">
                <div class="w-9 h-9 rounded-xl bg-indigo-600 flex items-center justify-center text-white font-bold shadow-md shadow-indigo-600/30">
                    <i class="fa-solid fa-boxes-packing"></i>
                </div>
                <div>
                    <h1 class="font-bold text-base md:text-lg leading-tight">Inbound Return Station</h1>
                    <p class="text-[11px] text-slate-400">Video Scanner di Kiri &bull; Form Input di Kanan &bull; Laragon Native</p>
                </div>
            </div>
            
            <div class="flex items-center space-x-3">
                <span id="cameraStatusBadge" class="bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 text-xs px-2.5 py-1 rounded-full font-semibold flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span> Kamera Siap
                </span>
                <a href="admin.php" class="bg-indigo-600 hover:bg-indigo-700 text-white text-xs px-3.5 py-2 rounded-xl font-semibold transition flex items-center gap-1.5 shadow-sm">
                    <i class="fa-solid fa-chart-pie"></i>
                    <span>Admin Panel</span>
                </a>
            </div>
        </div>
    </header>

    <!-- Main 2-Column Layout 100% Width -->
    <main class="w-full px-4 md:px-8 py-6 grid grid-cols-1 lg:grid-cols-12 gap-6 flex-1 items-start">

        <!-- ============================================================== -->
        <!-- KOLOM KIRI (4-5 KOLOM): VIDEO KAMERA SCANNER                  -->
        <!-- ============================================================== -->
        <div class="lg:col-span-5 xl:col-span-4 space-y-4">

            <!-- Card Live Video Dokumentasi / Record (Tanpa Kotak Scanner) -->
            <div class="bg-white rounded-2xl shadow-xs border border-slate-200 overflow-hidden">
                <div class="p-3.5 bg-slate-900 text-white flex justify-between items-center">
                    <div class="flex items-center space-x-2 text-sm font-semibold">
                        <span class="w-2.5 h-2.5 rounded-full bg-rose-500 animate-pulse"></span>
                        <i class="fa-solid fa-video text-rose-400"></i>
                        <span>Live Video Record</span>
                        <span id="cameraRecBadge" class="hidden bg-rose-600 text-white font-mono text-[10px] font-bold px-2 py-0.5 rounded-full flex items-center gap-1">
                            <span class="w-1.5 h-1.5 rounded-full bg-white animate-ping"></span> REC <span id="cameraRecTime">00:00</span>
                        </span>
                    </div>
                    <button id="btnSwitchCamera" onclick="switchCamera()" class="text-xs bg-slate-800 hover:bg-slate-700 text-slate-300 px-2.5 py-1 rounded-lg border border-slate-700 transition flex items-center gap-1">
                        <i class="fa-solid fa-arrows-rotate"></i> Putar Kamera
                    </button>
                </div>

                <!-- Video Viewport Bersih (Full Layar Tanpa Kotak Scanner) -->
                <div class="relative bg-slate-950 flex justify-center items-center min-h-[300px]">
                    <video id="liveVideoFeed" autoplay playsinline muted class="w-full h-auto object-cover min-h-[300px] max-h-[440px]"></video>
                    <div id="cameraLoading" class="absolute inset-0 bg-slate-900 flex flex-col items-center justify-center text-white space-y-2">
                        <i class="fa-solid fa-circle-notch fa-spin text-3xl text-indigo-500"></i>
                        <span class="text-xs text-slate-300">Menghubungkan ke kamera video...</span>
                    </div>
                </div>

                <!-- Status Mode Scan -->
                <div class="p-3 bg-slate-50 border-t border-slate-200 text-xs flex justify-between items-center text-slate-600">
                    <span class="flex items-center gap-1.5 text-[11px] font-semibold text-slate-700">
                        <i class="fa-solid fa-barcode text-indigo-600 text-sm"></i> Barcode Gun Scanner Aktif
                    </span>
                    <span id="scanModeIndicator" class="font-bold text-indigo-700 bg-indigo-50 px-2 py-0.5 rounded border border-indigo-200 text-[11px]">
                        LANGKAH 1: SCAN INVOICE
                    </span>
                </div>
            </div>

            <!-- Card Tombol Demo Cepat (Bila Tanpa Fisik Barcode) -->
            <div class="bg-white p-4 rounded-2xl shadow-xs border border-slate-200 space-y-2.5 text-xs">
                <div class="font-bold text-slate-700 flex items-center justify-between">
                    <span>💡 Simulasi Barcode Testing:</span>
                    <span class="text-[10px] text-slate-400">Klik untuk test scan</span>
                </div>
                
                <div>
                    <span class="text-[11px] text-slate-500 block mb-1 font-medium">Contoh Invoice / Resi (Klik untuk Test Auto-Detect):</span>
                    <div class="flex flex-wrap gap-1.5">
                        <button type="button" onclick="simulateScan('SPXID04928172')" class="bg-orange-50 hover:bg-orange-100 text-orange-700 px-2.5 py-1 rounded-lg font-mono text-[11px] font-bold border border-orange-200 transition">SPXID04928172 (SPX)</button>
                        <button type="button" onclick="simulateScan('GTL99824102')" class="bg-emerald-50 hover:bg-emerald-100 text-emerald-700 px-2.5 py-1 rounded-lg font-mono text-[11px] font-bold border border-emerald-200 transition">GTL99824102 (GTL)</button>
                        <button type="button" onclick="simulateScan('JP98412048')" class="bg-rose-50 hover:bg-rose-100 text-rose-700 px-2.5 py-1 rounded-lg font-mono text-[11px] font-bold border border-rose-200 transition">JP98412048 (J&T)</button>
                        <button type="button" onclick="simulateScan('002847192837')" class="bg-amber-50 hover:bg-amber-100 text-amber-700 px-2.5 py-1 rounded-lg font-mono text-[11px] font-bold border border-amber-200 transition">002847192837 (SiCepat)</button>
                    </div>
                </div>

                <div class="pt-1 border-t border-slate-100">
                    <span class="text-[11px] text-slate-500 block mb-1 font-medium">Contoh Barcode Produk:</span>
                    <div class="flex flex-wrap gap-1.5">
                        <button type="button" onclick="simulateScan('8991001')" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-2 py-1 rounded-lg font-mono text-[11px] border border-slate-200 transition">8991001 (Kipas)</button>
                        <button type="button" onclick="simulateScan('8991002')" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-2 py-1 rounded-lg font-mono text-[11px] border border-slate-200 transition">8991002 (TWS)</button>
                        <button type="button" onclick="simulateScan('8991003')" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-2 py-1 rounded-lg font-mono text-[11px] border border-slate-200 transition">8991003 (PB)</button>
                        <button type="button" onclick="simulateScan('8991004')" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-2 py-1 rounded-lg font-mono text-[11px] border border-slate-200 transition">8991004 (Adaptor)</button>
                        <button type="button" onclick="simulateScan('8991005')" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-2 py-1 rounded-lg font-mono text-[11px] border border-slate-200 transition">8991005 (Bohlam)</button>
                    </div>
                </div>
            </div>

            <!-- Info Operator & Stasiun -->
            <div class="bg-white p-3.5 rounded-2xl shadow-xs border border-slate-200 flex items-center justify-between text-xs text-slate-500">
                <div class="flex items-center space-x-2.5">
                    <i class="fa-solid fa-circle-user text-indigo-600 text-xl"></i>
                    <div>
                        <div class="font-bold text-slate-800">Operator: <span id="displayOperator">Gudang 01</span></div>
                        <div class="text-[11px]">Stasiun Inbound Dock A</div>
                    </div>
                </div>
                <button onclick="resetInvoiceSession()" class="text-rose-600 hover:text-rose-700 font-bold flex items-center gap-1">
                    <i class="fa-solid fa-rotate-left"></i> Reset Sesi
                </button>
            </div>

        </div>

        <!-- ============================================================== -->
        <!-- KOLOM KANAN (7-8 KOLOM): FORM INPUT & TABEL ITEM              -->
        <!-- ============================================================== -->
        <div class="lg:col-span-7 xl:col-span-8 space-y-4">

            <!-- 1. INPUT NOMOR INVOICE (Auto Record on Scan) -->
            <div id="sectionInvoice" class="bg-white rounded-2xl p-5 shadow-xs border border-slate-200 transition-all">
                
                <!-- Status Belum Terkunci -->
                <div id="invoiceInputWrapper" class="space-y-3">
                    <div class="flex items-center justify-between">
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider flex items-center gap-1.5">
                            <span class="w-5 h-5 rounded-full bg-indigo-600 text-white text-[11px] flex items-center justify-center font-bold">1</span>
                            <span>Pilih Ekspedisi & Nomor Invoice Return</span>
                        </label>
                        <span class="text-xs text-indigo-600 font-semibold flex items-center gap-1">
                            <i class="fa-solid fa-bolt"></i> Auto Record saat di-scan
                        </span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                        <!-- Select Ekspedisi (4 Kolom) -->
                        <div class="md:col-span-4">
                            <div class="flex items-center justify-between mb-1">
                                <label class="block text-xs font-bold text-slate-700 flex items-center gap-1">
                                    <i class="fa-solid fa-truck-fast text-indigo-600"></i>
                                    <span>Ekspedisi / Kurir *</span>
                                </label>
                                <span id="autoDetectBadge" class="hidden text-[10px] text-emerald-700 bg-emerald-100 border border-emerald-300 px-2 py-0.5 rounded-full font-bold">
                                    <i class="fa-solid fa-wand-magic-sparkles text-emerald-600"></i> <span id="autoDetectLabel">Auto-Detect</span>
                                </span>
                            </div>
                            <select id="selectExpedition" class="w-full px-3 py-2.5 bg-slate-50 border-2 border-indigo-200 rounded-xl text-xs font-bold text-slate-800 focus:bg-white focus:border-indigo-600 focus:ring-0 focus:outline-none transition">
                                <option value="">-- Pilih Ekspedisi --</option>
                            </select>
                        </div>

                        <!-- Input Nomor Invoice (8 Kolom) -->
                        <div class="md:col-span-8">
                            <label class="block text-xs font-bold text-slate-700 mb-1 flex items-center gap-1">
                                <i class="fa-solid fa-barcode text-indigo-600"></i>
                                <span>Nomor Invoice *</span>
                            </label>
                            <div class="flex space-x-2">
                                <div class="relative flex-1">
                                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400">
                                        <i class="fa-solid fa-receipt text-sm"></i>
                                    </span>
                                    <input type="text" id="inputInvoice" placeholder="Scan barcode invoice atau ketik lalu Enter..."
                                        autocomplete="off"
                                        class="w-full pl-9 pr-4 py-2.5 bg-slate-50 border-2 border-indigo-200 rounded-xl text-sm font-bold font-mono text-slate-800 focus:bg-white focus:border-indigo-600 focus:ring-0 focus:outline-none transition">
                                </div>
                                <button id="btnLockInvoice" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl font-bold text-xs transition shadow-sm flex items-center gap-1.5 shrink-0">
                                    <span>Lanjut</span>
                                    <i class="fa-solid fa-arrow-right"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Status Sudah Terkunci (Banner Hijau) -->
                <div id="invoiceLockedBanner" class="hidden flex items-center justify-between bg-emerald-50/80 border border-emerald-200 p-3.5 rounded-xl">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 rounded-xl bg-emerald-600 text-white flex items-center justify-center text-base shadow-sm">
                            <i class="fa-solid fa-check"></i>
                        </div>
                        <div>
                            <div class="text-[10px] text-emerald-700 font-bold uppercase tracking-wider">Invoice & Ekspedisi Terkunci</div>
                            <div class="flex flex-wrap items-center gap-2 mt-0.5">
                                <span class="text-lg font-black text-slate-900 font-mono leading-tight" id="displayActiveInvoice">-</span>
                                <span id="displayActiveExpedition" class="bg-indigo-100 text-indigo-800 text-xs font-bold px-2.5 py-0.5 rounded-lg flex items-center gap-1 border border-indigo-200">
                                    <i class="fa-solid fa-truck-fast text-indigo-600"></i>
                                    <span id="displayExpeditionText">-</span>
                                </span>
                            </div>
                        </div>
                    </div>
                    <button onclick="resetInvoiceSession()" class="text-xs text-rose-600 hover:text-rose-800 bg-white hover:bg-rose-50 px-3 py-1.5 rounded-lg border border-rose-200 font-bold transition flex items-center gap-1">
                        <i class="fa-solid fa-rotate-left"></i> Ganti
                    </button>
                </div>

            </div>

            <!-- 2. FORM INPUT PRODUK: BARCODE, BATCH, EXP DATE, QTY, TYPE -->
            <div id="sectionProductInput" class="hidden bg-white rounded-2xl p-5 shadow-xs border border-indigo-200 ring-2 ring-indigo-500/10 space-y-4 transition-all">
                
                <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                    <div class="flex items-center space-x-2">
                        <span class="w-5 h-5 rounded-full bg-indigo-600 text-white text-[11px] flex items-center justify-center font-bold">2</span>
                        <h2 class="text-xs font-bold uppercase tracking-wider text-slate-700">Scan Barcode & Detail Produk</h2>
                    </div>
                    <div class="text-[11px] text-slate-400">
                        Tekan <kbd class="px-1.5 py-0.5 bg-slate-100 border border-slate-300 rounded font-mono text-[10px]">Enter</kbd> untuk pindah kolom
                    </div>
                </div>

                <form id="formProductEntry" onsubmit="handleAddItem(event)" class="space-y-3">
                    
                    <!-- Row 1: Barcode Produk & No. Batch -->
                    <div class="grid grid-cols-1 sm:grid-cols-12 gap-3">
                        <!-- Barcode Produk (7 Kolom) -->
                        <div class="sm:col-span-7">
                            <label class="block text-xs font-bold text-slate-700 mb-1">Scan Barcode Produk *</label>
                            <div class="relative">
                                <input type="text" id="inputBarcode" placeholder="Scan barcode produk..."
                                    autocomplete="off" required
                                    class="w-full pl-3 pr-8 py-2 bg-slate-50 border border-slate-300 rounded-xl text-sm font-mono font-bold text-slate-800 focus:bg-white focus:border-indigo-600 focus:ring-0 focus:outline-none transition">
                                <span id="barcodeLoadingIcon" class="hidden absolute right-3 top-2.5 text-indigo-500">
                                    <i class="fa-solid fa-circle-notch fa-spin text-sm"></i>
                                </span>
                            </div>
                        </div>

                        <!-- No. Batch (5 Kolom) -->
                        <div class="sm:col-span-5">
                            <label class="block text-xs font-bold text-slate-700 mb-1">No. Batch</label>
                            <input type="text" id="inputBatch" placeholder="Contoh: B260901"
                                class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl text-sm font-mono text-slate-800 focus:bg-white focus:border-indigo-600 focus:ring-0 focus:outline-none transition">
                        </div>
                    </div>

                    <!-- Row 2: Exp Date, Qty, Type -->
                    <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end">
                        <!-- Exp Date (4 Kolom) -->
                        <div class="sm:col-span-4">
                            <label class="block text-xs font-bold text-slate-700 mb-1">Exp Date</label>
                            <input type="date" id="inputExpDate"
                                class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl text-xs font-mono text-slate-800 focus:bg-white focus:border-indigo-600 focus:ring-0 focus:outline-none transition">
                        </div>

                        <!-- Qty (3 Kolom) -->
                        <div class="sm:col-span-3">
                            <label class="block text-xs font-bold text-slate-700 mb-1">Qty</label>
                            <input type="number" id="inputQty" value="1" min="1" required
                                class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl text-sm font-bold text-center text-slate-800 focus:bg-white focus:border-indigo-600 focus:ring-0 focus:outline-none transition">
                        </div>

                        <!-- Type (5 Kolom) -->
                        <div class="sm:col-span-5">
                            <label class="block text-xs font-bold text-slate-700 mb-1">Type / Kondisi</label>
                            <select id="inputType" class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl text-xs font-bold text-slate-800 focus:bg-white focus:border-indigo-600 focus:ring-0 focus:outline-none transition">
                                <option value="GOOD">GOOD (Layak Jual)</option>
                                <option value="RUSAK">RUSAK (Defect)</option>
                                <option value="EXPIRED">EXPIRED (Kadaluarsa)</option>
                                <option value="SALAH_KIRIM">SALAH KIRIM</option>
                            </select>
                        </div>
                    </div>

                    <!-- Status Preview Produk & Tombol Tambah -->
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 pt-2 bg-slate-50 p-2.5 rounded-xl border border-slate-200">
                        <div class="text-xs truncate">
                            <span class="text-slate-400 font-medium">Produk:</span>
                            <span id="detectedProductName" class="font-bold text-indigo-700 ml-1 text-xs">Menunggu scan barcode...</span>
                            <span id="detectedProductSku" class="text-slate-400 text-xs ml-1 font-mono"></span>
                        </div>

                        <button type="submit" id="btnSubmitItem" class="w-full sm:w-auto bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-4 py-2 rounded-xl text-xs transition flex items-center justify-center gap-1.5 shadow-sm">
                            <i class="fa-solid fa-plus-circle"></i> Tambahkan Item
                        </button>
                    </div>

                </form>

            </div>

            <!-- 3. TABEL DAFTAR BARANG YANG SUDAH TER-INPUT -->
            <div id="sectionItemsList" class="bg-white rounded-2xl shadow-xs border border-slate-200 overflow-hidden">
                <div class="p-3.5 bg-slate-50 border-b border-slate-200 flex justify-between items-center">
                    <div class="flex items-center space-x-2">
                        <i class="fa-solid fa-list-check text-indigo-600 text-sm"></i>
                        <h3 class="font-bold text-xs uppercase tracking-wider text-slate-800">
                            Item Terdata (<span id="totalItemsBadge" class="text-indigo-600">0</span>)
                        </h3>
                    </div>

                    <div class="flex items-center space-x-2 text-xs">
                        <span class="bg-slate-200 text-slate-700 px-2.5 py-0.5 rounded-lg font-bold">
                            Total: <span id="summaryTotalUnits">0</span> Pcs
                        </span>
                    </div>
                </div>

                <!-- Table Content -->
                <div class="overflow-x-auto max-h-[260px]">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead class="bg-slate-100 text-slate-600 uppercase font-semibold sticky top-0">
                            <tr>
                                <th class="p-2.5 text-center w-10">#</th>
                                <th class="p-2.5">Barcode</th>
                                <th class="p-2.5">Nama Produk & SKU</th>
                                <th class="p-2.5">Batch</th>
                                <th class="p-2.5">Exp Date</th>
                                <th class="p-2.5 text-center">Qty</th>
                                <th class="p-2.5 text-center">Type</th>
                                <th class="p-2.5 text-center w-12">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="itemsTableBody" class="divide-y divide-slate-100">
                            <tr id="emptyTablePlaceholder">
                                <td colspan="8" class="text-center py-8 text-slate-400 italic">
                                    Belum ada produk yang dimasukkan untuk invoice ini.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Action Footer -->
                <div class="p-3.5 bg-slate-50 border-t border-slate-200 flex flex-col sm:flex-row justify-between items-center gap-2.5">
                    <input type="text" id="sessionNotesInput" placeholder="Catatan invoice (opsional)..."
                        class="w-full sm:w-auto flex-1 border border-slate-300 rounded-xl px-3 py-1.5 text-xs bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    
                    <button id="btnFinalizeSession" disabled onclick="submitFinalSession()"
                        class="w-full sm:w-auto bg-emerald-600 hover:bg-emerald-700 disabled:opacity-40 disabled:cursor-not-allowed text-white font-bold px-5 py-2 rounded-xl text-xs transition flex items-center justify-center gap-2 shadow-sm">
                        <i class="fa-solid fa-cloud-arrow-up"></i> Selesaikan Inbound Invoice
                    </button>
                </div>
            </div>

        </div>

    </main>

    <!-- Footer Status -->
    <footer class="bg-white border-t border-slate-200 py-3 px-4 md:px-8 text-center text-xs text-slate-500 w-full mt-auto">
        <span>Gudang Dock A &bull; Laragon MySQL (<span class="text-emerald-600 font-bold">inbound_return</span>) &bull; Sistem Inbound Return</span>
    </footer>

    <!-- Modal Konfirmasi / Sukses -->
    <div id="successModal" class="fixed inset-0 bg-black/60 z-50 flex items-center justify-center hidden p-4">
        <div class="bg-white rounded-2xl max-w-sm w-full p-6 text-center shadow-2xl space-y-4">
            <div class="w-16 h-16 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center text-3xl mx-auto">
                <i class="fa-solid fa-check"></i>
            </div>
            <h3 class="text-lg font-bold text-slate-800">Inbound Return Berhasil!</h3>
            <p class="text-xs text-slate-500" id="modalSuccessDesc">Data return invoice telah direkam ke database sistem.</p>
            <div class="pt-2">
                <button onclick="resetInvoiceSession()" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 rounded-xl text-sm transition">
                    Scan Invoice Selanjutnya &rarr;
                </button>
            </div>
        </div>
    </div>

    <script src="assets/js/operator.js?v=<?= file_exists(__DIR__ . '/assets/js/operator.js') ? filemtime(__DIR__ . '/assets/js/operator.js') : time() ?>"></script>
</body>
</html>
