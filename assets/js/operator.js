let mediaStream = null;
let isCameraActive = false;
let activeInvoice = null;
let activeExpedition = null;
let cachedExpeditionsList = [];
let currentDetectedProduct = null;
let scannedProductsList = [];

// Global Loading Overlay Controls (Bola-bola Merah, Kuning, Hijau)
window.showGlobalLoading = function(title = 'Memproses...', desc = 'Mohon tunggu sebentar.') {
    const el = document.getElementById('globalLoadingOverlay');
    if (!el) return;
    const t = document.getElementById('globalLoadingTitle');
    const d = document.getElementById('globalLoadingDesc');
    if (t) t.innerText = title;
    if (d) d.innerText = desc;
    el.classList.remove('hidden');
};

window.hideGlobalLoading = function() {
    const el = document.getElementById('globalLoadingOverlay');
    if (el) el.classList.add('hidden');
};

// Audio Synthesizer Beep (Web Audio API)
const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
function playBeep(type = 'success') {
    try {
        if (audioCtx.state === 'suspended') audioCtx.resume();
        const osc = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        osc.connect(gain);
        gain.connect(audioCtx.destination);

        if (type === 'success') {
            osc.frequency.setValueAtTime(1200, audioCtx.currentTime);
            gain.gain.setValueAtTime(0.2, audioCtx.currentTime);
            osc.start();
            osc.stop(audioCtx.currentTime + 0.12);
        } else {
            osc.frequency.setValueAtTime(300, audioCtx.currentTime);
            gain.gain.setValueAtTime(0.3, audioCtx.currentTime);
            osc.start();
            osc.stop(audioCtx.currentTime + 0.25);
        }
    } catch (e) {
        console.warn('Audio error:', e);
    }
}

// -------------------------------------------------------------
// 1. INVOICE HANDLING (Auto Record on Scan / Enter)
// -------------------------------------------------------------
const inputInvoice = document.getElementById('inputInvoice');
const btnLockInvoice = document.getElementById('btnLockInvoice');

if (inputInvoice) {
    inputInvoice.addEventListener('input', () => {
        const val = inputInvoice.value.trim();
        const autoBadge = document.getElementById('autoDetectBadge');
        const autoLabel = document.getElementById('autoDetectLabel');
        const expSelect = document.getElementById('selectExpedition');

        if (val.length >= 2) {
            const detected = detectExpeditionFromCode(val);
            if (detected && expSelect) {
                expSelect.value = detected.name;
                expSelect.classList.add('border-emerald-500', 'bg-emerald-50/50');
                if (autoBadge) {
                    if (autoLabel) autoLabel.innerText = `Auto: ${detected.name}`;
                    autoBadge.classList.remove('hidden');
                }
                return;
            }
        }
        if (autoBadge) autoBadge.classList.add('hidden');
        if (expSelect) expSelect.classList.remove('border-emerald-500', 'bg-emerald-50/50');
    });

    inputInvoice.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            const val = inputInvoice.value.trim();
            if (val) processInvoiceScan(val);
        }
    });
}

if (btnLockInvoice) {
    btnLockInvoice.addEventListener('click', () => {
        const val = inputInvoice.value.trim();
        if (val) processInvoiceScan(val);
    });
}

window.quickSelectInvoice = function(code) {
    if (inputInvoice) {
        inputInvoice.value = code;
        processInvoiceScan(code);
    }
};

async function processInvoiceScan(invoiceNumber) {
    try {
        const res = await fetch(`api/invoice/${encodeURIComponent(invoiceNumber)}`);
        const data = await res.json();

        activeInvoice = data.invoice_number || invoiceNumber;
        
        // Auto-detect ekspedisi dari invoice / resi
        const detected = detectExpeditionFromCode(invoiceNumber);
        const expSelect = document.getElementById('selectExpedition');

        if (detected) {
            activeExpedition = detected.name;
            if (expSelect) expSelect.value = detected.name;
        } else if (expSelect && expSelect.value) {
            activeExpedition = expSelect.value;
        } else {
            activeExpedition = 'Reguler / Kurir';
        }
        
        const expText = document.getElementById('displayExpeditionText');
        if (expText) expText.innerText = activeExpedition;

        playBeep('success');

        // Update Indikator Mode
        const scanMode = document.getElementById('scanModeIndicator');
        if (scanMode) {
            scanMode.innerText = 'LANGKAH 2: SCAN BARCODE PRODUK';
            scanMode.className = 'font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200 text-[11px]';
        }

        // Tampilkan Banner Terkunci & Sembunyikan Input Invoice
        document.getElementById('displayActiveInvoice').innerText = activeInvoice;
        document.getElementById('invoiceInputWrapper').classList.add('hidden');
        document.getElementById('invoiceLockedBanner').classList.remove('hidden');

        // Tampilkan Form Input Produk (Langkah 2)
        const secProd = document.getElementById('sectionProductInput');
        secProd.classList.remove('hidden');

        // Auto Focus Langsung ke Barcode Produk!
        setTimeout(() => {
            const inputBarcode = document.getElementById('inputBarcode');
            if (inputBarcode) {
                inputBarcode.focus();
                inputBarcode.select();
            }
        }, 100);

        // Mulai Perekaman Video Unboxing Otomatis
        startVideoRecording();

    } catch (err) {
        playBeep('error');
        alert("Gagal memproses invoice: " + err.message);
    }
}

window.resetInvoiceSession = function() {
    if (scannedProductsList.length > 0 && !confirm("Ada barang yang sudah di-scan. Yakin ingin mereset sesi ini?")) {
        return;
    }

    stopVideoRecording();

    activeInvoice = null;
    activeExpedition = null;
    currentDetectedProduct = null;
    scannedProductsList = [];

    // Reset UI Invoice & Ekspedisi
    document.getElementById('inputInvoice').value = '';
    const expSelect = document.getElementById('selectExpedition');
    if (expSelect) {
        expSelect.value = '';
        expSelect.classList.remove('border-emerald-500', 'bg-emerald-50/50');
    }
    const autoBadge = document.getElementById('autoDetectBadge');
    if (autoBadge) autoBadge.classList.add('hidden');

    document.getElementById('invoiceInputWrapper').classList.remove('hidden');
    document.getElementById('invoiceLockedBanner').classList.add('hidden');

    // Sembunyikan Form Produk
    document.getElementById('sectionProductInput').classList.add('hidden');
    resetProductInputs();

    // Reset Indikator Mode
    const scanMode = document.getElementById('scanModeIndicator');
    if (scanMode) {
        scanMode.innerText = 'LANGKAH 1: SCAN INVOICE';
        scanMode.className = 'font-bold text-indigo-700 bg-indigo-50 px-2 py-0.5 rounded border border-indigo-200 text-[11px]';
    }

    // Sembunyikan Modal Sukses jika ada
    document.getElementById('successModal').classList.add('hidden');

    renderItemsTable();

    // Auto Focus kembali ke Invoice
    setTimeout(() => {
        const invEl = document.getElementById('inputInvoice');
        if (invEl) invEl.focus();
    }, 100);
};

// -------------------------------------------------------------
// 2. PRODUCT SCAN & DETAIL INPUT (Barcode, Batch, Exp Date, Qty, Type)
// -------------------------------------------------------------
const inputBarcode = document.getElementById('inputBarcode');
const inputBatch = document.getElementById('inputBatch');
const inputExpDate = document.getElementById('inputExpDate');
const inputQty = document.getElementById('inputQty');
const inputType = document.getElementById('inputType');

// Helper demo click barcode
window.quickFillBarcode = function(barcode) {
    if (!activeInvoice) {
        alert("Harap scan invoice terlebih dahulu!");
        return;
    }
    inputBarcode.value = barcode;
    lookupProduct(barcode);
};

// Deteksi Enter / Scan pada Kolom Barcode
inputBarcode.addEventListener('keypress', (e) => {
    if (e.key === 'Enter') {
        e.preventDefault();
        const code = inputBarcode.value.trim();
        if (code) lookupProduct(code);
    }
});

// Deteksi Enter berpindah kolom secara natural:
// Barcode -> Batch -> ExpDate -> Qty -> Type -> Submit
inputBatch.addEventListener('keypress', (e) => {
    if (e.key === 'Enter') {
        e.preventDefault();
        inputExpDate.focus();
    }
});

inputExpDate.addEventListener('keypress', (e) => {
    if (e.key === 'Enter') {
        e.preventDefault();
        inputQty.focus();
        inputQty.select();
    }
});

inputQty.addEventListener('keypress', (e) => {
    if (e.key === 'Enter') {
        e.preventDefault();
        inputType.focus();
    }
});

inputType.addEventListener('keypress', (e) => {
    if (e.key === 'Enter') {
        e.preventDefault();
        document.getElementById('btnSubmitItem').click();
    }
});

// Fungsi Lookup Produk dari Barcode ke Database MySQL
async function lookupProduct(barcode) {
    const loading = document.getElementById('barcodeLoadingIcon');
    if (loading) loading.classList.remove('hidden');

    try {
        const res = await fetch(`api/product/${encodeURIComponent(barcode)}`);
        if (!res.ok) {
            playBeep('error');
            currentDetectedProduct = null;
            document.getElementById('detectedProductName').innerText = `Produk [${barcode}] tidak ditemukan!`;
            document.getElementById('detectedProductName').className = "font-bold text-rose-600 ml-1 text-sm";
            document.getElementById('detectedProductSku').innerText = "";
            alert(`Barcode [${barcode}] belum terdaftar di master data produk!`);
            inputBarcode.focus();
            inputBarcode.select();
            return;
        }

        const product = await res.json();
        currentDetectedProduct = product;
        playBeep('success');

        // Tampilkan info produk terdeteksi (Seller SKU, SAP Code, Rak/Bin)
        const nameEl = document.getElementById('detectedProductName');
        nameEl.innerText = product.name;
        nameEl.className = "font-bold text-emerald-700 ml-1 text-sm";
        
        const sellerSku = product.seller_sku || product.sku || '-';
        const sapCode = product.sap_code || '-';
        const binCode = product.bin_code || '';
        const shop = product.shop || '';

        document.getElementById('detectedProductSku').innerHTML = `
            <span class="inline-flex flex-wrap items-center gap-1.5 ml-2 mt-1">
                <span class="bg-indigo-100 text-indigo-800 text-[11px] font-mono font-bold px-2 py-0.5 rounded border border-indigo-200">
                    <i class="fa-solid fa-tag text-[10px]"></i> Seller SKU: ${sellerSku}
                </span>
                <span class="bg-emerald-100 text-emerald-800 text-[11px] font-mono font-bold px-2 py-0.5 rounded border border-emerald-200">
                    <i class="fa-solid fa-barcode text-[10px]"></i> SAP: ${sapCode}
                </span>
                ${binCode ? `<span class="bg-amber-100 text-amber-800 text-[11px] font-mono font-bold px-2 py-0.5 rounded border border-amber-200"><i class="fa-solid fa-cubes-stacked text-[10px]"></i> Rak: ${binCode}</span>` : ''}
                ${shop ? `<span class="bg-purple-100 text-purple-800 text-[11px] font-bold px-2 py-0.5 rounded border border-purple-200">${shop}</span>` : ''}
            </span>
        `;

        // Pindahkan kursor otomatis ke NO. BATCH!
        setTimeout(() => {
            inputBatch.focus();
        }, 50);

    } catch (err) {
        playBeep('error');
        console.error(err);
    } finally {
        if (loading) loading.classList.add('hidden');
    }
}

// Handler Submit Tambah Item ke Daftar
window.handleAddItem = function(e) {
    if (e) e.preventDefault();

    if (!activeInvoice) {
        alert("Harap scan invoice terlebih dahulu!");
        return;
    }

    const barcode = inputBarcode.value.trim();
    if (!barcode) {
        inputBarcode.focus();
        return;
    }

    if (!currentDetectedProduct || currentDetectedProduct.barcode !== barcode) {
        // Jika belum ter-lookup, lookup dulu
        lookupProduct(barcode).then(() => {
            if (currentDetectedProduct) commitAddItem();
        });
        return;
    }

    commitAddItem();
};

function formatExpDate(val) {
    if (!val || val === '-' || val.trim() === '') return '-';
    const clean = val.trim();
    if (/^\d{2}-\d{2}-\d{4}$/.test(clean)) return clean;
    const parts = clean.split(/[-/]/);
    if (parts.length === 3) {
        if (parts[0].length === 4) {
            return `${parts[2].padStart(2, '0')}-${parts[1].padStart(2, '0')}-${parts[0]}`;
        } else if (parts[2].length === 4) {
            return `${parts[0].padStart(2, '0')}-${parts[1].padStart(2, '0')}-${parts[2]}`;
        }
    }
    return clean;
}

function commitAddItem() {
    const batchNo = inputBatch.value.trim();
    const expDate = inputExpDate.value.trim();
    const qty = parseInt(inputQty.value, 10) || 1;
    const type = inputType.value || 'GOOD';

    scannedProductsList.push({
        barcode: currentDetectedProduct.barcode,
        product_name: currentDetectedProduct.name,
        sku: currentDetectedProduct.sku,
        seller_sku: currentDetectedProduct.seller_sku || currentDetectedProduct.sku || '-',
        sap_code: currentDetectedProduct.sap_code || '-',
        shop: currentDetectedProduct.shop || '',
        bin_code: currentDetectedProduct.bin_code || '',
        batch_no: batchNo || '-',
        exp_date: formatExpDate(expDate),
        qty: qty,
        type: type,
        condition: (type === 'GOOD') ? 'GOOD' : 'RUSAK'
    });

    playBeep('success');
    renderItemsTable();
    resetProductInputs();

    // Auto Focus kembali ke Barcode untuk scan item berikutnya!
    setTimeout(() => {
        inputBarcode.focus();
    }, 50);
}

function resetProductInputs() {
    currentDetectedProduct = null;
    inputBarcode.value = '';
    inputBatch.value = '';
    inputExpDate.value = '';
    inputQty.value = 1;
    inputType.value = 'GOOD';
    document.getElementById('detectedProductName').innerText = "Silakan scan / ketik barcode...";
    document.getElementById('detectedProductName').className = "font-bold text-indigo-700 ml-1 text-sm";
    document.getElementById('detectedProductSku').innerText = "";
}

// -------------------------------------------------------------
// 3. TABEL DAFTAR ITEM & RINGKASAN
// -------------------------------------------------------------
function renderItemsTable() {
    const tbody = document.getElementById('itemsTableBody');
    if (!tbody) return;
    tbody.innerHTML = '';

    if (scannedProductsList.length === 0) {
        tbody.innerHTML = `
            <tr id="emptyTablePlaceholder">
                <td colspan="8" class="text-center py-10 text-slate-400 italic">
                    Belum ada produk yang dimasukkan untuk invoice ini. Silakan scan barcode di atas.
                </td>
            </tr>`;
        document.getElementById('btnFinalizeSession').disabled = true;
        document.getElementById('totalItemsBadge').innerText = '0';
        document.getElementById('summaryTotalUnits').innerText = '0';
        return;
    }

    let totalUnits = 0;

    scannedProductsList.forEach((item, index) => {
        totalUnits += item.qty;

        // Badge Tipe
        let badge = `<span class="bg-emerald-100 text-emerald-800 px-2 py-0.5 rounded font-bold text-[10px]">GOOD</span>`;
        if (item.type === 'RUSAK') {
            badge = `<span class="bg-rose-100 text-rose-800 px-2 py-0.5 rounded font-bold text-[10px]">RUSAK</span>`;
        } else if (item.type === 'EXPIRED') {
            badge = `<span class="bg-amber-100 text-amber-800 px-2 py-0.5 rounded font-bold text-[10px]">EXPIRED</span>`;
        } else if (item.type === 'SALAH_KIRIM') {
            badge = `<span class="bg-purple-100 text-purple-800 px-2 py-0.5 rounded font-bold text-[10px]">SALAH KIRIM</span>`;
        }

        const tr = document.createElement('tr');
        tr.className = 'hover:bg-slate-50 transition border-b border-slate-100';
        tr.innerHTML = `
            <td class="p-3 text-center text-slate-400 font-mono text-[11px]">${index + 1}</td>
            <td class="p-3 font-mono font-bold text-indigo-700">${item.barcode}</td>
            <td class="p-3">
                <div class="font-bold text-slate-800">${item.product_name}</div>
                <div class="text-[10px] text-slate-500 flex flex-wrap gap-1.5 mt-0.5">
                    <span class="bg-indigo-50 text-indigo-700 px-1.5 py-0.5 rounded font-mono font-bold border border-indigo-200">SKU: ${item.seller_sku || item.sku}</span>
                    <span class="bg-emerald-50 text-emerald-700 px-1.5 py-0.5 rounded font-mono font-bold border border-emerald-200">SAP: ${item.sap_code || '-'}</span>
                    ${item.bin_code ? `<span class="bg-amber-50 text-amber-700 px-1.5 py-0.5 rounded font-mono text-[10px] border border-amber-200">Rak: ${item.bin_code}</span>` : ''}
                </div>
            </td>
            <td class="p-3 font-mono text-slate-600 whitespace-nowrap">${item.batch_no || '-'}</td>
            <td class="p-3 font-mono text-slate-600 whitespace-nowrap font-medium">${formatExpDate(item.exp_date)}</td>
            <td class="p-3 text-center font-bold text-slate-900 text-sm font-mono">${item.qty}</td>
            <td class="p-3 text-center">${badge}</td>
            <td class="p-3 text-center">
                <button type="button" onclick="removeItem(${index})" title="Hapus Item" class="text-rose-500 hover:text-rose-700 p-1.5 rounded-lg hover:bg-rose-50 transition">
                    <i class="fa-solid fa-trash-can"></i>
                </button>
            </td>
        `;
        tbody.appendChild(tr);
    });

    document.getElementById('totalItemsBadge').innerText = scannedProductsList.length;
    document.getElementById('summaryTotalUnits').innerText = totalUnits;
    document.getElementById('btnFinalizeSession').disabled = false;
}

window.removeItem = function(index) {
    scannedProductsList.splice(index, 1);
    renderItemsTable();
};

// -------------------------------------------------------------
// 4. SELESAIKAN INBOUND RETURN (Simpan ke MySQL & Upload Video)
// -------------------------------------------------------------
let mediaRecorder = null;
let recordedChunks = [];
let recordingTimerInterval = null;
let recordingSeconds = 0;
let watermarkCanvas = null;
let watermarkCtx = null;
let watermarkAnimId = null;
let isVideoRecordingActive = false;

function startVideoRecording() {
    if (!mediaStream) {
        console.warn("mediaStream belum aktif, perekaman video ditunda.");
        return;
    }
    try {
        recordedChunks = [];
        let mime = 'video/webm;codecs=vp8,opus';
        if (!window.MediaRecorder || !MediaRecorder.isTypeSupported(mime)) {
            mime = 'video/webm';
            if (!MediaRecorder.isTypeSupported(mime)) {
                mime = '';
            }
        }
        const options = mime ? { mimeType: mime } : undefined;

        // Inisialisasi Canvas Watermark (Membakar Watermark ke Dalam Video)
        const liveVideo = document.getElementById('liveVideoFeed');
        const vW = (liveVideo && liveVideo.videoWidth) ? liveVideo.videoWidth : 1280;
        const vH = (liveVideo && liveVideo.videoHeight) ? liveVideo.videoHeight : 720;

        if (!watermarkCanvas) {
            watermarkCanvas = document.createElement('canvas');
        }
        watermarkCanvas.width = vW;
        watermarkCanvas.height = vH;
        watermarkCtx = watermarkCanvas.getContext('2d');

        isVideoRecordingActive = true;

        function renderWatermarkLoop() {
            if (!isVideoRecordingActive) return;

            // 1. Render frame kamera terkini
            if (liveVideo && liveVideo.readyState >= 2) {
                watermarkCtx.drawImage(liveVideo, 0, 0, vW, vH);
            }

            // 2. Render Banner Watermark di bagian bawah video
            const barH = Math.max(38, Math.round(vH * 0.08));
            const yTop = vH - barH;

            // Background banner gelap elegan
            watermarkCtx.fillStyle = 'rgba(15, 23, 42, 0.88)';
            watermarkCtx.fillRect(0, yTop, vW, barH);

            // Garis aksen atas (Indigo)
            watermarkCtx.fillStyle = '#6366f1';
            watermarkCtx.fillRect(0, yTop, vW, 3);

            // Waktu & Tanggal Realtime
            const now = new Date();
            const dateStr = now.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
            const timeStr = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' }) + ' WIB';

            const inv = activeInvoice || '-';
            const exp = activeExpedition || 'Reguler';

            const fontSize = Math.max(12, Math.round(barH * 0.38));
            watermarkCtx.font = `bold ${fontSize}px "Segoe UI", Roboto, sans-serif`;
            watermarkCtx.textBaseline = 'middle';
            const centerY = yTop + (barH / 2) + 1;

            // Teks Kiri: INVOICE & EKSPEDISI
            watermarkCtx.fillStyle = '#ffffff';
            watermarkCtx.fillText(`INV: ${inv}`, 16, centerY);
            const invW = watermarkCtx.measureText(`INV: ${inv}`).width;

            watermarkCtx.fillStyle = '#64748b';
            watermarkCtx.fillText(' • ', 16 + invW + 3, centerY);
            const dotW = watermarkCtx.measureText(' • ').width;

            watermarkCtx.fillStyle = '#38bdf8';
            watermarkCtx.fillText(`KURIR: ${exp}`, 16 + invW + 3 + dotW + 3, centerY);

            // Teks Kanan: TANGGAL & JAM
            const rightText = `${dateStr}  ${timeStr}`;
            const rightW = watermarkCtx.measureText(rightText).width;
            watermarkCtx.fillStyle = '#f8fafc';
            watermarkCtx.fillText(rightText, vW - rightW - 16, centerY);

            watermarkAnimId = requestAnimationFrame(renderWatermarkLoop);
        }

        renderWatermarkLoop();

        // Rekam dari canvas stream (dengan watermark), fallback ke mediaStream jika tidak didukung
        let streamToRecord = mediaStream;
        try {
            if (watermarkCanvas.captureStream) {
                streamToRecord = watermarkCanvas.captureStream(25);
                if (mediaStream.getAudioTracks && mediaStream.getAudioTracks().length > 0) {
                    streamToRecord.addTrack(mediaStream.getAudioTracks()[0]);
                }
            }
        } catch (csErr) {
            console.warn("captureStream error, fallback ke mediaStream:", csErr);
            streamToRecord = mediaStream;
        }

        mediaRecorder = new MediaRecorder(streamToRecord, options);
        mediaRecorder.ondataavailable = (e) => {
            if (e.data && e.data.size > 0) {
                recordedChunks.push(e.data);
            }
        };
        mediaRecorder.start(1000); // slice tiap 1 detik

        recordingSeconds = 0;
        const recBadge = document.getElementById('cameraRecBadge');
        if (recBadge) recBadge.classList.remove('hidden');
        if (recordingTimerInterval) clearInterval(recordingTimerInterval);
        recordingTimerInterval = setInterval(() => {
            recordingSeconds++;
            const mins = String(Math.floor(recordingSeconds / 60)).padStart(2, '0');
            const secs = String(recordingSeconds % 60).padStart(2, '0');
            const timeEl = document.getElementById('cameraRecTime');
            if (timeEl) timeEl.innerText = `${mins}:${secs}`;
        }, 1000);
        console.log("Perekaman video unboxing dengan watermark dimulai...");
    } catch (e) {
        console.warn("Gagal start recording video:", e);
    }
}

function stopVideoRecording() {
    return new Promise((resolve) => {
        isVideoRecordingActive = false;
        if (watermarkAnimId) {
            cancelAnimationFrame(watermarkAnimId);
            watermarkAnimId = null;
        }

        if (recordingTimerInterval) {
            clearInterval(recordingTimerInterval);
            recordingTimerInterval = null;
        }
        const recBadge = document.getElementById('cameraRecBadge');
        if (recBadge) recBadge.classList.add('hidden');

        if (!mediaRecorder || mediaRecorder.state === 'inactive') {
            resolve(null);
            return;
        }

        mediaRecorder.onstop = () => {
            if (recordedChunks.length > 0) {
                const blob = new Blob(recordedChunks, { type: 'video/webm' });
                resolve(blob);
            } else {
                resolve(null);
            }
        };

        try {
            mediaRecorder.stop();
        } catch (e) {
            resolve(null);
        }
    });
}

window.submitFinalSession = async function() {
    if (!activeInvoice || scannedProductsList.length === 0) return;

    const notes = document.getElementById('sessionNotesInput').value.trim();
    const btn = document.getElementById('btnFinalizeSession');
    btn.disabled = true;
    btn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> Menyimpan Sesi & Video...`;

    // Ambil file video rekaman sesi unboxing jika kamera aktif
    let videoBlob = null;
    if (mediaRecorder && mediaRecorder.state === 'recording') {
        videoBlob = await stopVideoRecording();
    }

    const payload = {
        invoice_number: activeInvoice,
        expedition: activeExpedition || 'Lainnya',
        operator_name: 'Gudang 01',
        customer_name: 'Pelanggan Return',
        notes: notes,
        items: scannedProductsList
    };

    // Tampilkan bola-bola animasi loading saat menyimpan
    showGlobalLoading(
        'Menyimpan Transaksi Inbound...',
        'Sedang merekam data produk retur' + (videoBlob ? ' dan mengunggah video unboxing' : '') + ' ke server...'
    );

    try {
        let res;
        if (videoBlob && videoBlob.size > 0) {
            const formData = new FormData();
            formData.append('data', JSON.stringify(payload));
            formData.append('video', videoBlob, `video_${activeInvoice}.webm`);
            res = await fetch('api/returns.php', {
                method: 'POST',
                body: formData
            });
        } else {
            res = await fetch('api/returns.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
        }
        const result = await res.json();
        hideGlobalLoading();

        if (result.success) {
            const videoNotice = (videoBlob && videoBlob.size > 0) ? ' Rekaman video unboxing berhasil disimpan.' : '';
            document.getElementById('modalSuccessDesc').innerText = `Invoice [${activeInvoice}] berhasil disimpan ke MySQL dengan ${scannedProductsList.length} jenis barang.${videoNotice}`;
            document.getElementById('successModal').classList.remove('hidden');
        } else {
            alert("Gagal: " + (result.error || 'Terjadi kesalahan saat menyimpan'));
        }
    } catch (err) {
        hideGlobalLoading();
        alert("Gagal koneksi ke server: " + err.message);
    } finally {
        hideGlobalLoading();
        btn.disabled = false;
        btn.innerHTML = `<i class="fa-solid fa-cloud-arrow-up"></i> Selesaikan Inbound Invoice`;
    }
};

// -------------------------------------------------------------
// 5. KAMERA DOKUMENTASI / LIVE VIDEO RECORD (Tanpa Kotak Scanner)
// -------------------------------------------------------------
let videoDevices = [];
let currentDeviceIndex = 0;

async function getAvailableVideoDevices() {
    try {
        if (!navigator.mediaDevices || !navigator.mediaDevices.enumerateDevices) return [];
        const devices = await navigator.mediaDevices.enumerateDevices();
        videoDevices = devices.filter(d => d.kind === 'videoinput');
        return videoDevices;
    } catch (e) {
        console.warn("enumerateDevices error:", e);
        return [];
    }
}

window.switchCamera = async function() {
    if (videoDevices.length <= 1) {
        await getAvailableVideoDevices();
    }
    if (videoDevices.length <= 1) {
        alert("Hanya ada 1 kamera yang terdeteksi pada perangkat ini.");
        return;
    }
    currentDeviceIndex = (currentDeviceIndex + 1) % videoDevices.length;
    const targetId = videoDevices[currentDeviceIndex].deviceId;
    await startCamera(targetId);
};

window.simulateScan = function(code) {
    if (!code) return;
    onScanSuccess(code);
};

async function startCamera(deviceId = null) {
    const loading = document.getElementById('cameraLoading');
    const badge = document.getElementById('cameraStatusBadge');
    const videoElement = document.getElementById('liveVideoFeed');

    if (loading) {
        loading.classList.remove('hidden');
        loading.innerHTML = `
            <i class="fa-solid fa-circle-notch fa-spin text-3xl text-indigo-500"></i>
            <span class="text-xs text-slate-300">Menghubungkan ke kamera video...</span>
        `;
    }

    // Periksa apakah browser memblokir getUserMedia karena akses lewat IP HTTP biasa
    const isLocalhost = location.hostname === 'localhost' || location.hostname === '127.0.0.1';
    const isSecure = window.isSecureContext || isLocalhost || location.protocol === 'https:';

    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        console.warn("navigator.mediaDevices tidak tersedia. isSecureContext:", window.isSecureContext);
        isCameraActive = false;
        if (loading) {
            if (!isSecure) {
                loading.innerHTML = `
                    <div class="text-center p-4 max-w-sm space-y-2">
                        <i class="fa-solid fa-shield-halved text-amber-400 text-3xl mb-1"></i>
                        <p class="text-xs text-white font-bold">Kamera Diblokir Keamanan Browser (HTTP IP)</p>
                        <p class="text-[11px] text-slate-300 leading-normal">
                            Browser (Chrome/Edge) mewajibkan <b>localhost</b> atau <b>HTTPS</b> untuk membuka kamera webcam.
                        </p>
                        <div class="bg-slate-800/90 p-2.5 rounded-xl border border-slate-700 text-[10px] text-slate-300 text-left space-y-1">
                            <div><b>1. Jika di PC Ini (Server):</b> Buka alamat <a href="http://localhost/return.inbound" class="text-indigo-400 underline font-bold">http://localhost/return.inbound</a></div>
                            <div><b>2. Jika dari PC Lain:</b> Buka tab baru di Chrome <code>chrome://flags/#unsafely-treat-insecure-origin-as-secure</code> &rarr; masukkan <code>http://${location.host}</code> &rarr; Enabled & Relaunch.</div>
                        </div>
                        <button onclick="startCamera()" class="mt-2 bg-indigo-600 hover:bg-indigo-700 text-white px-3.5 py-1.5 rounded-lg text-xs font-semibold inline-flex items-center gap-1.5">
                            <i class="fa-solid fa-rotate-right"></i> Coba Hubungkan Lagi
                        </button>
                    </div>
                `;
            } else {
                loading.innerHTML = `
                    <div class="text-center p-4">
                        <i class="fa-solid fa-video-slash text-3xl text-rose-400 mb-2"></i>
                        <p class="text-xs text-slate-300 font-semibold">Kamera Tidak Tersedia</p>
                        <p class="text-[10px] text-slate-400 mt-1">Browser ini tidak mendukung WebRTC Camera API.</p>
                    </div>
                `;
            }
        }
        if (badge) {
            badge.className = "bg-amber-500/20 text-amber-400 border border-amber-500/30 text-xs px-2.5 py-1 rounded-full font-semibold flex items-center gap-1.5";
            badge.innerHTML = `<span class="w-2 h-2 rounded-full bg-amber-400"></span> Kamera Off (Gun Aktif)`;
        }
        return;
    }

    try {
        stopCamera();

        if (videoDevices.length === 0) {
            await getAvailableVideoDevices();
        }

        let stream = null;

        // Upaya 1: Coba dengan constraint spesifik / deviceId
        try {
            const constraints = {
                video: deviceId 
                    ? { deviceId: { exact: deviceId } } 
                    : { facingMode: { ideal: "environment" }, width: { ideal: 1280 } },
                audio: false
            };
            stream = await navigator.mediaDevices.getUserMedia(constraints);
        } catch (specErr) {
            console.warn("Gagal constraint spesifik, fallback ke video standar:", specErr);
            // Upaya 2: Fallback ke parameter paling dasar (cocok untuk semua jenis USB webcam)
            stream = await navigator.mediaDevices.getUserMedia({
                video: deviceId ? { deviceId: deviceId } : true,
                audio: false
            });
        }

        mediaStream = stream;
        await getAvailableVideoDevices();

        if (videoElement) {
            videoElement.srcObject = mediaStream;
            videoElement.muted = true;
            try {
                await videoElement.play();
            } catch (playErr) {
                console.warn("video.play error:", playErr);
            }
            if (loading) loading.classList.add('hidden');
        }

        isCameraActive = true;
        if (badge) {
            badge.className = "bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 text-xs px-2.5 py-1 rounded-full font-semibold flex items-center gap-1.5";
            badge.innerHTML = `<span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span> Video Record Siap`;
        }
    } catch (err) {
        console.warn("Kamera tidak aktif atau izin ditolak:", err);
        isCameraActive = false;
        if (loading) {
            loading.innerHTML = `
                <div class="text-center p-4 max-w-xs space-y-2">
                    <i class="fa-solid fa-video-slash text-3xl text-amber-400 mb-1"></i>
                    <p class="text-xs text-white font-semibold">Izin Kamera Belum Diberikan</p>
                    <p class="text-[10px] text-slate-400">Klik ikon kamera/gembok di sebelah kiri address bar browser Anda lalu pilih <b>Izinkan (Allow)</b>.</p>
                    <button onclick="startCamera()" class="mt-2 bg-indigo-600 hover:bg-indigo-700 text-white px-3.5 py-1.5 rounded-lg text-xs font-semibold inline-flex items-center gap-1.5">
                        <i class="fa-solid fa-power-off"></i> Nyalakan Kamera
                    </button>
                </div>
            `;
        }
        if (badge) {
            badge.className = "bg-amber-500/20 text-amber-400 border border-amber-500/30 text-xs px-2.5 py-1 rounded-full font-semibold flex items-center gap-1.5";
            badge.innerHTML = `<span class="w-2 h-2 rounded-full bg-amber-400"></span> Butuh Izin Kamera`;
        }
    }
}

function stopCamera() {
    try {
        if (mediaStream) {
            mediaStream.getTracks().forEach(track => track.stop());
            mediaStream = null;
        }
        const videoElement = document.getElementById('liveVideoFeed');
        if (videoElement) {
            videoElement.srcObject = null;
        }
        isCameraActive = false;
    } catch (e) {
        console.warn("Stop camera error:", e);
    }
}

async function onScanSuccess(decodedText) {
    const text = decodedText.trim();
    if (!text) return;

    playBeep('success');

    if (!activeInvoice) {
        // Jika invoice belum diisi, kode ini otomatis dianggap Invoice!
        if (inputInvoice) inputInvoice.value = text;
        await processInvoiceScan(text);
    } else {
        // Jika invoice sudah aktif, kode ini otomatis dianggap Barcode Produk!
        if (inputBarcode) inputBarcode.value = text;
        await lookupProduct(text);
    }
}

// Helper Algoritma Auto-Detect Ekspedisi dari Barcode / Invoice / Resi
function detectExpeditionFromCode(code) {
    if (!code || !cachedExpeditionsList || cachedExpeditionsList.length === 0) return null;
    const cleanCode = code.toUpperCase().trim();

    // 1. Cek dari prefix pattern yang terdaftar
    for (const exp of cachedExpeditionsList) {
        if (exp.status !== 'ACTIVE') continue;

        if (exp.prefix_pattern) {
            const prefixes = exp.prefix_pattern.split(',').map(p => p.trim().toUpperCase()).filter(p => p.length > 0);
            for (const prefix of prefixes) {
                if (cleanCode.startsWith(prefix)) {
                    return exp;
                }
            }
        }

        // Cek juga jika kode berawalan nama/kode ekspedisi langsung (misal SPX..., GTL...)
        if (exp.code && cleanCode.startsWith(exp.code.toUpperCase())) {
            return exp;
        }
    }
    return null;
}

// Helper Load Daftar Ekspedisi untuk Dropdown
async function loadExpeditions() {
    try {
        const res = await fetch('api/expeditions.php');
        const list = await res.json();
        cachedExpeditionsList = Array.isArray(list) ? list : [];
        const select = document.getElementById('selectExpedition');
        if (!select || !Array.isArray(list)) return;

        select.innerHTML = '<option value="">-- Pilih Ekspedisi --</option>';
        cachedExpeditionsList.forEach(item => {
            if (item.status === 'ACTIVE') {
                const opt = document.createElement('option');
                opt.value = item.name;
                opt.innerText = `${item.name} (${item.code})`;
                select.appendChild(opt);
            }
        });
    } catch (e) {
        console.warn('Gagal memuat master ekspedisi:', e);
    }
}

// Inisialisasi Otomatis saat Halaman Dimuat
window.addEventListener('DOMContentLoaded', () => {
    if (inputInvoice) {
        inputInvoice.focus();
    }
    // Muat daftar ekspedisi
    loadExpeditions();
    // Langsung jalankan kamera live scanner di sebelah kiri
    startCamera();
});
