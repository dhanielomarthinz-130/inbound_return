let mediaStream = null;
let isCameraActive = false;
let activeInvoice = null;
let activeExpedition = null;
let cachedExpeditionsList = [];
let scannedProductsList = [];
let currentDetectedProduct = null;
let capturedPackagePhoto = null;
let capturedProductPhoto = null;
let capturedPhotosList = [];

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
        showToast('error', "Gagal memproses invoice: " + err.message, "Gagal Invoice");
    }
}

window.resetInvoiceSession = function(force = false) {
    if (!force && scannedProductsList.length > 0 && !confirm("Ada barang yang sudah di-scan. Yakin ingin mereset sesi ini?")) {
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

    // Reset Foto Dokumentasi
    capturedPhotosList = [];
    capturedPackagePhoto = null;
    capturedProductPhoto = null;
    if (typeof renderPhotosGallery === 'function') renderPhotosGallery();

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
        showToast('warning', "Harap scan nomor resi / invoice terlebih dahulu!", "Invoice Belum Di-scan");
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

// -------------------------------------------------------------
// AUTO-DETECT EXP DATE DARI NO. BATCH
// -------------------------------------------------------------
function parseExpDateFromBatch(batchStr) {
    if (!batchStr) return null;
    const raw = batchStr.trim().toUpperCase();

    // 1. Cek format dengan tanda pemisah: YYYY-MM-DD, DD-MM-YYYY, DD/MM/YYYY, MM/YYYY, dll.
    let m = raw.match(/\b(20[2-3]\d)[-/.](0[1-9]|1[0-2])[-/.](0[1-9]|[12]\d|3[01])\b/);
    if (m) return { date: `${m[1]}-${m[2]}-${m[3]}`, label: 'Pola Tanggal' };

    m = raw.match(/\b(0[1-9]|[12]\d|3[01])[-/.](0[1-9]|1[0-2])[-/.](20[2-3]\d)\b/);
    if (m) return { date: `${m[3]}-${m[2]}-${m[1]}`, label: 'Pola Tanggal' };

    m = raw.match(/\b(0[1-9]|[12]\d|3[01])[-/.](0[1-9]|1[0-2])[-/.]([2-3]\d)\b/);
    if (m) return { date: `20${m[3]}-${m[2]}-${m[1]}`, label: 'Pola Tanggal' };

    m = raw.match(/\b(0[1-9]|1[0-2])[-/.](20[2-3]\d)\b/);
    if (m) return { date: `${m[2]}-${m[1]}-01`, label: 'Pola Bulan/Tahun' };

    m = raw.match(/\b(0[1-9]|1[0-2])[-/.]([2-3]\d)\b/);
    if (m) return { date: `20${m[2]}-${m[1]}-01`, label: 'Pola Bulan/Tahun' };

    // 2. Format dengan nama bulan (cth: 20 APR 2029, 20APR29, 20-APR-2029, APR 2029)
    const monthNames = {
        'JAN': '01', 'FEB': '02', 'MAR': '03', 'APR': '04',
        'MEI': '05', 'MAY': '05', 'JUN': '06', 'JUL': '07',
        'AGU': '08', 'AUG': '08', 'SEP': '09', 'OKT': '10',
        'OCT': '10', 'NOV': '11', 'DES': '12', 'DEC': '12'
    };
    const monthList = 'JAN|FEB|MAR|APR|MEI|MAY|JUN|JUL|AGU|AUG|SEP|OKT|OCT|NOV|DES|DEC';

    // cth: 20 APR 2029 atau 20APR29
    let mText = raw.match(new RegExp(`\\b(0?[1-9]|[12]\\d|3[01])[-/\\s]?(${monthList})[-/\\s]?(20[2-3]\\d|[2-3]\\d)\\b`));
    if (mText) {
        const d = mText[1].padStart(2, '0');
        const mo = monthNames[mText[2]];
        const yr = mText[3].length === 2 ? `20${mText[3]}` : mText[3];
        return { date: `${yr}-${mo}-${d}`, label: `Pola (${mText[2]} ${yr})` };
    }

    // cth: APR 2029 atau APR 29
    mText = raw.match(new RegExp(`\\b(${monthList})[-/\\s]?(20[2-3]\\d|[2-3]\\d)\\b`));
    if (mText) {
        const mo = monthNames[mText[1]];
        const yr = mText[2].length === 2 ? `20${mText[2]}` : mText[2];
        return { date: `${yr}-${mo}-01`, label: `Pola (${mText[1]} ${yr})` };
    }

    // 3. Format Huruf Bulan + Tahun Produksi + No. Lot (Standar Pabrik Kosmetik & FMCG)
    // Cth: D26342 -> D = Bulan 4 (April), 26 = Thn Produksi 2026 -> Exp: 2026 + 3 thn shelf-life = Apr 2029
    // Huruf A=Jan, B=Feb, C=Mar, D=Apr, E=Mei, F=Jun, G=Jul, H=Agu, I=Sep, J=Okt, K=Nov, L=Des
    const letterToMonth = {
        'A': { mo: '01', name: 'Jan' },
        'B': { mo: '02', name: 'Feb' },
        'C': { mo: '03', name: 'Mar' },
        'D': { mo: '04', name: 'Apr' },
        'E': { mo: '05', name: 'Mei' },
        'F': { mo: '06', name: 'Jun' },
        'G': { mo: '07', name: 'Jul' },
        'H': { mo: '08', name: 'Agu' },
        'I': { mo: '09', name: 'Sep' },
        'J': { mo: '10', name: 'Okt' },
        'K': { mo: '11', name: 'Nov' },
        'L': { mo: '12', name: 'Des' }
    };
    const fmcgMatch = raw.match(/^([A-L])(2[3-9]|[3-4]\d)(\d{1,5})?$/);
    if (fmcgMatch) {
        const letter = fmcgMatch[1];
        const mfgYear = parseInt(fmcgMatch[2]); // 26 -> 2026
        const info = letterToMonth[letter];
        if (info && mfgYear >= 23 && mfgYear <= 35) {
            const expYear = 2000 + mfgYear + 3; // Masa simpan standar kosmetik/FMCG: 3 Tahun
            return {
                date: `${expYear}-${info.mo}-20`,
                label: `Pola ${letter}${mfgYear} (${info.name} ${expYear})`
            };
        }
    }

    // 4. Bersihkan prefix umum (B, LOT, EXP, ED, BT, BATCH, #, dll)
    const cleanedDigits = raw.replace(/^(B|LOT|EXP|ED|BT|BATCH|L)[-_\s.:]*/i, '');
    const numMatch = cleanedDigits.match(/\d{4,8}/);
    if (!numMatch) return null;
    const digits = numMatch[0];

    // Kasus 8 digit: YYYYMMDD atau DDMMYYYY
    if (digits.length === 8) {
        const y1 = parseInt(digits.slice(0, 4));
        const m1 = parseInt(digits.slice(4, 6));
        const d1 = parseInt(digits.slice(6, 8));
        if (y1 >= 2023 && y1 <= 2038 && m1 >= 1 && m1 <= 12 && d1 >= 1 && d1 <= 31) {
            return { date: `${y1}-${String(m1).padStart(2, '0')}-${String(d1).padStart(2, '0')}`, label: 'Pola 8 Digit' };
        }

        const d2 = parseInt(digits.slice(0, 2));
        const m2 = parseInt(digits.slice(2, 4));
        const y2 = parseInt(digits.slice(4, 8));
        if (y2 >= 2023 && y2 <= 2038 && m2 >= 1 && m2 <= 12 && d2 >= 1 && d2 <= 31) {
            return { date: `${y2}-${String(m2).padStart(2, '0')}-${String(d2).padStart(2, '0')}`, label: 'Pola 8 Digit' };
        }
    }

    // Kasus 6 digit: YYMMDD atau DDMMYY (Format standar Indonesia, cth: B260901 -> 2026-09-01)
    if (digits.length === 6) {
        const yy1 = parseInt(digits.slice(0, 2));
        const mm1 = parseInt(digits.slice(2, 4));
        const dd1 = parseInt(digits.slice(4, 6));
        if (yy1 >= 23 && yy1 <= 38 && mm1 >= 1 && mm1 <= 12 && dd1 >= 1 && dd1 <= 31) {
            return { date: `20${String(yy1).padStart(2, '0')}-${String(mm1).padStart(2, '0')}-${String(dd1).padStart(2, '0')}`, label: 'Pola 6 Digit' };
        }

        const dd2 = parseInt(digits.slice(0, 2));
        const mm2 = parseInt(digits.slice(2, 4));
        const yy2 = parseInt(digits.slice(4, 6));
        if (yy2 >= 23 && yy2 <= 38 && mm2 >= 1 && mm2 <= 12 && dd2 >= 1 && dd2 <= 31) {
            return { date: `20${String(yy2).padStart(2, '0')}-${String(mm2).padStart(2, '0')}-${String(dd2).padStart(2, '0')}`, label: 'Pola 6 Digit' };
        }
    }

    // Kasus 5 digit Julian date: YYDDD (cth: 26244 -> thn 2026, hari ke-244)
    if (digits.length === 5) {
        const yy = parseInt(digits.slice(0, 2));
        const ddd = parseInt(digits.slice(2, 5));
        if (yy >= 23 && yy <= 38 && ddd >= 1 && ddd <= 366) {
            const dateObj = new Date(2000 + yy, 0, ddd);
            if (!isNaN(dateObj.getTime())) {
                const yyyy = dateObj.getFullYear();
                const mm = String(dateObj.getMonth() + 1).padStart(2, '0');
                const dd = String(dateObj.getDate()).padStart(2, '0');
                return { date: `${yyyy}-${mm}-${dd}`, label: 'Pola Julian Date' };
            }
        }
    }

    // Kasus 4 digit: YYMM atau MMYY (cth: 2609 -> 2026-09-01)
    if (digits.length === 4) {
        const p1 = parseInt(digits.slice(0, 2));
        const p2 = parseInt(digits.slice(2, 4));
        if (p1 >= 23 && p1 <= 38 && p2 >= 1 && p2 <= 12) {
            return { date: `20${p1}-${String(p2).padStart(2, '0')}-01`, label: 'Pola YYMM' };
        }
        if (p1 >= 1 && p1 <= 12 && p2 >= 23 && p2 <= 38) {
            return { date: `20${p2}-${String(p1).padStart(2, '0')}-01`, label: 'Pola MMYY' };
        }
    }

    return null;
}

let batchLookupDebounceTimer = null;

async function checkAndApplyExpDateFromBatch(batchVal) {
    const val = (batchVal || '').trim();
    if (!val) {
        clearAutoExpIndicator();
        return false;
    }

    // 1. Coba deteksi pola tanggal secara cerdas langsung di browser (0ms / instan)
    const detected = parseExpDateFromBatch(val);
    if (detected) {
        const expDateStr = typeof detected === 'object' ? detected.date : detected;
        const expLabel = typeof detected === 'object' && detected.label ? detected.label : 'Pola Batch Terdeteksi';
        inputExpDate.value = expDateStr;
        showAutoExpIndicator(expLabel);
    }

    // 2. Cek juga riwayat batch dari server database (untuk mengambil tanggal persis yang pernah diinput sebelumnya)
    const currentBarcode = inputBarcode ? inputBarcode.value.trim() : '';
    if (batchLookupDebounceTimer) clearTimeout(batchLookupDebounceTimer);
    batchLookupDebounceTimer = setTimeout(async () => {
        try {
            const res = await fetch(`api/batch_lookup.php?barcode=${encodeURIComponent(currentBarcode)}&batch=${encodeURIComponent(val)}`);
            const data = await res.json();
            if (data && data.found && data.exp_date) {
                inputExpDate.value = data.exp_date;
                showAutoExpIndicator('Dari Riwayat Scan');
            } else if (!detected) {
                clearAutoExpIndicator();
            }
        } catch (e) {
            if (!detected) clearAutoExpIndicator();
        }
    }, 200);

    return Boolean(detected);
}

function showAutoExpIndicator(label = 'Auto') {
    const badge = document.getElementById('autoExpBadge');
    const badgeLabel = document.getElementById('autoExpLabel');
    if (badge) {
        if (badgeLabel) badgeLabel.innerText = label;
        badge.classList.remove('hidden');
    }
    if (inputExpDate) {
        inputExpDate.classList.add('border-emerald-500', 'bg-emerald-50/50');
    }
}

function clearAutoExpIndicator() {
    const badge = document.getElementById('autoExpBadge');
    if (badge) badge.classList.add('hidden');
    if (inputExpDate) {
        inputExpDate.classList.remove('border-emerald-500', 'bg-emerald-50/50');
    }
}

// Deteksi perubahan pada input No. Batch
inputBatch.addEventListener('input', () => {
    checkAndApplyExpDateFromBatch(inputBatch.value);
});

inputBatch.addEventListener('paste', () => {
    setTimeout(() => {
        checkAndApplyExpDateFromBatch(inputBatch.value);
    }, 50);
});

// Deteksi Enter berpindah kolom secara terpisah & berurutan:
// Barcode -> (Enter) -> Batch -> (Enter) -> Exp Date -> (Enter) -> Qty -> (Enter) -> Type -> (Enter) -> Submit
inputBatch.addEventListener('keypress', (e) => {
    if (e.key === 'Enter') {
        e.preventDefault();
        // Tombol Enter terpisah: selalu arahkan ke kolom Exp Date
        if (inputExpDate) {
            inputExpDate.focus();
        }
    }
});

inputExpDate.addEventListener('keypress', (e) => {
    if (e.key === 'Enter') {
        e.preventDefault();
        if (inputQty) {
            inputQty.focus();
            inputQty.select();
        }
    }
});

inputQty.addEventListener('keypress', (e) => {
    if (e.key === 'Enter') {
        e.preventDefault();
        if (inputType) {
            inputType.focus();
        }
    }
});

inputType.addEventListener('keypress', (e) => {
    if (e.key === 'Enter') {
        e.preventDefault();
        const btnAdd = document.getElementById('btnSubmitItem');
        if (btnAdd) btnAdd.click();
    }
});

// -------------------------------------------------------------
// VIRTUAL ENTER BUTTON & INPUT FOCUS TRACKER
// -------------------------------------------------------------
let currentActiveFieldId = 'inputBarcode';

function updateVirtualEnterBadge(fieldId) {
    currentActiveFieldId = fieldId;
    const labelEl = document.getElementById('virtualEnterTargetLabel');
    if (!labelEl) return;
    if (fieldId === 'inputBarcode') {
        labelEl.innerText = 'Lanjut ke No. Batch ➔';
    } else if (fieldId === 'inputBatch') {
        labelEl.innerText = 'Lanjut ke Exp Date ➔';
    } else if (fieldId === 'inputExpDate') {
        labelEl.innerText = 'Lanjut ke Qty ➔';
    } else if (fieldId === 'inputQty') {
        labelEl.innerText = 'Lanjut ke Type ➔';
    } else if (fieldId === 'inputType') {
        labelEl.innerText = 'Submit Tambah Item ✓';
    }
}

['inputBarcode', 'inputBatch', 'inputExpDate', 'inputQty', 'inputType'].forEach(id => {
    const el = document.getElementById(id);
    if (el) {
        el.addEventListener('focus', () => updateVirtualEnterBadge(id));
    }
});

window.triggerVirtualEnter = function() {
    if (currentActiveFieldId === 'inputBarcode') {
        const barcodeVal = inputBarcode ? inputBarcode.value.trim() : '';
        if (barcodeVal) {
            lookupProduct(barcodeVal).then(() => {
                if (inputBatch) inputBatch.focus();
            });
        } else {
            if (inputBatch) inputBatch.focus();
        }
    } else if (currentActiveFieldId === 'inputBatch') {
        if (inputExpDate) inputExpDate.focus();
    } else if (currentActiveFieldId === 'inputExpDate') {
        if (inputQty) {
            inputQty.focus();
            inputQty.select();
        }
    } else if (currentActiveFieldId === 'inputQty') {
        if (inputType) inputType.focus();
    } else if (currentActiveFieldId === 'inputType') {
        const btnAdd = document.getElementById('btnSubmitItem');
        if (btnAdd) btnAdd.click();
    } else {
        if (inputBarcode) inputBarcode.focus();
    }
};

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
            showToast('warning', `Barcode [${barcode}] belum terdaftar di master data produk!`, "Produk Tidak Ditemukan");
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
        showToast('warning', "Harap scan nomor resi / invoice terlebih dahulu!", "Invoice Belum Di-scan");
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
    clearAutoExpIndicator();
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
let watermarkIntervalId = null;
let isVideoRecordingActive = false;
let pendingVideoRecord = false;

function startVideoRecording() {
    if (!mediaStream) {
        console.warn("mediaStream belum aktif, perekaman video ditunda hingga kamera siap.");
        pendingVideoRecord = true;
        return;
    }
    pendingVideoRecord = false;

    try {
        if (mediaRecorder && mediaRecorder.state !== 'inactive') {
            try { mediaRecorder.stop(); } catch(e){}
        }
        recordedChunks = [];

        let mime = '';
        const candidateTypes = [
            'video/webm;codecs=vp8,opus',
            'video/webm;codecs=vp8',
            'video/webm',
            'video/mp4;codecs=avc1',
            'video/mp4'
        ];
        if (window.MediaRecorder) {
            for (const t of candidateTypes) {
                if (MediaRecorder.isTypeSupported(t)) {
                    mime = t;
                    break;
                }
            }
        }

        // Gunakan bitrate hemat 800 kbps agar file sangat ringan & cepat diunggah
        const options = {
            mimeType: mime || 'video/webm',
            videoBitsPerSecond: 800000
        };

        try {
            mediaRecorder = new MediaRecorder(mediaStream, options);
        } catch (recErr) {
            console.warn("MediaRecorder dengan options gagal, fallback:", recErr);
            mediaRecorder = new MediaRecorder(mediaStream);
        }

        mediaRecorder.ondataavailable = (e) => {
            if (e.data && e.data.size > 0) {
                recordedChunks.push(e.data);
            }
        };

        mediaRecorder.onerror = (err) => {
            console.error("MediaRecorder runtime error:", err);
        };

        mediaRecorder.start(1000); // kumpulkan chunk tiap 1 detik

        isVideoRecordingActive = true;
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

        console.log("Perekaman video unboxing aktif dimulai untuk invoice:", activeInvoice);
    } catch (e) {
        console.warn("Gagal start recording video:", e);
    }
}

function stopVideoRecording() {
    return new Promise((resolve) => {
        isVideoRecordingActive = false;
        pendingVideoRecord = false;

        if (recordingTimerInterval) {
            clearInterval(recordingTimerInterval);
            recordingTimerInterval = null;
        }
        const recBadge = document.getElementById('cameraRecBadge');
        if (recBadge) recBadge.classList.add('hidden');

        if (!mediaRecorder || mediaRecorder.state === 'inactive') {
            if (recordedChunks.length > 0) {
                resolve(new Blob(recordedChunks, { type: 'video/webm' }));
            } else {
                resolve(null);
            }
            return;
        }

        let resolved = false;
        const safetyTimeout = setTimeout(() => {
            if (!resolved) {
                resolved = true;
                if (recordedChunks.length > 0) {
                    resolve(new Blob(recordedChunks, { type: 'video/webm' }));
                } else {
                    resolve(null);
                }
            }
        }, 1200);

        mediaRecorder.onstop = () => {
            if (!resolved) {
                resolved = true;
                clearTimeout(safetyTimeout);
                if (recordedChunks.length > 0) {
                    resolve(new Blob(recordedChunks, { type: 'video/webm' }));
                } else {
                    resolve(null);
                }
            }
        };

        try {
            if (mediaRecorder.state === 'recording' && typeof mediaRecorder.requestData === 'function') {
                mediaRecorder.requestData();
            }
            mediaRecorder.stop();
        } catch (e) {
            console.warn("mediaRecorder.stop error:", e);
            if (!resolved) {
                resolved = true;
                clearTimeout(safetyTimeout);
                if (recordedChunks.length > 0) {
                    resolve(new Blob(recordedChunks, { type: 'video/webm' }));
                } else {
                    resolve(null);
                }
            }
        }
    });
}

window.submitFinalSession = async function() {
    if (!activeInvoice || scannedProductsList.length === 0) return;

    const notes = document.getElementById('sessionNotesInput').value.trim();
    const btn = document.getElementById('btnFinalizeSession');
    btn.disabled = true;
    btn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> Menyimpan Sesi & Video...`;

    // Ambil rekaman video unboxing dari sesi ini
    let videoBlob = null;
    if (mediaRecorder && (mediaRecorder.state === 'recording' || mediaRecorder.state === 'paused')) {
        videoBlob = await stopVideoRecording();
    } else if (recordedChunks.length > 0) {
        videoBlob = new Blob(recordedChunks, { type: 'video/webm' });
    }

    const displayOp = document.getElementById('displayOperator');
    const operatorName = (displayOp && displayOp.innerText.trim()) ? displayOp.innerText.trim() : 'Gudang 01';

    const savedInv = activeInvoice;
    const totalItemsCount = scannedProductsList.length;

    const payload = {
        invoice_number: activeInvoice,
        expedition: activeExpedition || 'Lainnya',
        operator_name: operatorName,
        customer_name: 'Pelanggan Return',
        notes: notes,
        package_photo: capturedPackagePhoto,
        product_photo: capturedProductPhoto,
        photos: capturedPhotosList.map(p => ({
            type: p.type,
            data: p.dataUrl,
            title: p.title
        })),
        items: scannedProductsList
    };

    showGlobalLoading(
        'Menyimpan Transaksi...',
        'Sedang mencatat data produk' + (videoBlob ? ' dan rekaman video unboxing' : '') + '...'
    );

    try {
        let res;
        if (videoBlob && videoBlob.size > 0) {
            const formData = new FormData();
            formData.append('data', JSON.stringify(payload));
            formData.append('video', videoBlob, `video_${savedInv}.webm`);
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

        const rawText = await res.text();
        let result;
        try {
            result = JSON.parse(rawText);
        } catch (jsonErr) {
            result = {
                success: false,
                error: `Respon server [HTTP ${res.status}]: ${rawText.replace(/<[^>]*>?/gm, '').trim().substring(0, 150)}`
            };
        }

        hideGlobalLoading();

        if (result.success) {
            playBeep('success');

            // 1. Popup modal sukses dihilangkan sepenuhnya
            const successModal = document.getElementById('successModal');
            if (successModal) successModal.classList.add('hidden');

            // 2. Langsung reset sesi dan mulai scan baru secara instan
            resetInvoiceSession(true);

            // 3. Tampilkan toast notifikasi cepat dan elegan
            const vidNote = (videoBlob && videoBlob.size > 0) ? ' & video rekaman' : '';
            showToast('success', `Invoice [${savedInv}]${vidNote} berhasil disimpan (${totalItemsCount} barang). Silakan scan invoice baru!`, "Inbound Selesai");
        } else {
            showToast('error', result.error || 'Terjadi kesalahan saat menyimpan', "Gagal Menyimpan");
        }
    } catch (err) {
        hideGlobalLoading();
        showToast('error', "Gagal koneksi ke server: " + err.message, "Koneksi Terputus");
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
        showToast('info', "Hanya ada 1 kamera yang terdeteksi pada perangkat ini.", "Informasi Kamera");
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

        // Jika invoice sudah aktif/terkunci (atau tertunda menunggu kamera siap), otomatis mulai rekam sekarang!
        if (activeInvoice && (pendingVideoRecord || !mediaRecorder || mediaRecorder.state === 'inactive')) {
            console.log("Kamera siap, otomatis memulai perekaman unboxing untuk invoice:", activeInvoice);
            startVideoRecording();
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

// -------------------------------------------------------------
// 7. FOTO UNBOXING DOKUMENTASI (PAKET & PRODUK DENGAN WATERMARK)
// -------------------------------------------------------------
function playShutterSound() {
    try {
        const ctx = audioCtx;
        if (ctx.state === 'suspended') ctx.resume();
        const now = ctx.currentTime;

        // Click 1 (shutter open)
        const osc1 = ctx.createOscillator();
        const gain1 = ctx.createGain();
        osc1.type = 'triangle';
        osc1.frequency.setValueAtTime(1400, now);
        osc1.frequency.exponentialRampToValueAtTime(300, now + 0.04);
        gain1.gain.setValueAtTime(0.3, now);
        gain1.gain.exponentialRampToValueAtTime(0.01, now + 0.04);
        osc1.connect(gain1);
        gain1.connect(ctx.destination);
        osc1.start(now);
        osc1.stop(now + 0.04);

        // Click 2 (shutter close)
        const osc2 = ctx.createOscillator();
        const gain2 = ctx.createGain();
        osc2.type = 'triangle';
        osc2.frequency.setValueAtTime(1100, now + 0.06);
        osc2.frequency.exponentialRampToValueAtTime(200, now + 0.12);
        gain2.gain.setValueAtTime(0.35, now + 0.06);
        gain2.gain.exponentialRampToValueAtTime(0.01, now + 0.12);
        osc2.connect(gain2);
        gain2.connect(ctx.destination);
        osc2.start(now + 0.06);
        osc2.stop(now + 0.12);
    } catch (e) {}
}

function triggerCameraFlash() {
    const flash = document.getElementById('cameraFlashOverlay');
    if (!flash) return;
    flash.classList.remove('opacity-0');
    flash.classList.add('opacity-90');
    setTimeout(() => {
        flash.classList.remove('opacity-90');
        flash.classList.add('opacity-0');
    }, 120);
}

function getNowFormattedWIB() {
    const d = new Date();
    const pad = n => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())} WIB`;
}

function generateWatermarkedPhoto({ badgeText, badgeColor = '#4f46e5', fields = [], sourceImage = null }) {
    let source = sourceImage;
    if (!source) {
        const videoElement = document.getElementById('liveVideoFeed');
        if (!videoElement || videoElement.readyState < 2) {
            throw new Error("Kamera belum aktif atau belum siap menerima gambar.");
        }
        source = videoElement;
    }

    const canvas = document.createElement('canvas');
    const width = source.videoWidth || source.naturalWidth || source.width || 1280;
    const height = source.videoHeight || source.naturalHeight || source.height || 720;
    
    if (!width || !height || width === 0 || height === 0) {
        throw new Error("Ukuran frame gambar kamera tidak valid.");
    }

    canvas.width = width;
    canvas.height = height;
    const ctx = canvas.getContext('2d');

    // 1. Gambar frame langsung dari webcam / gambar
    ctx.drawImage(source, 0, 0, width, height);

    // 2. Bar Atas (Header Branding & Badge)
    const topBarHeight = Math.max(48, Math.round(height * 0.075));
    ctx.fillStyle = 'rgba(15, 23, 42, 0.88)';
    ctx.fillRect(0, 0, width, topBarHeight);

    // Garis aksen tipis di bawah bar atas
    ctx.fillStyle = badgeColor;
    ctx.fillRect(0, topBarHeight, width, 3);

    // Judul Kiri Atas
    const titleFontSize = Math.max(14, Math.round(topBarHeight * 0.40));
    ctx.font = `bold ${titleFontSize}px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif`;
    ctx.fillStyle = '#ffffff';
    ctx.fillText("IEG • INBOUND RETURN STATION", 20, Math.round(topBarHeight * 0.65));

    // Badge Kanan Atas
    const badgeFontSize = Math.max(12, Math.round(topBarHeight * 0.36));
    ctx.font = `bold ${badgeFontSize}px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif`;
    const badgeW = ctx.measureText(badgeText).width + 24;
    const badgeH = Math.round(topBarHeight * 0.62);
    const badgeX = width - badgeW - 20;
    const badgeY = Math.round((topBarHeight - badgeH) / 2);

    ctx.fillStyle = badgeColor;
    ctx.beginPath();
    if (ctx.roundRect) {
        ctx.roundRect(badgeX, badgeY, badgeW, badgeH, 6);
    } else {
        ctx.rect(badgeX, badgeY, badgeW, badgeH);
    }
    ctx.fill();

    ctx.fillStyle = '#ffffff';
    ctx.fillText(badgeText, badgeX + 12, badgeY + badgeH * 0.72);

    // 3. Panel Watermark Bawah (Gradient hitam transparan dengan detail tajam)
    const totalLines = Math.ceil(fields.length / 2);
    const bottomH = Math.max(95, Math.round(height * (0.05 + totalLines * 0.04)));
    const grad = ctx.createLinearGradient(0, height - bottomH - 35, 0, height);
    grad.addColorStop(0, 'rgba(0, 0, 0, 0)');
    grad.addColorStop(0.25, 'rgba(15, 23, 42, 0.90)');
    grad.addColorStop(1, 'rgba(15, 23, 42, 0.98)');
    ctx.fillStyle = grad;
    ctx.fillRect(0, height - bottomH - 35, width, bottomH + 35);

    // Garis aksen di atas panel bawah
    ctx.fillStyle = badgeColor;
    ctx.fillRect(0, height - bottomH - 2, width, 3);

    // Render Fields Teks 2 Kolom
    const fontSize = Math.max(13, Math.round(height * 0.024));
    const col1X = 24;
    const col2X = Math.round(width * 0.52);
    let startY = height - bottomH + fontSize + 4;
    const lineSpacing = Math.round(fontSize * 1.55);

    fields.forEach((f, idx) => {
        const isCol2 = (idx % 2 === 1);
        const x = isCol2 ? col2X : col1X;
        const y = startY + Math.floor(idx / 2) * lineSpacing;

        ctx.font = `500 ${fontSize}px monospace, sans-serif`;
        ctx.fillStyle = '#94a3b8';
        const labelText = f.label + ': ';
        ctx.fillText(labelText, x, y);
        const lblW = ctx.measureText(labelText).width;

        ctx.font = `bold ${fontSize}px monospace, sans-serif`;
        ctx.fillStyle = f.highlight ? '#fde047' : '#ffffff';
        ctx.fillText(f.val || '-', x + lblW, y);
    });

    return canvas.toDataURL('image/jpeg', 0.88);
}

window.renderPhotosGallery = function() {
    const listEl = document.getElementById('photosGridList');
    const emptyEl = document.getElementById('photosEmptyState');
    const badgeEl = document.getElementById('badgeTotalPhotos');
    const btnClearEl = document.getElementById('btnClearAllPhotos');

    const total = capturedPhotosList.length;
    if (badgeEl) {
        badgeEl.innerText = `${total} Foto`;
        if (total > 0) {
            badgeEl.className = 'text-xs font-bold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 border border-emerald-300';
        } else {
            badgeEl.className = 'text-xs font-semibold px-2 py-0.5 rounded-full bg-slate-100 text-slate-500';
        }
    }

    if (btnClearEl) {
        if (total > 0) btnClearEl.classList.remove('hidden');
        else btnClearEl.classList.add('hidden');
    }

    // Perbarui legacy fallback
    const pkgPhotos = capturedPhotosList.filter(p => p.type === 'package');
    const prodPhotos = capturedPhotosList.filter(p => p.type === 'product');
    capturedPackagePhoto = pkgPhotos.length > 0 ? pkgPhotos[pkgPhotos.length - 1].dataUrl : null;
    capturedProductPhoto = prodPhotos.length > 0 ? prodPhotos[prodPhotos.length - 1].dataUrl : null;

    if (!listEl) return;

    if (total === 0) {
        if (emptyEl) emptyEl.classList.remove('hidden');
        listEl.innerHTML = '';
        listEl.classList.add('hidden');
        return;
    }

    if (emptyEl) emptyEl.classList.add('hidden');
    listEl.classList.remove('hidden');
    listEl.innerHTML = '';

    capturedPhotosList.forEach((item, index) => {
        const isPkg = (item.type === 'package');
        const badgeColor = isPkg ? 'bg-indigo-600' : 'bg-emerald-600';
        const badgeIcon = isPkg ? 'fa-box' : 'fa-tag';
        const badgeText = isPkg ? 'Paket' : 'Produk';
        const safeTitle = (item.title || 'Foto Unboxing').replace(/"/g, '&quot;');

        const card = document.createElement('div');
        card.className = 'relative group bg-slate-900 rounded-xl overflow-hidden border border-slate-200 shadow-sm hover:shadow-md transition';
        card.innerHTML = `
            <div class="aspect-video w-full overflow-hidden bg-slate-950 flex items-center justify-center cursor-pointer" onclick="previewImageDirect('${item.dataUrl}', '${safeTitle}')">
                <img src="${item.dataUrl}" alt="${safeTitle}" class="w-full h-full object-cover transition duration-200 group-hover:scale-105">
            </div>
            <!-- Header Badges -->
            <div class="absolute top-1.5 left-1.5 flex items-center gap-1 pointer-events-none">
                <span class="${badgeColor} text-white text-[10px] font-bold px-1.5 py-0.5 rounded shadow">
                    <i class="fa-solid ${badgeIcon} text-[9px] mr-0.5"></i>${badgeText} #${index + 1}
                </span>
            </div>
            <!-- Action Buttons -->
            <div class="absolute top-1.5 right-1.5 flex items-center gap-1">
                <button type="button" onclick="previewImageDirect('${item.dataUrl}', '${safeTitle}')" class="w-6 h-6 rounded-full bg-slate-900/80 hover:bg-indigo-600 text-white flex items-center justify-center text-[10px] transition shadow" title="Perbesar Foto">
                    <i class="fa-solid fa-expand"></i>
                </button>
                <button type="button" onclick="deletePhotoItem('${item.id}')" class="w-6 h-6 rounded-full bg-slate-900/80 hover:bg-rose-600 text-white flex items-center justify-center text-[10px] transition shadow" title="Hapus Foto">
                    <i class="fa-solid fa-trash-can"></i>
                </button>
            </div>
            <!-- Bottom Title Bar -->
            <div class="p-1.5 bg-white border-t border-slate-100 flex items-center justify-between text-[11px]">
                <span class="truncate font-semibold text-slate-700" title="${safeTitle}">${safeTitle}</span>
                <span class="text-[9px] text-slate-400 font-mono flex-shrink-0 ml-1">${item.createdAt ? item.createdAt.split(' ')[1] : ''}</span>
            </div>
        `;
        listEl.appendChild(card);
    });
};

window.deletePhotoItem = function(id) {
    capturedPhotosList = capturedPhotosList.filter(p => p.id !== id);
    renderPhotosGallery();
    showToast('info', 'Foto telah dihapus dari antrean unboxing.', 'Foto Dihapus');
};

window.clearAllPhotos = function() {
    if (capturedPhotosList.length === 0) return;
    if (confirm(`Hapus seluruh (${capturedPhotosList.length}) foto dokumentasi ini?`)) {
        capturedPhotosList = [];
        capturedPackagePhoto = null;
        capturedProductPhoto = null;
        renderPhotosGallery();
        showToast('info', 'Semua foto dokumentasi telah dibersihkan.', 'Foto Dibersihkan');
    }
};

window.previewImageDirect = function(src, title = 'Preview Foto Watermark') {
    if (!src) return;
    const modalImg = document.getElementById('photoPreviewModalImg');
    const modalTitle = document.getElementById('photoPreviewModalTitle');
    const modal = document.getElementById('photoPreviewModal');
    if (modalImg) modalImg.src = src;
    if (modalTitle) modalTitle.innerText = title;
    if (modal) modal.classList.remove('hidden');
};

window.capturePackagePhoto = function(sourceImage = null) {
    const videoElement = document.getElementById('liveVideoFeed');
    if (!sourceImage && (!isCameraActive || !videoElement || videoElement.readyState < 2)) {
        showToast('warning', 'Kamera belum aktif. Menghubungkan kamera...', 'Kamera Belum Aktif');
        startCamera();
        return;
    }

    try {
        if (!sourceImage) {
            triggerCameraFlash();
            playShutterSound();
        }

        const displayOp = document.getElementById('displayOperator');
        const opName = (displayOp && displayOp.innerText.trim()) ? displayOp.innerText.trim() : 'Gudang 01';
        const inv = activeInvoice || document.getElementById('inputInvoice')?.value?.trim() || 'MENUNGGU_SCAN';
        const exp = activeExpedition || document.getElementById('selectExpedition')?.value || 'Reguler / Kurir';

        const fields = [
            { label: 'NO. INVOICE / RESI', val: inv, highlight: true },
            { label: 'EKSPEDISI PENGANTAR', val: exp },
            { label: 'PETUGAS OPERATOR', val: opName },
            { label: 'WAKTU DOKUMENTASI', val: getNowFormattedWIB() }
        ];

        const dataUrl = generateWatermarkedPhoto({
            badgeText: '📦 FOTO PAKET UNBOXING',
            badgeColor: '#4f46e5',
            fields: fields,
            sourceImage: sourceImage
        });

        const pkgCount = capturedPhotosList.filter(p => p.type === 'package').length + 1;
        const photoItem = {
            id: 'pkg_' + Date.now() + '_' + Math.random().toString(36).substring(2, 6),
            type: 'package',
            title: `Foto Paket #${pkgCount} (${inv})`,
            dataUrl: dataUrl,
            createdAt: getNowFormattedWIB()
        };
        capturedPhotosList.push(photoItem);
        renderPhotosGallery();

        showToast('success', `Foto Paket #${pkgCount} berhasil disimpan! [Tuts F2]`, 'Foto Paket Siap');
    } catch (err) {
        console.error("capturePackagePhoto error:", err);
        showToast('error', 'Gagal mengambil foto paket: ' + err.message, 'Gagal Foto');
    }
};

window.captureProductPhoto = function(sourceImage = null) {
    const videoElement = document.getElementById('liveVideoFeed');
    if (!sourceImage && (!isCameraActive || !videoElement || videoElement.readyState < 2)) {
        showToast('warning', 'Kamera belum aktif. Menghubungkan kamera...', 'Kamera Belum Aktif');
        startCamera();
        return;
    }

    try {
        if (!sourceImage) {
            triggerCameraFlash();
            playShutterSound();
        }

        const displayOp = document.getElementById('displayOperator');
        const opName = (displayOp && displayOp.innerText.trim()) ? displayOp.innerText.trim() : 'Gudang 01';
        const inv = activeInvoice || document.getElementById('inputInvoice')?.value?.trim() || 'MENUNGGU_SCAN';

        const batchEl = document.getElementById('inputBatch');
        const expEl = document.getElementById('inputExpDate');
        const typeEl = document.getElementById('inputType');

        let pBatch = (batchEl && batchEl.value) ? batchEl.value.trim() : '-';
        let pExp = (expEl && expEl.value) ? expEl.value.trim() : '-';
        let pType = (typeEl && typeEl.value) ? typeEl.value : 'GOOD';
        let pName = 'Produk Return';
        let pSku = '-';
        let pSap = '-';

        if (typeof currentDetectedProduct !== 'undefined' && currentDetectedProduct) {
            pName = currentDetectedProduct.name || 'Produk Return';
            pSku = currentDetectedProduct.seller_sku || currentDetectedProduct.sku || '-';
            pSap = currentDetectedProduct.sap_code || '-';
        } else if (Array.isArray(scannedProductsList) && scannedProductsList.length > 0) {
            const last = scannedProductsList[scannedProductsList.length - 1];
            pName = last.product_name || 'Produk Return';
            pSku = last.seller_sku || last.sku || '-';
            pSap = last.sap_code || '-';
            pBatch = last.batch_no || pBatch;
            pExp = last.exp_date || pExp;
            pType = last.type || pType;
        }

        const fields = [
            { label: 'NO. INVOICE', val: inv, highlight: true },
            { label: 'KONDISI / TIPE', val: pType, highlight: (pType !== 'GOOD') },
            { label: 'NAMA PRODUK', val: pName.length > 28 ? pName.substring(0, 28) + '...' : pName },
            { label: 'SKU / SAP', val: `${pSku} | ${pSap}` },
            { label: 'BATCH & EXP', val: `B:${pBatch || '-'} | Exp:${pExp || '-'}` },
            { label: 'WAKTU & OPERATOR', val: `${getNowFormattedWIB()} (${opName})` }
        ];

        const dataUrl = generateWatermarkedPhoto({
            badgeText: '🏷️ FOTO PRODUK UNBOXING',
            badgeColor: '#059669',
            fields: fields,
            sourceImage: sourceImage
        });

        const prodCount = capturedPhotosList.filter(p => p.type === 'product').length + 1;
        const photoItem = {
            id: 'prod_' + Date.now() + '_' + Math.random().toString(36).substring(2, 6),
            type: 'product',
            title: `Foto Produk #${prodCount} (${pName})`,
            dataUrl: dataUrl,
            createdAt: getNowFormattedWIB()
        };
        capturedPhotosList.push(photoItem);
        renderPhotosGallery();

        showToast('success', `Foto Produk #${prodCount} berhasil disimpan! [Tuts F4]`, 'Foto Produk Siap');
    } catch (err) {
        console.error("captureProductPhoto error:", err);
        showToast('error', 'Gagal mengambil foto produk: ' + err.message, 'Gagal Foto');
    }
};

window.handlePhotosMultipleUpload = async function(inputElement) {
    if (!inputElement || !inputElement.files || inputElement.files.length === 0) return;
    const files = Array.from(inputElement.files);
    
    showGlobalLoading('Memproses Foto...', `Sedang menambahkan ${files.length} foto dengan watermark...`);
    try {
        for (const file of files) {
            await new Promise((resolve) => {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const img = new Image();
                    img.onload = function() {
                        // Tentukan otomatis apakah foto paket atau produk (default paket jika belum ada foto, atau produk jika sudah scan barcode)
                        const hasProduct = Boolean(currentDetectedProduct || (scannedProductsList && scannedProductsList.length > 0));
                        if (hasProduct && capturedPhotosList.filter(p => p.type === 'package').length > 0) {
                            captureProductPhoto(img);
                        } else {
                            capturePackagePhoto(img);
                        }
                        resolve();
                    };
                    img.onerror = () => resolve();
                    img.src = e.target.result;
                };
                reader.onerror = () => resolve();
                reader.readAsDataURL(file);
            });
        }
    } finally {
        hideGlobalLoading();
        inputElement.value = '';
    }
};

window.handlePhotoUpload = function(target, inputElement) {
    if (!inputElement || !inputElement.files || !inputElement.files[0]) return;
    const file = inputElement.files[0];
    const reader = new FileReader();
    reader.onload = function(e) {
        const img = new Image();
        img.onload = function() {
            if (target === 'package') {
                capturePackagePhoto(img);
            } else {
                captureProductPhoto(img);
            }
        };
        img.src = e.target.result;
    };
    reader.readAsDataURL(file);
    inputElement.value = '';
};

window.clearPackagePhoto = function() {
    capturedPhotosList = capturedPhotosList.filter(p => p.type !== 'package');
    renderPhotosGallery();
};

window.clearProductPhoto = function() {
    capturedPhotosList = capturedPhotosList.filter(p => p.type !== 'product');
    renderPhotosGallery();
};

window.previewImageModal = function(imgElementId, title = 'Preview Foto Watermark') {
    const srcEl = document.getElementById(imgElementId);
    if (!srcEl || !srcEl.src) return;
    previewImageDirect(srcEl.src, title);
};

window.closePhotoPreviewModal = function() {
    const modal = document.getElementById('photoPreviewModal');
    if (modal) modal.classList.add('hidden');
};

// Global Keyboard Shortcut: F2 (Foto Paket), F4 (Foto Produk)
window.addEventListener('keydown', (e) => {
    if (e.key === 'F2') {
        e.preventDefault();
        capturePackagePhoto();
    } else if (e.key === 'F4') {
        e.preventDefault();
        captureProductPhoto();
    }
});

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
