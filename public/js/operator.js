// State Management Operator
let html5QrCode = null;
let currentScannerTarget = 'INVOICE'; // 'INVOICE' atau 'PRODUCT'
let activeInvoice = null;
let scannedProductsList = [];
let pendingProduct = null;

// Audio Synthesizer Beep (Web Audio API - Berjalan tanpa file audio eksternal)
const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
function playBeep(type = 'success') {
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
}

// Inisialisasi Auto-Open Camera
async function startAutoCamera() {
    const loadingElem = document.getElementById('cameraLoading');
    try {
        html5QrCode = new Html5Qrcode("reader");
        const cameras = await Html5Qrcode.getCameras();
        
        if (cameras && cameras.length) {
            // Utamakan kamera belakang jika tersedia (khusus perangkat mobile gudang)
            const backCam = cameras.find(c => c.label.toLowerCase().includes('back') || c.label.toLowerCase().includes('rear')) || cameras[0];
            
            await html5QrCode.start(
                backCam.id,
                {
                    fps: 15,
                    qrbox: { width: 250, height: 250 },
                    aspectRatio: 1.0
                },
                onScanSuccess,
                () => {} // abaikan frame kosong
            );
            if (loadingElem) loadingElem.classList.add('hidden');
        } else {
            console.warn("Tidak ada kamera yang terdeteksi.");
            if (loadingElem) {
                loadingElem.innerHTML = `<span class="text-xs text-amber-400 p-4 text-center">Kamera tidak ditemukan. Gunakan barcode scanner USB atau input manual.</span>`;
            }
        }
    } catch (err) {
        console.error("Gagal membuka kamera:", err);
        if (loadingElem) {
            loadingElem.innerHTML = `<span class="text-xs text-rose-400 p-4 text-center">Kamera tidak aktif: ${err.message}. Silakan gunakan barcode scanner USB.</span>`;
        }
    }
}

// Handler Saat Kamera / Gun Mendeteksi Kode Barcode atau QR
async function onScanSuccess(decodedText) {
    playBeep('success');
    console.log("Kode Terdeteksi:", decodedText);

    if (currentScannerTarget === 'INVOICE') {
        await processInvoiceScan(decodedText.trim());
    } else if (currentScannerTarget === 'PRODUCT') {
        await processProductScan(decodedText.trim());
    }
}

// Helper untuk klik tombol demo barcode
window.simulateScan = function(code) {
    onScanSuccess(code);
};

// 1. Logika Pemrosesan Scan Invoice
async function processInvoiceScan(invoiceNumber) {
    try {
        const res = await fetch(`/api/invoice/${encodeURIComponent(invoiceNumber)}`);
        const data = await res.json();

        activeInvoice = data.invoice_number;
        document.getElementById('displayInvoice').innerText = activeInvoice;
        document.getElementById('invoiceBadge').classList.remove('hidden');

        // Beralih otomatis ke mode SCAN PRODUK
        currentScannerTarget = 'PRODUCT';
        document.getElementById('currentScanTarget').innerText = 'Scan Barcode Produk';
        document.getElementById('scanModeIndicator').innerText = 'LANGKAH 2: SCAN PRODUK';
        document.getElementById('productStatusHint').innerText = 'Silakan scan barcode produk...';
    } catch (err) {
        playBeep('error');
        alert("Gagal memproses invoice: " + err.message);
    }
}

// 2. Logika Pemrosesan Scan Barcode Produk
async function processProductScan(barcode) {
    if (!activeInvoice) {
        alert("Harap scan invoice terlebih dahulu!");
        return;
    }

    try {
        const res = await fetch(`/api/product/${encodeURIComponent(barcode)}`);
        if (!res.ok) {
            playBeep('error');
            alert(`Produk dengan barcode [${barcode}] belum terdaftar di master data!`);
            return;
        }

        const product = await res.json();
        pendingProduct = product;

        // Tampilkan Detail Produk
        document.getElementById('prodName').innerText = product.name;
        document.getElementById('prodBarcodeSku').innerText = `Barcode: ${product.barcode} | SKU: ${product.sku}`;
        document.getElementById('inputQty').value = 1;
        document.getElementById('btnAddItemToList').disabled = false;
        document.getElementById('productStatusHint').innerText = 'Tentukan Qty dan Kondisi:';

        // Auto focus ke input Qty
        const inputQty = document.getElementById('inputQty');
        inputQty.focus();
        inputQty.select();

    } catch (err) {
        playBeep('error');
        console.error(err);
    }
}

// Event Listeners: Qty Plus Minus
document.getElementById('btnQtyPlus').addEventListener('click', () => {
    const input = document.getElementById('inputQty');
    input.value = parseInt(input.value || 0) + 1;
});

document.getElementById('btnQtyMinus').addEventListener('click', () => {
    const input = document.getElementById('inputQty');
    if (parseInt(input.value) > 1) input.value = parseInt(input.value) - 1;
});

// Event Listener: Radio Kondisi Good / Rusak
document.querySelectorAll('input[name="itemCondition"]').forEach(radio => {
    radio.addEventListener('change', (e) => {
        const damageWrapper = document.getElementById('damageReasonWrapper');
        if (e.target.value === 'RUSAK') {
            damageWrapper.classList.remove('hidden');
            document.getElementById('damageReasonInput').focus();
        } else {
            damageWrapper.classList.add('hidden');
        }
    });
});

// Tambahkan Produk ke Tabel Sesi (Multi-Product)
document.getElementById('btnAddItemToList').addEventListener('click', () => {
    if (!pendingProduct) return;

    const qty = parseInt(document.getElementById('inputQty').value, 10) || 1;
    const condition = document.querySelector('input[name="itemCondition"]:checked').value;
    const damageReason = document.getElementById('damageReasonInput').value;

    scannedProductsList.push({
        barcode: pendingProduct.barcode,
        sku: pendingProduct.sku,
        product_name: pendingProduct.name,
        qty: qty,
        condition: condition,
        damage_reason: damageReason
    });

    renderItemsTable();
    playBeep('success');

    // Reset Form Input Produk untuk scan berikutnya
    pendingProduct = null;
    document.getElementById('prodName').innerText = '-';
    document.getElementById('prodBarcodeSku').innerText = 'Barcode: - | SKU: -';
    document.getElementById('inputQty').value = 1;
    document.getElementById('damageReasonInput').value = '';
    document.getElementById('btnAddItemToList').disabled = true;
    document.getElementById('productStatusHint').innerText = 'Scan produk berikutnya...';

    // Kembalikan fokus ke scan input
    document.getElementById('manualBarcodeInput').focus();
});

// Render Ulang Tabel Item
function renderItemsTable() {
    const tbody = document.getElementById('itemsTableBody');
    tbody.innerHTML = '';

    if (scannedProductsList.length === 0) {
        tbody.innerHTML = `
            <tr id="emptyTablePlaceholder">
                <td colspan="5" class="text-center py-8 text-slate-400 italic">
                    Belum ada produk yang dimasukkan untuk invoice ini.
                </td>
            </tr>`;
        document.getElementById('btnSubmitSession').disabled = true;
        document.getElementById('totalItemsBadge').innerText = '0';
        document.getElementById('summaryGoodCount').innerText = '0';
        document.getElementById('summaryDamagedCount').innerText = '0';
        return;
    }

    let goodCount = 0;
    let damagedCount = 0;

    scannedProductsList.forEach((item, index) => {
        if (item.condition === 'GOOD') goodCount += item.qty;
        else damagedCount += item.qty;

        const isGood = item.condition === 'GOOD';
        const badge = isGood 
            ? `<span class="bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded font-bold text-[10px]">GOOD</span>`
            : `<span class="bg-rose-100 text-rose-700 px-2 py-0.5 rounded font-bold text-[10px]">RUSAK</span>`;

        const row = document.createElement('tr');
        row.className = 'hover:bg-slate-50';
        row.innerHTML = `
            <td class="p-2.5">
                <div class="font-bold text-slate-800">${item.product_name}</div>
                <div class="text-[10px] text-slate-400 font-mono">${item.barcode}</div>
            </td>
            <td class="p-2.5 text-center font-bold font-mono">${item.qty}</td>
            <td class="p-2.5 text-center">${badge}</td>
            <td class="p-2.5 text-slate-500">${item.damage_reason || '-'}</td>
            <td class="p-2.5 text-center">
                <button onclick="removeItem(${index})" class="text-rose-500 hover:text-rose-700 p-1">
                    <i class="fa-solid fa-trash-can"></i>
                </button>
            </td>
        `;
        tbody.appendChild(row);
    });

    document.getElementById('totalItemsBadge').innerText = scannedProductsList.length;
    document.getElementById('summaryGoodCount').innerText = goodCount;
    document.getElementById('summaryDamagedCount').innerText = damagedCount;
    document.getElementById('btnSubmitSession').disabled = false;
}

window.removeItem = function(index) {
    scannedProductsList.splice(index, 1);
    renderItemsTable();
};

// Finalisasi / Submit Sesi Inbound Return
document.getElementById('btnSubmitSession').addEventListener('click', async () => {
    if (!activeInvoice || scannedProductsList.length === 0) return;

    const payload = {
        invoice_number: activeInvoice,
        operator_name: 'Gudang 01',
        customer_name: 'Pelanggan Return',
        notes: document.getElementById('sessionNotesInput').value,
        items: scannedProductsList
    };

    try {
        const res = await fetch('/api/returns', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const result = await res.json();

        if (result.success) {
            document.getElementById('modalSuccessDesc').innerText = `Invoice ${activeInvoice} berhasil disimpan dengan ${scannedProductsList.length} jenis produk (${result.session_id}).`;
            document.getElementById('successModal').classList.remove('hidden');
        }
    } catch (err) {
        alert("Gagal menyimpan data: " + err.message);
    }
});

// Reset untuk Invoice Baru
function resetAll() {
    activeInvoice = null;
    scannedProductsList = [];
    pendingProduct = null;
    currentScannerTarget = 'INVOICE';

    document.getElementById('displayInvoice').innerText = 'BELUM SCAN INVOICE';
    document.getElementById('invoiceBadge').classList.add('hidden');
    document.getElementById('currentScanTarget').innerText = 'Scan Invoice';
    document.getElementById('scanModeIndicator').innerText = 'LANGKAH 1: SCAN INVOICE';
    document.getElementById('sessionNotesInput').value = '';
    document.getElementById('successModal').classList.add('hidden');
    renderItemsTable();
}

document.getElementById('btnModalNextInvoice').addEventListener('click', resetAll);
document.getElementById('btnResetAll').addEventListener('click', () => {
    if (confirm("Reset sesi inbound saat ini?")) resetAll();
});

// Barcode Gun Manual Input / Enter
document.getElementById('btnProcessManual').addEventListener('click', () => {
    const input = document.getElementById('manualBarcodeInput');
    const val = input.value.trim();
    if (val) {
        onScanSuccess(val);
        input.value = '';
    }
});
document.getElementById('manualBarcodeInput').addEventListener('keypress', (e) => {
    if (e.key === 'Enter') {
        document.getElementById('btnProcessManual').click();
    }
});

// Jalankan auto kamera saat halaman dibuka
window.addEventListener('DOMContentLoaded', startAutoCamera);
