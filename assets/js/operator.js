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

// Global Loading Overlay Controls (Bola-bola Merah, Kuning, Hijau & Progress Bar)
window.showGlobalLoading = function(title = 'Memproses...', desc = 'Mohon tunggu sebentar.') {
    const el = document.getElementById('globalLoadingOverlay');
    if (!el) return;
    const t = document.getElementById('globalLoadingTitle');
    const d = document.getElementById('globalLoadingDesc');
    const pWrap = document.getElementById('globalLoadingProgressBarWrapper');
    const pBar = document.getElementById('globalLoadingProgressBar');
    if (t) t.innerText = title;
    if (d) d.innerText = desc;
    if (pWrap) pWrap.classList.add('hidden');
    if (pBar) pBar.style.width = '0%';
    el.classList.remove('hidden');
};

window.updateGlobalLoadingProgress = function(title, desc, percent = null) {
    const t = document.getElementById('globalLoadingTitle');
    const d = document.getElementById('globalLoadingDesc');
    const pWrap = document.getElementById('globalLoadingProgressBarWrapper');
    const pBar = document.getElementById('globalLoadingProgressBar');
    if (t && title) t.innerText = title;
    if (d && desc) d.innerText = desc;
    if (pWrap && pBar) {
        if (percent !== null && percent >= 0) {
            pWrap.classList.remove('hidden');
            pBar.style.width = `${Math.min(100, Math.max(0, percent))}%`;
        } else {
            pWrap.classList.add('hidden');
        }
    }
};

window.hideGlobalLoading = function() {
    const el = document.getElementById('globalLoadingOverlay');
    if (el) el.classList.add('hidden');
    const pWrap = document.getElementById('globalLoadingProgressBarWrapper');
    if (pWrap) pWrap.classList.add('hidden');
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
// HELPER VALIDASI STATUS FORM UNTUK TOMBOL FOTO
// -------------------------------------------------------------
window.isInvoiceAndExpeditionFilled = function() {
    const invInput = document.getElementById('inputInvoice');
    const expSelect = document.getElementById('selectExpedition');
    const invVal = (activeInvoice && String(activeInvoice).trim().length > 0)
        ? String(activeInvoice).trim()
        : (invInput && invInput.value ? invInput.value.trim() : '');
    const expVal = (activeExpedition && String(activeExpedition).trim().length > 0)
        ? String(activeExpedition).trim()
        : (expSelect && expSelect.value ? expSelect.value.trim() : '');
    return Boolean(invVal.length > 0 && expVal.length > 0);
};

window.isInvoiceFilled = function() {
    return window.isInvoiceAndExpeditionFilled();
};

window.isProductDetailAndBatchExpFilled = function() {
    if (!window.isInvoiceAndExpeditionFilled()) return false;
    const hasProduct = Boolean(typeof currentDetectedProduct !== 'undefined' && currentDetectedProduct && (currentDetectedProduct.barcode || currentDetectedProduct.sku || currentDetectedProduct.name));
    if (!hasProduct) return false;

    // Jika produk tidak dikenal / tanda '-', izinkan foto langsung tanpa harus memaksa nomor batch / exp date valid
    if (currentDetectedProduct && (currentDetectedProduct.barcode === '-' || currentDetectedProduct.is_unknown)) {
        return true;
    }

    const batchInput = document.getElementById('inputBatch');
    const hasBatch = Boolean(batchInput && batchInput.value && batchInput.value.trim().length > 0);
    const expInput = document.getElementById('inputExpDate');
    const expVal = expInput && expInput.value ? expInput.value.trim() : '';
    const hasExp = Boolean(expVal.length > 0 && expVal !== '-' && expVal !== 'dd-mm-yyyy');
    return hasBatch && hasExp;
};

window.isConditionDamaged = function() {
    const typeEl = document.getElementById('inputType');
    const val = String(typeEl?.value || '').toUpperCase().trim();
    return val !== '' && val !== 'GOOD' && val !== 'BAGUS' && val !== 'LAYAK';
};

window.updatePhotoButtonsState = function() {
    const hasInvExp = isInvoiceAndExpeditionFilled();
    const hasProdDetail = isProductDetailAndBatchExpFilled();
    const isDamaged = isConditionDamaged();

    const btnPkg = document.getElementById('btnCapturePackagePhoto');
    const btnPrd = document.getElementById('btnCaptureProductPhoto');
    const btnDmg = document.getElementById('btnCaptureDamagedPhoto');
    const btnUp  = document.getElementById('btnUploadPhotosFile');
    const hint   = document.getElementById('photoShortcutsHint');

    // 1. Tombol Foto Paket [F2]: Aktif jika Ekspedisi & Invoice sudah diinput
    const canPkg = hasInvExp;
    if (btnPkg) {
        btnPkg.disabled = !canPkg;
        btnPkg.classList.toggle('opacity-40', !canPkg);
        btnPkg.classList.toggle('cursor-not-allowed', !canPkg);
        if (canPkg) {
            btnPkg.setAttribute('title', 'Ambil Foto Paket Unboxing [TUTS F2]');
        } else {
            btnPkg.setAttribute('title', 'Pilih ekspedisi dan isi nomor invoice/resi terlebih dahulu');
        }
    }

    // 2. Tombol Foto Produk [F4]: Aktif jika Ekspedisi, Invoice, Detail Produk, Batch, Exp Date sudah diisi
    const canPrd = hasProdDetail;
    if (btnPrd) {
        btnPrd.disabled = !canPrd;
        btnPrd.classList.toggle('opacity-40', !canPrd);
        btnPrd.classList.toggle('cursor-not-allowed', !canPrd);
        if (canPrd) {
            btnPrd.setAttribute('title', 'Ambil Foto Produk Unboxing [TUTS F4]');
        } else if (!hasInvExp) {
            btnPrd.setAttribute('title', 'Pilih ekspedisi dan isi nomor invoice/resi terlebih dahulu');
        } else if (!currentDetectedProduct || !currentDetectedProduct.barcode) {
            btnPrd.setAttribute('title', 'Scan barcode produk terlebih dahulu sampai detail produk muncul');
        } else if (!document.getElementById('inputBatch')?.value?.trim() || !document.getElementById('inputExpDate')?.value?.trim()) {
            btnPrd.setAttribute('title', 'Isi nomor batch dan exp date produk terlebih dahulu');
        } else {
            btnPrd.setAttribute('title', 'Lengkapi data produk terlebih dahulu');
        }
    }

    // 3. Tombol Foto Barang Rusak [F5]: Aktif jika Ekspedisi, Invoice, Detail Produk, Batch, Exp Date sudah diisi DAN kondisi RUSAK/NON-GOOD
    const canDmg = hasProdDetail && isDamaged;
    if (btnDmg) {
        btnDmg.disabled = !canDmg;
        btnDmg.classList.toggle('opacity-40', !canDmg);
        btnDmg.classList.toggle('cursor-not-allowed', !canDmg);
        if (canDmg) {
            btnDmg.setAttribute('title', 'Ambil Foto Bukti Barang Rusak / Cacat [TUTS F5]');
        } else if (!hasInvExp) {
            btnDmg.setAttribute('title', 'Pilih ekspedisi dan isi nomor invoice/resi terlebih dahulu');
        } else if (!currentDetectedProduct || !currentDetectedProduct.barcode) {
            btnDmg.setAttribute('title', 'Scan barcode produk terlebih dahulu sampai detail produk muncul');
        } else if (!document.getElementById('inputBatch')?.value?.trim() || !document.getElementById('inputExpDate')?.value?.trim()) {
            btnDmg.setAttribute('title', 'Isi nomor batch dan exp date produk terlebih dahulu');
        } else if (!isDamaged) {
            btnDmg.setAttribute('title', 'Tombol aktif saat kondisi produk yang di-scan diset Rusak / Defect');
        } else {
            btnDmg.setAttribute('title', 'Lengkapi data produk rusak terlebih dahulu');
        }
    }

    // 4. Upload File
    if (btnUp) {
        btnUp.classList.toggle('opacity-40', !hasInvExp);
        btnUp.classList.toggle('cursor-not-allowed', !hasInvExp);
        if (hasInvExp) {
            btnUp.setAttribute('title', 'Unggah foto bukti dari galeri / komputer');
        } else {
            btnUp.setAttribute('title', 'Pilih ekspedisi dan isi nomor invoice/resi terlebih dahulu');
        }
    }

    // 5. Hint Informasi Shortcut
    if (hint) {
        if (!hasInvExp) {
            hint.innerHTML = '<span class="text-amber-600 font-semibold flex items-center gap-1"><i class="fa-solid fa-lock text-[9px]"></i> Isi Ekspedisi & Resi dahulu</span>';
        } else if (!hasProdDetail) {
            hint.innerHTML = '<span class="text-indigo-600 font-medium flex items-center gap-1"><i class="fa-solid fa-camera text-[9px]"></i> <b>[F2]</b> Foto Paket Aktif &bull; Scan produk, batch & exp untuk foto produk</span>';
        } else if (isDamaged) {
            hint.innerHTML = '<span class="text-rose-600 font-bold flex items-center gap-1"><i class="fa-solid fa-triangle-exclamation text-[9px]"></i> Kondisi Rusak &bull; <b>[F5]</b> Foto Barang Rusak Aktif</span>';
        } else {
            hint.innerHTML = '<span class="text-emerald-700 font-bold flex items-center gap-1"><i class="fa-solid fa-circle-check text-[9px]"></i> Kondisi Good &bull; <b>[F2]</b> Foto Paket &bull; <b>[F4]</b> Foto Produk Aktif</span>';
        }
    }
};

window.triggerPhotosUploadClick = function() {
    if (!isInvoiceFilled()) {
        showToast('warning', 'Silakan scan atau masukkan Nomor Invoice / Resi terlebih dahulu sebelum mengunggah foto bukti!', 'Resi Belum Terisi');
        const inputInv = document.getElementById('inputInvoice');
        if (inputInv && !inputInv.disabled && inputInv.offsetParent !== null) {
            inputInv.focus();
        }
        return;
    }
    const fileEl = document.getElementById('filePhotosUpload');
    if (fileEl) fileEl.click();
};

// -------------------------------------------------------------
// 1. INVOICE HANDLING (Auto Record on Scan / Enter)
// -------------------------------------------------------------
const inputInvoice = document.getElementById('inputInvoice');
const btnLockInvoice = document.getElementById('btnLockInvoice');

if (inputInvoice) {
    inputInvoice.addEventListener('input', () => {
        updatePhotoButtonsState();
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

    inputInvoice.addEventListener('change', () => {
        updatePhotoButtonsState();
    });

    inputInvoice.addEventListener('keyup', () => {
        updatePhotoButtonsState();
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

// Event listener untuk update status tombol foto saat ekspedisi, batch, atau exp date diisi
const expSelectGlobal = document.getElementById('selectExpedition');
if (expSelectGlobal) {
    expSelectGlobal.addEventListener('change', () => {
        if (typeof updatePhotoButtonsState === 'function') updatePhotoButtonsState();
    });
}

const inputBatchGlobal = document.getElementById('inputBatch');
if (inputBatchGlobal) {
    inputBatchGlobal.addEventListener('input', () => {
        if (typeof updatePhotoButtonsState === 'function') updatePhotoButtonsState();
    });
    inputBatchGlobal.addEventListener('change', () => {
        if (typeof updatePhotoButtonsState === 'function') updatePhotoButtonsState();
    });
}

const inputExpDateGlobal = document.getElementById('inputExpDate');
if (inputExpDateGlobal) {
    inputExpDateGlobal.addEventListener('input', () => {
        if (typeof updatePhotoButtonsState === 'function') updatePhotoButtonsState();
    });
    inputExpDateGlobal.addEventListener('change', () => {
        if (typeof updatePhotoButtonsState === 'function') updatePhotoButtonsState();
    });
}

window.quickSelectInvoice = function(code) {
    if (inputInvoice) {
        inputInvoice.value = code;
        updatePhotoButtonsState();
        processInvoiceScan(code);
    }
};

let isProcessingInvoice = false;
async function processInvoiceScan(invoiceNumber) {
    invoiceNumber = String(invoiceNumber || '').trim();
    // Guard: cegah proses ganda (Enter + klik Lanjut / scanner double Enter) & jangan restart rekaman sesi aktif
    if (!invoiceNumber || isProcessingInvoice || activeInvoice) return;
    isProcessingInvoice = true;
    try {
        // Lookup invoice ke server bersifat opsional (non-blocking): gagal / non-JSON tidak menghentikan sesi
        let data = {};
        try {
            const res = await fetch(`api/invoice.php?invoice_number=${encodeURIComponent(invoiceNumber)}`);
            const ct = res.headers.get('content-type') || '';
            if (res.ok && ct.includes('json')) {
                data = await res.json();
            }
        } catch (lookupErr) {
            console.warn('Lookup invoice gagal (diabaikan):', lookupErr);
        }

        activeInvoice = (data && data.invoice_number) || invoiceNumber;
        
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
        if (typeof updatePhotoButtonsState === 'function') updatePhotoButtonsState();

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
    } finally {
        isProcessingInvoice = false;
    }
}

window.resetInvoiceSession = function(force = false) {
    if (!force && scannedProductsList.length > 0 && !confirm("Ada barang yang sudah di-scan. Yakin ingin mereset sesi ini?")) {
        return;
    }

    stopVideoRecording();
    // Lepas recorder & chunk sesi lama agar video invoice sebelumnya tidak ikut terkirim ke invoice berikutnya
    mediaRecorder = null;
    recordedChunks = [];

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
    if (typeof updatePhotoButtonsState === 'function') updatePhotoButtonsState();

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
const inputWrongBarcode = document.getElementById('inputWrongBarcode');
let currentWrongProduct = null;
let wrongBarcodeDebounceTimer = null;
let isManualWrongMode = false;

// Helper deteksi kondisi Salah Kirim (SALAH_KIRIM, WRONG, dll)
function isWrongItemCondition(val) {
    if (!val) return false;
    const s = String(val).toUpperCase().trim();
    return s === 'SALAH_KIRIM' || s === 'WRONG' || s.includes('SALAH') || s.includes('WRONG');
}

// Sinkronisasi tampilan antara Mode Scan Barcode vs Mode Input Keterangan Manual
window.syncWrongProductInputMode = function() {
    const barcodeVal = inputBarcode ? inputBarcode.value.trim() : '';
    const isMainBarcodeDash = (barcodeVal === '-' || (currentDetectedProduct && currentDetectedProduct.barcode === '-'));
    
    // Jika barcode utama adalah '-', PAKSA mode manual Keterangan!
    if (isMainBarcodeDash) {
        isManualWrongMode = true;
    }

    const wrapperBarcode = document.getElementById('wrapperWrongBarcode');
    const wrapperKeterangan = document.getElementById('wrapperWrongKeterangan');
    const titleEl = document.getElementById('wrongSectionTitle');
    const subtitleEl = document.getElementById('wrongSectionSubtitle');
    const badgeEl = document.getElementById('wrongSectionBadge');
    const iconEl = document.getElementById('wrongSectionIcon');
    const btnToggle = document.getElementById('btnToggleWrongMode');
    const labelToggle = document.getElementById('labelToggleWrongMode');
    const detailBox = document.getElementById('detailProductSalahBox');

    if (isManualWrongMode) {
        // TAMPILKAN KOLOM KETERANGAN MANUAL, JANGAN TAMPILKAN KOLOM BARCODE!
        if (wrapperBarcode) wrapperBarcode.classList.add('hidden');
        if (wrapperKeterangan) wrapperKeterangan.classList.remove('hidden');
        if (detailBox) detailBox.classList.add('hidden');

        if (titleEl) titleEl.innerText = 'Keterangan / Input Barang Salah (Manual)';
        if (subtitleEl) subtitleEl.innerText = 'Ketik manual nama barang yang salah atau keterangan alasan retur';
        if (badgeEl) {
            badgeEl.className = 'text-[10px] font-bold px-2 py-0.5 rounded-full bg-amber-100 text-amber-900 border border-amber-300 flex items-center gap-1 shadow-2xs';
            badgeEl.innerHTML = '<i class="fa-solid fa-pen-to-square text-amber-600"></i> Input Manual';
        }
        if (iconEl) iconEl.className = 'fa-solid fa-pen-to-square';
        if (labelToggle) labelToggle.innerText = isMainBarcodeDash ? 'Mode Keterangan' : 'Scan Barcode';
        if (btnToggle) {
            btnToggle.style.display = isMainBarcodeDash ? 'none' : 'inline-flex';
        }
    } else {
        // TAMPILKAN KOLOM SCAN BARCODE FISIK
        if (wrapperBarcode) wrapperBarcode.classList.remove('hidden');
        if (wrapperKeterangan) wrapperKeterangan.classList.add('hidden');

        if (titleEl) titleEl.innerText = 'Scan Barcode Fisik Salah Kirim';
        if (subtitleEl) subtitleEl.innerText = 'Scan barcode produk fisik yang salah dikirimkan (yang diterima)';
        if (badgeEl) {
            badgeEl.className = 'text-[10px] font-bold px-2 py-0.5 rounded-full bg-purple-200/90 text-purple-900 border border-purple-300 flex items-center gap-1 shadow-2xs';
            badgeEl.innerHTML = '<i class="fa-solid fa-triangle-exclamation text-amber-600"></i> Fisik Salah';
        }
        if (iconEl) iconEl.className = 'fa-solid fa-arrows-split-up-and-left';
        if (labelToggle) labelToggle.innerText = 'Input Manual';
        if (btnToggle) btnToggle.style.display = 'inline-flex';
    }
};

window.toggleWrongProductInputMode = function() {
    isManualWrongMode = !isManualWrongMode;
    syncWrongProductInputMode();
    if (isManualWrongMode) {
        const ketInp = document.getElementById('inputWrongKeterangan');
        if (ketInp) {
            ketInp.focus();
            ketInp.select();
        }
    } else {
        const bInp = document.getElementById('inputWrongBarcode');
        if (bInp) {
            bInp.focus();
            bInp.select();
        }
    }
};

// Handler perubahan tipe kondisi untuk menampilkan input barcode atau keterangan salah kirim
function handleConditionChange() {
    updateDamagedPhotoBanner();
    const typeVal = inputType ? inputType.value : '';
    const isWrong = isWrongItemCondition(typeVal);
    const barcodeVal = inputBarcode ? inputBarcode.value.trim() : '';
    const isMainBarcodeDash = (barcodeVal === '-' || (currentDetectedProduct && currentDetectedProduct.barcode === '-'));
    
    const container = document.getElementById('containerWrongProductSection');
    if (container) {
        if (isWrong || (isMainBarcodeDash && typeVal)) {
            container.classList.remove('hidden');
            syncWrongProductInputMode();
            setTimeout(() => {
                if (isManualWrongMode || isMainBarcodeDash) {
                    const ketInp = document.getElementById('inputWrongKeterangan');
                    if (ketInp) ketInp.focus();
                } else {
                    const wrongInp = document.getElementById('inputWrongBarcode');
                    if (wrongInp) {
                        wrongInp.focus();
                        if (wrongInp.value.trim() && !currentWrongProduct) {
                            lookupWrongProduct(wrongInp.value.trim());
                        }
                    }
                }
            }, 60);
        } else {
            container.classList.add('hidden');
            clearWrongProductInput(false);
            clearWrongKeteranganInput(false);
        }
    }
}

// Reset kolom dan data produk salah kirim
window.clearWrongProductInput = function(focus = true) {
    currentWrongProduct = null;
    const inp = document.getElementById('inputWrongBarcode');
    if (inp) {
        inp.value = '';
        if (focus) inp.focus();
    }
    clearWrongProductDisplayOnly();
};

window.clearWrongKeteranganInput = function(focus = true) {
    const ketInp = document.getElementById('inputWrongKeterangan');
    if (ketInp) {
        ketInp.value = '';
        if (focus) ketInp.focus();
    }
};

function clearWrongProductDisplayOnly() {
    currentWrongProduct = null;
    const detailBox = document.getElementById('detailProductSalahBox');
    if (detailBox) detailBox.classList.add('hidden');
    const nameEl = document.getElementById('wrongProductNameDisplay');
    if (nameEl) nameEl.innerText = '-';
    const metaEl = document.getElementById('wrongProductMetaDisplay');
    if (metaEl) metaEl.innerHTML = '';
}

// Lookup data produk salah kirim dari barcode
async function lookupWrongProduct(barcode) {
    if (!barcode) return;
    const cleanBarcode = barcode.trim();
    if (!cleanBarcode) return;

    const loading = document.getElementById('wrongBarcodeLoadingIcon');
    const detailBox = document.getElementById('detailProductSalahBox');
    const nameEl = document.getElementById('wrongProductNameDisplay');
    const metaEl = document.getElementById('wrongProductMetaDisplay');
    const badgeEl = document.getElementById('badgeWrongProductStatus');

    if (loading) loading.classList.remove('hidden');

    // Cek jika produk fisik salah kirim tanpa barcode / tanda '-'
    if (cleanBarcode === '-' || cleanBarcode.toUpperCase() === 'NON_BARCODE' || cleanBarcode.toUpperCase() === 'TANPA_BARCODE') {
        currentWrongProduct = {
            barcode: '-',
            name: 'Produk Fisik Tanpa Barcode (Salah Return)',
            sku: '-',
            seller_sku: '-',
            sap_code: '-',
            shop: '-',
            bin_code: '',
            is_master: false,
            scanned: cleanBarcode
        };

        if (detailBox) detailBox.classList.remove('hidden');
        if (nameEl) {
            nameEl.innerText = `Barang Fisik: [Tanpa Barcode / Salah Return]`;
            nameEl.className = "text-xs sm:text-sm font-bold text-amber-900 leading-snug";
        }
        if (badgeEl) {
            badgeEl.className = "text-[9px] font-bold px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 border border-amber-300";
            badgeEl.innerHTML = '<i class="fa-solid fa-triangle-exclamation text-amber-600 mr-0.5"></i> Tanpa Barcode';
        }
        if (metaEl) {
            metaEl.innerHTML = `
                <span class="text-[10px] text-amber-700 italic">
                    <i class="fa-solid fa-circle-info mr-0.5"></i> Fisik barang retur tidak ada barcode / salah return.
                </span>
            `;
        }
        playBeep('success');
        if (loading) loading.classList.add('hidden');
        return;
    }

    try {
        const res = await fetch(`api/product.php?barcode=${encodeURIComponent(cleanBarcode)}`);
        if (!res.ok) {
            // Jika tidak ditemukan di master, tetap catat barcode fisiknya
            currentWrongProduct = {
                barcode: cleanBarcode,
                name: `Produk Luar Master (${cleanBarcode})`,
                sku: '-',
                seller_sku: '-',
                sap_code: '-',
                shop: '-',
                bin_code: '',
                is_master: false,
                scanned: cleanBarcode
            };

            if (detailBox) detailBox.classList.remove('hidden');
            if (nameEl) {
                nameEl.innerText = `Barang Fisik: [${cleanBarcode}] (Non-Master Data)`;
                nameEl.className = "text-xs sm:text-sm font-bold text-amber-900 leading-snug";
            }
            if (badgeEl) {
                badgeEl.className = "text-[9px] font-bold px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 border border-amber-300";
                badgeEl.innerHTML = '<i class="fa-solid fa-triangle-exclamation text-amber-600 mr-0.5"></i> Non-Master Data';
            }
            if (metaEl) {
                metaEl.innerHTML = `
                    <span class="text-[10px] text-amber-700 italic">
                        <i class="fa-solid fa-circle-info mr-0.5"></i> Barcode fisik tidak terdaftar di master data, namun tetap dicatat sebagai salah kirim.
                    </span>
                `;
            }
            playBeep('warning');
            return;
        }

        const product = await res.json();
        currentWrongProduct = {
            barcode: product.barcode || cleanBarcode,
            name: product.name,
            sku: product.sku || '',
            seller_sku: product.seller_sku || product.sku || '-',
            sap_code: product.sap_code || '-',
            shop: product.shop || '',
            bin_code: product.bin_code || '',
            is_master: true,
            scanned: cleanBarcode
        };

        if (detailBox) detailBox.classList.remove('hidden');
        if (nameEl) {
            nameEl.innerText = product.name;
            nameEl.className = "text-xs sm:text-sm font-bold text-slate-900 leading-snug";
        }
        if (badgeEl) {
            badgeEl.className = "text-[9px] font-bold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 border border-emerald-300";
            badgeEl.innerHTML = '<i class="fa-solid fa-circle-check text-emerald-600 mr-0.5"></i> Ditemukan di Master';
        }
        if (metaEl) {
            metaEl.innerHTML = `
                <span class="bg-purple-100 text-purple-800 text-[10px] font-mono font-bold px-2 py-0.5 rounded border border-purple-200">
                    <i class="fa-solid fa-tag text-[9px]"></i> SKU: ${escapeHtml(product.seller_sku || product.sku || '-')}
                </span>
                <span class="bg-indigo-100 text-indigo-800 text-[10px] font-mono font-bold px-2 py-0.5 rounded border border-indigo-200">
                    <i class="fa-solid fa-barcode text-[9px]"></i> SAP: ${escapeHtml(product.sap_code || '-')}
                </span>
                ${product.shop ? `<span class="bg-slate-100 text-slate-700 text-[10px] font-bold px-2 py-0.5 rounded border border-slate-200"><i class="fa-solid fa-store text-[9px]"></i> ${escapeHtml(product.shop)}</span>` : ''}
                ${product.bin_code ? `<span class="bg-amber-100 text-amber-800 text-[10px] font-mono font-bold px-2 py-0.5 rounded border border-amber-200"><i class="fa-solid fa-cubes-stacked text-[9px]"></i> Rak: ${escapeHtml(product.bin_code)}</span>` : ''}
            `;
        }
        playBeep('success');
    } catch (err) {
        console.error('Error lookup wrong product:', err);
    } finally {
        if (loading) loading.classList.add('hidden');
    }
}

// Inisialisasi event listener scan/input barcode salah kirim & keterangan manual
setTimeout(() => {
    const wrongInpEl = document.getElementById('inputWrongBarcode');
    if (wrongInpEl) {
        wrongInpEl.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                const code = wrongInpEl.value.trim();
                if (code) {
                    lookupWrongProduct(code).then(() => {
                        const btnAdd = document.getElementById('btnSubmitItem');
                        if (btnAdd) btnAdd.focus();
                    });
                }
            }
        });

        wrongInpEl.addEventListener('input', () => {
            clearTimeout(wrongBarcodeDebounceTimer);
            const code = wrongInpEl.value.trim();
            if (!code) {
                clearWrongProductDisplayOnly();
                return;
            }
            if (code === '-') {
                lookupWrongProduct(code);
                return;
            }
            if (code.length >= 6) {
                wrongBarcodeDebounceTimer = setTimeout(() => {
                    if (code === wrongInpEl.value.trim()) {
                        lookupWrongProduct(code);
                    }
                }, 350);
            }
        });
    }

    const wrongKetEl = document.getElementById('inputWrongKeterangan');
    if (wrongKetEl) {
        wrongKetEl.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                const btnAdd = document.getElementById('btnSubmitItem');
                if (btnAdd) {
                    btnAdd.focus();
                    btnAdd.click();
                }
            }
        });
    }
}, 100);

// Helper demo click barcode
window.quickFillBarcode = function(barcode) {
    if (!activeInvoice) {
        showToast('warning', "Harap scan nomor resi / invoice terlebih dahulu!", "Invoice Belum Di-scan");
        return;
    }
    inputBarcode.value = barcode;
    lookupProduct(barcode);
};

// Cek apakah produk terdeteksi saat ini berasal dari kode yang di-scan (barcode / BPOM / SKU / SAP)
function isDetectedProductFor(code) {
    if (!currentDetectedProduct || !code) return false;
    return currentDetectedProduct._scanned === code || currentDetectedProduct.barcode === code;
}

// Deteksi Enter / Scan / Input pada Kolom Barcode
let barcodeDebounceTimer = null;
inputBarcode.addEventListener('keypress', (e) => {
    if (e.key === 'Enter') {
        e.preventDefault();
        const code = inputBarcode.value.trim();
        if (code) lookupProduct(code);
    }
});

inputBarcode.addEventListener('input', () => {
    clearTimeout(barcodeDebounceTimer);
    const code = inputBarcode.value.trim();
    if (!code) {
        currentDetectedProduct = null;
        const nameEl = document.getElementById('detectedProductName');
        if (nameEl) {
            nameEl.innerText = "Silakan scan / ketik barcode...";
            nameEl.className = "font-bold text-indigo-700 text-xs";
        }
        const skuEl = document.getElementById('detectedProductSku');
        if (skuEl) skuEl.innerText = "";
        if (typeof updatePhotoButtonsState === 'function') updatePhotoButtonsState();
        if (typeof syncWrongProductInputMode === 'function') syncWrongProductInputMode();
        return;
    }
    // Langsung deteksi tanda '-' tanpa harus tunggu Enter!
    if (code === '-') {
        lookupProduct(code);
        if (typeof syncWrongProductInputMode === 'function') syncWrongProductInputMode();
        return;
    }
    // Jika berubah dari '-' ke kode barcode biasa
    if (isManualWrongMode && code !== '-') {
        isManualWrongMode = false;
        if (typeof syncWrongProductInputMode === 'function') syncWrongProductInputMode();
    }
    if (code.length >= 6) {
        barcodeDebounceTimer = setTimeout(() => {
            if (code === inputBarcode.value.trim() && !isDetectedProductFor(code)) {
                lookupProduct(code);
            }
        }, 350);
    }
});

inputBarcode.addEventListener('change', () => {
    const code = inputBarcode.value.trim();
    if (code && !isDetectedProductFor(code)) {
        lookupProduct(code);
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
        inputExpDate.value = formatExpDate(expDateStr);
        updateNumpadDisplay();
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
                inputExpDate.value = formatExpDate(data.exp_date);
                updateNumpadDisplay();
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
        const barcodeVal = inputBarcode ? inputBarcode.value.trim() : '';
        const isMainBarcodeDash = (barcodeVal === '-' || (currentDetectedProduct && currentDetectedProduct.barcode === '-'));
        const isWrong = isWrongItemCondition(inputType.value);

        if (isWrong || isMainBarcodeDash) {
            if (isManualWrongMode || isMainBarcodeDash) {
                const ketInp = document.getElementById('inputWrongKeterangan');
                if (ketInp && !ketInp.value.trim()) {
                    ketInp.focus();
                    return;
                }
            } else {
                const wrongInp = document.getElementById('inputWrongBarcode');
                if (wrongInp && (!wrongInp.value.trim() || !currentWrongProduct)) {
                    wrongInp.focus();
                    return;
                }
            }
        }
        const btnAdd = document.getElementById('btnSubmitItem');
        if (btnAdd) btnAdd.click();
    }
});

let currentActiveDamagedPhoto = null;

function updateDamagedPhotoBanner() {
    const banner = document.getElementById('damagedPhotoPromptBanner');
    const status = document.getElementById('damagedPhotoBannerStatus');
    const previewMini = document.getElementById('damagedPhotoPreviewMini');
    const imgThumb = document.getElementById('imgDamagedPhotoThumb');
    if (!banner) return;

    const typeVal = String(inputType?.value || '').toUpperCase().trim();
    const isDamaged = (typeVal !== 'GOOD' && typeVal !== 'BAGUS' && typeVal !== 'LAYAK' && typeVal !== '');

    if (isDamaged) {
        banner.classList.remove('hidden');
        if (currentActiveDamagedPhoto) {
            banner.className = 'mt-2 p-2.5 bg-emerald-50 border-2 border-emerald-400 rounded-xl flex items-center justify-between gap-2 shadow-xs transition-all';
            if (status) status.innerHTML = `<span class="text-emerald-700 font-bold"><i class="fa-solid fa-circle-check"></i> Foto produk rusak [${typeVal}] siap disimpan!</span>`;
            if (previewMini && imgThumb) {
                previewMini.classList.remove('hidden');
                imgThumb.src = currentActiveDamagedPhoto;
            }
        } else {
            banner.className = 'mt-2 p-2.5 bg-rose-50 border-2 border-rose-400 rounded-xl flex items-center justify-between gap-2 shadow-xs transition-all animate-pulse';
            if (status) status.innerHTML = `<span class="text-rose-600 font-black"><i class="fa-solid fa-triangle-exclamation"></i> WAJIB ambil foto bukti barang [${typeVal}] sebelum tambah item!</span>`;
            if (previewMini) previewMini.classList.add('hidden');
        }
    } else {
        banner.classList.add('hidden');
        if (previewMini) previewMini.classList.add('hidden');
    }
}

window.triggerDamagedPhotoCapture = function() {
    captureProductPhoto(null, 'RUSAK');
};

window.viewCurrentDamagedPhoto = function() {
    if (currentActiveDamagedPhoto) {
        previewImageDirect(currentActiveDamagedPhoto, `Foto Bukti Barang Rusak (${inputType?.value || 'RUSAK'})`);
    }
};

window.showDamagedPhotoRequiredModal = function(type) {
    const modal = document.getElementById('modalDamagedPhotoRequired');
    if (!modal) return;
    const badge = document.getElementById('modalDmgCondBadge');
    if (badge) badge.innerText = type || 'RUSAK';
    const prodName = document.getElementById('modalDmgProdName');
    if (prodName) prodName.innerText = currentDetectedProduct?.name || 'Produk Return';
    const prodSku = document.getElementById('modalDmgProdSku');
    if (prodSku) prodSku.innerText = `Barcode: ${inputBarcode.value.trim() || '-'} | SKU: ${currentDetectedProduct?.seller_sku || currentDetectedProduct?.sku || '-'}`;
    modal.classList.remove('hidden');
};

window.closeDamagedPhotoRequiredModal = function() {
    const modal = document.getElementById('modalDamagedPhotoRequired');
    if (modal) modal.classList.add('hidden');
};

window.confirmTakeDamagedPhotoNow = function() {
    closeDamagedPhotoRequiredModal();
    captureProductPhoto(null, 'RUSAK');
};

function updateProductPhotoButtonState() {
    const btn = document.getElementById('btnCaptureProductPhoto');
    if (btn) {
        btn.classList.remove('bg-rose-600', 'hover:bg-rose-700', 'shadow-rose-600/25');
        btn.classList.add('bg-emerald-600', 'hover:bg-emerald-700', 'shadow-emerald-600/25');
        const icon = btn.querySelector('i');
        const label = btn.querySelector('#labelCaptureProductPhoto') || btn.querySelector('div span:last-child');
        if (icon) icon.className = 'fa-solid fa-tag text-xs';
        if (label) label.innerText = 'Foto Produk';
    }

    const labelNp = document.getElementById('labelNpPhoto');
    const btnNp = document.getElementById('btnNpEnter');
    if (labelNp && btnNp) {
        labelNp.innerText = 'FOTO PRODUK [F4] ➔ QTY';
        btnNp.className = 'w-full h-9 bg-gradient-to-r from-emerald-600 via-teal-600 to-emerald-700 hover:from-emerald-500 hover:to-teal-500 active:scale-95 text-white font-bold text-xs rounded-xl flex items-center justify-center gap-1.5 transition shadow-md shadow-emerald-950/40 select-none cursor-pointer border border-emerald-300/40';
    }

    updateDamagedPhotoBanner();
}

if (inputType) {
    inputType.addEventListener('change', () => {
        updateProductPhotoButtonState();
        if (typeof updatePhotoButtonsState === 'function') updatePhotoButtonsState();
        handleConditionChange();
    });
}

// -------------------------------------------------------------
// VIRTUAL ENTER BUTTON & INPUT FOCUS TRACKER
// -------------------------------------------------------------
let currentActiveFieldId = 'inputBarcode';

function updateVirtualEnterBadge(fieldId) {
    currentActiveFieldId = fieldId;
    const labelEl = document.getElementById('virtualEnterTargetLabel');
    if (!labelEl) return;
    const barcodeVal = inputBarcode ? inputBarcode.value.trim() : '';
    const isMainBarcodeDash = (barcodeVal === '-' || (currentDetectedProduct && currentDetectedProduct.barcode === '-'));

    if (fieldId === 'inputBarcode') {
        labelEl.innerText = 'Lanjut ke No. Batch ➔';
    } else if (fieldId === 'inputBatch') {
        labelEl.innerText = 'Lanjut ke Exp Date ➔';
    } else if (fieldId === 'inputExpDate') {
        labelEl.innerText = 'Lanjut ke Qty ➔';
    } else if (fieldId === 'inputQty') {
        labelEl.innerText = 'Lanjut ke Type ➔';
    } else if (fieldId === 'inputType') {
        if (isWrongItemCondition(inputType?.value) || isMainBarcodeDash) {
            labelEl.innerText = (isManualWrongMode || isMainBarcodeDash) ? 'Lanjut ke Keterangan ➔' : 'Lanjut ke Barcode Salah ➔';
        } else {
            labelEl.innerText = 'Submit Tambah Item ✓';
        }
    } else if (fieldId === 'inputWrongBarcode' || fieldId === 'inputWrongKeterangan') {
        labelEl.innerText = 'Submit Tambah Item ✓';
    }
}

['inputBarcode', 'inputBatch', 'inputExpDate', 'inputQty', 'inputType', 'inputWrongBarcode', 'inputWrongKeterangan'].forEach(id => {
    const el = document.getElementById(id);
    if (el) {
        el.addEventListener('focus', () => updateVirtualEnterBadge(id));
    }
});

// -------------------------------------------------------------
// VIRTUAL KEYBOARD & NUMPAD TOUCHSCREEN MANAGEMENT
// -------------------------------------------------------------
function updateNumpadDisplay() {
    const disp = document.getElementById('numpadDateDisplay');
    if (!disp) return;
    if (inputExpDate && inputExpDate.value && inputExpDate.value.trim()) {
        disp.innerText = inputExpDate.value.trim();
    } else {
        disp.innerText = 'dd - mm - yyyy';
    }
}

// ========================================================
// 1. KEYBOARD TOUCHSCREEN: RODA ABJAD & NUMPAD UNTUK BATCH
// ========================================================
const ALPHABET_LIST = "ABCDEFGHIJKLMNOPQRSTUVWXYZ".split('');
let currentWheelIndex = 1; // Default 'B' (index 1)
let currentWheelChar = 'B';
let isAlphabetWheelInitialized = false;

window.initAlphabetWheel = function() {
    const listEl = document.getElementById('alphabetWheelList');
    if (!listEl) return;
    
    // Render list abjad A sampai Z
    let html = '';
    ALPHABET_LIST.forEach((ch, idx) => {
        html += `<div class="wheel-char-item h-10 flex items-center justify-center font-mono font-bold text-slate-400 text-base transition-all cursor-pointer snap-center rounded-lg hover:text-white" 
            data-index="${idx}" 
            data-char="${ch}" 
            onclick="selectWheelChar('${ch}', true)">
            ${ch}
        </div>`;
    });
    listEl.innerHTML = html;

    // Listener scroll untuk deteksi huruf aktif di tengah roda
    let scrollTimeout = null;
    listEl.addEventListener('scroll', () => {
        clearTimeout(scrollTimeout);
        scrollTimeout = setTimeout(updateActiveWheelCharFromScroll, 30);
    }, { passive: true });

    isAlphabetWheelInitialized = true;

    // Scroll default ke huruf 'B'
    setTimeout(() => {
        selectWheelChar('B', false);
        updateBatchPreviewDisplay();
    }, 120);
};

function updateActiveWheelCharFromScroll() {
    const listEl = document.getElementById('alphabetWheelList');
    if (!listEl) return;

    const items = listEl.querySelectorAll('.wheel-char-item');
    if (!items || items.length === 0) return;

    const containerRect = listEl.getBoundingClientRect();
    const centerY = containerRect.top + containerRect.height / 2;

    let closestItem = null;
    let minDistance = Infinity;

    items.forEach(item => {
        const itemRect = item.getBoundingClientRect();
        const itemCenterY = itemRect.top + itemRect.height / 2;
        const dist = Math.abs(centerY - itemCenterY);
        if (dist < minDistance) {
            minDistance = dist;
            closestItem = item;
        }
    });

    if (closestItem) {
        const char = closestItem.getAttribute('data-char') || 'A';
        const idx = parseInt(closestItem.getAttribute('data-index') || '0', 10);
        applyActiveWheelStyle(char, idx);
    }
}

function applyActiveWheelStyle(char, idx) {
    currentWheelChar = char;
    currentWheelIndex = idx;

    const listEl = document.getElementById('alphabetWheelList');
    if (listEl) {
        listEl.querySelectorAll('.wheel-char-item').forEach(el => {
            if (el.getAttribute('data-char') === char) {
                el.classList.add('active-wheel-char');
            } else {
                el.classList.remove('active-wheel-char');
            }
        });
    }

    const labelEl = document.getElementById('labelActiveWheelChar');
    if (labelEl) labelEl.innerText = char;
}

window.selectWheelChar = function(char, andAppend = false) {
    const listEl = document.getElementById('alphabetWheelList');
    if (!listEl) return;

    const idx = ALPHABET_LIST.indexOf(char.toUpperCase());
    if (idx === -1) return;

    const items = listEl.querySelectorAll('.wheel-char-item');
    if (items[idx]) {
        const itemTop = items[idx].offsetTop;
        const itemHeight = items[idx].offsetHeight;
        const containerHeight = listEl.clientHeight;
        const targetScrollTop = itemTop - (containerHeight / 2) + (itemHeight / 2);

        listEl.scrollTo({
            top: targetScrollTop,
            behavior: 'smooth'
        });

        applyActiveWheelStyle(char.toUpperCase(), idx);
    }

    if (andAppend) {
        vkPressChar(char.toUpperCase());
    }
};

window.wheelStepChar = function(direction) {
    let nextIdx = currentWheelIndex + direction;
    if (nextIdx < 0) nextIdx = 0;
    if (nextIdx >= ALPHABET_LIST.length) nextIdx = ALPHABET_LIST.length - 1;

    const targetChar = ALPHABET_LIST[nextIdx];
    selectWheelChar(targetChar, false);
};

window.insertActiveWheelChar = function() {
    if (currentWheelChar) {
        vkPressChar(currentWheelChar);
    }
};

window.updateBatchPreviewDisplay = function() {
    const disp = document.getElementById('vkBatchPreviewDisplay');
    const input = document.getElementById('inputBatch');
    if (disp) {
        const val = (input && input.value) ? input.value.trim() : '';
        disp.innerText = val ? val : '-';
    }
};

window.toggleVirtualKeyboard = function(forceShow = null) {
    const container = document.getElementById('virtualKeyboardContainer');
    const toggleBtnLabel = document.getElementById('btnToggleVKLabel');
    if (!container) return;

    const isHidden = container.classList.contains('hidden');
    const shouldShow = forceShow !== null ? forceShow : isHidden;

    if (shouldShow) {
        container.classList.remove('hidden');
        if (toggleBtnLabel) toggleBtnLabel.innerText = 'Tutup Keyboard';
        // Inisialisasi roda abjad jika belum
        if (!isAlphabetWheelInitialized) {
            initAlphabetWheel();
        } else {
            updateBatchPreviewDisplay();
        }
        // Tutup Numpad Exp Date saat Keyboard Batch aktif
        if (typeof toggleNumpadExpDate === 'function') toggleNumpadExpDate(false);
    } else {
        container.classList.add('hidden');
        if (toggleBtnLabel) toggleBtnLabel.innerText = 'Buka Keyboard';
    }
};

window.vkPressChar = function(char) {
    const input = document.getElementById('inputBatch');
    if (!input) return;
    input.value = (input.value || '') + char;
    input.dispatchEvent(new Event('input', { bubbles: true }));
    input.focus();
    updateBatchPreviewDisplay();
};

window.vkBackspace = function() {
    const input = document.getElementById('inputBatch');
    if (!input) return;
    input.value = (input.value || '').slice(0, -1);
    input.dispatchEvent(new Event('input', { bubbles: true }));
    input.focus();
    updateBatchPreviewDisplay();
};

window.vkClear = function() {
    const input = document.getElementById('inputBatch');
    if (!input) return;
    input.value = '';
    input.dispatchEvent(new Event('input', { bubbles: true }));
    input.focus();
    updateBatchPreviewDisplay();
};

window.vkEnter = function() {
    toggleVirtualKeyboard(false);
    const inputExp = document.getElementById('inputExpDate');
    if (inputExp) {
        inputExp.focus();
    }
    toggleNumpadExpDate(true);
    if (typeof updateVirtualEnterBadge === 'function') {
        updateVirtualEnterBadge('inputExpDate');
    }
};

// 2. Numpad Touchscreen untuk Exp Date
window.toggleNumpadExpDate = function(forceShow = null) {
    const container = document.getElementById('numpadExpDateContainer');
    const toggleBtnLabel = document.getElementById('btnToggleNPLabel');
    if (!container) return;

    const isHidden = container.classList.contains('hidden');
    const shouldShow = forceShow !== null ? forceShow : isHidden;

    if (shouldShow) {
        container.classList.remove('hidden');
        if (toggleBtnLabel) toggleBtnLabel.innerText = 'Tutup Numpad';
        // Tutup Keyboard Batch saat Numpad aktif
        if (typeof toggleVirtualKeyboard === 'function') toggleVirtualKeyboard(false);
        updateNumpadDisplay();
    } else {
        container.classList.add('hidden');
        if (toggleBtnLabel) toggleBtnLabel.innerText = 'Numpad';
    }
};

// Format string digit murni menjadi DD-MM-YYYY secara seketika
function formatDigitsToDate(digits) {
    if (!digits) return '';
    const d = digits.slice(0, 8);
    if (d.length <= 2) {
        return d;
    } else if (d.length <= 4) {
        return d.slice(0, 2) + '-' + d.slice(2);
    } else {
        return d.slice(0, 2) + '-' + d.slice(2, 4) + '-' + d.slice(4);
    }
}

window.npDigit = function(digit) {
    if (!inputExpDate) return;
    let raw = (inputExpDate.value || '').replace(/\D/g, '');
    if (raw.length >= 8) return;
    raw += String(digit);
    inputExpDate.value = formatDigitsToDate(raw);
    inputExpDate.dispatchEvent(new Event('input', { bubbles: true }));
    updateNumpadDisplay();
};

window.npBackspace = function() {
    if (!inputExpDate) return;
    let raw = (inputExpDate.value || '').replace(/\D/g, '');
    raw = raw.slice(0, -1);
    inputExpDate.value = formatDigitsToDate(raw);
    inputExpDate.dispatchEvent(new Event('input', { bubbles: true }));
    updateNumpadDisplay();
};

window.npClear = function() {
    if (inputExpDate) {
        inputExpDate.value = '';
        inputExpDate.dispatchEvent(new Event('input', { bubbles: true }));
    }
    updateNumpadDisplay();
};

window.npSetYear = function(yearStr) {
    if (!inputExpDate) return;
    let raw = (inputExpDate.value || '').trim();
    let parts = raw.split(/[-/]/);
    let dd = '01';
    let mm = '01';
    if (parts.length >= 2 && parts[0] && parts[1]) {
        dd = parts[0].replace(/\D/g, '').padStart(2, '0');
        mm = parts[1].replace(/\D/g, '').padStart(2, '0');
    } else if (parts.length === 1 && parts[0].length >= 2) {
        dd = parts[0].slice(0, 2).replace(/\D/g, '').padStart(2, '0');
    }
    inputExpDate.value = `${dd}-${mm}-${yearStr}`;
    inputExpDate.dispatchEvent(new Event('input', { bubbles: true }));
    updateNumpadDisplay();
};

window.npSetPreset = function(type) {
    const now = new Date();
    let targetYear = now.getFullYear();
    if (type === '1Y') targetYear += 1;
    else if (type === '2Y') targetYear += 2;
    else if (type === '3Y') targetYear += 3;

    const mm = String(now.getMonth() + 1).padStart(2, '0');
    const dd = String(now.getDate()).padStart(2, '0');
    if (inputExpDate) {
        inputExpDate.value = `${dd}-${mm}-${targetYear}`;
        inputExpDate.dispatchEvent(new Event('input', { bubbles: true }));
    }
    updateNumpadDisplay();
};

window.npEnter = function() {
    // Tombol di Numpad Exp Date:
    // 1. Sembunyikan Numpad Exp Date
    toggleNumpadExpDate(false);

    // 2. Ambil Foto Produk
    if (typeof captureProductPhoto === 'function') {
        captureProductPhoto(null, null);
    }

    // 3. Setelah itu baru pindah ke kolom Qty dan seleksi teks
    setTimeout(() => {
        if (inputQty) {
            inputQty.focus();
            inputQty.select();
        }
        if (typeof updateVirtualEnterBadge === 'function') {
            updateVirtualEnterBadge('inputQty');
        }
    }, 200);
};

// 3. Event Listener Fokus Kolom:
// Kolom No. Batch -> Buka Keyboard Batch, Tutup Numpad
if (inputBatch) {
    inputBatch.addEventListener('focus', () => {
        toggleVirtualKeyboard(true);
        toggleNumpadExpDate(false);
    });
}

// Kolom Exp Date -> Buka Numpad, Tutup Keyboard Batch, dan auto-format input manual
if (inputExpDate) {
    inputExpDate.addEventListener('focus', () => {
        toggleVirtualKeyboard(false);
        toggleNumpadExpDate(true);
    });
    inputExpDate.addEventListener('input', () => {
        const raw = inputExpDate.value.replace(/\D/g, '').slice(0, 8);
        inputExpDate.value = formatDigitsToDate(raw);
        updateNumpadDisplay();
    });
}

// Kolom Lain (Barcode, Qty, Type) -> Tutup Semua Keyboard & Numpad
['inputBarcode', 'inputQty', 'inputType'].forEach(id => {
    const el = document.getElementById(id);
    if (el) {
        el.addEventListener('focus', () => {
            toggleVirtualKeyboard(false);
            toggleNumpadExpDate(false);
        });
    }
});

// 4. Tombol ENTER Navigasi Terpisah
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
        toggleVirtualKeyboard(false);
        if (inputExpDate) inputExpDate.focus();
        toggleNumpadExpDate(true);
    } else if (currentActiveFieldId === 'inputExpDate') {
        toggleNumpadExpDate(false);
        if (inputQty) {
            inputQty.focus();
            inputQty.select();
        }
    } else if (currentActiveFieldId === 'inputQty') {
        toggleVirtualKeyboard(false);
        toggleNumpadExpDate(false);
        if (inputType) inputType.focus();
    } else if (currentActiveFieldId === 'inputType') {
        toggleVirtualKeyboard(false);
        toggleNumpadExpDate(false);
        const barcodeValT = inputBarcode ? inputBarcode.value.trim() : '';
        const isMainBarcodeDashT = (barcodeValT === '-' || (currentDetectedProduct && currentDetectedProduct.barcode === '-'));
        if (isWrongItemCondition(inputType ? inputType.value : '') || isMainBarcodeDashT) {
            if (isManualWrongMode || isMainBarcodeDashT) {
                const ketInp = document.getElementById('inputWrongKeterangan');
                if (ketInp && !ketInp.value.trim()) {
                    ketInp.focus();
                    return;
                }
            } else {
                const wrongInp = document.getElementById('inputWrongBarcode');
                if (wrongInp && (!wrongInp.value.trim() || !currentWrongProduct)) {
                    wrongInp.focus();
                    return;
                }
            }
        }
        const btnAdd = document.getElementById('btnSubmitItem');
        if (btnAdd) btnAdd.click();
    } else if (currentActiveFieldId === 'inputWrongBarcode' || currentActiveFieldId === 'inputWrongKeterangan') {
        toggleVirtualKeyboard(false);
        toggleNumpadExpDate(false);
        const btnAdd = document.getElementById('btnSubmitItem');
        if (btnAdd) btnAdd.click();
    } else {
        if (inputBarcode) inputBarcode.focus();
    }
};

// Fungsi Lookup Produk dari Barcode ke Database MySQL
async function lookupProduct(barcode) {
    if (!barcode) return;
    const cleanBarcode = barcode.trim();
    if (!cleanBarcode) return;

    const loading = document.getElementById('barcodeLoadingIcon');
    if (loading) loading.classList.remove('hidden');

    // Langsung handle tanda '-' / placeholder non-barcode untuk produk salah return tanpa barcode
    if (cleanBarcode === '-' || cleanBarcode.toUpperCase() === 'NON_BARCODE' || cleanBarcode.toUpperCase() === 'TANPA_BARCODE') {
        const unknownProd = {
            id: 0,
            barcode: '-',
            name: 'Produk Tidak Dikenal (Salah Return / Tanpa Barcode)',
            sku: '-',
            seller_sku: '-',
            sap_code: '-',
            shop: '-',
            bin_code: '-',
            category: 'Salah Return',
            is_unknown: true
        };
        unknownProd._scanned = cleanBarcode;
        currentDetectedProduct = unknownProd;
        if (typeof updatePhotoButtonsState === 'function') updatePhotoButtonsState();
        playBeep('success');

        const nameEl = document.getElementById('detectedProductName');
        if (nameEl) {
            nameEl.innerText = unknownProd.name;
            nameEl.className = "font-bold text-amber-700 ml-1 text-sm";
        }
        const skuEl = document.getElementById('detectedProductSku');
        if (skuEl) {
            skuEl.innerHTML = `
                <span class="inline-flex flex-wrap items-center gap-1.5 ml-2 mt-1">
                    <span class="bg-amber-100 text-amber-900 text-[11px] font-bold px-2 py-0.5 rounded border border-amber-300">
                        <i class="fa-solid fa-triangle-exclamation text-amber-600 mr-1"></i> Salah Return / Tanpa Barcode
                    </span>
                    <span class="bg-slate-100 text-slate-700 text-[11px] font-mono px-2 py-0.5 rounded border border-slate-200">
                        Barcode: -
                    </span>
                </span>
            `;
        }

        setTimeout(() => {
            if (inputBatch) inputBatch.focus();
        }, 50);

        if (loading) loading.classList.add('hidden');
        return;
    }

    try {
        const res = await fetch(`api/product.php?barcode=${encodeURIComponent(cleanBarcode)}`);
        if (!res.ok) {
            playBeep('error');
            currentDetectedProduct = null;
            if (typeof updatePhotoButtonsState === 'function') updatePhotoButtonsState();
            document.getElementById('detectedProductName').innerText = `Produk [${cleanBarcode}] tidak ditemukan!`;
            document.getElementById('detectedProductName').className = "font-bold text-rose-600 ml-1 text-sm";
            document.getElementById('detectedProductSku').innerText = "";
            showToast('warning', `Barcode [${cleanBarcode}] belum terdaftar di master data produk!`, "Produk Tidak Ditemukan");
            inputBarcode.focus();
            inputBarcode.select();
            return;
        }

        const product = await res.json();
        if (product && typeof product === 'object') product._scanned = cleanBarcode;
        currentDetectedProduct = product;
        if (typeof updatePhotoButtonsState === 'function') updatePhotoButtonsState();
        playBeep('success');

        // Tampilkan info produk terdeteksi (Seller SKU, SAP Code, Rak/Bin)
        const nameEl = document.getElementById('detectedProductName');
        nameEl.innerText = product.name;
        nameEl.className = product.is_unknown ? "font-bold text-amber-700 ml-1 text-sm" : "font-bold text-emerald-700 ml-1 text-sm";
        
        const sellerSku = product.seller_sku || product.sku || '-';
        const sapCode = product.sap_code || '-';
        const binCode = product.bin_code || '';
        const shop = product.shop || '';

        document.getElementById('detectedProductSku').innerHTML = `
            <span class="inline-flex flex-wrap items-center gap-1.5 ml-2 mt-1">
                ${product.is_unknown ? `<span class="bg-amber-100 text-amber-900 text-[11px] font-bold px-2 py-0.5 rounded border border-amber-300"><i class="fa-solid fa-triangle-exclamation text-amber-600 mr-1"></i> Salah Return / Tanpa Barcode</span>` : ''}
                <span class="bg-indigo-100 text-indigo-800 text-[11px] font-mono font-bold px-2 py-0.5 rounded border border-indigo-200">
                    <i class="fa-solid fa-tag text-[10px]"></i> Seller SKU: ${sellerSku}
                </span>
                <span class="bg-emerald-100 text-emerald-800 text-[11px] font-mono font-bold px-2 py-0.5 rounded border border-emerald-200">
                    <i class="fa-solid fa-barcode text-[10px]"></i> SAP: ${sapCode}
                </span>
                ${binCode && binCode !== '-' ? `<span class="bg-amber-100 text-amber-800 text-[11px] font-mono font-bold px-2 py-0.5 rounded border border-amber-200"><i class="fa-solid fa-cubes-stacked text-[10px]"></i> Rak: ${binCode}</span>` : ''}
                ${shop && shop !== '-' ? `<span class="bg-purple-100 text-purple-800 text-[11px] font-bold px-2 py-0.5 rounded border border-purple-200">${shop}</span>` : ''}
            </span>
        `;

        // Pindahkan kursor otomatis ke NO. BATCH!
        setTimeout(() => {
            if (inputBatch) inputBatch.focus();
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

    const type = inputType.value ? inputType.value.trim() : '';
    if (!type) {
        playBeep('error');
        showToast('warning', "Silakan pilih Type / Kondisi produk terlebih dahulu!", "Kondisi Belum Dipilih");
        inputType.focus();
        return;
    }
    const isItemDamaged = (type !== 'GOOD' && type !== 'BAGUS' && type !== 'LAYAK');
    if (isItemDamaged && !currentActiveDamagedPhoto) {
        playBeep('error');
        showToast('error', `Barang dengan kondisi [${type}] WAJIB difoto buktinya terlebih dahulu!`, 'Wajib Foto Bukti Rusak');
        showDamagedPhotoRequiredModal(type);
        return;
    }

    if (!isDetectedProductFor(barcode)) {
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

function commitAddItem(_afterWrongLookup = false) {
    if (!currentDetectedProduct) return;
    const batchNo = inputBatch.value.trim();
    const expDate = inputExpDate.value.trim();
    const qty = parseInt(inputQty.value, 10) || 1;
    const type = inputType.value ? inputType.value.trim() : '';

    if (!type) {
        playBeep('error');
        showToast('warning', "Silakan pilih Type / Kondisi produk terlebih dahulu!", "Kondisi Belum Dipilih");
        inputType.focus();
        return;
    }

    const isWrong = isWrongItemCondition(type);
    let wrongBarcodeVal = '';
    let wrongProductNameVal = '';
    let wrongSkuVal = '';

    const isCurrentUnknown = Boolean(currentDetectedProduct && (currentDetectedProduct.barcode === '-' || currentDetectedProduct.is_unknown));
    const ketInput = document.getElementById('inputWrongKeterangan');
    const enteredKeterangan = ketInput ? ketInput.value.trim() : '';
    const wrongInput = document.getElementById('inputWrongBarcode');
    const enteredWrongBarcode = wrongInput ? wrongInput.value.trim() : '';

    if (isWrong || isCurrentUnknown) {
        if (isManualWrongMode || isCurrentUnknown || enteredKeterangan) {
            // Mode Manual Keterangan (termasuk saat barcode utama adalah '-')
            wrongBarcodeVal = '-';
            wrongProductNameVal = enteredKeterangan || (isCurrentUnknown ? 'Produk Fisik Tanpa Barcode (Salah Return)' : 'Barang Salah Kirim');
            wrongSkuVal = '-';
        } else {
            // Mode Scan Barcode Fisik
            if (!enteredWrongBarcode && (!currentWrongProduct || !currentWrongProduct.barcode)) {
                playBeep('error');
                showToast('warning', "Untuk kondisi Salah Kirim, silakan scan barcode atau ketik keterangan manual!", "Barcode / Keterangan Diperlukan");
                const containerWrong = document.getElementById('containerWrongProductSection');
                if (containerWrong) containerWrong.classList.remove('hidden');
                if (wrongInput) {
                    wrongInput.focus();
                    wrongInput.select();
                }
                return;
            } else if (!_afterWrongLookup && enteredWrongBarcode && (!currentWrongProduct || (currentWrongProduct.scanned || currentWrongProduct.barcode) !== enteredWrongBarcode)) {
                // Lookup sekali saja lalu commit (cegah loop tanpa akhir bila kode = SKU/BPOM atau jaringan error)
                lookupWrongProduct(enteredWrongBarcode).then(() => {
                    commitAddItem(true);
                });
                return;
            } else {
                // Abaikan data produk salah kirim yang basi (dari kode lain) bila lookup terakhir gagal
                const wp = (currentWrongProduct && (!enteredWrongBarcode || (currentWrongProduct.scanned || currentWrongProduct.barcode) === enteredWrongBarcode)) ? currentWrongProduct : null;
                wrongBarcodeVal = wp ? wp.barcode : enteredWrongBarcode;
                wrongProductNameVal = wp ? wp.name : '';
                wrongSkuVal = wp ? (wp.seller_sku || wp.sku || '') : '';
            }
        }
    }

    const isItemDamaged = (type !== 'GOOD' && type !== 'BAGUS' && type !== 'LAYAK');

    if (isItemDamaged && !currentActiveDamagedPhoto) {
        playBeep('error');
        showToast('error', `Barang dengan kondisi [${type}] WAJIB difoto buktinya terlebih dahulu!`, 'Wajib Foto Bukti Rusak');
        showDamagedPhotoRequiredModal(type);
        return;
    }

    const itemPhoto = isItemDamaged ? currentActiveDamagedPhoto : null;

    let finalProdName = currentDetectedProduct.name;
    if (isCurrentUnknown && wrongProductNameVal && wrongProductNameVal !== 'Produk Fisik Tanpa Barcode (Salah Return)') {
        finalProdName = `[Salah Return] ${wrongProductNameVal}`;
    }

    scannedProductsList.push({
        barcode: currentDetectedProduct.barcode,
        wrong_barcode: wrongBarcodeVal,
        wrong_product_name: wrongProductNameVal,
        wrong_sku: wrongSkuVal,
        product_name: finalProdName,
        sku: currentDetectedProduct.sku,
        seller_sku: currentDetectedProduct.seller_sku || currentDetectedProduct.sku || '-',
        sap_code: currentDetectedProduct.sap_code || '-',
        shop: currentDetectedProduct.shop || '',
        bin_code: currentDetectedProduct.bin_code || '',
        batch_no: batchNo || '-',
        exp_date: formatExpDate(expDate),
        qty: qty,
        type: type,
        condition: isItemDamaged ? 'RUSAK' : 'GOOD',
        damage_reason: wrongProductNameVal || type,
        notes: wrongProductNameVal,
        photo: itemPhoto,
        photo_path: itemPhoto
    });

    // Perbarui watermark HANYA untuk foto yang terikat ke item ini (jangan timpa foto rusak item sebelumnya)
    const newItem = scannedProductsList[scannedProductsList.length - 1];
    let hasUpdatedPhotos = false;
    const boundPhoto = (itemPhoto && Array.isArray(capturedPhotosList))
        ? capturedPhotosList.find(p => p.dataUrl === itemPhoto)
        : null;
    if (boundPhoto) {
        boundPhoto.isDamaged = isItemDamaged;
        boundPhoto.type = isItemDamaged ? 'damaged' : 'product';
        boundPhoto.badge = isItemDamaged ? 'Barang Rusak' : 'Produk Retur';
        boundPhoto.title = isItemDamaged 
            ? `Foto Bukti Barang Rusak (${currentDetectedProduct.name} - ${type} x${qty})` 
            : `Foto Produk (${currentDetectedProduct.name})`;
        boundPhoto.isInitialBeforeScan = false;

        if (boundPhoto.rawCanvas) {
            const condLabel = isItemDamaged ? `⚠️ ${type} (RUSAK)` : (type || 'GOOD');
            const bText = isItemDamaged ? `⚠️ FOTO BUKTI BARANG RUSAK (${type})` : '🏷️ FOTO PRODUK UNBOXING';
            const bColor = isItemDamaged ? '#dc2626' : '#059669';
            const fields = [
                { label: 'NO. INVOICE', val: activeInvoice || 'INVOICE', highlight: true },
                { label: 'KONDISI / TIPE', val: condLabel, highlight: isItemDamaged },
                { label: 'NAMA PRODUK', val: currentDetectedProduct.name.length > 28 ? currentDetectedProduct.name.substring(0, 28) + '...' : currentDetectedProduct.name },
                { label: 'SKU / SAP', val: `${currentDetectedProduct.seller_sku || currentDetectedProduct.sku || '-'} | ${currentDetectedProduct.sap_code || '-'}` },
                { label: 'QTY PRODUK', val: `${qty} Unit`, highlight: isItemDamaged },
                { label: 'BATCH & EXP', val: `B:${batchNo || '-'} | Exp:${expDate || '-'}` },
                { label: 'WAKTU & OPERATOR', val: `${getNowFormattedWIB()} (${(document.getElementById('displayOperator')?.innerText || 'Gudang 01').trim()})` }
            ];
            try {
                boundPhoto.dataUrl = generateWatermarkedPhoto({
                    badgeText: bText,
                    badgeColor: bColor,
                    fields: fields,
                    sourceImage: boundPhoto.rawCanvas
                });
            } catch (wmErr) {
                console.warn('Re-watermark foto gagal, pakai foto asli:', wmErr);
            }
        }
        // Sinkronkan item dengan foto terbaru agar photo_ref tetap cocok saat submit
        if (newItem) {
            newItem.photo = boundPhoto.dataUrl;
            newItem.photo_path = boundPhoto.dataUrl;
            newItem.photo_id = boundPhoto.id;
        }
        hasUpdatedPhotos = true;
    }
    if (hasUpdatedPhotos) {
        renderPhotosGallery();
    }

    playBeep('success');
    currentActiveDamagedPhoto = null;
    updateDamagedPhotoBanner();
    renderItemsTable();
    resetProductInputs();

    // Auto Focus kembali ke Barcode untuk scan item berikutnya!
    setTimeout(() => {
        inputBarcode.focus();
    }, 50);
}

function resetProductInputs() {
    currentActiveDamagedPhoto = null;
    updateDamagedPhotoBanner();
    currentDetectedProduct = null;
    clearWrongProductInput(false);
    clearWrongKeteranganInput(false);
    isManualWrongMode = false;
    if (typeof syncWrongProductInputMode === 'function') syncWrongProductInputMode();
    const containerWrong = document.getElementById('containerWrongProductSection');
    if (containerWrong) containerWrong.classList.add('hidden');
    inputBarcode.value = '';
    inputBatch.value = '';
    inputExpDate.value = '';
    inputQty.value = 1;
    inputType.value = '';
    clearAutoExpIndicator();
    if (typeof updateNumpadDisplay === 'function') updateNumpadDisplay();
    if (typeof toggleVirtualKeyboard === 'function') toggleVirtualKeyboard(false);
    if (typeof toggleNumpadExpDate === 'function') toggleNumpadExpDate(false);
    document.getElementById('detectedProductName').innerText = "Silakan scan / ketik barcode...";
    document.getElementById('detectedProductName').className = "font-bold text-indigo-700 ml-1 text-sm";
    document.getElementById('detectedProductSku').innerText = "";
    if (typeof updateProductPhotoButtonState === 'function') updateProductPhotoButtonState();
    if (typeof updatePhotoButtonsState === 'function') updatePhotoButtonsState();
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
                <td colspan="8" class="text-center py-6 text-slate-400 italic">
                    <div class="flex flex-col items-center justify-center space-y-1">
                        <i class="fa-solid fa-box-open text-xl text-slate-300"></i>
                        <span class="text-xs">Belum ada produk yang dimasukkan untuk invoice ini. Silakan scan barcode di atas.</span>
                    </div>
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

        // Badge Tipe Dinamis dari Master Kondisi
        const itemType = (item.type || 'GOOD').toUpperCase();
        const matchedCond = cachedConditionsList.find(c => (c.code || '').toUpperCase() === itemType);
        let badge = '';
        if (matchedCond) {
            const clr = matchedCond.color || 'slate';
            const colorClassMap = {
                emerald: 'bg-emerald-100 text-emerald-800 border-emerald-200',
                rose:    'bg-rose-100 text-rose-800 border-rose-200',
                red:     'bg-rose-100 text-rose-800 border-rose-200',
                amber:   'bg-amber-100 text-amber-800 border-amber-200',
                orange:  'bg-orange-100 text-orange-800 border-orange-200',
                purple:  'bg-purple-100 text-purple-800 border-purple-200',
                blue:    'bg-blue-100 text-blue-800 border-blue-200',
                slate:   'bg-slate-100 text-slate-700 border-slate-200'
            };
            const cCls = colorClassMap[clr] || 'bg-slate-100 text-slate-700 border-slate-200';
            badge = `<span class="${cCls} border px-1.5 py-0.5 rounded font-bold text-[10px] font-mono">${escapeHtml(matchedCond.name || item.type)}</span>`;
        } else if (item.type === 'RUSAK') {
            badge = `<span class="bg-rose-100 text-rose-800 px-1.5 py-0.5 rounded font-bold text-[10px]">RUSAK</span>`;
        } else if (item.type === 'EXPIRED') {
            badge = `<span class="bg-amber-100 text-amber-800 px-1.5 py-0.5 rounded font-bold text-[10px]">EXPIRED</span>`;
        } else if (item.type === 'SALAH_KIRIM') {
            badge = `<span class="bg-purple-100 text-purple-800 px-1.5 py-0.5 rounded font-bold text-[10px]">SALAH KIRIM</span>`;
        } else {
            badge = `<span class="bg-emerald-100 text-emerald-800 px-1.5 py-0.5 rounded font-bold text-[10px]">${escapeHtml(item.type || 'GOOD')}</span>`;
        }

        const isDmgItem = (itemType !== 'GOOD' && itemType !== 'BAGUS' && itemType !== 'LAYAK');
        const itemPhotoSrc = item.photo || item.photo_path;
        let photoBtn = '';
        if (itemPhotoSrc) {
            photoBtn = `
                <div class="mt-1">
                    <button type="button" onclick="previewItemPhoto(${index})" 
                        class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-rose-50 hover:bg-rose-100 text-rose-700 font-bold text-[9px] border border-rose-200 transition shadow-2xs cursor-pointer" title="Lihat Foto Bukti">
                        <i class="fa-solid fa-camera"></i> Foto Siap
                    </button>
                </div>
            `;
        } else if (isDmgItem) {
            photoBtn = `
                <div class="mt-1">
                    <span class="inline-flex items-center gap-1 text-[9px] text-rose-600 font-bold">
                        <i class="fa-solid fa-triangle-exclamation"></i> Tanpa Foto
                    </span>
                </div>
            `;
        }

        let wrongInfoHtml = '';
        if (item.wrong_product_name || (item.wrong_barcode && item.wrong_barcode !== '-')) {
            const hasRealBarcode = Boolean(item.wrong_barcode && item.wrong_barcode !== '-');
            wrongInfoHtml = `
                <div class="mt-1 px-2 py-1 bg-purple-50 border border-purple-200 rounded text-[10px] text-purple-900 leading-snug">
                    <div class="font-bold flex items-center gap-1 text-purple-800">
                        <i class="fa-solid ${hasRealBarcode ? 'fa-arrows-split-up-and-left' : 'fa-pen-to-square'} text-purple-600"></i> ${hasRealBarcode ? 'Fisik Salah Kirim:' : 'Keterangan Barang Salah:'}
                    </div>
                    <div class="font-semibold text-slate-800">${escapeHtml(item.wrong_product_name || 'Item Non-Master')}</div>
                    ${hasRealBarcode ? `<div class="font-mono text-[9px] text-purple-700">Barcode: <b class="text-purple-950 font-bold">${escapeHtml(item.wrong_barcode)}</b> ${item.wrong_sku ? '| SKU: ' + escapeHtml(item.wrong_sku) : ''}</div>` : ''}
                </div>
            `;
        }

        const tr = document.createElement('tr');
        tr.className = 'hover:bg-slate-50 transition border-b border-slate-100';
        tr.innerHTML = `
            <td class="py-1.5 px-2 text-center text-slate-400 font-mono text-[11px]">${index + 1}</td>
            <td class="py-1.5 px-2 font-mono font-bold text-indigo-700 text-xs">${escapeHtml(item.barcode)}</td>
            <td class="py-1.5 px-2">
                <div class="font-bold text-slate-800 text-xs">${escapeHtml(item.product_name)}</div>
                <div class="text-[9px] text-slate-500 flex flex-wrap gap-1 mt-0.5">
                    <span class="bg-indigo-50 text-indigo-700 px-1.5 py-0.2 rounded font-mono font-bold border border-indigo-200">SKU: ${escapeHtml(item.seller_sku || item.sku)}</span>
                    <span class="bg-emerald-50 text-emerald-700 px-1.5 py-0.2 rounded font-mono font-bold border border-emerald-200">SAP: ${escapeHtml(item.sap_code || '-')}</span>
                    ${item.bin_code ? `<span class="bg-amber-50 text-amber-700 px-1.5 py-0.2 rounded font-mono text-[9px] border border-amber-200">Rak: ${escapeHtml(item.bin_code)}</span>` : ''}
                </div>
                ${wrongInfoHtml}
            </td>
            <td class="py-1.5 px-2 font-mono text-slate-600 whitespace-nowrap text-xs">${escapeHtml(item.batch_no || '-')}</td>
            <td class="py-1.5 px-2 font-mono text-slate-600 whitespace-nowrap text-xs font-medium">${formatExpDate(item.exp_date)}</td>
            <td class="py-1.5 px-2 text-center font-bold text-slate-900 text-xs font-mono">${item.qty}</td>
            <td class="py-1.5 px-2 text-center">${badge}${photoBtn}</td>
            <td class="py-1.5 px-2 text-center">
                <button type="button" onclick="removeItem(${index})" title="Hapus Item" class="text-rose-500 hover:text-rose-700 p-1 rounded-lg hover:bg-rose-50 transition">
                    <i class="fa-solid fa-trash-can text-xs"></i>
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
    const removed = scannedProductsList.splice(index, 1)[0];
    // Hapus juga foto bukti rusak yang terikat KHUSUS ke item ini (jika tidak dipakai item lain)
    if (removed && removed.photo_id) {
        const stillUsed = scannedProductsList.some(it => it.photo_id === removed.photo_id);
        if (!stillUsed) {
            const before = capturedPhotosList.length;
            capturedPhotosList = capturedPhotosList.filter(p => p.id !== removed.photo_id);
            if (capturedPhotosList.length !== before) {
                renderPhotosGallery();
                showToast('info', 'Foto bukti milik item yang dihapus juga dihapus dari antrean unboxing.', 'Foto Dihapus');
            }
        }
    }
    renderItemsTable();
};

window.previewItemPhoto = function(index) {
    const it = scannedProductsList[index];
    if (!it) return;
    previewImageDirect(it.photo || it.photo_path, `Foto Bukti: ${it.product_name || it.barcode || '-'} (${it.type || '-'})`);
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
    if (!window.MediaRecorder) {
        console.warn("MediaRecorder tidak didukung browser ini.");
        showToast('error', 'Browser ini tidak mendukung perekaman video unboxing (MediaRecorder). Gunakan Chrome / Edge terbaru.', 'Rekam Video Gagal');
        return;
    }
    const hasLiveTrack = Boolean(mediaStream && mediaStream.getVideoTracks().some(t => t.readyState === 'live'));
    if (!hasLiveTrack) {
        console.warn("mediaStream belum aktif, perekaman video ditunda hingga kamera siap.");
        pendingVideoRecord = true;
        return;
    }
    // Sudah merekam untuk invoice yang sama -> jangan reset rekaman (cegah double start)
    if (mediaRecorder && mediaRecorder.state !== 'inactive' && mediaRecorder._invoice === activeInvoice) {
        pendingVideoRecord = false;
        return;
    }
    pendingVideoRecord = false;

    try {
        if (mediaRecorder && mediaRecorder.state !== 'inactive') {
            try { mediaRecorder.stop(); } catch(e){}
        }

        let mime = '';
        const candidateTypes = [
            'video/webm;codecs=vp9,opus',
            'video/webm;codecs=vp9',
            'video/webm;codecs=vp8,opus',
            'video/webm;codecs=vp8',
            'video/webm',
            'video/mp4;codecs=avc1',
            'video/mp4'
        ];
        if (typeof MediaRecorder.isTypeSupported === 'function') {
            for (const t of candidateTypes) {
                if (MediaRecorder.isTypeSupported(t)) {
                    mime = t;
                    break;
                }
            }
        }

        // Gunakan bitrate HD 2.0 Mbps agar video unboxing jernih & barcode/produk terlihat jelas
        let rec = null;
        let options = {};
        if (mime) options.mimeType = mime;
        try {
            options.videoBitsPerSecond = 2000000;
            rec = new MediaRecorder(mediaStream, options);
        } catch (recErr) {
            console.warn("MediaRecorder dengan options bitrate gagal, fallback:", recErr);
            try {
                rec = mime ? new MediaRecorder(mediaStream, { mimeType: mime }) : new MediaRecorder(mediaStream);
            } catch (e2) {
                rec = new MediaRecorder(mediaStream);
            }
        }

        // Chunk disimpan per-recorder agar event 'dataavailable' terlambat dari recorder lama
        // tidak tercampur ke rekaman baru (video korup / video invoice lain)
        const chunks = [];
        rec._chunks = chunks;
        rec._invoice = activeInvoice;

        rec.ondataavailable = (e) => {
            if (e.data && e.data.size > 0) {
                chunks.push(e.data);
            }
        };

        rec.onerror = (ev) => {
            console.error("MediaRecorder runtime error:", ev);
            const msg = (ev && ev.error && ev.error.message) ? ev.error.message : 'kesalahan tidak diketahui';
            showToast('error', 'Rekaman video unboxing terhenti: ' + msg, 'Rekam Video');
        };

        rec.start(1000); // kumpulkan chunk tiap 1 detik

        mediaRecorder = rec;
        recordedChunks = chunks;

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
        showToast('error', 'Gagal memulai rekaman video unboxing: ' + (e && e.message ? e.message : e) + '. Coba hubungkan ulang kamera.', 'Rekam Video Gagal');
    }
}

// Bangun Blob video dari chunk milik recorder tertentu dengan MIME asli (webm / mp4)
function buildRecordingBlob(rec) {
    const chunks = (rec && rec._chunks) ? rec._chunks : [];
    if (chunks.length === 0) return null;
    const rawType = (rec && rec.mimeType) || (chunks[0] && chunks[0].type) || 'video/webm';
    return new Blob(chunks, { type: String(rawType).split(';')[0] || 'video/webm' });
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

        const rec = mediaRecorder;
        if (!rec || rec.state === 'inactive') {
            resolve(buildRecordingBlob(rec));
            return;
        }

        let resolved = false;
        let safetyTimeout = null;
        const finish = () => {
            if (resolved) return;
            resolved = true;
            if (safetyTimeout) clearTimeout(safetyTimeout);
            resolve(buildRecordingBlob(rec));
        };
        // Batas aman lebih longgar untuk perangkat lambat (PDT) agar chunk terakhir tidak hilang
        safetyTimeout = setTimeout(finish, 5000);
        rec.addEventListener('stop', finish, { once: true });

        try {
            rec.stop(); // 'dataavailable' terakhir selalu terpicu sebelum event 'stop'
        } catch (e) {
            console.warn("mediaRecorder.stop error:", e);
            finish();
        }
    });
}

let isSubmittingFinalSession = false;
window.submitFinalSession = async function() {
    if (isSubmittingFinalSession) return;
    if (!activeInvoice || scannedProductsList.length === 0) return;

    // Auto-fallback: Jika ada item kondisi bukan GOOD yang belum terikat foto individu,
    // pasangkan otomatis foto dokumentasi yang sudah diambil di sesi ini
    const sessionFallbackPhoto = currentActiveDamagedPhoto || capturedProductPhoto || capturedPackagePhoto || (capturedPhotosList.length > 0 ? (capturedPhotosList[0].dataUrl || capturedPhotosList[0].data) : null);
    scannedProductsList.forEach(it => {
        const c = (it.type || it.condition || '').toUpperCase().trim();
        const isD = (c !== 'GOOD' && c !== 'BAGUS' && c !== 'LAYAK' && c !== '');
        if (isD && !it.photo && !it.photo_path && sessionFallbackPhoto) {
            it.photo = sessionFallbackPhoto;
            it.photo_path = sessionFallbackPhoto;
        }
    });

    // VALIDASI WAJIB FOTO BARANG RUSAK: Setiap item kondisi bukan GOOD wajib punya foto!
    const unphotographedDamaged = scannedProductsList.find(it => {
        const c = (it.type || it.condition || '').toUpperCase().trim();
        const isD = (c !== 'GOOD' && c !== 'BAGUS' && c !== 'LAYAK' && c !== '');
        return isD && !it.photo && !it.photo_path;
    });
    if (unphotographedDamaged) {
        playBeep('error');
        showToast('error', `Produk [${unphotographedDamaged.product_name || unphotographedDamaged.barcode}] dengan kondisi ${unphotographedDamaged.type} belum memiliki foto bukti fisik! Silakan ambil foto bukti terlebih dahulu.`, 'Wajib Foto Bukti');
        return;
    }

    isSubmittingFinalSession = true;
    const notes = document.getElementById('sessionNotesInput').value.trim();
    const btn = document.getElementById('btnFinalizeSession');
    if (!btn.dataset.origHtml) btn.dataset.origHtml = btn.innerHTML;
    const btnOrigHtml = btn.dataset.origHtml;
    btn.disabled = true;
    btn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> Menyimpan...`;

    // Ambil rekaman video unboxing HANYA dari recorder milik invoice aktif ini
    let videoBlob = null;
    if (mediaRecorder && mediaRecorder._invoice === activeInvoice) {
        videoBlob = await stopVideoRecording();
    }

    if (!videoBlob || videoBlob.size < 100) {
        playBeep('error');
        showToast('error', 'Rekaman video unboxing belum terdeteksi! Pastikan kamera menyala dan merekam proses unboxing sebelum menyelesaikan sesi.', 'Wajib Ada Video Unboxing');
        isSubmittingFinalSession = false;
        btn.disabled = scannedProductsList.length === 0;
        btn.innerHTML = btnOrigHtml;
        // Mulai ulang perekaman agar sesi ini tetap bisa diselesaikan dengan video
        startVideoRecording();
        return;
    }

    const displayOp = document.getElementById('displayOperator');
    const operatorName = (displayOp && displayOp.innerText.trim()) ? displayOp.innerText.trim() : 'Gudang 01';

    const savedInv = activeInvoice;
    const totalItemsCount = scannedProductsList.length;

    // OPTIMASI: Deduplikasi Base64 gambar agar JSON payload tidak bengkak puluhan MB
    const optimizedItems = scannedProductsList.map((it) => {
        let photoRef = null;
        if (it.photo) {
            const foundIdx = capturedPhotosList.findIndex(p => (it.photo_id && p.id === it.photo_id) || p.dataUrl === it.photo || p.data === it.photo);
            if (foundIdx !== -1) {
                photoRef = foundIdx;
            }
        }
        return {
            barcode: it.barcode,
            wrong_barcode: it.wrong_barcode,
            wrong_product_name: it.wrong_product_name,
            wrong_sku: it.wrong_sku,
            product_name: it.product_name,
            sku: it.sku,
            seller_sku: it.seller_sku,
            sap_code: it.sap_code,
            shop: it.shop,
            bin_code: it.bin_code,
            batch_no: it.batch_no,
            exp_date: it.exp_date,
            qty: it.qty,
            type: it.type,
            condition: it.condition,
            damage_reason: it.damage_reason,
            photo_ref: photoRef,
            // Jika photoRef sudah menunjuk ke foto di array photos, kirim null agar JSON ringan & super cepat
            photo: (photoRef !== null) ? null : it.photo,
            photo_path: (photoRef !== null) ? null : it.photo_path
        };
    });

    const videoSizeMb = (videoBlob && videoBlob.size > 0) ? (videoBlob.size / (1024 * 1024)).toFixed(1) : null;
    showGlobalLoading('Menyimpan Transaksi...', videoSizeMb ? `Memproses data & video unboxing (${videoSizeMb} MB)...` : 'Memproses data unboxing...');

    // Konversi video blob ke Base64 agar kebal terhadap PHP Error 6 (UPLOAD_ERR_NO_TMP_DIR)
    // yang terjadi di shared hosting seperti InfinityFree karena server tidak memiliki folder tmp PHP.
    let videoBase64 = null;
    let vExt = 'webm';
    if (videoBlob && videoBlob.size > 0) {
        vExt = /mp4/i.test(videoBlob.type || '') ? 'mp4' : 'webm';
        try {
            videoBase64 = await new Promise((resolve) => {
                const reader = new FileReader();
                let done = false;
                reader.onload = () => {
                    if (!done) { done = true; resolve(reader.result || null); }
                };
                reader.onloadend = () => {
                    if (!done) { done = true; resolve(reader.result || null); }
                };
                reader.onerror = () => {
                    if (!done) { done = true; resolve(null); }
                };
                reader.readAsDataURL(videoBlob);
            });
        } catch (convErr) {
            console.warn('Konversi video unboxing ke Base64 gagal:', convErr);
        }
    }

    if (!videoBase64 || videoBase64.length < 100) {
        hideGlobalLoading();
        isSubmittingFinalSession = false;
        btn.disabled = false;
        btn.innerHTML = btnOrigHtml;
        showToast('error', 'Gagal memproses file rekaman video unboxing di browser. Silakan klik Simpan kembali.', 'Gagal Proses Video');
        return;
    }

    const payload = {
        invoice_number: activeInvoice,
        expedition: activeExpedition || 'Lainnya',
        operator_name: operatorName,
        customer_name: 'Pelanggan Return',
        notes: notes,
        // Foto paket/produk sudah ada di array photos -> jangan kirim ulang (hemat payload)
        package_photo: null,
        product_photo: null,
        photos: capturedPhotosList.map(p => ({
            type: p.type,
            data: p.dataUrl,
            title: p.title
        })),
        items: optimizedItems,
        video_ext: vExt
    };

    const payloadJson = JSON.stringify(payload);
    const jsonSizeKb = Math.round(new Blob([payloadJson]).size / 1024);

    try {
        const formData = new FormData();
        formData.append('data', payloadJson);
        formData.append('video_base64', videoBase64);
        formData.append('video_ext', vExt);

        // Gunakan XMLHttpRequest untuk memantau progress upload secara real-time
        const result = await new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'api/returns.php', true);

            xhr.upload.onprogress = function(e) {
                if (e.lengthComputable && e.total > 0) {
                    const percent = Math.min(99, Math.round((e.loaded / e.total) * 100));
                    const totalMb = (e.total / (1024 * 1024)).toFixed(1);
                    const loadedMb = (e.loaded / (1024 * 1024)).toFixed(1);
                    updateGlobalLoadingProgress(
                        `Mengunggah Video & Data (${percent}%)`,
                        `Terkirim ${loadedMb} MB dari ${totalMb} MB... Mohon tunggu.`,
                        percent
                    );
                }
            };

            xhr.onload = function() {
                updateGlobalLoadingProgress(
                    'Menyimpan Transaksi...',
                    'Data & video terunggah! Memproses penyimpanan ke database...',
                    100
                );
                try {
                    const resJson = JSON.parse(xhr.responseText);
                    if (resJson && typeof resJson === 'object') resJson._httpStatus = xhr.status;
                    resolve(resJson);
                } catch (jsonErr) {
                    resolve({
                        success: false,
                        error: `Respon server [HTTP ${xhr.status}]: ${xhr.responseText.replace(/<[^>]*>?/gm, '').trim().substring(0, 150)}`
                    });
                }
            };

            xhr.onerror = function() {
                reject(new Error("Koneksi jaringan terputus saat mengunggah data ke server."));
            };

            xhr.send(formData);
        });

        hideGlobalLoading();

        if (result && result.success) {
            playBeep('success');

            // 1. Popup modal sukses dihilangkan sepenuhnya
            const successModal = document.getElementById('successModal');
            if (successModal) successModal.classList.add('hidden');

            // 2. Langsung reset sesi dan mulai scan baru secara instan
            resetInvoiceSession(true);

            // 3. Tampilkan toast notifikasi cepat dan elegan
            const vidNote = (videoBlob && videoBlob.size > 0) ? ' & video rekaman' : '';
            showToast('success', `Invoice [${savedInv}]${vidNote} berhasil disimpan (${totalItemsCount} barang). Silakan scan invoice baru!`, "Inbound Selesai");
        } else if (result && result._httpStatus === 401) {
            // Sesi login habis: JANGAN tinggalkan halaman ini (video & item masih tersimpan di memori)
            showToast('error', 'Sesi login berakhir. Buka TAB BARU, login kembali, lalu kembali ke tab ini dan klik Submit lagi. Jangan muat ulang halaman ini agar video & data tidak hilang.', "Sesi Login Berakhir", 15000);
        } else {
            showToast('error', (result && result.error) || 'Terjadi kesalahan saat menyimpan transaksi', "Gagal Menyimpan");
        }
    } catch (err) {
        hideGlobalLoading();
        showToast('error', "Gagal koneksi ke server: " + err.message, "Koneksi Terputus");
    } finally {
        isSubmittingFinalSession = false;
        hideGlobalLoading();
        if (btn) {
            btn.disabled = scannedProductsList.length === 0;
            btn.innerHTML = btnOrigHtml;
        }
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

// Kamera sedang merekam sesi unboxing aktif -> jangan putus stream (rekaman sebelumnya bisa hilang)
function isRecordingLiveSession() {
    return Boolean(mediaRecorder && mediaRecorder.state === 'recording' && mediaStream && mediaStream.active);
}

window.switchCamera = async function() {
    if (isRecordingLiveSession()) {
        showToast('warning', 'Tidak bisa ganti kamera saat rekaman video unboxing berjalan. Selesaikan atau reset sesi invoice terlebih dahulu.', 'Rekaman Berjalan');
        return;
    }
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
    if (isRecordingLiveSession()) {
        console.warn('startCamera diabaikan: kamera sedang merekam sesi unboxing aktif.');
        return;
    }
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

        // Pasang timer informatif jika izin kamera memakan waktu > 3.5 detik (menunggu operator klik 'Allow' di browser)
        const permissionNoticeTimeout = setTimeout(() => {
            if (loading && !isCameraActive) {
                loading.innerHTML = `
                    <div class="text-center p-4 max-w-xs space-y-2">
                        <i class="fa-solid fa-camera text-indigo-400 text-2xl animate-pulse mb-1"></i>
                        <p class="text-xs text-white font-bold">Menunggu Izin Kamera...</p>
                        <p class="text-[11px] text-slate-300 leading-normal">
                            Silakan klik <b>Izinkan (Allow)</b> pada notifikasi izin kamera browser di atas layar.
                        </p>
                        <button onclick="startCamera()" class="mt-1 bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-lg text-xs font-semibold inline-flex items-center gap-1.5">
                            <i class="fa-solid fa-rotate-right"></i> Hubungkan Ulang
                        </button>
                    </div>
                `;
            }
        }, 3500);

        let stream = null;

        // Upaya 1: Coba dengan constraint ideal HD / deviceId
        try {
            const constraints = {
                video: deviceId 
                    ? { deviceId: { exact: deviceId }, width: { ideal: 1920, min: 1280 }, height: { ideal: 1080, min: 720 }, frameRate: { ideal: 30, min: 15 } } 
                    : { facingMode: { ideal: "environment" }, width: { ideal: 1920, min: 1280 }, height: { ideal: 1080, min: 720 }, frameRate: { ideal: 30, min: 15 } },
                audio: false
            };
            stream = await navigator.mediaDevices.getUserMedia(constraints);
        } catch (specErr) {
            console.warn("Gagal constraint spesifik HD, fallback ke video standar:", specErr);
            // Upaya 2: Fallback ke parameter paling universal (didukung semua webcam USB)
            stream = await navigator.mediaDevices.getUserMedia({
                video: deviceId ? { deviceId: deviceId } : true,
                audio: false
            });
        }

        clearTimeout(permissionNoticeTimeout);
        mediaStream = stream;

        // Ambil daftar perangkat kamera di background setelah izin didapat
        getAvailableVideoDevices().catch(e => console.warn(e));

        if (videoElement) {
            videoElement.srcObject = mediaStream;
            videoElement.muted = true;
            try {
                await videoElement.play();
            } catch (playErr) {
                console.warn("video.play error:", playErr);
            }
        }
        if (loading) loading.classList.add('hidden');

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

// Helper Load Daftar Kondisi untuk Dropdown & Badge Table Inbound Unboxing
let cachedConditionsList = [];
async function loadConditions() {
    try {
        const res = await fetch('api/conditions.php');
        const list = await res.json();
        if (Array.isArray(list) && list.length > 0) {
            cachedConditionsList = list;
            try { localStorage.setItem('cached_master_conditions', JSON.stringify(list)); } catch (e) {}
            populateConditionDropdown(list);
        } else {
            fallbackLoadCachedConditions();
        }
    } catch (e) {
        console.warn('Gagal memuat master kondisi dari API:', e);
        fallbackLoadCachedConditions();
    }
}

function fallbackLoadCachedConditions() {
    try {
        const saved = localStorage.getItem('cached_master_conditions');
        if (saved) {
            cachedConditionsList = JSON.parse(saved);
            populateConditionDropdown(cachedConditionsList);
        }
    } catch (e) {}
}

function populateConditionDropdown(list) {
    const select = document.getElementById('inputType');
    if (!select || !Array.isArray(list) || list.length === 0) return;

    select.innerHTML = '<option value="" selected>-- Pilih Kondisi --</option>';
    list.forEach(c => {
        const opt = document.createElement('option');
        opt.value = c.code;
        opt.innerText = `${c.name} (${c.code})`;
        select.appendChild(opt);
    });

    select.value = '';
    if (typeof updatePhotoButtonsState === 'function') updatePhotoButtonsState();
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[m]);
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

    const origW = source.videoWidth || source.naturalWidth || source.width || 1280;
    const origH = source.videoHeight || source.naturalHeight || source.height || 720;
    
    if (!origW || !origH || origW === 0 || origH === 0) {
        throw new Error("Ukuran frame gambar kamera tidak valid.");
    }

    // Skala resolusi cerdas: batasi maksimal lebar 1280px agar foto ringan (<150KB) dan upload instan
    const MAX_W = 1280;
    let width = origW;
    let height = origH;
    if (width > MAX_W) {
        height = Math.round((origH * MAX_W) / origW);
        width = MAX_W;
    }

    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;
    const ctx = canvas.getContext('2d');

    // 1. Gambar frame langsung dari webcam / gambar (dengan scaling cerdas)
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

    return canvas.toDataURL('image/jpeg', 0.80);
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
    const prodPhotos = capturedPhotosList.filter(p => p.type === 'product' && !p.isDamaged);
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
        const isDmg = (item.type === 'damaged' || item.isDamaged || (item.title && item.title.toLowerCase().includes('rusak')));
        const badgeColor = isPkg ? 'bg-indigo-600' : (isDmg ? 'bg-rose-600' : 'bg-emerald-600');
        const badgeIcon = isPkg ? 'fa-box' : (isDmg ? 'fa-triangle-exclamation' : 'fa-tag');
        const badgeText = isPkg ? 'Paket' : (isDmg ? 'Rusak' : 'Produk');
        const safeTitle = escapeHtml(item.title || (isPkg ? 'Foto Bukti Paket' : (isDmg ? 'Foto Barang Rusak' : 'Foto Bukti Produk')));
        const timeStr = item.createdAt ? (item.createdAt.split(' ')[1] || item.createdAt) : '';

        const row = document.createElement('div');
        row.className = 'flex items-center justify-between py-1 px-2.5 bg-slate-50 hover:bg-slate-100 rounded-xl border border-slate-200 transition text-xs group';
        row.innerHTML = `
            <div class="flex items-center gap-1.5 min-w-0 flex-1">
                <span class="${badgeColor} text-white text-[10px] font-bold px-1.5 py-0.5 rounded-md flex items-center gap-1 shrink-0 shadow-2xs">
                    <i class="fa-solid ${badgeIcon} text-[9px]"></i> ${badgeText} #${index + 1}
                </span>
                <span class="truncate font-semibold text-slate-800 text-xs" title="${safeTitle}">${safeTitle}</span>
                ${timeStr ? `<span class="text-[10px] text-slate-400 font-mono shrink-0 hidden sm:inline">${timeStr}</span>` : ''}
            </div>
            <div class="flex items-center gap-1 shrink-0 ml-1.5">
                <button type="button" onclick="previewPhotoByIndex(${index})" class="text-indigo-600 hover:text-indigo-800 bg-white hover:bg-indigo-50 border border-indigo-200 px-2 py-0.5 rounded-lg text-[11px] font-bold transition flex items-center gap-1 shadow-2xs" title="Lihat Foto">
                    <i class="fa-solid fa-eye text-[10px]"></i> <span>Lihat</span>
                </button>
                <button type="button" onclick="deletePhotoItem('${item.id}')" class="text-rose-600 hover:text-rose-800 bg-white hover:bg-rose-50 border border-rose-200 px-2 py-0.5 rounded-lg text-[11px] font-bold transition flex items-center gap-1 shadow-2xs" title="Hapus Foto">
                    <i class="fa-solid fa-trash-can text-[10px]"></i>
                </button>
            </div>
        `;
        listEl.appendChild(row);
    });
};

window.previewPhotoByIndex = function(index) {
    const p = capturedPhotosList[index];
    if (!p) return;
    previewImageDirect(p.dataUrl, p.title || 'Preview Foto Watermark');
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
    if (!isInvoiceAndExpeditionFilled()) {
        showToast('warning', 'Pilih Ekspedisi dan masukkan Nomor Invoice / Resi terlebih dahulu sebelum mengambil foto paket!', 'Data Belum Lengkap');
        const inputInv = document.getElementById('inputInvoice');
        if (inputInv && !inputInv.disabled && inputInv.offsetParent !== null) {
            inputInv.focus();
        }
        return;
    }

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

window.captureProductPhoto = function(sourceImage = null, forcedCondition = null) {
    // 1. Validasi Ekspedisi & Invoice
    if (!isInvoiceAndExpeditionFilled()) {
        showToast('warning', 'Silakan pilih Ekspedisi dan masukkan Nomor Invoice / Resi terlebih dahulu!', 'Data Belum Lengkap');
        const inputInv = document.getElementById('inputInvoice');
        if (inputInv && !inputInv.disabled && inputInv.offsetParent !== null) {
            inputInv.focus();
        }
        return;
    }

    // 2. Validasi Scan Produk (Detail Produk Muncul)
    if (!currentDetectedProduct || !currentDetectedProduct.barcode) {
        showToast('warning', 'Silakan scan barcode produk terlebih dahulu sampai detail produk muncul!', 'Produk Belum Discan');
        const inputBar = document.getElementById('inputBarcode');
        if (inputBar && inputBar.offsetParent !== null) {
            inputBar.focus();
        }
        return;
    }

    // 3. Validasi Batch & Exp Date
    const batchEl = document.getElementById('inputBatch');
    const expEl = document.getElementById('inputExpDate');
    const bVal = batchEl?.value?.trim() || '';
    const eVal = expEl?.value?.trim() || '';
    if (!bVal || !eVal || eVal === '-' || eVal === 'dd-mm-yyyy') {
        showToast('warning', 'Silakan input No. Batch dan Exp Date produk terlebih dahulu sebelum mengambil foto!', 'Batch & Exp Wajib');
        if (!bVal && batchEl) batchEl.focus();
        else if (expEl) expEl.focus();
        return;
    }

    // 4. Validasi Kondisi Produk Sesuai Tombol
    const isDmgCond = isConditionDamaged();
    if (forcedCondition === 'RUSAK' && !isDmgCond) {
        showToast('warning', 'Ubah Type / Kondisi ke RUSAK / DEFECT terlebih dahulu untuk mengambil foto bukti barang rusak!', 'Kondisi Belum Rusak');
        const typeEl = document.getElementById('inputType');
        if (typeEl) typeEl.focus();
        return;
    }

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

        const typeEl = document.getElementById('inputType');
        const qtyEl = document.getElementById('inputQty');

        let pBatch = bVal || '-';
        let pExp = eVal || '-';
        let pType = (typeEl && typeEl.value) ? typeEl.value.trim() : (forcedCondition === 'RUSAK' ? 'RUSAK' : 'GOOD');
        let pQty = (qtyEl && qtyEl.value) ? parseInt(qtyEl.value, 10) : 1;
        let pName = currentDetectedProduct.name || 'Produk Return';
        let pSku = currentDetectedProduct.seller_sku || currentDetectedProduct.sku || '-';
        let pSap = currentDetectedProduct.sap_code || '-';
        let isDamaged = (forcedCondition === 'RUSAK');

        if (isDamaged && (pType === 'GOOD' || pType === 'BAGUS' || pType === 'LAYAK' || !pType)) {
            pType = 'RUSAK';
        }

        // PASTIKAN: Jika status isDamaged, label kondisi di watermark TIDAK BOLEH "GOOD"!
        if (isDamaged && (String(pType).toUpperCase() === 'GOOD' || String(pType).toUpperCase() === 'BAGUS' || String(pType).toUpperCase() === 'LAYAK' || !pType)) {
            pType = 'RUSAK';
        }

        const conditionLabel = isDamaged ? `⚠️ ${pType} (RUSAK)` : (pType || 'GOOD');
        const badgeText = isDamaged ? `⚠️ FOTO BUKTI BARANG RUSAK (${pType})` : '🏷️ FOTO PRODUK UNBOXING';
        const badgeColor = isDamaged ? '#dc2626' : '#059669';

        // Simpan snapshot canvas asli untuk re-watermark otomatis jika nanti item di-commit
        let rawCanvas = null;
        const sourceForSnapshot = sourceImage || document.getElementById('liveVideoFeed');
        if (sourceForSnapshot) {
            const w = sourceForSnapshot.videoWidth || sourceForSnapshot.naturalWidth || sourceForSnapshot.width || 1280;
            const h = sourceForSnapshot.videoHeight || sourceForSnapshot.naturalHeight || sourceForSnapshot.height || 720;
            if (w > 0 && h > 0) {
                rawCanvas = document.createElement('canvas');
                rawCanvas.width = w;
                rawCanvas.height = h;
                const rCtx = rawCanvas.getContext('2d');
                if (rCtx) rCtx.drawImage(sourceForSnapshot, 0, 0, w, h);
            }
        }

        const fields = [
            { label: 'NO. INVOICE', val: inv, highlight: true },
            { label: 'KONDISI / TIPE', val: conditionLabel, highlight: isDamaged },
            { label: 'NAMA PRODUK', val: pName.length > 28 ? pName.substring(0, 28) + '...' : pName },
            { label: 'SKU / SAP', val: `${pSku} | ${pSap}` },
            { label: 'QTY PRODUK', val: `${pQty || 1} Unit`, highlight: isDamaged },
            { label: 'BATCH & EXP', val: `B:${pBatch || '-'} | Exp:${pExp || '-'}` },
            { label: 'WAKTU & OPERATOR', val: `${getNowFormattedWIB()} (${opName})` }
        ];

        const dataUrl = generateWatermarkedPhoto({
            badgeText: badgeText,
            badgeColor: badgeColor,
            fields: fields,
            sourceImage: rawCanvas || sourceImage
        });

        const prodCount = capturedPhotosList.filter(p => p.type === 'product' || p.type === 'damaged').length + 1;
        const isPreScan = (!currentDetectedProduct && (!scannedProductsList || scannedProductsList.length === 0));
        const photoItem = {
            id: (isDamaged ? 'dmg_' : 'prod_') + Date.now() + '_' + Math.random().toString(36).substring(2, 6),
            type: isDamaged ? 'damaged' : 'product',
            badge: isDamaged ? 'Barang Rusak' : 'Produk Retur',
            isDamaged: isDamaged,
            title: isDamaged ? `Foto Bukti Barang Rusak #${prodCount} (${pName} - ${pType} x${pQty})` : `Foto Produk #${prodCount} (${pName})`,
            dataUrl: dataUrl,
            rawCanvas: rawCanvas,
            isInitialBeforeScan: isPreScan,
            createdAt: getNowFormattedWIB()
        };
        capturedPhotosList.push(photoItem);

        if (isDamaged) {
            currentActiveDamagedPhoto = dataUrl;
            updateDamagedPhotoBanner();
        }

        renderPhotosGallery();

        showToast(
            'success',
            `${isDamaged ? 'Foto Bukti Barang Rusak' : 'Foto Produk'} #${prodCount} berhasil disimpan! [Tuts ${isDamaged ? 'F5' : 'F4'}]`,
            isDamaged ? 'Foto Barang Rusak Siap' : 'Foto Produk Siap'
        );
    } catch (err) {
        console.error("captureProductPhoto error:", err);
        showToast('error', 'Gagal mengambil foto: ' + err.message, 'Gagal Foto');
    }
};

window.handlePhotosMultipleUpload = async function(inputElement) {
    if (!isInvoiceFilled()) {
        showToast('warning', 'Silakan scan atau masukkan Nomor Invoice / Resi terlebih dahulu sebelum mengunggah foto bukti!', 'Resi Belum Terisi');
        if (inputElement) inputElement.value = '';
        const inputInv = document.getElementById('inputInvoice');
        if (inputInv && !inputInv.disabled && inputInv.offsetParent !== null) {
            inputInv.focus();
        }
        return;
    }

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
                        // Foto produk hanya bila detail produk + batch/exp sedang terisi; selain itu simpan sebagai foto paket (jangan dibuang)
                        const canProductPhoto = Boolean(typeof window.isProductDetailAndBatchExpFilled === 'function' && window.isProductDetailAndBatchExpFilled());
                        if (canProductPhoto && capturedPhotosList.filter(p => p.type === 'package').length > 0) {
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
    if (!isInvoiceFilled()) {
        showToast('warning', 'Silakan scan atau masukkan Nomor Invoice / Resi terlebih dahulu sebelum mengunggah foto bukti!', 'Resi Belum Terisi');
        if (inputElement) inputElement.value = '';
        const inputInv = document.getElementById('inputInvoice');
        if (inputInv && !inputInv.disabled && inputInv.offsetParent !== null) {
            inputInv.focus();
        }
        return;
    }

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

// Global Keyboard Shortcut: F2 (Foto Paket), F4 (Foto Produk Baik), F5 (Foto Barang Rusak)
window.addEventListener('keydown', (e) => {
    if (e.key === 'F2') {
        e.preventDefault();
        capturePackagePhoto();
    } else if (e.key === 'F4') {
        e.preventDefault();
        captureProductPhoto(null, null);
    } else if (e.key === 'F5') {
        // Cegah default browser reload jika sedang aktif di halaman
        e.preventDefault();
        captureProductPhoto(null, 'RUSAK');
    }
});

// Peringatan sebelum meninggalkan halaman saat sesi unboxing berisi item / sedang merekam video
window.addEventListener('beforeunload', (e) => {
    const hasItems = Array.isArray(scannedProductsList) && scannedProductsList.length > 0;
    const isRecording = Boolean(mediaRecorder && mediaRecorder.state === 'recording' && activeInvoice);
    if (isSubmittingFinalSession || hasItems || isRecording) {
        e.preventDefault();
        e.returnValue = 'Sesi unboxing belum disimpan. Yakin ingin meninggalkan halaman?';
        return e.returnValue;
    }
});

// Inisialisasi Otomatis saat Halaman Dimuat
window.addEventListener('DOMContentLoaded', () => {
    if (typeof updatePhotoButtonsState === 'function') {
        updatePhotoButtonsState();
    }
    if (inputInvoice) {
        inputInvoice.focus();
    }
    // Muat daftar ekspedisi
    loadExpeditions();
    // Muat daftar kondisi produk (Master Condition dinamis)
    loadConditions();
    // Langsung jalankan kamera live scanner di sebelah kiri
    startCamera();
});
