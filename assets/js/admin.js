// State Management & Instances
let ratioChartInstance = null;
let currentTab = 'dashboard';
let cachedProducts = [];

// Global Loading Overlay Controls (Bola-bola Merah, Kuning, Hijau)
window.showGlobalLoading = function(title = 'Memuat Data...', desc = 'Mohon tunggu sebentar, sistem sedang memproses data.') {
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

// Sidebar Mobile Toggle
const sidebar = document.getElementById('sidebar');
const backdrop = document.getElementById('sidebarBackdrop');
const btnOpen = document.getElementById('btnOpenSidebar');
const btnClose = document.getElementById('btnCloseSidebar');

if (btnOpen) {
    btnOpen.addEventListener('click', () => {
        sidebar.classList.remove('-translate-x-full');
        backdrop.classList.remove('hidden');
    });
}

function closeMobileSidebar() {
    sidebar.classList.add('-translate-x-full');
    backdrop.classList.add('hidden');
}

if (btnClose) btnClose.addEventListener('click', closeMobileSidebar);
if (backdrop) backdrop.addEventListener('click', closeMobileSidebar);

// Switch Tabs
window.switchTab = function(tabName) {
    currentTab = tabName;

    // Reset styles navigasi
    document.querySelectorAll('.nav-item').forEach(el => {
        el.classList.remove('text-white', 'bg-indigo-600', 'shadow-sm', 'shadow-indigo-600/30');
        el.classList.add('text-slate-400', 'hover:text-white', 'hover:bg-slate-800/80');
        const icon = el.querySelector('i');
        if (icon) icon.classList.remove('text-indigo-200');
    });

    const activeNav = document.getElementById(`nav-${tabName}`);
    if (activeNav) {
        activeNav.classList.remove('text-slate-400', 'hover:text-white', 'hover:bg-slate-800/80');
        activeNav.classList.add('text-white', 'bg-indigo-600', 'shadow-sm', 'shadow-indigo-600/30');
        const icon = activeNav.querySelector('i');
        if (icon) icon.classList.add('text-indigo-200');
    }

    // Toggle Tab Content
    document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
    const targetContent = document.getElementById(`tab-${tabName}`);
    if (targetContent) targetContent.classList.remove('hidden');

    // Update Title
    const titleEl = document.getElementById('currentViewTitle');
    if (tabName === 'dashboard') titleEl.innerText = 'Dashboard Monitoring Retur';
    else if (tabName === 'transactions') titleEl.innerText = 'Riwayat Transaksi Inbound';
    else if (tabName === 'products') titleEl.innerText = 'Master Data Produk & Barcode';
    else if (tabName === 'expeditions') titleEl.innerText = 'Master Data Ekspedisi & Kurir';

    // Auto close sidebar on mobile after click
    if (window.innerWidth < 1024) closeMobileSidebar();

    // Trigger tab-specific refresh if needed
    if (tabName === 'products') loadProducts();
    if (tabName === 'transactions') loadTransactions();
    if (tabName === 'expeditions') loadExpeditions();
};

// 1. Load Metrics KPI
async function loadMetrics() {
    try {
        const res = await fetch('api/admin/metrics');
        const data = await res.json();

        document.getElementById('kpiTotalInvoice').innerText = data.total_invoices || 0;
        document.getElementById('kpiTotalItems').innerText = data.total_items || 0;
        document.getElementById('kpiTotalGood').innerText = data.total_good || 0;
        document.getElementById('kpiTotalDamaged').innerText = data.total_damaged || 0;

        renderChart(data.total_good || 0, data.total_damaged || 0);
    } catch (err) {
        console.error("Gagal memuat metrics:", err);
    }
}

// Render Doughnut Chart
function renderChart(good, damaged) {
    const canvas = document.getElementById('ratioChart');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    if (ratioChartInstance) ratioChartInstance.destroy();

    const total = good + damaged;
    const dataVals = total === 0 ? [1, 0] : [good, damaged];
    const bgColors = total === 0 ? ['#cbd5e1', '#e2e8f0'] : ['#10b981', '#f43f5e'];

    ratioChartInstance = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Good (Restock)', 'Rusak (Defect)'],
            datasets: [{
                data: dataVals,
                backgroundColor: bgColors,
                borderWidth: 0,
                hoverOffset: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: {
                legend: { 
                    position: 'bottom',
                    labels: {
                        boxWidth: 12,
                        padding: 15,
                        font: { size: 11, family: 'sans-serif' }
                    }
                }
            }
        }
    });
}

let cachedTransactions = [];

// 2. Load Transaksi (Full & Preview)
async function loadTransactions() {
    const searchInput = document.getElementById('filterSearch');
    const dateInput = document.getElementById('filterDate');
    const search = searchInput ? searchInput.value : '';
    const date = dateInput ? dateInput.value : '';
    
    let url = `api/admin/transactions.php?`;
    if (search) url += `search=${encodeURIComponent(search)}&`;
    if (date) url += `date=${encodeURIComponent(date)}&`;

    try {
        const res = await fetch(url);
        const rows = await res.json();
        cachedTransactions = Array.isArray(rows) ? rows : [];
        
        // Render di tabel transaksi penuh
        const tbody = document.getElementById('transactionsTableBody');
        if (tbody) {
            tbody.innerHTML = '';
            if (!rows || rows.length === 0) {
                tbody.innerHTML = `<tr><td colspan="9" class="text-center py-8 text-slate-400">Tidak ada riwayat transaksi ditemukan.</td></tr>`;
            } else {
                rows.forEach(r => tbody.appendChild(createTransactionRow(r)));
            }
        }

        // Render di tabel preview (5 teratas) pada tab dashboard
        const previewTbody = document.getElementById('previewTransactionsTableBody');
        if (previewTbody) {
            previewTbody.innerHTML = '';
            if (!rows || rows.length === 0) {
                previewTbody.innerHTML = `<tr><td colspan="9" class="text-center py-6 text-slate-400">Belum ada transaksi retur hari ini.</td></tr>`;
            } else {
                rows.slice(0, 5).forEach(r => previewTbody.appendChild(createTransactionRow(r)));
            }
        }

    } catch (err) {
        console.error("Gagal load transaksi:", err);
    }
}

function createTransactionRow(r) {
    const tr = document.createElement('tr');
    tr.className = 'hover:bg-slate-50 transition border-b border-slate-100';

    const expBadge = r.expedition ? 
        `<span class="bg-indigo-50 text-indigo-700 border border-indigo-200 px-2 py-0.5 rounded-md font-semibold text-[11px] inline-flex items-center gap-1">
            <i class="fa-solid fa-truck-fast text-[10px]"></i> ${r.expedition}
         </span>` : 
        `<span class="text-slate-400 italic text-[11px]">-</span>`;

    const hasVideo = Boolean(r.video_path && r.video_path.trim() !== '');
    const actionBtn = hasVideo ? 
        `<button onclick="viewDetails(${r.id})" class="bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 px-2.5 py-1.5 rounded-xl font-bold text-[11px] transition inline-flex items-center gap-1.5 shadow-xs">
            <i class="fa-solid fa-circle-play text-rose-600"></i> Video & Detail
         </button>` :
        `<button onclick="viewDetails(${r.id})" class="bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 px-2.5 py-1.5 rounded-xl font-semibold text-[11px] transition inline-flex items-center gap-1">
            <i class="fa-solid fa-eye text-slate-500"></i> Detail
         </button>`;

    tr.innerHTML = `
        <td class="p-3 text-slate-500 font-mono text-[11px]">${new Date(r.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</td>
        <td class="p-3 font-mono font-bold text-indigo-700">${r.invoice_number}</td>
        <td class="p-3">${expBadge}</td>
        <td class="p-3 font-semibold text-slate-700">${r.operator_name}</td>
        <td class="p-3 text-center font-bold text-slate-800">${r.total_items}</td>
        <td class="p-3 text-center text-emerald-600 font-bold">${r.total_good}</td>
        <td class="p-3 text-center text-rose-600 font-bold">${r.total_damaged}</td>
        <td class="p-3 text-slate-600 truncate max-w-xs text-xs">${r.items_summary || '-'}</td>
        <td class="p-3 text-center">${actionBtn}</td>
    `;
    return tr;
}

// 2b. View Details & Video Player Modal
window.viewDetails = async function(id) {
    const r = cachedTransactions.find(t => t.id == id);
    if (!r) return;

    const modal = document.getElementById('transactionDetailModal');
    if (!modal) return;

    document.getElementById('modalDetailInvoice').innerText = r.invoice_number;
    document.getElementById('modalDetailExpedition').innerText = r.expedition || 'Reguler';
    document.getElementById('modalDetailMeta').innerText = `Operator: ${r.operator_name} • ${new Date(r.created_at).toLocaleString('id-ID')}`;
    document.getElementById('modalTotalUnit').innerText = r.total_items || 0;
    document.getElementById('modalTotalGood').innerText = r.total_good || 0;
    document.getElementById('modalTotalDamaged').innerText = r.total_damaged || 0;
    document.getElementById('modalNotes').innerText = r.notes || 'Tidak ada catatan.';

    // Setup Video Player
    const videoPlayer = document.getElementById('modalVideoPlayer');
    const noVideoNotice = document.getElementById('modalNoVideo');
    const videoBadge = document.getElementById('modalVideoStatusBadge');
    const videoFilename = document.getElementById('modalVideoFilename');
    const downloadBtn = document.getElementById('modalDownloadVideoBtn');

    if (r.video_path && r.video_path.trim() !== '') {
        videoPlayer.src = r.video_path;
        videoPlayer.classList.remove('hidden');
        noVideoNotice.classList.add('hidden');
        videoBadge.className = "text-[10px] font-semibold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200";
        videoBadge.innerHTML = `<i class="fa-solid fa-circle-check text-emerald-500"></i> Rekaman Tersedia`;
        videoFilename.innerText = r.video_path.split('/').pop();
        downloadBtn.href = r.video_path;
        downloadBtn.classList.remove('hidden');
        videoPlayer.load();
    } else {
        videoPlayer.pause();
        videoPlayer.src = '';
        videoPlayer.classList.add('hidden');
        noVideoNotice.classList.remove('hidden');
        videoBadge.className = "text-[10px] font-semibold text-amber-600 bg-amber-50 px-2 py-0.5 rounded border border-amber-200";
        videoBadge.innerHTML = `<i class="fa-solid fa-circle-exclamation text-amber-500"></i> Tanpa Video`;
        videoFilename.innerText = 'Tidak ada file rekaman';
        downloadBtn.classList.add('hidden');
    }

    // Load Items List
    const tbody = document.getElementById('modalItemsTableBody');
    tbody.innerHTML = `<tr><td colspan="5" class="text-center py-4 text-slate-400"><i class="fa-solid fa-spinner fa-spin mr-1"></i> Memuat detail item...</td></tr>`;

    modal.classList.remove('hidden');

    try {
        const res = await fetch(`api/admin/session_items.php?session_id=${id}`);
        const items = await res.json();
        tbody.innerHTML = '';
        if (!items || items.length === 0) {
            tbody.innerHTML = `<tr><td colspan="5" class="text-center py-4 text-slate-400">Tidak ada rincian produk untuk invoice ini.</td></tr>`;
            document.getElementById('modalItemCount').innerText = 0;
            return;
        }

        document.getElementById('modalItemCount').innerText = items.length;
        items.forEach(it => {
            const tr = document.createElement('tr');
            tr.className = 'hover:bg-slate-50 border-b border-slate-100 text-xs';
            const isGood = (it.type || it.condition) === 'GOOD';
            const badgeCond = isGood ? 
                `<span class="bg-emerald-50 text-emerald-700 font-semibold px-2 py-0.5 rounded text-[10px] border border-emerald-200">GOOD</span>` :
                `<span class="bg-rose-50 text-rose-700 font-semibold px-2 py-0.5 rounded text-[10px] border border-rose-200">${it.type || 'RUSAK'}</span>`;

            tr.innerHTML = `
                <td class="p-2.5 font-mono font-bold text-slate-700">${it.barcode}</td>
                <td class="p-2.5 font-medium text-slate-800">${it.product_name || '-'}</td>
                <td class="p-2.5 text-slate-500 font-mono text-[11px]">${it.batch_no || '-'} / ${it.exp_date || '-'}</td>
                <td class="p-2.5 text-center font-bold text-slate-800">${it.qty}</td>
                <td class="p-2.5 text-center">${badgeCond}</td>
            `;
            tbody.appendChild(tr);
        });
    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="5" class="text-center py-4 text-rose-500 font-semibold">Gagal memuat item: ${e.message}</td></tr>`;
    }
};

window.closeDetailModal = function() {
    const modal = document.getElementById('transactionDetailModal');
    if (modal) modal.classList.add('hidden');
    const videoPlayer = document.getElementById('modalVideoPlayer');
    if (videoPlayer) {
        videoPlayer.pause();
        videoPlayer.src = '';
    }
};

// 3. Load Master Produk
async function loadProducts() {
    try {
        const res = await fetch('api/products.php');
        cachedProducts = await res.json();

        // Render Quick Products Table (di Dashboard)
        const quickTbody = document.getElementById('quickProductsTableBody');
        if (quickTbody) {
            quickTbody.innerHTML = '';
            cachedProducts.slice(0, 5).forEach(p => {
                const tr = document.createElement('tr');
                tr.className = 'hover:bg-slate-50';
                tr.innerHTML = `
                    <td class="p-2.5 font-mono font-bold text-indigo-700">${p.barcode}</td>
                    <td class="p-2.5 font-mono text-slate-500">${p.seller_sku || p.sku}</td>
                    <td class="p-2.5 font-semibold text-slate-800">${p.name}</td>
                    <td class="p-2.5 text-slate-500">${p.shop || p.category}</td>
                `;
                quickTbody.appendChild(tr);
            });
        }

        // Populate dropdown filter toko
        populateShopDropdown(cachedProducts);

        // Render Full Products Table
        renderFullProductsTable(cachedProducts);

    } catch (err) {
        console.error("Gagal load produk:", err);
    }
}

// Mengisi pilihan Toko / Shop di dropdown filter
function populateShopDropdown(products) {
    const select = document.getElementById('filterProductShop');
    if (!select) return;
    const currentVal = select.value;
    const shops = Array.from(new Set(products.map(p => (p.shop || p.category || '').trim()).filter(Boolean))).sort();

    select.innerHTML = '<option value="">Semua Toko / Shop (' + products.length + ')</option>';
    shops.forEach(s => {
        const count = products.filter(p => (p.shop || p.category || '').trim() === s).length;
        const opt = document.createElement('option');
        opt.value = s;
        opt.innerText = `${s} (${count})`;
        if (s === currentVal) opt.selected = true;
        select.appendChild(opt);
    });
}

function renderFullProductsTable(products) {
    const fullTbody = document.getElementById('fullProductsTableBody');
    if (!fullTbody) return;
    fullTbody.innerHTML = '';

    if (!products || products.length === 0) {
        fullTbody.innerHTML = `<tr><td colspan="8" class="text-center py-8 text-slate-400">Tidak ada produk yang cocok dengan pencarian / filter toko.</td></tr>`;
        return;
    }

    products.forEach((p, idx) => {
        const tr = document.createElement('tr');
        tr.className = 'hover:bg-slate-50 border-b border-slate-100 transition';
        const sellerSku = p.seller_sku || p.sku || '-';
        const shop = p.shop || p.category || '-';
        const binCode = p.bin_code 
            ? `<span class="bg-amber-50 text-amber-800 border border-amber-200 px-2 py-0.5 rounded font-mono font-bold text-[11px]">${p.bin_code}</span>` 
            : `<span class="text-slate-400 italic text-[11px]">-</span>`;
        const barcode = p.barcode 
            ? `<span class="font-mono font-bold text-slate-800">${p.barcode}</span>` 
            : `<span class="text-slate-400 italic text-[11px]">-</span>`;
        const barcodeBpom = p.barcode_bpom 
            ? `<span class="font-mono text-[11px] text-slate-600">${p.barcode_bpom}</span>` 
            : `<span class="text-slate-400 italic text-[11px]">-</span>`;
        const sapCode = (p.sap_code && p.sap_code !== '0') 
            ? `<span class="bg-indigo-50 text-indigo-700 px-2 py-0.5 rounded font-mono font-bold text-[11px]">${p.sap_code}</span>` 
            : `<span class="text-slate-400 italic text-[11px]">-</span>`;

        tr.innerHTML = `
            <td class="p-3 text-slate-400 font-mono text-xs text-center">${idx + 1}</td>
            <td class="p-3 font-mono font-bold text-indigo-700">${sellerSku}</td>
            <td class="p-3">
                <span class="bg-purple-50 text-purple-700 border border-purple-200 px-2 py-0.5 rounded-md font-semibold text-[11px]">${shop}</span>
            </td>
            <td class="p-3 text-center">${binCode}</td>
            <td class="p-3 font-mono text-slate-700">${barcode}</td>
            <td class="p-3 font-mono text-slate-500">${barcodeBpom}</td>
            <td class="p-3">${sapCode}</td>
            <td class="p-3 font-semibold text-slate-800">${p.name}</td>
        `;
        fullTbody.appendChild(tr);
    });
}

// Sinkronisasi data dari OCS WMS IEG System dengan Animasi Bola Merah Kuning Hijau
window.syncProductsFromOCS = async function() {
    const btn = document.getElementById('btnSyncOcs');
    const icon = document.getElementById('syncOcsIcon');
    if (btn) btn.disabled = true;
    if (icon) icon.classList.add('fa-spin');

    // Tampilkan overlay bola-bola animasi
    showGlobalLoading(
        'Sinkronisasi Master Produk dari OCS IEG...',
        'Sedang menghubungkan ke https://ocs.iegsystem.id/ untuk menarik data 700+ Master Produk, Barcode, dan Rak...'
    );

    try {
        const res = await fetch('api/sync_ocs.php');
        const data = await res.json();
        if (data.success) {
            await loadProducts();
            hideGlobalLoading();
            alert(`Berhasil sinkronisasi!\nTotal ${data.total_synced || 0} produk dan rak dari OCS WMS berhasil diperbarui ke database.`);
        } else {
            hideGlobalLoading();
            alert('Sinkronisasi gagal: ' + (data.error || 'Terjadi kesalahan sistem'));
        }
    } catch (err) {
        hideGlobalLoading();
        alert('Gagal menghubungi server sync: ' + err.message);
    } finally {
        hideGlobalLoading();
        if (btn) btn.disabled = false;
        if (icon) icon.classList.remove('fa-spin');
    }
};

// Filter produk berdasarkan Keyword & Pilihan Toko / Shop
window.filterProductTable = function() {
    const q = (document.getElementById('filterProductSearch')?.value || '').toLowerCase().trim();
    const selectedShop = (document.getElementById('filterProductShop')?.value || '').toLowerCase().trim();

    const filtered = cachedProducts.filter(p => {
        const pShop = (p.shop || p.category || '').toLowerCase().trim();
        const matchShop = !selectedShop || pShop === selectedShop;

        const matchQuery = !q || (
            (p.name && p.name.toLowerCase().includes(q)) || 
            (p.barcode && p.barcode.toLowerCase().includes(q)) || 
            (p.barcode_bpom && p.barcode_bpom.toLowerCase().includes(q)) || 
            (p.sku && p.sku.toLowerCase().includes(q)) ||
            (p.seller_sku && p.seller_sku.toLowerCase().includes(q)) ||
            (p.sap_code && p.sap_code.toLowerCase().includes(q)) ||
            (p.shop && p.shop.toLowerCase().includes(q)) ||
            (p.bin_code && p.bin_code.toLowerCase().includes(q)) ||
            (p.category && p.category.toLowerCase().includes(q))
        );

        return matchShop && matchQuery;
    });

    renderFullProductsTable(filtered);
};

// Modal Tambah Produk Baru
window.openAddProductModal = function() {
    document.getElementById('addProductModal').classList.remove('hidden');
    document.getElementById('newBarcode').focus();
};

window.closeAddProductModal = function() {
    document.getElementById('addProductModal').classList.add('hidden');
    document.getElementById('formAddProduct').reset();
};

window.submitNewProduct = async function(e) {
    e.preventDefault();
    const barcode = document.getElementById('newBarcode').value.trim();
    const name = document.getElementById('newName').value.trim();
    const sku = document.getElementById('newSku').value.trim();
    const unit = document.getElementById('newUnit').value;
    const category = document.getElementById('newCategory').value.trim() || 'Umum';

    const btn = document.getElementById('btnSaveProduct');
    btn.disabled = true;
    btn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> Menyimpan...`;

    try {
        const res = await fetch('api/products.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ barcode, name, sku, unit, category })
        });
        const result = await res.json();

        if (result.success) {
            alert(`Produk "${name}" berhasil ditambahkan ke database Laragon!`);
            closeAddProductModal();
            loadProducts();
        } else {
            alert("Gagal: " + (result.error || 'Terjadi kesalahan'));
        }
    } catch (err) {
        alert("Gagal koneksi ke server: " + err.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = `<i class="fa-solid fa-save"></i> Simpan ke MySQL`;
    }
};

// Detail Invoice Modal
window.viewDetails = async function(sessionId, invoiceNumber, expedition = '') {
    try {
        const res = await fetch(`api/admin/session/${sessionId}/items`);
        const items = await res.json();

        document.getElementById('modalInvoiceTitle').innerText = `Detail Invoice: ${invoiceNumber}`;
        const expLabel = expedition ? ` &bull; Ekspedisi: ${expedition}` : '';
        document.getElementById('modalInvoiceSubtitle').innerText = `Total ${items.length} item terdaftar dalam sesi ini${expLabel}`;

        const tbody = document.getElementById('modalItemsBody');
        tbody.innerHTML = '';

        items.forEach(it => {
            const itemType = it.type || it.condition || 'GOOD';
            let badge = `<span class="bg-emerald-100 text-emerald-800 px-2 py-0.5 rounded font-bold text-[10px]">GOOD</span>`;
            if (itemType === 'RUSAK') {
                badge = `<span class="bg-rose-100 text-rose-800 px-2 py-0.5 rounded font-bold text-[10px]">RUSAK</span>`;
            } else if (itemType === 'EXPIRED') {
                badge = `<span class="bg-amber-100 text-amber-800 px-2 py-0.5 rounded font-bold text-[10px]">EXPIRED</span>`;
            } else if (itemType === 'SALAH_KIRIM') {
                badge = `<span class="bg-purple-100 text-purple-800 px-2 py-0.5 rounded font-bold text-[10px]">SALAH KIRIM</span>`;
            }

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="p-2.5 font-mono text-slate-600">${it.barcode}</td>
                <td class="p-2.5 font-bold text-slate-800">${it.product_name}</td>
                <td class="p-2.5 font-mono text-slate-600">${it.batch_no || '-'}</td>
                <td class="p-2.5 font-mono text-slate-600">${it.exp_date || '-'}</td>
                <td class="p-2.5 text-center font-bold">${it.qty}</td>
                <td class="p-2.5 text-center">${badge}</td>
            `;
            tbody.appendChild(tr);
        });

        document.getElementById('detailModal').classList.remove('hidden');
    } catch (err) {
        alert("Gagal membuka detail item: " + err.message);
    }
};

const btnCloseModal = document.getElementById('btnCloseModal');
if (btnCloseModal) {
    btnCloseModal.addEventListener('click', () => {
        document.getElementById('detailModal').classList.add('hidden');
    });
}

// Filter Transaksi
const btnApply = document.getElementById('btnApplyFilter');
if (btnApply) btnApply.addEventListener('click', loadTransactions);

// Export CSV
const btnCsv = document.getElementById('btnExportCsv');
if (btnCsv) {
    btnCsv.addEventListener('click', async () => {
        const res = await fetch('api/admin/transactions');
        const rows = await res.json();
        if (!rows || !rows.length) return alert("Tidak ada data transaksi untuk diekspor");

        let csv = "ID,Tanggal,Invoice,Ekspedisi,Operator,Total Unit,Total Good,Total Rusak,Ringkasan Item\n";
        rows.forEach(r => {
            csv += `"${r.id}","${r.created_at}","${r.invoice_number}","${r.expedition || '-'}","${r.operator_name}","${r.total_items}","${r.total_good}","${r.total_damaged}","${r.items_summary || ''}"\n`;
        });

        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `Inbound_Return_Report_${new Date().toISOString().slice(0,10)}.csv`;
        a.click();
    });
}

// 4. MASTER EKSPEDISI CRUD
let cachedExpeditions = [];

async function loadExpeditions() {
    try {
        const res = await fetch('api/expeditions.php');
        cachedExpeditions = await res.json();
        renderExpeditionsTable(cachedExpeditions);
    } catch (err) {
        console.error("Gagal load ekspedisi:", err);
    }
}

function renderExpeditionsTable(list) {
    const tbody = document.getElementById('fullExpeditionsTableBody');
    if (!tbody) return;
    tbody.innerHTML = '';

    if (!list || list.length === 0) {
        tbody.innerHTML = `<tr><td colspan="6" class="text-center py-8 text-slate-400">Tidak ada data ekspedisi ditemukan.</td></tr>`;
        return;
    }

    list.forEach((item, index) => {
        const statusBadge = item.status === 'ACTIVE' 
            ? `<span class="bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded-full font-bold text-[10px]">Aktif</span>`
            : `<span class="bg-slate-200 text-slate-600 px-2 py-0.5 rounded-full font-bold text-[10px]">Nonaktif</span>`;

        const prefixBadge = item.prefix_pattern 
            ? `<div class="flex flex-wrap gap-1">${item.prefix_pattern.split(',').map(p => `<span class="bg-indigo-50 text-indigo-700 border border-indigo-200 px-1.5 py-0.5 rounded font-mono font-bold text-[10px]">${p.trim()}</span>`).join('')}</div>`
            : `<span class="text-slate-400 italic text-[11px]">-</span>`;

        const tr = document.createElement('tr');
        tr.className = 'hover:bg-slate-50 border-b border-slate-100 transition';
        tr.innerHTML = `
            <td class="p-3 text-slate-400 font-mono text-xs">${index + 1}</td>
            <td class="p-3 font-mono font-bold text-indigo-700 uppercase">${item.code}</td>
            <td class="p-3 font-bold text-slate-800 flex items-center gap-1.5">
                <i class="fa-solid fa-truck-fast text-slate-400"></i>
                <span>${item.name}</span>
            </td>
            <td class="p-3">${prefixBadge}</td>
            <td class="p-3 text-center">${statusBadge}</td>
            <td class="p-3 text-center">
                <div class="flex items-center justify-center space-x-1.5">
                    <button onclick="editExpedition(${item.id})" title="Edit Ekspedisi" class="bg-indigo-50 hover:bg-indigo-100 text-indigo-700 px-2 py-1 rounded-lg text-xs font-semibold transition">
                        <i class="fa-solid fa-pen-to-square"></i> Edit
                    </button>
                    <button onclick="deleteExpedition(${item.id}, '${item.name}')" title="Hapus Ekspedisi" class="bg-rose-50 hover:bg-rose-100 text-rose-600 px-2 py-1 rounded-lg text-xs font-semibold transition">
                        <i class="fa-solid fa-trash-can"></i> Hapus
                    </button>
                </div>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

window.filterExpeditionTable = function() {
    const q = (document.getElementById('filterExpeditionSearch').value || '').toLowerCase();
    const filtered = cachedExpeditions.filter(item => 
        item.name.toLowerCase().includes(q) || 
        item.code.toLowerCase().includes(q)
    );
    renderExpeditionsTable(filtered);
};

window.openAddExpeditionModal = function() {
    document.getElementById('expeditionModalTitle').innerText = 'Tambah Ekspedisi Baru';
    document.getElementById('expeditionModalSubtitle').innerText = 'Simpan data armada/kurir ke database MySQL';
    document.getElementById('formExpedition').reset();
    document.getElementById('expeditionId').value = '';
    document.getElementById('expeditionPrefix').value = '';
    document.getElementById('expeditionModal').classList.remove('hidden');
    document.getElementById('expeditionCode').focus();
};

window.closeExpeditionModal = function() {
    document.getElementById('expeditionModal').classList.add('hidden');
    document.getElementById('formExpedition').reset();
};

window.editExpedition = function(id) {
    const item = cachedExpeditions.find(x => x.id == id);
    if (!item) return;

    document.getElementById('expeditionModalTitle').innerText = 'Edit Data Ekspedisi';
    document.getElementById('expeditionModalSubtitle').innerText = `Perbarui rincian untuk ${item.name}`;
    document.getElementById('expeditionId').value = item.id;
    document.getElementById('expeditionCode').value = item.code;
    document.getElementById('expeditionName').value = item.name;
    document.getElementById('expeditionPrefix').value = item.prefix_pattern || '';
    document.getElementById('expeditionStatus').value = item.status;
    document.getElementById('expeditionModal').classList.remove('hidden');
    document.getElementById('expeditionName').focus();
};

window.submitExpedition = async function(e) {
    e.preventDefault();
    const id = document.getElementById('expeditionId').value;
    const code = document.getElementById('expeditionCode').value.trim();
    const name = document.getElementById('expeditionName').value.trim();
    const prefix_pattern = document.getElementById('expeditionPrefix').value.trim();
    const status = document.getElementById('expeditionStatus').value;

    const btn = document.getElementById('btnSaveExpedition');
    btn.disabled = true;
    btn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> Menyimpan...`;

    try {
        const payload = { id, code, name, prefix_pattern, status };
        if (id) payload.action = 'update';

        const res = await fetch('api/expeditions.php', {
            method: id ? 'PUT' : 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        const data = await res.json();
        if (data.success) {
            closeExpeditionModal();
            loadExpeditions();
        } else {
            alert("Gagal: " + (data.error || 'Terjadi kesalahan'));
        }
    } catch (err) {
        alert("Gagal koneksi ke server: " + err.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = `<i class="fa-solid fa-save"></i> Simpan Ekspedisi`;
    }
};

window.deleteExpedition = async function(id, name) {
    if (!confirm(`Yakin ingin menghapus ekspedisi "${name}" dari master data?`)) return;

    try {
        const res = await fetch(`api/expeditions.php?action=delete&id=${id}`, {
            method: 'POST'
        });
        const data = await res.json();
        if (data.success) {
            loadExpeditions();
        } else {
            alert("Gagal: " + (data.error || 'Tidak dapat menghapus'));
        }
    } catch (err) {
        alert("Gagal koneksi ke server: " + err.message);
    }
};

// Refresh All Data function
window.refreshAllData = function() {
    const icon = document.getElementById('refreshIcon');
    if (icon) icon.classList.add('fa-spin');

    const promises = [loadMetrics(), loadTransactions(), loadProducts(), loadExpeditions(), loadUsers()];
    if (document.getElementById('tab-maintenance')) {
        promises.push(loadMaintenanceStatus());
    }

    Promise.all(promises).then(() => {
        setTimeout(() => {
            if (icon) icon.classList.remove('fa-spin');
        }, 600);
    });
};

// -------------------------------------------------------------
// 5. USER MANAGEMENT (CRUD)
// -------------------------------------------------------------
let cachedUsers = [];

async function loadUsers() {
    try {
        const res = await fetch('api/users.php');
        if (res.status === 401) {
            window.location.href = 'login.php';
            return;
        }
        cachedUsers = await res.json();
        renderUsersTable(cachedUsers);
    } catch (err) {
        console.error("Gagal load users:", err);
    }
}

function renderUsersTable(list) {
    const tbody = document.getElementById('fullUsersTableBody');
    if (!tbody) return;
    tbody.innerHTML = '';

    if (!list || list.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" class="text-center py-8 text-slate-400">Tidak ada pengguna ditemukan.</td></tr>`;
        return;
    }

    list.forEach((u, idx) => {
        let roleBadge = `<span class="bg-emerald-50 text-emerald-700 border border-emerald-200 px-2 py-0.5 rounded font-mono font-bold text-[10px]">OPERATOR</span>`;
        if (u.role === 'admin') {
            roleBadge = `<span class="bg-indigo-50 text-indigo-700 border border-indigo-200 px-2 py-0.5 rounded font-mono font-bold text-[10px]">ADMIN</span>`;
        } else if (u.role === 'superadmin') {
            roleBadge = `<span class="bg-amber-50 text-amber-700 border border-amber-200 px-2 py-0.5 rounded font-mono font-bold text-[10px]"><i class="fa-solid fa-shield-halved text-[9px]"></i> SUPERADMIN</span>`;
        }

        const statusBadge = (u.status === 'ACTIVE')
            ? `<span class="bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded-full font-bold text-[10px]">Aktif</span>`
            : `<span class="bg-slate-200 text-slate-600 px-2 py-0.5 rounded-full font-bold text-[10px]">Nonaktif</span>`;

        const tr = document.createElement('tr');
        tr.className = 'hover:bg-slate-50 border-b border-slate-100 transition';
        tr.innerHTML = `
            <td class="p-3 text-slate-400 font-mono text-xs text-center">${idx + 1}</td>
            <td class="p-3 font-mono font-bold text-slate-800">${u.username}</td>
            <td class="p-3 font-semibold text-slate-800">${u.name}</td>
            <td class="p-3 text-center">${roleBadge}</td>
            <td class="p-3 text-center">${statusBadge}</td>
            <td class="p-3 font-mono text-[11px] text-slate-500">${new Date(u.created_at).toLocaleDateString('id-ID')}</td>
            <td class="p-3 text-center">
                <div class="flex items-center justify-center space-x-1.5">
                    <button onclick="editUser(${u.id})" title="Edit Pengguna" class="bg-indigo-50 hover:bg-indigo-100 text-indigo-700 px-2 py-1 rounded-lg text-xs font-semibold transition">
                        <i class="fa-solid fa-pen-to-square"></i> Edit
                    </button>
                    <button onclick="deleteUser(${u.id}, '${u.name}')" title="Hapus Pengguna" class="bg-rose-50 hover:bg-rose-100 text-rose-600 px-2 py-1 rounded-lg text-xs font-semibold transition">
                        <i class="fa-solid fa-trash-can"></i> Hapus
                    </button>
                </div>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

window.filterUserTable = function() {
    const q = (document.getElementById('filterUserSearch')?.value || '').toLowerCase().trim();
    const filtered = cachedUsers.filter(u => 
        u.username.toLowerCase().includes(q) || 
        u.name.toLowerCase().includes(q) ||
        u.role.toLowerCase().includes(q)
    );
    renderUsersTable(filtered);
};

window.openAddUserModal = function() {
    document.getElementById('userModalTitle').innerText = 'Tambah Pengguna Baru';
    document.getElementById('userModalSubtitle').innerText = 'Daftarkan akun operator atau admin';
    document.getElementById('formUser').reset();
    document.getElementById('userId').value = '';
    document.getElementById('userPasswordLabel').innerText = 'Password *';
    document.getElementById('userPassword').required = true;
    document.getElementById('userPasswordHelp').innerText = 'Wajib diisi saat membuat akun baru.';
    document.getElementById('userModal').classList.remove('hidden');
    document.getElementById('userUsername').focus();
};

window.closeUserModal = function() {
    document.getElementById('userModal').classList.add('hidden');
    document.getElementById('formUser').reset();
};

window.editUser = function(id) {
    const u = cachedUsers.find(x => x.id == id);
    if (!u) return;

    document.getElementById('userModalTitle').innerText = 'Edit Pengguna';
    document.getElementById('userModalSubtitle').innerText = `Perbarui akun ${u.name}`;
    document.getElementById('userId').value = u.id;
    document.getElementById('userUsername').value = u.username;
    document.getElementById('userName').value = u.name;
    document.getElementById('userRole').value = u.role;
    document.getElementById('userStatus').value = u.status;
    document.getElementById('userPasswordLabel').innerText = 'Ganti Password (Opsional)';
    document.getElementById('userPassword').value = '';
    document.getElementById('userPassword').required = false;
    document.getElementById('userPasswordHelp').innerText = 'Kosongkan jika tidak ingin mengubah password saat ini.';
    document.getElementById('userModal').classList.remove('hidden');
};

window.submitUser = async function(e) {
    e.preventDefault();
    const id = document.getElementById('userId').value;
    const username = document.getElementById('userUsername').value.trim();
    const name = document.getElementById('userName').value.trim();
    const password = document.getElementById('userPassword').value.trim();
    const role = document.getElementById('userRole').value;
    const status = document.getElementById('userStatus').value;

    const btn = document.getElementById('btnSaveUser');
    btn.disabled = true;
    btn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> Menyimpan...`;

    try {
        const payload = { id, username, name, password, role, status };
        const res = await fetch('api/users.php', {
            method: id ? 'PUT' : 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.success) {
            closeUserModal();
            loadUsers();
            alert(data.message || 'Pengguna berhasil disimpan!');
        } else {
            alert('Gagal: ' + (data.error || 'Terjadi kesalahan'));
        }
    } catch (err) {
        alert('Gagal koneksi ke server: ' + err.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = `<i class="fa-solid fa-save"></i> Simpan Pengguna`;
    }
};

window.deleteUser = async function(id, name) {
    if (!confirm(`Yakin ingin menghapus pengguna "${name}"?`)) return;

    try {
        const res = await fetch(`api/users.php?action=delete&id=${id}`, {
            method: 'POST'
        });
        const data = await res.json();
        if (data.success) {
            loadUsers();
            alert(data.message || 'Pengguna berhasil dihapus.');
        } else {
            alert('Gagal: ' + (data.error || 'Tidak dapat menghapus user'));
        }
    } catch (err) {
        alert('Gagal koneksi ke server: ' + err.message);
    }
};

// -------------------------------------------------------------
// 6. MAINTENANCE MODE & SYSTEM TOOLS (SUPERADMIN ONLY)
// -------------------------------------------------------------
async function loadMaintenanceStatus() {
    const maintTab = document.getElementById('tab-maintenance');
    if (!maintTab) return; // Bukan superadmin

    try {
        const res = await fetch('api/maintenance.php');
        if (!res.ok) return;
        const data = await res.json();
        if (!data.success) return;

        // Update badge & buttons
        const isMaint = data.maintenance_mode;
        const badge = document.getElementById('maintStatusBadge');
        const icon = document.getElementById('maintStatusIcon');
        const text = document.getElementById('maintStatusText');
        const title = document.getElementById('maintModeTitle');
        const desc = document.getElementById('maintModeDesc');
        const btnToggle = document.getElementById('btnToggleMaint');

        if (isMaint) {
            if (badge) badge.className = "bg-rose-100 text-rose-800 text-xs font-bold px-3.5 py-1.5 rounded-xl shadow-sm flex items-center gap-2 border border-rose-200";
            if (icon) icon.className = "fa-solid fa-circle-exclamation text-rose-600 animate-pulse";
            if (text) text.innerText = "Mode Pemeliharaan AKTIF";
            if (title) title.innerText = "Status: Pemeliharaan (Maintenance)";
            if (desc) desc.innerText = "Operator dan admin biasa diblokir sementara sampai maintenance selesai.";
            if (btnToggle) {
                btnToggle.innerText = "Nonaktifkan Maintenance";
                btnToggle.className = "bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs px-4 py-2.5 rounded-xl transition shadow-sm shrink-0";
            }
        } else {
            if (badge) badge.className = "bg-white text-slate-800 text-xs font-bold px-3.5 py-1.5 rounded-xl shadow-sm flex items-center gap-2";
            if (icon) icon.className = "fa-solid fa-circle-check text-emerald-500";
            if (text) text.innerText = "Sistem Online Normal";
            if (title) title.innerText = "Mode Normal (Online)";
            if (desc) desc.innerText = "Sistem dapat diakses secara normal oleh semua user.";
            if (btnToggle) {
                btnToggle.innerText = "Aktifkan Maintenance";
                btnToggle.className = "bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs px-4 py-2.5 rounded-xl transition shadow-sm shrink-0";
            }
        }

        // Table counts
        if (data.tables) {
            const elProd = document.getElementById('countMasterProducts');
            const elSess = document.getElementById('countReturnSessions');
            const elItem = document.getElementById('countReturnItems');
            const elUser = document.getElementById('countUsers');
            if (elProd) elProd.innerText = data.tables.master_products || 0;
            if (elSess) elSess.innerText = data.tables.return_sessions || 0;
            if (elItem) elItem.innerText = data.tables.return_items || 0;
            if (elUser) elUser.innerText = data.tables.users || 0;
        }

    } catch (err) {
        console.error("Gagal load status maintenance:", err);
    }
}

window.toggleMaintenanceMode = async function() {
    const isActivating = (document.getElementById('maintModeTitle')?.innerText || '').includes('Normal');
    const msg = isActivating 
        ? "Yakin ingin MENGAKTIFKAN mode maintenance? Pengguna lain (operator & admin biasa) tidak akan bisa login sampai dinonaktifkan."
        : "Yakin ingin MENONAKTIFKAN mode maintenance dan kembali ke online normal?";
    if (!confirm(msg)) return;

    showGlobalLoading("Memperbarui Status Sistem...", "Sedang mengubah status mode pemeliharaan...");

    try {
        const res = await fetch('api/maintenance.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'toggle_maintenance' })
        });
        const data = await res.json();
        hideGlobalLoading();
        if (data.success) {
            await loadMaintenanceStatus();
            alert(data.message);
        } else {
            alert('Gagal: ' + (data.error || 'Terjadi kesalahan'));
        }
    } catch (err) {
        hideGlobalLoading();
        alert('Gagal koneksi ke server: ' + err.message);
    }
};

window.optimizeDatabaseTables = async function() {
    showGlobalLoading("Mengoptimasi Database...", "Menjalankan perintah SQL OPTIMIZE TABLE untuk defragmentasi indeks...");
    try {
        const res = await fetch('api/maintenance.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'optimize_tables' })
        });
        const data = await res.json();
        hideGlobalLoading();
        if (data.success) {
            alert(data.message || 'Optimasi tabel berhasil!');
            loadMaintenanceStatus();
        } else {
            alert('Gagal: ' + (data.error || 'Terjadi kesalahan'));
        }
    } catch (err) {
        hideGlobalLoading();
        alert('Gagal koneksi ke server: ' + err.message);
    }
};

window.cleanTestTransactions = async function() {
    if (!confirm("Hapus semua transaksi uji coba bertanda 'INV-DEMO' atau 'TEST'?")) return;
    showGlobalLoading("Membersihkan Data...", "Menghapus transaksi retur uji coba...");
    try {
        const res = await fetch('api/maintenance.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'clean_test_transactions' })
        });
        const data = await res.json();
        hideGlobalLoading();
        if (data.success) {
            alert(data.message);
            loadMaintenanceStatus();
            loadTransactions();
            loadMetrics();
        } else {
            alert('Gagal: ' + (data.error || 'Gagal membersihkan data'));
        }
    } catch (err) {
        hideGlobalLoading();
        alert('Gagal koneksi ke server: ' + err.message);
    }
};

// Initial Load
window.addEventListener('DOMContentLoaded', () => {
    refreshAllData();
    // Auto refresh tiap 15 detik
    setInterval(() => {
        loadMetrics();
        loadTransactions();
    }, 15000);
});
