// Helper Sanitasi HTML Global
function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
window.escapeHtml = escapeHtml;

// Helper Tanggal Sekarang (YYYY-MM-DD)
function getTodayYMD() {
    const d = new Date();
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${dd}`;
}

// State Management & Instances
let ratioChartInstance = null;
let trendChartInstance = null;
let expeditionChartInstance = null;
let currentTab = 'dashboard';
let cachedProducts = [];
let activeInboundDateFilter = getTodayYMD();
let activeDashboardDateFilter = getTodayYMD();
let activeReceivingDateFilter = getTodayYMD();
let activeDateFilter = getTodayYMD(); // alias mundur untuk kompatibilitas
let flatpickrTransactionsInstance = null;
let flatpickrDashboardInstance = null;

// Tab & Page Slug Mappings (URL Friendly)
const TAB_SLUG_MAP = {
    'dashboard': 'dashboard',
    'receiving': 'receiving-inbound',
    'transactions': 'inbound-unboxing',
    'claims': 'klaim',
    'orders': 'data-orders',
    'products': 'master-produk',
    'expeditions': 'master-ekspedisi',
    'conditions': 'master-kondisi',
    'users': 'kelola-pengguna',
    'maintenance': 'pemeliharaan'
};

const SLUG_TAB_MAP = {
    'dashboard': 'dashboard',
    'receiving-inbound': 'receiving',
    'receiving': 'receiving',
    'inbound-unboxing': 'transactions',
    'transactions': 'transactions',
    'klaim': 'claims',
    'claims': 'claims',
    'data-orders': 'orders',
    'orders': 'orders',
    'master-produk': 'products',
    'products': 'products',
    'master-ekspedisi': 'expeditions',
    'expeditions': 'expeditions',
    'master-kondisi': 'conditions',
    'conditions': 'conditions',
    'kelola-pengguna': 'users',
    'users': 'users',
    'pemeliharaan': 'maintenance',
    'maintenance': 'maintenance'
};

// Sinkronisasikan URL browser tanpa .php dengan page slug & filter parameter
function updateBrowserUrl(pushHistory = false) {
    const slug = TAB_SLUG_MAP[currentTab] || currentTab;
    const params = new URLSearchParams();

    // Selalu cantumkan nama halaman ?page=...
    params.set('page', slug);

    // Cantumkan filter tanggal jika sedang aktif (mandiri per-halaman)
    const dateToUse = (currentTab === 'dashboard') ? activeDashboardDateFilter : activeInboundDateFilter;
    if (dateToUse) {
        params.set('date', dateToUse);
    }

    // Filter tambahan per-tab
    if (currentTab === 'transactions') {
        const searchInput = document.getElementById('filterSearch');
        if (searchInput && searchInput.value.trim()) {
            params.set('search', searchInput.value.trim());
        }
    } else if (currentTab === 'orders') {
        const searchInput = document.getElementById('orderSearchInput');
        const platformFilter = document.getElementById('orderPlatformFilter');
        const dateFilter = document.getElementById('orderDateFilter');
        if (searchInput && searchInput.value.trim()) {
            params.set('search', searchInput.value.trim());
        }
        if (platformFilter && platformFilter.value !== 'ALL') {
            params.set('platform', platformFilter.value);
        }
        if (dateFilter && dateFilter.value) {
            params.set('period', dateFilter.value);
        }
    } else if (currentTab === 'products') {
        const shopFilter = document.getElementById('filterProductShop');
        const searchInput = document.getElementById('filterProductSearch');
        if (shopFilter && shopFilter.value.trim()) {
            params.set('shop', shopFilter.value.trim());
        }
        if (searchInput && searchInput.value.trim()) {
            params.set('search', searchInput.value.trim());
        }
    }

    // Pastikan path tidak memuat .php
    let pathname = window.location.pathname.replace(/\.php$/i, '');
    if (!pathname.endsWith('/admin') && !pathname.endsWith('admin')) {
        pathname = pathname.replace(/\/?$/, '/admin');
    }

    const newQuery = params.toString() ? `?${params.toString()}` : '';
    const newUrl = `${pathname}${newQuery}`;

    if (window.location.pathname + window.location.search === newUrl) {
        return;
    }

    if (pushHistory) {
        window.history.pushState({ tab: currentTab, query: params.toString() }, '', newUrl);
    } else {
        window.history.replaceState({ tab: currentTab, query: params.toString() }, '', newUrl);
    }
}

// Global Loading Overlay Controls (Bola-bola Merah, Kuning, Hijau)
window.showGlobalLoading = function (title = 'Memuat Data...', desc = 'Mohon tunggu sebentar, sistem sedang memproses data.') {
    const el = document.getElementById('globalLoadingOverlay');
    if (!el) return;
    const t = document.getElementById('globalLoadingTitle');
    const d = document.getElementById('globalLoadingDesc');
    if (t) t.innerText = title;
    if (d) d.innerText = desc;
    el.classList.remove('hidden');
};

window.hideGlobalLoading = function () {
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

// =========================================================================
// PEMBERSIH AUTOFILL KREDENSIAL BROWSER (Mencegah teks "admin.cs" masuk ke kolom search)
// =========================================================================
function clearAutofilledSearchInputs() {
    const searchSelectors = [
        '#searchReceivingInput',
        '#filterSearch',
        '#claimSearchInput',
        '#filterClaimSearch',
        '#orderSearchInput',
        '#filterProductSearch',
        '#filterExpeditionSearch',
        '#filterConditionSearch',
        '#filterUserSearch',
        '#pkgModalSearchInput'
    ];
    searchSelectors.forEach(sel => {
        const el = document.querySelector(sel);
        if (el) {
            const v = (el.value || '').trim().toLowerCase();
            if (v === 'admin.cs' || v.includes('admin.cs')) {
                el.value = '';
                if (typeof applyClaimCandidatesFilter === 'function' && el.id === 'filterClaimSearch') {
                    applyClaimCandidatesFilter();
                }
                if (typeof filterProductTable === 'function' && el.id === 'filterProductSearch') {
                    filterProductTable();
                }
            }
        }
    });
}
window.clearAutofilledSearchInputs = clearAutofilledSearchInputs;

// Jalankan pembersihan saat DOM dimuat dan dengan interval pendek untuk menangkal browser autofill delay
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        clearAutofilledSearchInputs();
        setTimeout(clearAutofilledSearchInputs, 150);
        setTimeout(clearAutofilledSearchInputs, 500);
        setTimeout(clearAutofilledSearchInputs, 1200);
    });
} else {
    clearAutofilledSearchInputs();
    setTimeout(clearAutofilledSearchInputs, 150);
    setTimeout(clearAutofilledSearchInputs, 500);
    setTimeout(clearAutofilledSearchInputs, 1200);
}

// Bersihkan jika input mendapatkan focus atau input baru
document.addEventListener('focusin', (e) => {
    if (e.target && (e.target.matches('input[type="text"], input[type="search"]'))) {
        const v = (e.target.value || '').trim().toLowerCase();
        if (v === 'admin.cs' || v.includes('admin.cs')) {
            e.target.value = '';
        }
    }
});

// Switch Tabs
window.switchTab = function (tabName, updateUrl = true) {
    currentTab = tabName;
    clearAutofilledSearchInputs();

    // Reset styles navigasi
    document.querySelectorAll('.nav-item').forEach(el => {
        el.classList.remove('text-white', 'bg-indigo-600', 'shadow-sm', 'shadow-indigo-600/30');
        el.classList.add('text-slate-600', 'hover:text-slate-900', 'hover:bg-slate-100');
        const icon = el.querySelector('i');
        if (icon) icon.classList.remove('text-indigo-100');
    });

    const activeNav = document.getElementById(`nav-${tabName}`);
    if (activeNav) {
        activeNav.classList.remove('text-slate-600', 'hover:text-slate-900', 'hover:bg-slate-100');
        activeNav.classList.add('text-white', 'bg-indigo-600', 'shadow-sm', 'shadow-indigo-600/30');
        const icon = activeNav.querySelector('i');
        if (icon) icon.classList.add('text-indigo-100');
    }

    // Toggle Tab Content
    document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
    const targetContent = document.getElementById(`tab-${tabName}`);
    if (targetContent) targetContent.classList.remove('hidden');

    // Update Title
    const titleEl = document.getElementById('currentViewTitle');
    if (titleEl) {
        if (tabName === 'dashboard') titleEl.innerText = 'Dashboard Monitoring Retur';
        else if (tabName === 'receiving') titleEl.innerText = 'Receiving Inbound - Penerimaan Ekspedisi';
        else if (tabName === 'transactions') titleEl.innerText = 'Inbound Unboxing';
        else if (tabName === 'claims') titleEl.innerText = 'Pusat Klaim & Banding Ekspedisi';
        else if (tabName === 'orders') titleEl.innerText = 'Data Orders OCS (Sinkronisasi Pesanan)';
        else if (tabName === 'products') titleEl.innerText = 'Master Data Produk & Barcode';
        else if (tabName === 'expeditions') titleEl.innerText = 'Master Data Ekspedisi & Kurir';
        else if (tabName === 'conditions') titleEl.innerText = 'Master Data Kondisi Produk';
        else if (tabName === 'users') titleEl.innerText = 'Kelola Akun Pengguna';
        else if (tabName === 'roles') titleEl.innerText = 'Kelola Role & Hak Akses Pengguna';
        else if (tabName === 'bank-settings') titleEl.innerText = 'Pengaturan Rekening Bank Perusahaan';
        else if (tabName === 'maintenance') titleEl.innerText = 'Pemeliharaan Sistem & Database';
    }

    // Auto close sidebar on mobile after click
    if (window.innerWidth < 1024) closeMobileSidebar();

    // Trigger tab-specific refresh if needed
    if (tabName === 'dashboard') {
        loadMetrics();
    }
    if (tabName === 'receiving') loadReceivingData();
    if (tabName === 'products') loadProducts();
    if (tabName === 'transactions') loadTransactions();
    if (tabName === 'claims') loadClaimCandidates();
    if (tabName === 'orders') {
        if (typeof loadOrdersStats === 'function') loadOrdersStats();
        if (typeof loadOrdersTable === 'function') loadOrdersTable(1);
    }
    if (tabName === 'expeditions') loadExpeditions();
    if (tabName === 'conditions') loadConditions();
    if (tabName === 'users') loadUsers();
    if (tabName === 'roles') loadRoles();
    if (tabName === 'bank-settings') loadStandaloneBankSettings();
    if (tabName === 'maintenance') loadMaintenanceStatus();

    // Sinkronisasikan URL browser
    if (updateUrl) {
        updateBrowserUrl(true);
    }
};

// 1. Load Metrics KPI (Refresh di backend via cache atau query)
async function loadMetrics(forceRefresh = false) {
    const elRecPkg = document.getElementById('kpiTotalReceivedPackages');
    const elRecSess = document.getElementById('kpiTotalReceptions');
    const elInv = document.getElementById('kpiTotalInvoice');
    const elItems = document.getElementById('kpiTotalItems');
    const elGood = document.getElementById('kpiTotalGood');
    const elDamaged = document.getElementById('kpiTotalDamaged');
    const picTbody = document.getElementById('dashPicTableBody');
    const expTbody = document.getElementById('dashExpeditionTableBody');

    // Tampilkan spinner loading pada KPI & tabel saat proses fetch
    if (elRecPkg) elRecPkg.innerHTML = '<i class="fa-solid fa-spinner fa-spin text-base text-emerald-400"></i>';
    if (elInv) elInv.innerHTML = '<i class="fa-solid fa-spinner fa-spin text-base text-indigo-400"></i>';
    if (elItems) elItems.innerHTML = '<i class="fa-solid fa-spinner fa-spin text-base text-blue-400"></i>';
    if (elGood) elGood.innerHTML = '<i class="fa-solid fa-spinner fa-spin text-base text-emerald-400"></i>';
    if (elDamaged) elDamaged.innerHTML = '<i class="fa-solid fa-spinner fa-spin text-base text-rose-400"></i>';

    if (picTbody) {
        picTbody.innerHTML = `<tr><td colspan="6" class="p-8 text-center text-slate-400 text-xs"><i class="fa-solid fa-spinner fa-spin mr-2 text-indigo-600 text-base"></i>Memuat produktivitas petugas inbound...</td></tr>`;
    }
    if (expTbody) {
        expTbody.innerHTML = `<tr><td colspan="5" class="p-6 text-center text-slate-400 text-xs"><i class="fa-solid fa-spinner fa-spin mr-2 text-indigo-600 text-base"></i>Memuat statistik ekspedisi...</td></tr>`;
    }

    try {
        let url = 'api/admin/metrics';
        const qParams = [];
        if (activeDashboardDateFilter) {
            qParams.push(`date=${encodeURIComponent(activeDashboardDateFilter)}`);
        }
        if (forceRefresh) {
            qParams.push('refresh=1');
        }
        if (qParams.length > 0) {
            url += '?' + qParams.join('&');
        }
        const res = await fetch(url);
        const data = await res.json();

        if (elRecPkg) elRecPkg.innerText = (data.total_received_packages ?? 0).toLocaleString('id-ID');
        if (elRecSess) elRecSess.innerText = (data.total_receptions ?? 0).toLocaleString('id-ID');
        if (elInv) elInv.innerText = (data.total_invoices ?? 0).toLocaleString('id-ID');
        if (elItems) elItems.innerText = (data.total_items ?? 0).toLocaleString('id-ID');
        if (elGood) elGood.innerText = (data.total_good ?? 0).toLocaleString('id-ID');
        if (elDamaged) elDamaged.innerText = (data.total_damaged ?? 0).toLocaleString('id-ID');

        renderRatioChart(data.total_good || 0, data.total_damaged || 0);
        renderTrendChart(data.trend_7days || []);
        renderExpeditionChart(data.by_expedition || []);
        renderDashExpeditionTable(data.by_expedition || []);
        renderDashPicTable(data.pic_stats || []);
        renderDashConditionBreakdown(data.by_condition || {});
    } catch (err) {
        console.error("Gagal memuat metrics:", err);
        if (picTbody) picTbody.innerHTML = `<tr><td colspan="6" class="p-6 text-center text-rose-500 text-xs font-semibold">Gagal memuat data petugas: ${err.message}</td></tr>`;
        if (expTbody) expTbody.innerHTML = `<tr><td colspan="5" class="p-6 text-center text-rose-500 text-xs font-semibold">Gagal memuat data ekspedisi: ${err.message}</td></tr>`;
    }
}

function renderRatioChart(good, damaged) {
    const canvas = document.getElementById('ratioChart');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    if (ratioChartInstance) ratioChartInstance.destroy();
    const total = good + damaged;
    const dataVals = total === 0 ? [1] : [good, damaged];
    const bgColors = total === 0 ? ['#e2e8f0'] : ['#10b981', '#f43f5e'];
    const lbls = total === 0 ? ['Tidak ada data'] : ['Good / Baik', 'Rusak / Defect'];
    ratioChartInstance = new Chart(ctx, {
        type: 'doughnut',
        data: { labels: lbls, datasets: [{ data: dataVals, backgroundColor: bgColors, borderWidth: 0, hoverOffset: 6 }] },
        options: {
            responsive: true, maintainAspectRatio: false, cutout: '72%',
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 10, padding: 12, font: { size: 10 } } },
                tooltip: { callbacks: { label: (ctx) => ` ${ctx.label}: ${ctx.raw.toLocaleString('id-ID')} pcs` } }
            }
        }
    });
}

function renderTrendChart(trend) {
    const canvas = document.getElementById('trendChart');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    if (trendChartInstance) trendChartInstance.destroy();
    const labels = trend.map(t => { const d = new Date(t.tgl); return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' }); });
    const values = trend.map(t => parseInt(t.total_qty) || 0);
    trendChartInstance = new Chart(ctx, {
        type: 'bar',
        data: { labels, datasets: [{ label: 'Total Qty Retur', data: values, backgroundColor: 'rgba(99,102,241,0.15)', borderColor: '#6366f1', borderWidth: 2, borderRadius: 6 }] },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false }, tooltip: { callbacks: { label: (ctx) => ` ${ctx.raw.toLocaleString('id-ID')} pcs` } } },
            scales: { x: { grid: { display: false }, ticks: { font: { size: 10 } } }, y: { grid: { color: '#f1f5f9' }, ticks: { font: { size: 10 }, precision: 0 }, beginAtZero: true } }
        }
    });
}

// Visualisasi Chart per Ekspedisi Total Paket (Receiving Fisik vs Unboxing Terproses)
function renderExpeditionChart(list) {
    const canvas = document.getElementById('expeditionChart');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    if (expeditionChartInstance) {
        expeditionChartInstance.destroy();
        expeditionChartInstance = null;
    }

    if (!list || list.length === 0) {
        return;
    }

    const labels = list.map(item => item.expedition || 'Lainnya');
    const recData = list.map(item => parseInt(item.receiving_packages || 0));
    const unboxData = list.map(item => parseInt(item.unboxing_packages || 0));

    expeditionChartInstance = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Receiving (Fisik Masuk)',
                    data: recData,
                    backgroundColor: 'rgba(99, 102, 241, 0.85)',
                    borderRadius: 6,
                    borderSkipped: false
                },
                {
                    label: 'Unboxing (Terproses)',
                    data: unboxData,
                    backgroundColor: 'rgba(16, 185, 129, 0.85)',
                    borderRadius: 6,
                    borderSkipped: false
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    align: 'end',
                    labels: {
                        boxWidth: 12,
                        padding: 10,
                        font: { size: 11, weight: 'bold' }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: (ctx) => ` ${ctx.dataset.label}: ${ctx.raw.toLocaleString('id-ID')} paket`
                    }
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { font: { size: 11, weight: '600' } }
                },
                y: {
                    grid: { color: '#f1f5f9' },
                    ticks: { font: { size: 10 }, precision: 0 },
                    beginAtZero: true
                }
            }
        }
    });
}

// Tabel Produktivitas Petugas Inbound (PIC Receiving vs Unboxing)
function renderDashPicTable(list) {
    const tbody = document.getElementById('dashPicTableBody');
    if (!tbody) return;
    tbody.innerHTML = '';

    if (!list || list.length === 0) {
        tbody.innerHTML = `<tr><td colspan="6" class="p-8 text-center text-slate-400 text-xs">
            <i class="fa-solid fa-users-slash text-2xl text-slate-300 mb-2 block"></i>
            Tidak ada riwayat aktivitas petugas inbound pada periode tanggal ini.
        </td></tr>`;
        return;
    }

    const sorted = [...list].sort((a, b) => (b.total_processed || 0) - (a.total_processed || 0));
    const totalAll = sorted.reduce((sum, item) => sum + (item.total_processed || 0), 0) || 1;

    const rankBadges = [
        '<span class="w-5 h-5 rounded-full bg-amber-100 text-amber-700 font-bold text-[10px] inline-flex items-center justify-center border border-amber-300">1</span>',
        '<span class="w-5 h-5 rounded-full bg-slate-200 text-slate-700 font-bold text-[10px] inline-flex items-center justify-center border border-slate-300">2</span>',
        '<span class="w-5 h-5 rounded-full bg-amber-700/10 text-amber-800 font-bold text-[10px] inline-flex items-center justify-center border border-amber-600/30">3</span>'
    ];

    sorted.forEach((pic, i) => {
        const badge = rankBadges[i] || `<span class="w-5 h-5 rounded-full bg-slate-100 text-slate-500 font-bold text-[10px] inline-flex items-center justify-center">${i + 1}</span>`;
        const pct = Math.round(((pic.total_processed || 0) / totalAll) * 100);

        const tr = document.createElement('tr');
        tr.className = 'hover:bg-slate-50 border-b border-slate-100 transition';
        tr.innerHTML = `
            <td class="py-3 px-4 text-center">${badge}</td>
            <td class="py-3 px-4">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-full bg-gradient-to-tr from-indigo-500 to-indigo-700 text-white font-black text-xs flex items-center justify-center uppercase shadow-2xs">
                        ${escapeHtml((pic.pic_name || 'U').charAt(0))}
                    </div>
                    <div>
                        <div class="font-bold text-xs text-slate-800">${escapeHtml(pic.pic_name)}</div>
                        <div class="text-[10px] text-slate-400">Petugas Gudang / Inbound</div>
                    </div>
                </div>
            </td>
            <td class="py-3 px-4 text-center">
                <div class="inline-flex flex-col items-center">
                    <span class="font-extrabold text-xs text-indigo-700 bg-indigo-50 border border-indigo-200/60 px-2 py-0.5 rounded-lg">
                        ${(pic.receiving_packages || 0).toLocaleString('id-ID')} <span class="text-[10px] font-normal text-slate-500">paket</span>
                    </span>
                    <span class="text-[10px] text-slate-400 mt-0.5">${(pic.receiving_sessions || 0).toLocaleString('id-ID')} surat jalan</span>
                </div>
            </td>
            <td class="py-3 px-4 text-center">
                <div class="inline-flex flex-col items-center">
                    <span class="font-extrabold text-xs text-emerald-700 bg-emerald-50 border border-emerald-200/60 px-2 py-0.5 rounded-lg">
                        ${(pic.unboxing_packages || 0).toLocaleString('id-ID')} <span class="text-[10px] font-normal text-slate-500">paket</span>
                    </span>
                    <span class="text-[10px] text-slate-400 mt-0.5">${(pic.unboxing_items || 0).toLocaleString('id-ID')} unit fisik</span>
                </div>
            </td>
            <td class="py-3 px-4 text-right">
                <span class="font-black text-sm text-slate-900">${(pic.total_processed || 0).toLocaleString('id-ID')}</span>
                <span class="text-[10px] text-slate-400 block font-medium">total paket</span>
            </td>
            <td class="py-3 px-4 w-36">
                <div class="flex items-center gap-2">
                    <div class="flex-1 bg-slate-100 rounded-full h-2 overflow-hidden">
                        <div class="h-full bg-gradient-to-r from-indigo-500 to-emerald-500 rounded-full" style="width:${pct}%"></div>
                    </div>
                    <span class="text-[11px] font-bold text-slate-600 w-8 text-right">${pct}%</span>
                </div>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

function renderDashExpeditionTable(list) {
    const tbody = document.getElementById('dashExpeditionTableBody');
    if (!tbody) return;
    tbody.innerHTML = '';
    if (!list || list.length === 0) { tbody.innerHTML = `<tr><td colspan="5" class="p-6 text-center text-slate-400 text-xs">Tidak ada data.</td></tr>`; return; }
    const totalAll = list.reduce((s, r) => s + parseInt(r.total_qty || 0), 0) || 1;
    const colors = ['indigo', 'blue', 'violet', 'cyan', 'teal', 'emerald', 'amber'];
    list.forEach((row, i) => {
        const qty = parseInt(row.total_qty || 0);
        const pct = Math.round((qty / totalAll) * 100);
        const clr = colors[i % colors.length];
        const tr = document.createElement('tr');
        tr.className = 'hover:bg-slate-50 border-b border-slate-100 transition';
        tr.innerHTML = `
            <td class="p-3 text-slate-400 font-mono text-xs text-center">${i + 1}</td>
            <td class="p-3"><span class="font-bold text-xs text-slate-800">${row.expedition}</span></td>
            <td class="p-3 text-center"><span class="bg-slate-100 text-slate-700 text-xs font-semibold px-2 py-0.5 rounded-lg">${parseInt(row.total_sessions || 0)}</span></td>
            <td class="p-3 text-right"><span class="font-black text-sm text-slate-800">${qty.toLocaleString('id-ID')}</span> <span class="text-[10px] text-slate-400">pcs</span></td>
            <td class="p-3 w-28"><div class="flex items-center gap-1.5"><div class="flex-1 bg-slate-100 rounded-full h-1.5 overflow-hidden"><div class="h-full bg-${clr}-500 rounded-full" style="width:${pct}%"></div></div><span class="text-[10px] text-slate-500 font-semibold w-8 text-right">${pct}%</span></div></td>
        `;
        tbody.appendChild(tr);
    });
}

const COND_COLOR = {
    GOOD: { dot: 'bg-emerald-500', badge: 'bg-emerald-50 text-emerald-700 border-emerald-200' },
    DAMAGED: { dot: 'bg-red-500', badge: 'bg-red-50 text-red-700 border-red-200' },
    MISSING: { dot: 'bg-amber-500', badge: 'bg-amber-50 text-amber-700 border-amber-200' },
    EXPIRED: { dot: 'bg-orange-500', badge: 'bg-orange-50 text-orange-700 border-orange-200' },
    WRONG: { dot: 'bg-purple-500', badge: 'bg-purple-50 text-purple-700 border-purple-200' },
};
function getCondColor(code) { return COND_COLOR[code?.toUpperCase()] || { dot: 'bg-slate-400', badge: 'bg-slate-100 text-slate-600 border-slate-200' }; }

function renderDashConditionBreakdown(byCondition) {
    const container = document.getElementById('dashConditionContainer');
    if (!container) return;
    const exps = Object.keys(byCondition);
    if (exps.length === 0) { container.innerHTML = `<div class="p-6 text-center text-slate-400 text-xs">Tidak ada data.</div>`; return; }
    container.innerHTML = exps.map(exp => {
        const conds = byCondition[exp];
        const badges = conds.map(c => { const clr = getCondColor(c.code); return `<span class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-lg border ${clr.badge}"><span class="w-1.5 h-1.5 rounded-full ${clr.dot} inline-block"></span>${c.code}: <strong>${c.total_qty.toLocaleString('id-ID')}</strong></span>`; }).join('');
        return `<div class="p-3 flex items-start gap-3"><div class="w-24 shrink-0 pt-0.5"><span class="font-bold text-xs text-slate-700">${exp}</span></div><div class="flex flex-wrap gap-1.5">${badges}</div></div>`;
    }).join('');
}

function renderChart(good, damaged) { renderRatioChart(good, damaged); }


let cachedTransactions = [];
let flatpickrInstance = null;

// Helper Format Tanggal & Jam Bahasa Indonesia yang Premium
function formatDateTime(dateStr) {
    if (!dateStr) return { date: '-', time: '-' };
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return { date: dateStr, time: '' };

    const dateFormatted = d.toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric'
    });

    const timeFormatted = d.toLocaleTimeString('id-ID', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: false
    });

    return { date: dateFormatted, time: timeFormatted };
}

// Update UI Filter Tanggal Inbound Unboxing (Mandiri - Tidak Mirroring Dashboard)
function updateInboundDateUI() {
    const trDateEl = document.getElementById('filterDate');
    const trClearBtn = document.getElementById('btnClearDate');
    if (trDateEl) trDateEl.value = activeInboundDateFilter;
    if (trClearBtn) {
        if (activeInboundDateFilter) trClearBtn.classList.remove('hidden');
        else trClearBtn.classList.add('hidden');
    }
}

// Update UI Filter Tanggal Dashboard (Mandiri - Tidak Mirroring Inbound)
function updateDashboardDateUI() {
    const dbDateEl = document.getElementById('dashboardFilterDate');
    const dbClearBtn = document.getElementById('btnClearDashboardDate');
    const dbBadge = document.getElementById('dashboardDateBadge');
    if (dbDateEl) dbDateEl.value = activeDashboardDateFilter;
    if (dbClearBtn) {
        if (activeDashboardDateFilter) dbClearBtn.classList.remove('hidden');
        else dbClearBtn.classList.add('hidden');
    }
    if (dbBadge) {
        if (activeDashboardDateFilter) {
            dbBadge.innerText = activeDashboardDateFilter;
            dbBadge.className = 'bg-amber-50 text-amber-700 text-[10px] font-bold px-2 py-0.5 rounded-full border border-amber-200';
        } else {
            dbBadge.innerText = 'Hari Ini';
            dbBadge.className = 'bg-indigo-50 text-indigo-700 text-[10px] font-bold px-2 py-0.5 rounded-full border border-indigo-200';
        }
    }
}

// Helper: ambil date string dari Flatpickr instance
function getDateStrFromInstance(instance) {
    if (!instance || !instance.selectedDates || instance.selectedDates.length === 0) return '';
    const fmt = (d) => {
        const y = d.getFullYear();
        const m = String(d.getMonth() + 1).padStart(2, '0');
        const dd = String(d.getDate()).padStart(2, '0');
        return `${y}-${m}-${dd}`;
    };
    if (instance.selectedDates.length === 2) {
        return `${fmt(instance.selectedDates[0])} to ${fmt(instance.selectedDates[1])}`;
    }
    return fmt(instance.selectedDates[0]);
}

// Inisialisasi Datepicker Flatpickr Premium (Mandiri untuk Inbound Unboxing & Dashboard)
function initFlatpickr() {
    if (typeof flatpickr === 'undefined') return;

    // 1. Inbound Unboxing (Hanya mengontrol tabel Inbound)
    const el = document.getElementById('filterDate');
    if (el) {
        if (flatpickrTransactionsInstance) flatpickrTransactionsInstance.destroy();
        flatpickrTransactionsInstance = flatpickr(el, {
            mode: 'range',
            dateFormat: 'Y-m-d',
            defaultDate: activeInboundDateFilter || getTodayYMD(),
            altInput: true,
            altFormat: 'j M Y',
            altInputClass: 'bg-slate-50 hover:bg-white focus:bg-white border border-slate-300 rounded-xl pl-8 pr-8 py-2 text-xs font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 shadow-2xs transition w-56 sm:w-64 cursor-pointer',
            locale: (flatpickr.l10ns && flatpickr.l10ns.id) ? flatpickr.l10ns.id : 'default',
            allowInput: false,
            onClose: function (selectedDates, dateStr, instance) {
                activeInboundDateFilter = getDateStrFromInstance(instance);
                updateInboundDateUI();
                updateBrowserUrl(false);
                loadTransactions();
            }
        });
        updateInboundDateUI();
    }

    // 2. Dashboard (Hanya mengontrol metrik Dashboard - Bebas dari Inbound)
    const dbEl = document.getElementById('dashboardFilterDate');
    if (dbEl) {
        if (flatpickrDashboardInstance) flatpickrDashboardInstance.destroy();
        flatpickrDashboardInstance = flatpickr(dbEl, {
            mode: 'range',
            dateFormat: 'Y-m-d',
            defaultDate: activeDashboardDateFilter || getTodayYMD(),
            altInput: true,
            altFormat: 'j M Y',
            altInputClass: 'bg-slate-50 hover:bg-white focus:bg-white border border-slate-300 rounded-xl pl-8 pr-8 py-2 text-xs font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 shadow-2xs transition w-full sm:w-64 cursor-pointer',
            locale: (flatpickr.l10ns && flatpickr.l10ns.id) ? flatpickr.l10ns.id : 'default',
            allowInput: false,
            onClose: function (selectedDates, dateStr, instance) {
                activeDashboardDateFilter = getDateStrFromInstance(instance);
                updateDashboardDateUI();
                updateBrowserUrl(false);
                loadMetrics();
            }
        });
        updateDashboardDateUI();
    }
}

// Reset filter tanggal Inbound Unboxing
window.clearDateFilter = function () {
    if (flatpickrTransactionsInstance) flatpickrTransactionsInstance.clear();
    activeInboundDateFilter = '';
    updateInboundDateUI();
    updateBrowserUrl(false);
    loadTransactions();
};

// Terapkan filter tanggal Dashboard
window.applyDashboardDateFilter = function () {
    const val = getDateStrFromInstance(flatpickrDashboardInstance)
        || document.getElementById('dashboardFilterDate')?.value?.trim()
        || '';
    activeDashboardDateFilter = val;
    updateDashboardDateUI();
    updateBrowserUrl(false);
    loadMetrics();
};

// Reset filter tanggal Dashboard
window.clearDashboardDateFilter = function () {
    if (flatpickrDashboardInstance) flatpickrDashboardInstance.clear();
    activeDashboardDateFilter = '';
    updateDashboardDateUI();
    updateBrowserUrl(false);
    loadMetrics();
};

// Refresh Metrik Dashboard di Backend
window.refreshDashboardMetrics = async function () {
    const btn = event?.currentTarget;
    if (btn) {
        btn.disabled = true;
        const icon = btn.querySelector('i');
        if (icon) icon.classList.add('fa-spin');
    }
    await loadMetrics(true);
    if (btn) {
        btn.disabled = false;
        const icon = btn.querySelector('i');
        if (icon) icon.classList.remove('fa-spin');
    }
    if (typeof showToast === 'function') {
        showToast('success', 'Statistik dashboard berhasil diperbarui dari server backend.', 'Dashboard Refreshed');
    }
};

// 2. Load Transaksi (Full & Preview)
async function loadTransactions() {
    const tbody = document.getElementById('transactionsTableBody');
    if (tbody) {
        tbody.innerHTML = `<tr><td colspan="12" class="py-12 text-center text-slate-400">
            <div class="inline-flex items-center gap-2.5 px-4 py-2 bg-indigo-50 text-indigo-600 rounded-xl">
                <i class="fa-solid fa-spinner fa-spin text-lg"></i>
                <span class="text-xs font-semibold">Memuat data unboxing paket...</span>
            </div>
        </td></tr>`;
    }

    const searchInput = document.getElementById('filterSearch');
    const search = searchInput ? searchInput.value.trim() : '';
    const date = activeInboundDateFilter;
    const expedition = document.getElementById('filterExpedition')?.value || '';
    const condition = document.getElementById('filterCondition')?.value || '';
    const operator = document.getElementById('filterOperator')?.value || '';

    let url = `api/admin/transactions?`;
    if (search) url += `search=${encodeURIComponent(search)}&`;
    if (date) url += `date=${encodeURIComponent(date)}&`;
    if (expedition) url += `expedition=${encodeURIComponent(expedition)}&`;
    if (condition) url += `condition=${encodeURIComponent(condition)}&`;
    if (operator) url += `operator=${encodeURIComponent(operator)}&`;
    if (!date && !search && !expedition && !condition && !operator) {
        url += `limit=200&`;
    }

    try {
        const res = await fetch(url);
        const rows = await res.json();
        cachedTransactions = Array.isArray(rows) ? rows : [];

        // Isi opsi filter dropdown ekspedisi & operator secara dinamis
        populateTransactionFilterDropdowns(cachedTransactions);

        // Render di tabel transaksi penuh (Tab Inbound Unboxing)
        if (tbody) {
            tbody.innerHTML = '';
            if (!rows || rows.length === 0) {
                tbody.innerHTML = `<tr><td colspan="10" class="text-center py-8 text-slate-400">Tidak ada riwayat transaksi unboxing ditemukan.</td></tr>`;
            } else {
                rows.forEach(r => tbody.appendChild(createTransactionRow(r, false)));
            }
        }
    } catch (err) {
        console.error("Gagal load transaksi:", err);
        if (tbody) {
            tbody.innerHTML = `<tr><td colspan="10" class="text-center py-8 text-rose-500 font-semibold">Gagal memuat transaksi: ${err.message}</td></tr>`;
        }
    }
}

function populateTransactionFilterDropdowns(rows) {
    if (!rows || !rows.length) return;

    const expSelect = document.getElementById('filterExpedition');
    if (expSelect && expSelect.options.length <= 1) {
        const currentExp = expSelect.value;
        const expeditions = Array.from(new Set(rows.map(r => (r.expedition || '').trim()).filter(Boolean))).sort();
        expSelect.innerHTML = '<option value="">Semua Ekspedisi</option>';
        expeditions.forEach(exp => {
            const opt = document.createElement('option');
            opt.value = exp;
            opt.textContent = exp;
            if (exp === currentExp) opt.selected = true;
            expSelect.appendChild(opt);
        });
    }

    const opSelect = document.getElementById('filterOperator');
    if (opSelect && opSelect.options.length <= 1) {
        const currentOp = opSelect.value;
        const operators = Array.from(new Set(rows.map(r => (r.operator_name || '').trim()).filter(Boolean))).sort();
        opSelect.innerHTML = '<option value="">Semua Operator</option>';
        operators.forEach(op => {
            const opt = document.createElement('option');
            opt.value = op;
            opt.textContent = op;
            if (op === currentOp) opt.selected = true;
            opSelect.appendChild(opt);
        });
    }
}

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

function createTransactionRow(r, isPreview = false) {
    const tr = document.createElement('tr');
    tr.className = 'hover:bg-slate-50 transition border-b border-slate-100';

    const { date, time } = formatDateTime(r.created_at);

    const expBadge = r.expedition ?
        `<span class="bg-indigo-50 text-indigo-700 border border-indigo-200 px-2 py-0.5 rounded-md font-semibold text-[11px] inline-flex items-center gap-1 whitespace-nowrap">
            <i class="fa-solid fa-truck-fast text-[10px]"></i> ${r.expedition}
         </span>` :
        `<span class="text-slate-400 italic text-[11px]">-</span>`;

    // Seller SKU Badge
    const skuBadge = r.seller_sku ?
        `<span class="font-mono font-bold text-indigo-700 bg-indigo-50/80 border border-indigo-200/80 px-2.5 py-1 rounded-lg text-xs inline-block tracking-tight">${r.seller_sku}</span>` :
        `<span class="text-slate-400 font-mono text-xs italic">-</span>`;

    // Type / Kondisi Dinamis dari Master Kondisi
    const condCode = (r.raw_type || r.condition_type || 'GOOD').toUpperCase();
    const matchedCond = (typeof allConditions !== 'undefined' && Array.isArray(allConditions))
        ? allConditions.find(x => (x.code || '').toUpperCase() === condCode)
        : null;

    let typeBadge = '';
    if (matchedCond) {
        const clr = CONDITION_COLOR_MAP[matchedCond.color] || CONDITION_COLOR_MAP.slate;
        typeBadge = `<span class="${clr.bg} ${clr.text} border ${clr.border} px-2.5 py-1 rounded-xl font-bold text-[10px] inline-flex items-center gap-1 shadow-2xs font-mono">
            <i class="fa-solid fa-tag text-[9px]"></i> ${matchedCond.name}
        </span>`;
    } else if (condCode === 'GOOD') {
        typeBadge = `<span class="bg-emerald-100 text-emerald-800 border border-emerald-200 px-2.5 py-1 rounded-xl font-bold text-[10px] inline-flex items-center gap-1 shadow-2xs">
            <i class="fa-solid fa-circle-check text-emerald-600"></i> GOOD
        </span>`;
    } else {
        typeBadge = `<span class="bg-rose-100 text-rose-800 border border-rose-200 px-2.5 py-1 rounded-xl font-bold text-[10px] inline-flex items-center gap-1 shadow-2xs">
            <i class="fa-solid fa-triangle-exclamation text-rose-600"></i> ${escapeHtml(condCode)}
        </span>`;
    }

    const hasVideo = Boolean(r.video_path && r.video_path.trim() !== '');

    // Tombol Video Unboxing: Play Video & Download Video
    let videoActionsHtml = '';
    if (hasVideo) {
        videoActionsHtml = `
            <div class="flex items-center justify-center gap-1.5 whitespace-nowrap">
                <button type="button" onclick="playTransactionVideo(${r.session_id || r.id})" title="Putar Video Unboxing" class="bg-indigo-600 hover:bg-indigo-700 text-white px-2.5 py-1.5 rounded-xl font-bold text-[11px] transition inline-flex items-center gap-1 shadow-sm shadow-indigo-600/30">
                    <i class="fa-solid fa-play text-[10px]"></i> Play
                </button>
                <a href="${r.video_path}" download="${r.invoice_number || 'inbound'}_unboxing.webm" title="Unduh File Video" class="bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200 px-2 py-1.5 rounded-xl font-bold text-[11px] transition inline-flex items-center gap-1 shadow-2xs">
                    <i class="fa-solid fa-download text-[10px]"></i>
                </a>
            </div>
        `;
    } else {
        videoActionsHtml = `
            <span class="text-slate-400 text-[11px] italic inline-flex items-center justify-center gap-1">
                <i class="fa-solid fa-video-slash text-slate-300"></i> No Video
            </span>
        `;
    }

    // Tombol Aksi Detail
    const detailBtn = `
        <button type="button" onclick="viewDetails(${r.session_id || r.id})" title="Lihat Rincian Sesi Invoice" class="bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 px-2.5 py-1.5 rounded-xl font-semibold text-[11px] transition inline-flex items-center gap-1">
            <i class="fa-solid fa-list-check text-slate-500"></i> Detail
        </button>
    `;

    const batchDisplay = r.batch_no
        ? `<span class="font-mono text-slate-700 font-semibold text-xs whitespace-nowrap">${r.batch_no}</span>`
        : `<span class="text-slate-400 italic text-[11px]">-</span>`;
    const expDisplay = r.exp_date
        ? `<span class="font-mono text-slate-600 text-xs font-semibold whitespace-nowrap">${formatExpDate(r.exp_date)}</span>`
        : `<span class="text-slate-400 italic text-[11px]">-</span>`;

    tr.innerHTML = `
        <td class="p-3 whitespace-nowrap">
            <div class="font-bold text-slate-800 text-xs flex items-center gap-1.5">
                <i class="fa-regular fa-calendar text-indigo-500 text-[10px]"></i>
                <span>${date}</span>
            </div>
            <div class="text-[10px] text-slate-400 font-mono mt-0.5 flex items-center gap-1">
                <i class="fa-regular fa-clock text-[9px]"></i>
                <span>${time} WIB</span>
            </div>
        </td>
        <td class="p-3 whitespace-nowrap">
            <!-- Baris 1: Tombol Link Invoice -->
            <div>
                <button type="button" onclick="viewDetails(${r.session_id || r.id}, '${escapeHtml(condCode)}')" class="inline-flex items-center gap-1.5 font-bold font-mono text-indigo-700 hover:text-indigo-900 bg-indigo-50 hover:bg-indigo-100 px-2.5 py-1 rounded-lg border border-indigo-200/80 transition text-xs shadow-2xs group cursor-pointer" title="Klik untuk lihat detail invoice & foto bukti">
                    <i class="fa-solid fa-file-invoice text-indigo-500 group-hover:scale-110 transition"></i>
                    <span class="underline decoration-indigo-300 underline-offset-2">${r.invoice_number}</span>
                </button>
            </div>
            <!-- Baris 2: Badge Ekspedisi -->
            <div class="mt-1">
                ${expBadge}
            </div>
        </td>
        <td class="p-3 font-semibold text-slate-700 whitespace-nowrap">${r.operator_name}</td>
        <td class="p-3 min-w-[220px] max-w-[360px]">
            <div class="text-xs font-semibold text-slate-800 leading-snug whitespace-normal break-words">${r.product_name}</div>
            <div class="flex flex-wrap items-center gap-1.5 mt-1">
                ${r.seller_sku ? `<span class="font-mono font-bold text-indigo-700 bg-indigo-50/90 border border-indigo-200/80 px-2 py-0.5 rounded-md text-[10px] tracking-tight inline-flex items-center gap-1" title="Seller SKU"><i class="fa-solid fa-tag text-[9px] text-indigo-500"></i> ${escapeHtml(r.seller_sku)}</span>` : ''}
                ${r.barcode ? `<span class="text-[10px] font-mono text-slate-400 inline-flex items-center gap-1" title="Barcode"><i class="fa-solid fa-barcode text-[9px]"></i> ${escapeHtml(r.barcode)}</span>` : ''}
            </div>
        </td>
        <td class="p-3 whitespace-nowrap">${batchDisplay}</td>
        <td class="p-3 whitespace-nowrap">${expDisplay}</td>
        <td class="p-3 text-center whitespace-nowrap">
            <span class="font-black text-slate-800 text-xs px-2.5 py-1 bg-slate-100 border border-slate-200 rounded-lg inline-block text-center min-w-[28px]">${r.qty || 1}</span>
        </td>
        <td class="p-3 text-center whitespace-nowrap">${typeBadge}</td>
        <td class="p-3 text-center whitespace-nowrap">${videoActionsHtml}</td>
        <td class="p-3 text-center whitespace-nowrap">
            <div class="flex items-center justify-center gap-1.5">
                <button type="button" onclick="viewDetails(${r.session_id || r.id}, '${escapeHtml(condCode)}')" title="Lihat Detail Transaksi Unboxing" class="w-8 h-8 rounded-xl bg-slate-100 hover:bg-indigo-50 hover:text-indigo-600 text-slate-600 border border-slate-200 flex items-center justify-center transition shadow-2xs cursor-pointer">
                    <i class="fa-solid fa-eye text-xs"></i>
                </button>
                <button type="button" onclick="editUnboxingTransaction(${r.session_id || r.id}, ${r.item_id || 0})" title="Edit Data Transaksi & Produk" class="w-8 h-8 rounded-xl bg-slate-100 hover:bg-amber-50 hover:text-amber-600 text-slate-600 border border-slate-200 flex items-center justify-center transition shadow-2xs cursor-pointer">
                    <i class="fa-solid fa-pen-to-square text-xs"></i>
                </button>
                <button type="button" onclick="deleteUnboxingTransaction(${r.session_id || r.id}, '${escapeHtml(r.invoice_number || '')}')" title="Hapus Data Unboxing" class="w-8 h-8 rounded-xl bg-slate-100 hover:bg-rose-50 hover:text-rose-600 text-slate-600 border border-slate-200 flex items-center justify-center transition shadow-2xs cursor-pointer">
                    <i class="fa-solid fa-trash-can text-xs"></i>
                </button>
            </div>
        </td>
    `;
    return tr;
}

// Handler Reset Filter Inbound Unboxing
window.resetTransactionFilters = function () {
    if (typeof clearDateFilter === 'function') clearDateFilter();
    const exp = document.getElementById('filterExpedition');
    const cond = document.getElementById('filterCondition');
    const op = document.getElementById('filterOperator');
    const search = document.getElementById('filterSearch');

    if (exp) exp.value = '';
    if (cond) cond.value = '';
    if (op) op.value = '';
    if (search) search.value = '';

    loadTransactions();
};

// Handler Edit Transaksi Unboxing
window.editUnboxingTransaction = async function (sessionId, itemId) {
    if (!sessionId) return;

    showGlobalLoading("Memuat Data...", "Mengambil rincian transaksi unboxing untuk diedit...");

    try {
        const res = await fetch(`api/returns.php?action=get_edit&id=${sessionId}&item_id=${itemId || 0}`);
        const data = await res.json();
        hideGlobalLoading();

        if (!data.success || !data.session) {
            showToast('error', data.error || 'Gagal memuat data transaksi untuk diedit', 'Gagal Memuat');
            return;
        }

        const sess = data.session;
        const items = data.items || [];
        const focusItemId = data.focus_item_id || 0;

        document.getElementById('editUnboxSessionId').value = sess.id;
        document.getElementById('editUnboxInvoice').value = sess.invoice_number || '';
        document.getElementById('badgeEditUnboxInvoice').innerText = sess.invoice_number || 'INV-XXX';
        document.getElementById('editUnboxOperator').value = sess.operator_name || '';
        document.getElementById('editUnboxNotes').value = sess.notes || '';

        // Isi pilihan ekspedisi
        const expSelect = document.getElementById('editUnboxExpedition');
        if (expSelect) {
            let found = false;
            for (let opt of expSelect.options) {
                if (opt.value.toLowerCase() === (sess.expedition || '').toLowerCase()) {
                    opt.selected = true;
                    found = true;
                    break;
                }
            }
            if (!found && sess.expedition) {
                const newOpt = document.createElement('option');
                newOpt.value = sess.expedition;
                newOpt.textContent = sess.expedition;
                newOpt.selected = true;
                expSelect.appendChild(newOpt);
            }
        }

        // Render Daftar Item Produk
        const itemsContainer = document.getElementById('editUnboxItemsList');
        if (itemsContainer) {
            itemsContainer.innerHTML = '';
            if (items.length === 0) {
                itemsContainer.innerHTML = `<div class="p-4 bg-slate-50 border border-slate-200 rounded-xl text-center text-slate-400 text-xs italic">Tidak ada item tercatat dalam sesi ini.</div>`;
            } else {
                items.forEach((it, idx) => {
                    const isFocused = (focusItemId > 0 && it.id == focusItemId);
                    const condVal = (it.type || it.condition || 'GOOD').toUpperCase();

                    // Bangun opsi kondisi dari allConditions
                    let condOptions = `<option value="GOOD" ${condVal === 'GOOD' ? 'selected' : ''}>GOOD (Barang Bagus)</option>`;
                    if (typeof allConditions !== 'undefined' && Array.isArray(allConditions)) {
                        allConditions.forEach(c => {
                            if (c.code.toUpperCase() !== 'GOOD') {
                                const sel = (c.code.toUpperCase() === condVal) ? 'selected' : '';
                                condOptions += `<option value="${escapeHtml(c.code)}" ${sel}>${escapeHtml(c.name)} (${escapeHtml(c.code)})</option>`;
                            }
                        });
                    } else {
                        condOptions += `
                            <option value="RUSAK" ${condVal === 'RUSAK' ? 'selected' : ''}>RUSAK / CACAT</option>
                            <option value="KARDUS PENYOK" ${condVal === 'KARDUS PENYOK' ? 'selected' : ''}>KARDUS PENYOK</option>
                            <option value="PECAH" ${condVal === 'PECAH' ? 'selected' : ''}>PECAH / BOCOR</option>
                            <option value="SALAH KIRIM" ${condVal === 'SALAH KIRIM' ? 'selected' : ''}>SALAH KIRIM</option>
                        `;
                    }

                    const card = document.createElement('div');
                    card.className = `p-4 rounded-2xl border transition ${isFocused ? 'bg-amber-50/50 border-amber-300 ring-2 ring-amber-400/40' : 'bg-slate-50/70 border-slate-200'}`;
                    card.innerHTML = `
                        <input type="hidden" class="edit-item-id" value="${it.id}">
                        <div class="flex items-center justify-between mb-2.5 pb-2 border-b border-slate-200">
                            <span class="font-bold text-slate-800 text-xs flex items-center gap-1.5">
                                <span class="w-5 h-5 rounded-md bg-indigo-600 text-white flex items-center justify-center text-[10px] font-black">${idx + 1}</span>
                                <span>Item #${idx + 1}</span>
                            </span>
                            <span class="font-mono text-[10px] text-slate-400">Barcode: <b class="text-slate-700">${escapeHtml(it.barcode || '-')}</b></span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-2.5 text-xs">
                            <div class="sm:col-span-2">
                                <label class="block font-semibold text-slate-600 text-[11px] mb-1">Nama Produk</label>
                                <input type="text" class="edit-item-name w-full bg-white border border-slate-300 rounded-xl px-3 py-1.5 text-xs font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500" value="${escapeHtml(it.product_name || '')}">
                            </div>
                            <div>
                                <label class="block font-semibold text-slate-600 text-[11px] mb-1">Seller SKU / SKU</label>
                                <input type="text" class="edit-item-sku w-full bg-white border border-slate-300 rounded-xl px-3 py-1.5 text-xs font-mono font-bold text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500" value="${escapeHtml(it.seller_sku || it.sku || '')}">
                            </div>
                            <div>
                                <label class="block font-semibold text-slate-600 text-[11px] mb-1">Barcode Scan</label>
                                <input type="text" class="edit-item-barcode w-full bg-white border border-slate-300 rounded-xl px-3 py-1.5 text-xs font-mono font-bold text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500" value="${escapeHtml(it.barcode || '')}">
                            </div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-3 md:grid-cols-6 gap-2.5 text-xs mt-2.5">
                            <div>
                                <label class="block font-semibold text-slate-600 text-[11px] mb-1">Qty</label>
                                <input type="number" min="1" class="edit-item-qty w-full bg-white border border-slate-300 rounded-xl px-3 py-1.5 text-xs font-black text-center text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500" value="${it.qty || 1}">
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block font-semibold text-slate-600 text-[11px] mb-1">Kondisi / Tipe</label>
                                <select class="edit-item-type w-full bg-white border border-slate-300 rounded-xl px-3 py-1.5 text-xs font-bold text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                    ${condOptions}
                                </select>
                            </div>
                            <div>
                                <label class="block font-semibold text-slate-600 text-[11px] mb-1">Batch No</label>
                                <input type="text" class="edit-item-batch w-full bg-white border border-slate-300 rounded-xl px-3 py-1.5 text-xs font-mono text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500" value="${escapeHtml(it.batch_no || '-')}">
                            </div>
                            <div>
                                <label class="block font-semibold text-slate-600 text-[11px] mb-1">Exp Date</label>
                                <input type="text" class="edit-item-exp w-full bg-white border border-slate-300 rounded-xl px-3 py-1.5 text-xs font-mono text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500" value="${escapeHtml(it.exp_date || '-')}">
                            </div>
                            <div>
                                <label class="block font-semibold text-slate-600 text-[11px] mb-1">Detail Rusak / Note</label>
                                <input type="text" class="edit-item-reason w-full bg-white border border-slate-300 rounded-xl px-3 py-1.5 text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="Catatan kerusakan..." value="${escapeHtml(it.damage_reason || '')}">
                            </div>
                        </div>
                    `;
                    itemsContainer.appendChild(card);
                });
            }
        }

        const modal = document.getElementById('modalEditUnboxing');
        if (modal) modal.classList.remove('hidden');

    } catch (err) {
        hideGlobalLoading();
        showToast('error', 'Gagal membuka form edit: ' + err.message, 'Koneksi Error');
    }
};

window.closeEditUnboxingModal = function () {
    const modal = document.getElementById('modalEditUnboxing');
    if (modal) modal.classList.add('hidden');
};

window.editCurrentModalSession = function () {
    if (currentModalSessionId) {
        closeDetailModal();
        editUnboxingTransaction(currentModalSessionId, 0);
    }
};

window.saveEditUnboxingTransaction = async function (event) {
    if (event) event.preventDefault();

    const sessionId = document.getElementById('editUnboxSessionId').value;
    const invoiceNumber = document.getElementById('editUnboxInvoice').value.trim();
    const expedition = document.getElementById('editUnboxExpedition').value;
    const operatorName = document.getElementById('editUnboxOperator').value.trim();
    const notes = document.getElementById('editUnboxNotes').value.trim();

    if (!sessionId || !invoiceNumber) {
        showToast('warning', 'Nomor invoice wajib diisi!', 'Peringatan');
        return;
    }

    const items = [];
    const itemCards = document.querySelectorAll('#editUnboxItemsList > div');
    itemCards.forEach(c => {
        const id = c.querySelector('.edit-item-id')?.value;
        const name = c.querySelector('.edit-item-name')?.value.trim();
        const sku = c.querySelector('.edit-item-sku')?.value.trim();
        const barcode = c.querySelector('.edit-item-barcode')?.value.trim();
        const qty = parseInt(c.querySelector('.edit-item-qty')?.value || '1', 10);
        const type = c.querySelector('.edit-item-type')?.value;
        const batch = c.querySelector('.edit-item-batch')?.value.trim();
        const exp = c.querySelector('.edit-item-exp')?.value.trim();
        const reason = c.querySelector('.edit-item-reason')?.value.trim();

        if (id) {
            items.push({
                id: id,
                product_name: name,
                sku: sku,
                seller_sku: sku,
                barcode: barcode,
                qty: qty,
                type: type,
                batch_no: batch,
                exp_date: exp,
                damage_reason: reason
            });
        }
    });

    const payload = {
        session_id: sessionId,
        invoice_number: invoiceNumber,
        expedition: expedition,
        operator_name: operatorName,
        notes: notes,
        items: items
    };

    const btnSave = document.getElementById('btnSaveEditUnboxing');
    if (btnSave) {
        btnSave.disabled = true;
        btnSave.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> Menyimpan...`;
    }

    try {
        const res = await fetch('api/returns.php?action=update', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();

        if (btnSave) {
            btnSave.disabled = false;
            btnSave.innerHTML = `<i class="fa-solid fa-floppy-disk"></i> <span>Simpan Perubahan</span>`;
        }

        if (data.success) {
            showToast('success', data.message || `Data transaksi unboxing [${invoiceNumber}] berhasil disimpan!`, 'Perubahan Disimpan');
            closeEditUnboxingModal();
            loadTransactions();
            if (typeof loadDashboardMetrics === 'function') loadDashboardMetrics();
        } else {
            showToast('error', data.error || 'Gagal menyimpan perubahan transaksi unboxing', 'Gagal Simpan');
        }
    } catch (err) {
        if (btnSave) {
            btnSave.disabled = false;
            btnSave.innerHTML = `<i class="fa-solid fa-floppy-disk"></i> <span>Simpan Perubahan</span>`;
        }
        showToast('error', 'Terjadi kesalahan koneksi: ' + err.message, 'Koneksi Terputus');
    }
};

// Handler Hapus Transaksi Unboxing
window.deleteUnboxingTransaction = async function (sessionId, invoiceNumber) {
    if (!sessionId) return;
    const invText = invoiceNumber ? `[${invoiceNumber}]` : 'ini';

    if (!confirm(`Apakah Anda yakin ingin menghapus data unboxing invoice ${invText}?\n\nSemua data produk, foto bukti, dan rekaman video unboxing terkait akan dihapus secara permanen.`)) {
        return;
    }

    try {
        const res = await fetch(`api/returns.php?action=delete&id=${sessionId}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: sessionId })
        });
        const data = await res.json();
        if (data.success) {
            showToast('success', data.message || `Data transaksi unboxing ${invText} berhasil dihapus!`, 'Berhasil Dihapus');
            if (typeof loadTransactions === 'function') loadTransactions();
            if (typeof loadDashboardMetrics === 'function') loadDashboardMetrics();
            const modal = document.getElementById('transactionDetailModal');
            if (modal && !modal.classList.contains('hidden')) {
                closeDetailModal();
            }
        } else {
            showToast('error', data.error || 'Gagal menghapus data transaksi unboxing', 'Gagal Hapus');
        }
    } catch (err) {
        showToast('error', 'Terjadi kesalahan koneksi: ' + err.message, 'Koneksi Terputus');
    }
};

window.deleteCurrentModalSession = function () {
    if (currentModalSessionId) {
        deleteUnboxingTransaction(currentModalSessionId, currentModalInvoice);
    }
};

// 2b. Putar Video Unboxing Langsung (Play Video Action)
window.playTransactionVideo = async function (id) {
    await viewDetails(id);
    const videoPlayer = document.getElementById('modalVideoPlayer');
    if (videoPlayer) {
        videoPlayer.currentTime = 0;
        setTimeout(() => {
            const p = videoPlayer.play();
            if (p !== undefined) {
                p.catch(err => {
                    console.log("Autoplay dicegah oleh browser, user dapat klik play kontrol:", err);
                });
            }
        }, 150);
    }
};

let currentModalSessionId = null;
let currentModalInvoice = '';

// 2b. View Details & Video Player Modal Sesuai Tipe Kondisi
window.viewDetails = async function (id, focusCondition) {
    const r = cachedTransactions.find(t => (t.session_id && t.session_id == id) || t.id == id);
    if (!r) return;

    const modal = document.getElementById('transactionDetailModal');
    if (!modal) return;

    const targetSessionId = r.session_id || r.id;
    currentModalSessionId = targetSessionId;
    currentModalInvoice = r.invoice_number || '';
    const condCode = (focusCondition || r.raw_type || r.condition_type || 'GOOD').toUpperCase();

    const initCond = (focusCondition || r.raw_type || r.condition_type || '').toUpperCase().trim();
    const isInitGood = (initCond === 'GOOD' || initCond === 'BAGUS' || initCond === 'LAYAK' || initCond === '');
    const initQty = parseInt(r.total_qty || r.qty || 1, 10);
    const initGood = parseInt(r.total_good !== undefined ? r.total_good : (isInitGood ? initQty : 0), 10);
    const initDamaged = parseInt(r.total_damaged !== undefined ? r.total_damaged : (!isInitGood ? initQty : 0), 10);

    document.getElementById('modalDetailInvoice').innerText = r.invoice_number;
    document.getElementById('modalDetailExpedition').innerText = r.expedition || 'Reguler';
    document.getElementById('modalDetailMeta').innerText = `Operator: ${r.operator_name} • ${new Date(r.created_at).toLocaleString('id-ID')}`;
    document.getElementById('modalTotalUnit').innerText = initQty;
    document.getElementById('modalTotalGood').innerText = initGood;
    document.getElementById('modalTotalDamaged').innerText = initDamaged;
    document.getElementById('modalNotes').innerText = r.notes || 'Tidak ada catatan.';

    // Tampilkan Badge Tipe Kondisi pada Header Modal
    const condBadge = document.getElementById('modalDetailConditionBadge');
    if (condBadge) {
        if (condCode) {
            const isGood = condCode === 'GOOD';
            condBadge.className = isGood
                ? "text-[10px] font-bold px-2.5 py-0.5 rounded-lg border font-mono shadow-2xs bg-emerald-50 text-emerald-700 border-emerald-300"
                : "text-[10px] font-bold px-2.5 py-0.5 rounded-lg border font-mono shadow-2xs bg-rose-50 text-rose-700 border-rose-300";
            condBadge.innerText = `Kondisi: ${condCode}`;
            condBadge.classList.remove('hidden');
        } else {
            condBadge.classList.add('hidden');
        }
    }

    // Tampilkan Alert Alasan Kerusakan / Catatan Kondisi
    const dmgAlert = document.getElementById('modalDetailDamageAlert');
    const dmgTxt = document.getElementById('modalDetailDamageText');
    if (dmgAlert && dmgTxt) {
        const reason = r.damage_reason || (condCode !== 'GOOD' ? r.notes : '');
        if (reason && reason.trim()) {
            dmgTxt.innerText = reason;
            dmgAlert.classList.remove('hidden');
        } else {
            dmgAlert.classList.add('hidden');
        }
    }

    // Setup Watermark Overlay pada Video Player
    const wmInv = document.getElementById('watermarkInvoice');
    if (wmInv) wmInv.innerText = `INV: ${r.invoice_number}`;
    const wmExp = document.getElementById('watermarkExpedition');
    if (wmExp) wmExp.innerText = `KURIR: ${r.expedition || 'Reguler'}`;
    const wmTime = document.getElementById('watermarkTime');
    if (wmTime) wmTime.innerHTML = `<i class="fa-regular fa-clock text-indigo-400 mr-1"></i> ${new Date(r.created_at).toLocaleString('id-ID')} WIB`;

    // Setup Video Player
    const videoPlayer = document.getElementById('modalVideoPlayer');
    const noVideoNotice = document.getElementById('modalNoVideo');
    const videoBadge = document.getElementById('modalVideoStatusBadge');
    const videoFilename = document.getElementById('modalVideoFilename');
    const downloadBtn = document.getElementById('modalDownloadVideoBtn');
    const wmOverlay = document.getElementById('modalVideoWatermark');

    if (r.video_path && r.video_path.trim() !== '') {
        videoPlayer.src = r.video_path;
        videoPlayer.classList.remove('hidden');
        noVideoNotice.classList.add('hidden');
        if (wmOverlay) wmOverlay.classList.remove('hidden');
        videoBadge.className = "text-[10px] font-semibold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200";
        videoBadge.innerHTML = `<i class="fa-solid fa-circle-check text-emerald-500"></i> Rekaman Tersedia`;
        videoFilename.innerText = r.video_path.split('/').pop();
        downloadBtn.href = r.video_path;
        downloadBtn.download = `${r.invoice_number || 'inbound'}_unboxing.webm`;
        downloadBtn.classList.remove('hidden');
        videoPlayer.load();
    } else {
        videoPlayer.pause();
        videoPlayer.src = '';
        videoPlayer.classList.add('hidden');
        noVideoNotice.classList.remove('hidden');
        if (wmOverlay) wmOverlay.classList.add('hidden');
        videoBadge.className = "text-[10px] font-semibold text-amber-600 bg-amber-50 px-2 py-0.5 rounded border border-amber-200";
        videoBadge.innerHTML = `<i class="fa-solid fa-circle-exclamation text-amber-500"></i> Tanpa Video`;
        videoFilename.innerText = 'Tidak ada file rekaman';
        downloadBtn.classList.add('hidden');
    }

    // ---- RENDER FOTO DOKUMENTASI SESUAI TIPE KONDISI ----
    const photosSection = document.getElementById('modalPhotosSection');
    const photosGrid = document.getElementById('modalPhotosGrid');
    const photoCount = document.getElementById('modalPhotoCount');

    // Kumpulkan semua foto: photos (JSON array), package_photo, product_photo
    const allPhotos = [];
    const seenUrls = new Set();
    const isSessionDamaged = (parseInt(r.total_damaged, 10) > 0 || (condCode && condCode !== 'GOOD' && condCode !== 'BAGUS'));

    function addPhotoUnique(photoObj) {
        if (!photoObj || !photoObj.url) return;
        const rawUrl = String(photoObj.url).trim();
        const cleanUrl = rawUrl.replace(/^(\.\.\/|\/)+/, '');
        if (!cleanUrl || seenUrls.has(cleanUrl)) return;
        seenUrls.add(cleanUrl);
        allPhotos.push(photoObj);
    }

    // 1. Ambil dari r.photos (array lengkap hasil unboxing dengan badge & title spesifik)
    if (r.photos) {
        let extraPhotos = r.photos;
        if (typeof extraPhotos === 'string') {
            try { extraPhotos = JSON.parse(extraPhotos); } catch (e) { extraPhotos = []; }
        }
        if (Array.isArray(extraPhotos)) {
            extraPhotos.forEach((p, idx) => {
                const url = (typeof p === 'object') ? (p.path || p.url || '') : p;
                if (!url || !url.trim()) return;
                let pType = (typeof p === 'object' && p.type) ? p.type : (condCode || 'BUKTI');
                const isObjDmg = (typeof p === 'object' && (p.isDamaged || p.is_damaged || p.badge === 'Barang Rusak' || pType === 'damaged' || String(p.title || '').toLowerCase().includes('rusak'))) || (pType === 'damaged');
                const isPkg = (pType === 'package' || String(p.title || '').toLowerCase().includes('paket'));
                let label = (typeof p === 'object' && p.title) ? p.title : '';
                if (!label) {
                    if (isPkg) label = `📦 Foto Paket Unboxing #${idx + 1}`;
                    else if (isObjDmg) label = `⚠️ Foto Bukti Barang Rusak #${idx + 1}`;
                    else label = `🏷️ Foto Produk Unboxing #${idx + 1}`;
                }
                addPhotoUnique({
                    url: url.trim(),
                    label: label,
                    type: isPkg ? 'PAKET' : (isObjDmg ? 'damaged' : pType),
                    isDamaged: isObjDmg
                });
            });
        }
    }

    // 2. Fallback Foto Paket jika belum tercakup
    if (r.package_photo && r.package_photo.trim()) {
        const hasPkg = allPhotos.some(p => p.type === 'PAKET');
        if (!hasPkg) {
            addPhotoUnique({ url: r.package_photo.trim(), label: '📦 Foto Paket Sebelum Unboxing', type: 'PAKET', isDamaged: false });
        }
    }

    // 3. Fallback Foto Produk jika belum ada foto produk sama sekali
    if (r.product_photo && r.product_photo.trim()) {
        const hasProdOrDmg = allPhotos.some(p => p.type !== 'PAKET');
        if (!hasProdOrDmg) {
            const isDmgProduct = isSessionDamaged;
            addPhotoUnique({
                url: r.product_photo.trim(),
                label: isDmgProduct ? `⚠️ Foto Bukti Barang Rusak (${condCode || 'RUSAK'})` : `🏷️ Foto Produk (${condCode || 'GOOD'})`,
                type: isDmgProduct ? 'damaged' : (condCode || 'GOOD'),
                isDamaged: isDmgProduct
            });
        }
    }

    // Prioritaskan foto barang rusak dan yang sesuai kondisi yang sedang dilihat di urutan pertama
    allPhotos.sort((a, b) => {
        const aDmg = a.isDamaged || (a.type || '').toUpperCase() === 'DAMAGED';
        const bDmg = b.isDamaged || (b.type || '').toUpperCase() === 'DAMAGED';
        if (aDmg && !bDmg) return -1;
        if (!aDmg && bDmg) return 1;

        const aMatch = (a.type || '').toUpperCase() === condCode;
        const bMatch = (b.type || '').toUpperCase() === condCode;
        if (aMatch && !bMatch) return -1;
        if (!aMatch && bMatch) return 1;
        return 0;
    });

    if (photosGrid) photosGrid.innerHTML = '';
    if (allPhotos.length > 0) {
        if (photosSection) photosSection.classList.remove('hidden');
        if (photoCount) photoCount.innerText = `${allPhotos.length} Foto (${condCode})`;
        allPhotos.forEach(photo => {
            const isDamagedPhoto = photo.isDamaged || (photo.type || '').toLowerCase() === 'damaged' || ((photo.type || '').toUpperCase() === condCode && condCode !== 'GOOD');
            const imgWrap = document.createElement('div');
            imgWrap.className = `relative group cursor-pointer rounded-xl overflow-hidden border ${isDamagedPhoto ? 'border-rose-500 ring-2 ring-rose-400' : 'border-slate-200'} bg-slate-100 aspect-square shadow-xs hover:shadow-md transition`;
            imgWrap.onclick = () => {
                const lb = document.getElementById('modalPhotoLightbox');
                const lbImg = document.getElementById('modalPhotoLightboxImg');
                const lbTitle = document.getElementById('modalPhotoLightboxTitle');
                const lbTag = document.getElementById('modalPhotoLightboxTag');
                const lbDl = document.getElementById('modalPhotoLightboxDownload');
                if (lb && lbImg) {
                    lbImg.src = photo.url;
                    if (lbDl) {
                        lbDl.href = photo.url;
                        lbDl.setAttribute('download', `unboxing_${r.invoice_number || 'photo'}_${photo.type || 'bukti'}.jpg`);
                    }
                    if (lbTitle) lbTitle.innerText = `${photo.label} • Invoice: ${r.invoice_number || '-'}`;
                    if (lbTag) {
                        lbTag.className = isDamagedPhoto
                            ? "px-2.5 py-0.5 rounded-lg font-mono font-bold text-[10px] bg-rose-600 text-white shadow-2xs"
                            : "px-2.5 py-0.5 rounded-lg font-mono font-bold text-[10px] bg-emerald-600 text-white shadow-2xs";
                        lbTag.innerText = isDamagedPhoto ? `KONDISI: ${condCode || 'RUSAK'}` : `KONDISI: ${condCode || 'GOOD'}`;
                    }
                    lb.classList.remove('hidden');
                    lb.classList.add('flex');
                }
            };
            imgWrap.innerHTML = `
                <img src="${photo.url}" alt="${photo.label}" loading="lazy"
                    class="w-full h-full object-cover group-hover:scale-105 transition duration-300"
                    onerror="this.parentElement.innerHTML='<div class=\\'flex flex-col items-center justify-center h-full text-slate-400 text-[10px] p-2 text-center\\'><i class=\\'fa-solid fa-image-slash text-2xl mb-1\\'></i>Foto tidak ditemukan</div>'">
                <div class="absolute bottom-0 left-0 right-0 ${isDamagedPhoto ? 'bg-rose-950/80 text-rose-100' : 'bg-black/60 text-white'} text-[9px] font-semibold px-2 py-1 flex items-center justify-between transition truncate">
                    <span class="truncate">${photo.label}</span>
                    <span class="${isDamagedPhoto ? 'bg-rose-600' : 'bg-indigo-600/80'} px-1.5 py-0.5 rounded text-[8px] shrink-0 font-mono font-bold">${isDamagedPhoto ? 'RUSAK' : (photo.type || 'FOTO')}</span>
                </div>
            `;
            if (photosGrid) photosGrid.appendChild(imgWrap);
        });
    } else {
        if (photosSection) photosSection.classList.add('hidden');
    }

    // Load Items List
    const tbody = document.getElementById('modalItemsTableBody');
    tbody.innerHTML = `<tr><td colspan="5" class="text-center py-4 text-slate-400"><i class="fa-solid fa-spinner fa-spin mr-1"></i> Memuat detail item...</td></tr>`;

    modal.classList.remove('hidden');

    try {
        const res = await fetch(`api/admin/session_items.php?session_id=${targetSessionId}`);
        const items = await res.json();
        tbody.innerHTML = '';
        if (!items || items.length === 0) {
            tbody.innerHTML = `<tr><td colspan="5" class="text-center py-4 text-slate-400">Tidak ada rincian produk untuk invoice ini.</td></tr>`;
            document.getElementById('modalItemCount').innerText = 0;
            return;
        }

        document.getElementById('modalItemCount').innerText = items.length;

        // Hitung ulang akumulasi Total Unit, Good, dan Rusak dari seluruh item sesi ini
        let sumTotal = 0;
        let sumGood = 0;
        let sumDamaged = 0;
        items.forEach(it => {
            const q = parseInt(it.qty, 10) || 1;
            sumTotal += q;
            const itCond = (it.type || it.condition || 'GOOD').toUpperCase().trim();
            if (itCond === 'GOOD' || itCond === 'BAGUS' || itCond === 'LAYAK') {
                sumGood += q;
            } else {
                sumDamaged += q;
            }
        });
        document.getElementById('modalTotalUnit').innerText = sumTotal;
        document.getElementById('modalTotalGood').innerText = sumGood;
        document.getElementById('modalTotalDamaged').innerText = sumDamaged;
        // Tambahkan foto produk dari masing-masing item ke galeri foto jika belum ada
        let hasItemPhotosAdded = false;
        items.forEach(it => {
            if (it.photo_path) {
                const itClean = String(it.photo_path).trim().replace(/^(\.\.\/|\/)+/, '');
                if (itClean && !seenUrls.has(itClean)) {
                    const itC = (it.type || it.condition || 'GOOD').toUpperCase().trim();
                    const isDmg = (itC !== 'GOOD' && itC !== 'BAGUS' && itC !== 'LAYAK');
                    if (isDmg && sumDamaged === 1 && allPhotos.some(p => p.isDamaged)) {
                        return;
                    }
                    seenUrls.add(itClean);
                    allPhotos.push({
                        url: it.photo_path,
                        label: isDmg ? `⚠️ Foto Bukti Rusak: ${it.product_name || it.barcode} (${itC})` : `Foto Item: ${it.product_name || it.barcode}`,
                        type: isDmg ? 'damaged' : 'product',
                        isDamaged: isDmg
                    });
                    hasItemPhotosAdded = true;
                }
            }
        });

        // Re-render galeri foto atas jika ada foto item baru
        if (hasItemPhotosAdded && photosGrid) {
            allPhotos.sort((a, b) => {
                const aDmg = a.isDamaged || (a.type || '').toUpperCase() === 'DAMAGED';
                const bDmg = b.isDamaged || (b.type || '').toUpperCase() === 'DAMAGED';
                if (aDmg && !bDmg) return -1;
                if (!aDmg && bDmg) return 1;
                return 0;
            });
            photosGrid.innerHTML = '';
            if (photosSection) photosSection.classList.remove('hidden');
            if (photoCount) photoCount.innerText = `${allPhotos.length} Foto (${condCode})`;
            allPhotos.forEach(photo => {
                const isDamagedPhoto = photo.isDamaged || (photo.type || '').toLowerCase() === 'damaged' || ((photo.type || '').toUpperCase() === condCode && condCode !== 'GOOD');
                const imgWrap = document.createElement('div');
                imgWrap.className = `relative group cursor-pointer rounded-xl overflow-hidden border ${isDamagedPhoto ? 'border-rose-500 ring-2 ring-rose-400' : 'border-slate-200'} bg-slate-100 aspect-square shadow-xs hover:shadow-md transition`;
                imgWrap.onclick = () => {
                    openPhotoLightboxDirect(photo.url, `${photo.label} • Invoice: ${r.invoice_number || '-'}`, condCode || (isDamagedPhoto ? 'RUSAK' : 'GOOD'), isDamagedPhoto);
                };
                imgWrap.innerHTML = `
                    <img src="${photo.url}" alt="${photo.label}" loading="lazy"
                        class="w-full h-full object-cover group-hover:scale-105 transition duration-300"
                        onerror="this.parentElement.innerHTML='<div class=\\'flex flex-col items-center justify-center h-full text-slate-400 text-[10px] p-2 text-center\\'><i class=\\'fa-solid fa-image-slash text-2xl mb-1\\'></i>Foto tidak ditemukan</div>'">
                    <div class="absolute bottom-0 left-0 right-0 ${isDamagedPhoto ? 'bg-rose-950/80 text-rose-100' : 'bg-black/60 text-white'} text-[9px] font-semibold px-2 py-1 flex items-center justify-between transition truncate">
                        <span class="truncate">${photo.label}</span>
                        <span class="${isDamagedPhoto ? 'bg-rose-600' : 'bg-indigo-600/80'} px-1.5 py-0.5 rounded text-[8px] shrink-0 font-mono font-bold">${isDamagedPhoto ? 'RUSAK' : (photo.type || 'FOTO')}</span>
                    </div>
                `;
                photosGrid.appendChild(imgWrap);
            });
        }

        items.forEach(it => {
            const tr = document.createElement('tr');
            const itCond = (it.type || it.condition || 'GOOD').toUpperCase();
            const isMatch = itCond === condCode;
            tr.className = `hover:bg-slate-50 border-b border-slate-100 text-xs ${isMatch ? 'bg-indigo-50/50' : ''}`;
            const isGood = itCond === 'GOOD' || itCond === 'BAGUS' || itCond === 'LAYAK';
            const badgeCond = isGood ?
                `<span class="bg-emerald-50 text-emerald-700 font-bold px-2 py-0.5 rounded text-[10px] border border-emerald-200">GOOD</span>` :
                `<span class="bg-rose-50 text-rose-700 font-bold px-2 py-0.5 rounded text-[10px] border border-rose-200">${escapeHtml(itCond)}</span>`;

            let photoBtn = '';
            if (it.photo_path) {
                photoBtn = `
                    <div class="mt-1">
                        <button type="button" onclick="openPhotoLightboxDirect('${escapeHtml(it.photo_path)}', 'Foto Bukti Barang Rusak: ${escapeHtml(it.product_name || it.barcode)}', '${escapeHtml(itCond)}', true)" 
                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-rose-100 hover:bg-rose-200 text-rose-800 font-bold text-[10px] border border-rose-300 shadow-2xs transition cursor-pointer">
                            <i class="fa-solid fa-camera text-rose-600"></i> Bukti Foto (${escapeHtml(itCond)})
                        </button>
                    </div>
                `;
            }

            tr.innerHTML = `
                <td class="p-2.5 font-mono font-bold text-slate-700">${escapeHtml(it.barcode || '-')}</td>
                <td class="p-2.5">
                    <div class="font-medium text-slate-800">${escapeHtml(it.product_name || '-')}</div>
                    ${it.wrong_barcode ? `<div class="text-[10px] text-purple-700 font-semibold mt-0.5"><i class="fa-solid fa-arrows-split-up-and-left mr-1 text-[9px]"></i>Fisik Salah Kirim: <b>${escapeHtml(it.wrong_product_name || '-')}</b> <span class="font-mono text-[9px]">(${escapeHtml(it.wrong_barcode)})</span></div>` : ''}
                    ${it.damage_reason ? `<div class="text-[10px] text-rose-600 font-medium italic mt-0.5"><i class="fa-solid fa-circle-exclamation mr-1 text-[9px]"></i>${escapeHtml(it.damage_reason)}</div>` : ''}
                    ${photoBtn}
                </td>
                <td class="p-2.5 text-slate-500 font-mono text-[11px] whitespace-nowrap">${escapeHtml(it.batch_no || '-')} / ${formatExpDate(it.exp_date)}</td>
                <td class="p-2.5 text-center font-bold text-slate-800">${it.qty || 1}</td>
                <td class="p-2.5 text-center">${badgeCond}</td>
            `;
            tbody.appendChild(tr);
        });
    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="5" class="text-center py-4 text-rose-500 font-semibold">Gagal memuat item: ${e.message}</td></tr>`;
    }
};

window.openPhotoLightboxDirect = function (url, title, condition, isDamaged) {
    const lb = document.getElementById('modalPhotoLightbox');
    const lbImg = document.getElementById('modalPhotoLightboxImg');
    const lbTitle = document.getElementById('modalPhotoLightboxTitle');
    const lbTag = document.getElementById('modalPhotoLightboxTag');
    const lbDl = document.getElementById('modalPhotoLightboxDownload');
    if (lb && lbImg) {
        lbImg.src = url;
        if (lbDl) {
            lbDl.href = url;
            lbDl.setAttribute('download', `foto_barang_${condition || 'rusak'}.jpg`);
        }
        if (lbTitle) lbTitle.innerText = title || 'Foto Bukti Barang';
        if (lbTag) {
            lbTag.className = isDamaged
                ? "px-2.5 py-0.5 rounded-lg font-mono font-bold text-[10px] bg-rose-600 text-white shadow-2xs"
                : "px-2.5 py-0.5 rounded-lg font-mono font-bold text-[10px] bg-emerald-600 text-white shadow-2xs";
            lbTag.innerText = `KONDISI: ${condition || 'RUSAK'}`;
        }
        lb.classList.remove('hidden');
        lb.classList.add('flex');
    }
};

window.closeDetailModal = function () {
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
    const quickTbody = document.getElementById('quickProductsTableBody');
    const fullTbody = document.getElementById('fullProductsTableBody');
    if (quickTbody) quickTbody.innerHTML = `<tr><td colspan="4" class="text-center py-6 text-slate-400 text-xs"><i class="fa-solid fa-spinner fa-spin mr-1.5 text-indigo-500"></i>Memuat produk...</td></tr>`;
    if (fullTbody) fullTbody.innerHTML = `<tr><td colspan="8" class="text-center py-10 text-slate-400 text-xs"><i class="fa-solid fa-spinner fa-spin mr-1.5 text-indigo-500 text-base"></i>Memuat master data produk...</td></tr>`;

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
        fullTbody.innerHTML = `<tr><td colspan="5" class="text-center py-8 text-slate-400">Tidak ada produk yang cocok dengan pencarian / filter toko.</td></tr>`;
        return;
    }

    products.forEach((p, idx) => {
        const tr = document.createElement('tr');
        tr.className = 'hover:bg-slate-50 border-b border-slate-100 transition';
        const sellerSku = p.seller_sku || p.sku || '-';
        const shop = p.shop || p.category || '-';
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
            <td class="p-3 text-slate-400 font-mono text-xs text-center whitespace-nowrap">${idx + 1}</td>
            <td class="p-3 whitespace-nowrap">
                <span class="bg-purple-50 text-purple-700 border border-purple-200 px-2.5 py-1 rounded-md font-semibold text-xs inline-block">${shop}</span>
            </td>
            <td class="p-3 whitespace-nowrap">
                <div class="flex flex-col space-y-1">
                    <div class="flex items-center gap-2">
                        <span class="text-[9px] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded bg-slate-100 text-slate-500 border border-slate-200 w-16 text-center shrink-0">Barcode</span>
                        <span class="font-mono font-bold text-slate-800 text-xs">${barcode}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-[9px] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded bg-indigo-50 text-indigo-700 border border-indigo-200 w-16 text-center shrink-0">Seller SKU</span>
                        <span class="font-mono font-bold text-indigo-700 text-xs">${sellerSku}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-[9px] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded bg-amber-50 text-amber-700 border border-amber-200 w-16 text-center shrink-0">SAP Code</span>
                        <span class="font-mono text-xs">${sapCode}</span>
                    </div>
                </div>
            </td>
            <td class="p-3 font-semibold text-slate-800 whitespace-normal break-words max-w-[220px]">${p.name}</td>
            <td class="p-3 font-mono text-slate-600 text-xs whitespace-nowrap">${barcodeBpom}</td>
        `;
        fullTbody.appendChild(tr);
    });
}

// Sinkronisasi data dari OCS WMS IEG System dengan Animasi Bola Merah Kuning Hijau
window.syncProductsFromOCS = async function () {
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
            showToast('success', `Total ${data.total_synced || 0} produk dan rak dari OCS WMS berhasil diperbarui ke database.`, 'Sinkronisasi Berhasil');
        } else {
            hideGlobalLoading();
            showToast('error', data.error || 'Terjadi kesalahan sistem', 'Sinkronisasi Gagal');
        }
    } catch (err) {
        hideGlobalLoading();
        showToast('error', 'Gagal menghubungi server sync: ' + err.message, 'Koneksi Terputus');
    } finally {
        hideGlobalLoading();
        if (btn) btn.disabled = false;
        if (icon) icon.classList.remove('fa-spin');
    }
};

// Filter produk berdasarkan Keyword & Pilihan Toko / Shop
window.filterProductTable = function () {
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
window.openAddProductModal = function () {
    document.getElementById('addProductModal').classList.remove('hidden');
    document.getElementById('newBarcode').focus();
};

window.closeAddProductModal = function () {
    document.getElementById('addProductModal').classList.add('hidden');
    document.getElementById('formAddProduct').reset();
};

window.submitNewProduct = async function (e) {
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
            showToast('success', `Produk "${name}" berhasil ditambahkan ke database!`, 'Produk Tersimpan');
            closeAddProductModal();
            loadProducts();
        } else {
            showToast('error', result.error || 'Terjadi kesalahan', 'Gagal Menambah Produk');
        }
    } catch (err) {
        showToast('error', "Gagal koneksi ke server: " + err.message, 'Koneksi Terputus');
    } finally {
        btn.disabled = false;
        btn.innerHTML = `<i class="fa-solid fa-save"></i> Simpan ke MySQL`;
    }
};



// Filter Transaksi
const btnApply = document.getElementById('btnApplyFilter');
if (btnApply) btnApply.addEventListener('click', loadTransactions);

// ==============================================================
// MODUL EKSPOR EXCEL NATIVE (.XLSX ASLI - 100% BEBAS CORRUPT)
// MENGGUNAKAN SHEETJS (STANDAR OPENXML MS EXCEL, WPS & G-SHEETS)
// ==============================================================
function generateExcelFile(sheets, defaultFileName) {
    if (typeof XLSX === 'undefined') {
        showToast('warning', "Library Excel sedang diunduh oleh browser, mohon coba kembali dalam 2 detik.", "Mohon Tunggu");
        return;
    }

    try {
        const wb = XLSX.utils.book_new();

        sheets.forEach(sheet => {
            const ws = XLSX.utils.json_to_sheet(sheet.data);

            // Auto-fit kolom agar rapi dan tidak terpotong saat dibuka di Excel
            if (sheet.data && sheet.data.length > 0) {
                const keys = Object.keys(sheet.data[0]);
                const colWidths = keys.map(k => {
                    let maxL = k.length;
                    sheet.data.forEach(r => {
                        const val = r[k] !== undefined && r[k] !== null ? String(r[k]) : '';
                        if (val.length > maxL) maxL = val.length;
                    });
                    return { wch: Math.min(Math.max(maxL + 3, 10), 60) };
                });
                ws['!cols'] = colWidths;
            }

            XLSX.utils.book_append_sheet(wb, ws, sheet.name || 'Sheet1');
        });

        const todayStr = new Date().toISOString().slice(0, 10);
        XLSX.writeFile(wb, `${defaultFileName}_${todayStr}.xlsx`);
    } catch (err) {
        console.error("Gagal export excel:", err);
        showToast('error', "Terjadi kesalahan saat membuat file Excel: " + err.message, 'Gagal Ekspor Excel');
    }
}

// 1. Export Excel: Dashboard Overview (KPI + Transaksi Terkini)
window.exportDashboardExcel = async function () {
    showGlobalLoading("Menyiapkan Excel...", "Mengumpulkan data dashboard dan transaksi...");
    try {
        const metricsUrl = activeDashboardDateFilter ? `api/admin/metrics?date=${encodeURIComponent(activeDashboardDateFilter)}` : 'api/admin/metrics';
        const resMetrics = await fetch(metricsUrl);
        const kpi = await resMetrics.json();

        const transUrl = activeDashboardDateFilter ? `api/admin/transactions.php?date=${encodeURIComponent(activeDashboardDateFilter)}` : 'api/admin/transactions.php';
        const resTrans = await fetch(transUrl);
        const transList = await resTrans.json();

        const kpiData = [
            { "Keterangan Indikator": "Total Invoice Terproses Hari Ini", "Nilai / Jumlah": kpi.total_invoices || 0 },
            { "Keterangan Indikator": "Total Fisik Unit Masuk", "Nilai / Jumlah": kpi.total_items || 0 },
            { "Keterangan Indikator": "Total Unit Layak (Good)", "Nilai / Jumlah": kpi.total_good || 0 },
            { "Keterangan Indikator": "Total Unit Rusak / Defect", "Nilai / Jumlah": kpi.total_damaged || 0 },
            { "Keterangan Indikator": "Waktu Download Laporan", "Nilai / Jumlah": new Date().toLocaleString('id-ID') }
        ];

        const transData = (Array.isArray(transList) ? transList : []).map((r, i) => {
            const { date, time } = formatDateTime(r.created_at);
            return {
                "No": i + 1,
                "Tanggal": date,
                "Waktu": time + ' WIB',
                "Nomor Invoice": r.invoice_number,
                "Ekspedisi": r.expedition || '-',
                "Operator": r.operator_name,
                "Seller SKU": r.seller_sku || '-',
                "Nama Produk": r.product_name || '-',
                "Batch No": r.batch_no || '-',
                "Exp Date": formatExpDate(r.exp_date),
                "Qty": r.qty || 1,
                "Type / Kondisi": r.condition_type || 'GOOD',
                "Status Rekaman Video": r.video_path ? 'Ada Video' : 'Tanpa Video'
            };
        });

        hideGlobalLoading();
        generateExcelFile([
            { name: "Ringkasan KPI", data: kpiData },
            { name: "Transaksi Terkini", data: transData }
        ], "Laporan_Dashboard_Inbound");

    } catch (err) {
        hideGlobalLoading();
        showToast('error', "Gagal mengunduh Excel Dashboard: " + err.message, 'Gagal Ekspor Excel');
    }
};

// 2. Export Excel: Inbound Unboxing (Audit Trail Lengkap Berdasarkan Seller SKU)
window.exportInboundUnboxingExcel = async function () {
    const searchInput = document.getElementById('filterSearch');
    const dateInput = document.getElementById('filterDate');
    const expSelect = document.getElementById('filterExpedition');
    const condSelect = document.getElementById('filterCondition');
    const opSelect = document.getElementById('filterOperator');

    const search = searchInput ? searchInput.value.trim() : '';
    const date = dateInput ? dateInput.value.trim() : '';
    const expedition = expSelect ? expSelect.value : '';
    const condition = condSelect ? condSelect.value : '';
    const operator = opSelect ? opSelect.value : '';

    showGlobalLoading("Menyiapkan Excel...", "Mengunduh audit trail riwayat unboxing berdasarkan SKU...");
    try {
        let url = `api/admin/transactions.php?`;
        if (search) url += `search=${encodeURIComponent(search)}&`;
        if (date) url += `date=${encodeURIComponent(date)}&`;
        if (expedition) url += `expedition=${encodeURIComponent(expedition)}&`;
        if (condition) url += `condition=${encodeURIComponent(condition)}&`;
        if (operator) url += `operator=${encodeURIComponent(operator)}&`;

        const res = await fetch(url);
        const rows = await res.json();
        hideGlobalLoading();

        if (!rows || !rows.length) {
            showToast('warning', "Tidak ada data transaksi inbound unboxing untuk diekspor.", "Data Kosong");
            return;
        }

        const excelData = rows.map((r, idx) => {
            const { date, time } = formatDateTime(r.created_at);
            return {
                "No": idx + 1,
                "Tanggal Transaksi": date,
                "Waktu (WIB)": time,
                "Nomor Invoice / Resi": r.invoice_number,
                "Ekspedisi": r.expedition || '-',
                "Nama Operator": r.operator_name,
                "Seller SKU": r.seller_sku || '-',
                "Nama Produk": r.product_name || '-',
                "Barcode": r.barcode || '-',
                "Batch No": r.batch_no || '-',
                "Exp Date": formatExpDate(r.exp_date),
                "Qty (Pcs)": r.qty || 1,
                "Type / Kondisi": r.condition_type || 'GOOD',
                "Keterangan Type": r.raw_type || '-',
                "Catatan": r.notes || '-',
                "Rekaman Video": r.video_path ? 'Tersedia' : 'Tidak Ada'
            };
        });

        generateExcelFile([
            { name: "Inbound Unboxing", data: excelData }
        ], "Audit_Inbound_Unboxing");

    } catch (err) {
        hideGlobalLoading();
        showToast('error', "Gagal mengunduh Excel Inbound Unboxing: " + err.message, 'Gagal Ekspor Excel');
    }
};

// 3. Export Excel: Master Data Produk
window.exportProductsExcel = async function () {
    showGlobalLoading("Menyiapkan Excel...", "Mengambil seluruh master data produk...");
    try {
        let list = cachedProducts;
        if (!list || list.length === 0) {
            const res = await fetch('api/products.php');
            list = await res.json();
        }
        hideGlobalLoading();

        if (!list || !list.length) {
            showToast('warning', "Tidak ada data produk untuk diekspor.", "Data Kosong");
            return;
        }

        const excelData = list.map((p, idx) => ({
            "No": idx + 1,
            "Shop / Toko": p.shop || '-',
            "Barcode Produk": p.barcode || '-',
            "Seller SKU": p.seller_sku || p.sku || '-',
            "SAP Code": p.sap_code || '-',
            "Nama Produk": p.name || '-',
            "Barcode BPOM": p.barcode_bpom || '-',
            "Kategori": p.category || '-',
            "Satuan": p.unit || 'Pcs'
        }));

        generateExcelFile([
            { name: "Master Produk", data: excelData }
        ], "Master_Data_Produk");

    } catch (err) {
        hideGlobalLoading();
        showToast('error', "Gagal mengunduh Excel Master Produk: " + err.message, 'Gagal Ekspor Excel');
    }
};

// 4. Export Excel: Master Ekspedisi
window.exportExpeditionsExcel = async function () {
    showGlobalLoading("Menyiapkan Excel...", "Mengambil daftar master ekspedisi...");
    try {
        let list = cachedExpeditions;
        if (!list || list.length === 0) {
            const res = await fetch('api/expeditions.php');
            list = await res.json();
        }
        hideGlobalLoading();

        if (!list || !list.length) {
            showToast('warning', "Tidak ada data ekspedisi untuk diekspor.", "Data Kosong");
            return;
        }

        const excelData = list.map((e, idx) => ({
            "No": idx + 1,
            "Kode Ekspedisi": e.code || '-',
            "Nama Ekspedisi": e.name || '-',
            "Prefix Resi (Auto Detect)": e.prefix_pattern || '-',
            "Status": e.status === 'ACTIVE' ? 'Aktif' : 'Nonaktif'
        }));

        generateExcelFile([
            { name: "Master Ekspedisi", data: excelData }
        ], "Master_Data_Ekspedisi");

    } catch (err) {
        hideGlobalLoading();
        showToast('error', "Gagal mengunduh Excel Ekspedisi: " + err.message, 'Gagal Ekspor Excel');
    }
};

// 4. MASTER EKSPEDISI CRUD
let cachedExpeditions = [];

async function loadExpeditions() {
    const tbody = document.getElementById('fullExpeditionsTableBody');
    if (tbody) tbody.innerHTML = `<tr><td colspan="6" class="text-center py-10 text-slate-400 text-xs"><i class="fa-solid fa-spinner fa-spin mr-1.5 text-indigo-500 text-base"></i>Memuat daftar ekspedisi...</td></tr>`;

    try {
        const res = await fetch('api/expeditions.php');
        cachedExpeditions = await res.json();
        renderExpeditionsTable(cachedExpeditions);
    } catch (err) {
        console.error("Gagal load ekspedisi:", err);
        if (tbody) tbody.innerHTML = `<tr><td colspan="6" class="text-center py-8 text-rose-500 font-semibold">Gagal memuat ekspedisi: ${err.message}</td></tr>`;
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

window.filterExpeditionTable = function () {
    const q = (document.getElementById('filterExpeditionSearch').value || '').toLowerCase();
    const filtered = cachedExpeditions.filter(item =>
        item.name.toLowerCase().includes(q) ||
        item.code.toLowerCase().includes(q)
    );
    renderExpeditionsTable(filtered);
};

window.openAddExpeditionModal = function () {
    document.getElementById('expeditionModalTitle').innerText = 'Tambah Ekspedisi Baru';
    const sub = document.getElementById('expeditionModalSubtitle');
    if (sub) sub.innerText = 'Simpan data armada/kurir ke database MySQL';
    document.getElementById('formExpedition').reset();
    document.getElementById('expeditionId').value = '';
    document.getElementById('expeditionPrefix').value = '';
    document.getElementById('expeditionModal').classList.remove('hidden');
    document.getElementById('expeditionCode').focus();
};

window.closeExpeditionModal = function () {
    document.getElementById('expeditionModal').classList.add('hidden');
    document.getElementById('formExpedition').reset();
};

window.editExpedition = function (id) {
    const item = cachedExpeditions.find(x => x.id == id);
    if (!item) return;

    document.getElementById('expeditionModalTitle').innerText = 'Edit Data Ekspedisi';
    const sub = document.getElementById('expeditionModalSubtitle');
    if (sub) sub.innerText = `Perbarui rincian untuk ${item.name}`;
    document.getElementById('expeditionId').value = item.id;
    document.getElementById('expeditionCode').value = item.code;
    document.getElementById('expeditionName').value = item.name;
    document.getElementById('expeditionPrefix').value = item.prefix_pattern || '';
    document.getElementById('expeditionStatus').value = item.status;
    document.getElementById('expeditionModal').classList.remove('hidden');
    document.getElementById('expeditionName').focus();
};

window.submitExpedition = async function (e) {
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
            showToast('success', `Ekspedisi berhasil disimpan!`, 'Ekspedisi Tersimpan');
        } else {
            showToast('error', data.error || 'Terjadi kesalahan', 'Gagal Simpan Ekspedisi');
        }
    } catch (err) {
        showToast('error', "Gagal koneksi ke server: " + err.message, 'Koneksi Terputus');
    } finally {
        btn.disabled = false;
        btn.innerHTML = `<i class="fa-solid fa-save"></i> Simpan Ekspedisi`;
    }
};

window.deleteExpedition = async function (id, name) {
    if (!confirm(`Yakin ingin menghapus ekspedisi "${name}" dari master data?`)) return;

    try {
        const res = await fetch(`api/expeditions.php?action=delete&id=${id}`, {
            method: 'POST'
        });
        const data = await res.json();
        if (data.success) {
            loadExpeditions();
            showToast('success', `Ekspedisi berhasil dihapus.`, 'Ekspedisi Dihapus');
        } else {
            showToast('error', data.error || 'Tidak dapat menghapus', 'Gagal Hapus Ekspedisi');
        }
    } catch (err) {
        showToast('error', "Gagal koneksi ke server: " + err.message, 'Koneksi Terputus');
    }
};

// Refresh All Data function
window.refreshAllData = function () {
    const icon = document.getElementById('refreshIcon');
    if (icon) icon.classList.add('fa-spin');

    const promises = [loadMetrics(true)];
    if (currentTab === 'transactions') promises.push(loadTransactions());
    else if (currentTab === 'receiving') promises.push(loadReceivingData(true));
    else if (currentTab === 'products') promises.push(loadProducts());
    else if (currentTab === 'expeditions') promises.push(loadExpeditions());
    else if (currentTab === 'conditions') promises.push(loadConditions());
    else if (currentTab === 'users') promises.push(loadUsers());
    else if (currentTab === 'roles') promises.push(loadRoles());
    else if (currentTab === 'bank-settings') promises.push(loadStandaloneBankSettings());
    if (document.getElementById('tab-maintenance') && currentTab === 'maintenance') {
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
    const tbody = document.getElementById('fullUsersTableBody');
    if (tbody) tbody.innerHTML = `<tr><td colspan="7" class="text-center py-10 text-slate-400 text-xs"><i class="fa-solid fa-spinner fa-spin mr-1.5 text-indigo-500 text-base"></i>Memuat daftar pengguna...</td></tr>`;

    try {
        const res = await fetch('api/users.php');
        if (res.status === 401) {
            window.location.href = 'login';
            return;
        }
        cachedUsers = await res.json();
        renderUsersTable(cachedUsers);
    } catch (err) {
        console.error("Gagal load users:", err);
        if (tbody) tbody.innerHTML = `<tr><td colspan="7" class="text-center py-8 text-rose-500 font-semibold">Gagal memuat pengguna: ${err.message}</td></tr>`;
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
        } else if (u.role === 'management') {
            roleBadge = `<span class="bg-blue-50 text-blue-700 border border-blue-200 px-2 py-0.5 rounded font-mono font-bold text-[10px]"><i class="fa-solid fa-briefcase text-[9px]"></i> MANAGEMENT</span>`;
        } else if (u.role === 'accounting') {
            roleBadge = `<span class="bg-rose-50 text-rose-700 border border-rose-200 px-2 py-0.5 rounded font-mono font-bold text-[10px]"><i class="fa-solid fa-calculator text-[9px]"></i> ACCOUNTING</span>`;
        } else {
            roleBadge = `<span class="bg-purple-50 text-purple-700 border border-purple-200 px-2 py-0.5 rounded font-mono font-bold text-[10px]">${escapeHtml((u.role_name || u.role).toUpperCase())}</span>`;
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
            <td class="p-3 text-center font-mono font-bold text-slate-700 bg-slate-50/70">${u.pin || '-'}</td>
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

window.filterUserTable = function () {
    const q = (document.getElementById('filterUserSearch')?.value || '').toLowerCase().trim();
    const filtered = cachedUsers.filter(u =>
        u.username.toLowerCase().includes(q) ||
        u.name.toLowerCase().includes(q) ||
        u.role.toLowerCase().includes(q) ||
        (u.pin && u.pin.toLowerCase().includes(q))
    );
    renderUsersTable(filtered);
};

window.openAddUserModal = function () {
    document.getElementById('userModalTitle').innerText = 'Tambah Pengguna Baru';
    const sub = document.getElementById('userModalSubtitle');
    if (sub) sub.innerText = 'Daftarkan akun operator, admin, management, atau accounting';
    document.getElementById('formUser').reset();
    document.getElementById('userId').value = '';
    document.getElementById('userPasswordLabel').innerText = 'Password *';
    document.getElementById('userPassword').required = true;
    document.getElementById('userPasswordHelp').innerText = 'Wajib diisi saat membuat akun baru.';
    document.getElementById('userPin').value = '123456';
    populateUserRoleDropdown('operator');
    document.getElementById('userModal').classList.remove('hidden');
    document.getElementById('userUsername').focus();
};

window.closeUserModal = function () {
    document.getElementById('userModal').classList.add('hidden');
    document.getElementById('formUser').reset();
};

window.editUser = function (id) {
    const u = cachedUsers.find(x => x.id == id);
    if (!u) return;

    document.getElementById('userModalTitle').innerText = 'Edit Pengguna';
    const sub = document.getElementById('userModalSubtitle');
    if (sub) sub.innerText = `Perbarui akun ${u.name}`;
    document.getElementById('userId').value = u.id;
    document.getElementById('userUsername').value = u.username;
    document.getElementById('userName').value = u.name;
    populateUserRoleDropdown(u.role);
    document.getElementById('userRole').value = u.role;
    document.getElementById('userStatus').value = u.status;
    document.getElementById('userPin').value = u.pin || '123456';
    document.getElementById('userPasswordLabel').innerText = 'Ganti Password (Opsional)';
    document.getElementById('userPassword').value = '';
    document.getElementById('userPassword').required = false;
    document.getElementById('userPasswordHelp').innerText = 'Kosongkan jika tidak ingin mengubah password saat ini.';
    document.getElementById('userModal').classList.remove('hidden');
};

window.submitUser = async function (e) {
    e.preventDefault();
    const id = document.getElementById('userId').value;
    const username = document.getElementById('userUsername').value.trim();
    const name = document.getElementById('userName').value.trim();
    const password = document.getElementById('userPassword').value.trim();
    const pin = document.getElementById('userPin').value.trim();
    const role = document.getElementById('userRole').value;
    const status = document.getElementById('userStatus').value;

    const btn = document.getElementById('btnSaveUser');
    btn.disabled = true;
    btn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> Menyimpan...`;

    try {
        const payload = { id, username, name, password, pin, role, status };
        const res = await fetch('api/users.php', {
            method: id ? 'PUT' : 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.success) {
            closeUserModal();
            loadUsers();
            showToast('success', data.message || 'Pengguna berhasil disimpan!', 'Pengguna Tersimpan');
        } else {
            showToast('error', data.error || 'Terjadi kesalahan', 'Gagal Simpan Pengguna');
        }
    } catch (err) {
        showToast('error', 'Gagal koneksi ke server: ' + err.message, 'Koneksi Terputus');
    } finally {
        btn.disabled = false;
        btn.innerHTML = `<i class="fa-solid fa-save"></i> Simpan Pengguna`;
    }
};

window.deleteUser = async function (id, name) {
    if (!confirm(`Yakin ingin menghapus pengguna "${name}"?`)) return;

    try {
        const res = await fetch(`api/users.php?action=delete&id=${id}`, {
            method: 'POST'
        });
        const data = await res.json();
        if (data.success) {
            loadUsers();
            showToast('success', data.message || 'Pengguna berhasil dihapus.', 'Pengguna Dihapus');
        } else {
            showToast('error', data.error || 'Tidak dapat menghapus user', 'Gagal Hapus Pengguna');
        }
    } catch (err) {
        showToast('error', 'Gagal koneksi ke server: ' + err.message, 'Koneksi Terputus');
    }
};

// =============================================================
// PENGATURAN BANK STANDALONE (MANAGEMENT & ACCOUNTING)
// =============================================================
async function loadStandaloneBankSettings() {
    try {
        const res = await fetch('api/bank_settings.php');
        if (res.status === 401) {
            window.location.href = 'login';
            return;
        }
        const data = await res.json();
        if (data.success && data.data) {
            const b = data.data;
            const elName = document.getElementById('standaloneBankName');
            const elAcc = document.getElementById('standaloneBankAccountNumber');
            const elHolder = document.getElementById('standaloneBankAccountHolder');
            const elNotes = document.getElementById('standaloneBankPaymentNotes');

            if (elName) elName.value = b.bank_name || '';
            if (elAcc) elAcc.value = b.bank_account_number || '';
            if (elHolder) elHolder.value = b.bank_account_holder || '';
            if (elNotes) elNotes.value = b.bank_payment_notes || '';
        }
    } catch (e) {
        console.error("Gagal memuat pengaturan bank:", e);
    }
}

window.saveBankSettingsStandalone = async function (e) {
    if (e) e.preventDefault();
    const btn = document.getElementById('btnSaveStandaloneBank');
    const origHtml = btn ? btn.innerHTML : '';
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> Menyimpan...`;
    }

    try {
        const payload = {
            bank_name: (document.getElementById('standaloneBankName')?.value || '').trim(),
            bank_account_number: (document.getElementById('standaloneBankAccountNumber')?.value || '').trim(),
            bank_account_holder: (document.getElementById('standaloneBankAccountHolder')?.value || '').trim(),
            bank_payment_notes: (document.getElementById('standaloneBankPaymentNotes')?.value || '').trim()
        };

        const res = await fetch('api/bank_settings.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.success) {
            showToast('success', data.message || 'Pengaturan Rekening Bank berhasil disimpan!', 'Tersimpan');
        } else {
            showToast('error', data.error || 'Gagal menyimpan pengaturan bank.', 'Gagal');
        }
    } catch (err) {
        showToast('error', 'Koneksi error: ' + err.message, 'Gagal');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = origHtml;
        }
    }
};

// =============================================================
// KELOLA ROLE & HAK AKSES (CRUD)
// =============================================================
let cachedRoles = [];

async function loadRoles() {
    const tbody = document.getElementById('rolesTableBody');
    if (tbody) {
        tbody.innerHTML = `<tr><td colspan="6" class="text-center py-10 text-slate-400 text-xs"><i class="fa-solid fa-spinner fa-spin mr-1.5 text-purple-600 text-base"></i>Memuat daftar role...</td></tr>`;
    }

    try {
        const res = await fetch('api/roles.php');
        if (res.status === 401) {
            window.location.href = 'login';
            return;
        }
        const data = await res.json();
        if (data.success) {
            cachedRoles = data.roles || [];
            renderRolesTable(cachedRoles);
            populateUserRoleDropdown();
        } else {
            if (tbody) tbody.innerHTML = `<tr><td colspan="6" class="text-center py-8 text-rose-500 font-semibold">${data.error || 'Gagal memuat role'}</td></tr>`;
        }
    } catch (err) {
        console.error("Gagal load roles:", err);
        if (tbody) tbody.innerHTML = `<tr><td colspan="6" class="text-center py-8 text-rose-500 font-semibold">Error: ${err.message}</td></tr>`;
    }
}

function renderRolesTable(roles) {
    const tbody = document.getElementById('rolesTableBody');
    if (!tbody) return;
    tbody.innerHTML = '';

    if (!roles || roles.length === 0) {
        tbody.innerHTML = `<tr><td colspan="6" class="text-center py-8 text-slate-400">Tidak ada role ditemukan.</td></tr>`;
        return;
    }

    roles.forEach(r => {
        const isSystem = (r.is_system == 1 || r.is_system === true);
        const typeBadge = isSystem
            ? `<span class="inline-flex items-center gap-1 bg-slate-100 text-slate-700 px-2 py-0.5 rounded font-bold text-[10px] border border-slate-200"><i class="fa-solid fa-lock text-[8px]"></i> Sistem</span>`
            : `<span class="inline-flex items-center gap-1 bg-purple-50 text-purple-700 px-2 py-0.5 rounded font-bold text-[10px] border border-purple-200"><i class="fa-solid fa-sparkles text-[8px]"></i> Kustom</span>`;

        let colorKey = 'bg-slate-100 text-slate-800 border-slate-300';
        if (r.role_key === 'superadmin') colorKey = 'bg-amber-100 text-amber-800 border-amber-300';
        else if (r.role_key === 'admin') colorKey = 'bg-indigo-100 text-indigo-800 border-indigo-300';
        else if (r.role_key === 'operator') colorKey = 'bg-emerald-100 text-emerald-800 border-emerald-300';
        else if (r.role_key === 'management') colorKey = 'bg-blue-100 text-blue-800 border-blue-300';
        else if (r.role_key === 'accounting') colorKey = 'bg-rose-100 text-rose-800 border-rose-300';

        const tr = document.createElement('tr');
        tr.className = 'hover:bg-slate-50 border-b border-slate-100 transition';
        tr.innerHTML = `
            <td class="py-3 px-4">
                <span class="font-mono font-bold text-xs px-2 py-0.5 rounded border ${colorKey}">${escapeHtml(r.role_key)}</span>
            </td>
            <td class="py-3 px-4 font-bold text-slate-800">${escapeHtml(r.role_name)}</td>
            <td class="py-3 px-4 text-slate-500 max-w-xs truncate" title="${escapeHtml(r.description || '-')}">${escapeHtml(r.description || '-')}</td>
            <td class="py-3 px-4 text-center">
                <span class="font-bold text-slate-700 px-2 py-0.5 rounded-full bg-slate-100 text-[11px]">${r.user_count || 0} user</span>
            </td>
            <td class="py-3 px-4 text-center">${typeBadge}</td>
            <td class="py-3 px-4 text-center">
                <div class="flex items-center justify-center space-x-1.5">
                    <button onclick="openEditRoleModal(${r.id})" title="Edit Role" class="bg-indigo-50 hover:bg-indigo-100 text-indigo-700 px-2.5 py-1 rounded-lg text-xs font-semibold transition cursor-pointer">
                        <i class="fa-solid fa-pen-to-square"></i> Edit
                    </button>
                    ${!isSystem ? `
                    <button onclick="deleteRole(${r.id}, '${escapeHtml(r.role_name)}')" title="Hapus Role" class="bg-rose-50 hover:bg-rose-100 text-rose-600 px-2.5 py-1 rounded-lg text-xs font-semibold transition cursor-pointer">
                        <i class="fa-solid fa-trash-can"></i> Hapus
                    </button>
                    ` : `
                    <span class="text-slate-300 text-[11px] italic px-2">Protected</span>
                    `}
                </div>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

function populateUserRoleDropdown(selectedRole = null) {
    const sel = document.getElementById('userRole');
    if (!sel) return;
    const currentVal = selectedRole || sel.value || 'operator';

    // Jika belum load roles dari backend, coba fetch sekali
    if (cachedRoles.length === 0) {
        fetch('api/roles.php').then(r => r.json()).then(d => {
            if (d.success && d.roles) {
                cachedRoles = d.roles;
                populateUserRoleDropdown(currentVal);
            }
        }).catch(() => {});
        return;
    }

    let html = '';
    cachedRoles.forEach(r => {
        html += `<option value="${escapeHtml(r.role_key)}" ${r.role_key === currentVal ? 'selected' : ''}>${escapeHtml(r.role_name)}</option>`;
    });
    sel.innerHTML = html;
}

window.openAddRoleModal = function () {
    document.getElementById('modalRoleTitle').innerText = 'Tambah Role Baru';
    document.getElementById('formRole').reset();
    document.getElementById('roleEditId').value = '';
    const keyInput = document.getElementById('roleInputKey');
    keyInput.readOnly = false;
    keyInput.classList.remove('bg-slate-100', 'cursor-not-allowed');
    document.getElementById('modalRole').classList.remove('hidden');
    keyInput.focus();
};

window.openEditRoleModal = function (id) {
    const r = cachedRoles.find(x => x.id == id);
    if (!r) return;

    document.getElementById('modalRoleTitle').innerText = `Edit Role: ${r.role_name}`;
    document.getElementById('roleEditId').value = r.id;
    const keyInput = document.getElementById('roleInputKey');
    keyInput.value = r.role_key;
    keyInput.readOnly = true;
    keyInput.classList.add('bg-slate-100', 'cursor-not-allowed');

    document.getElementById('roleInputName').value = r.role_name;
    document.getElementById('roleInputDesc').value = r.description || '';
    document.getElementById('modalRole').classList.remove('hidden');
};

window.closeRoleModal = function () {
    document.getElementById('modalRole').classList.add('hidden');
    document.getElementById('formRole').reset();
};

window.saveRole = async function (e) {
    if (e) e.preventDefault();
    const id = document.getElementById('roleEditId').value;
    const role_key = document.getElementById('roleInputKey').value.trim().toLowerCase();
    const role_name = document.getElementById('roleInputName').value.trim();
    const description = document.getElementById('roleInputDesc').value.trim();

    if (!role_key || !role_name) {
        showToast('warning', 'Kode Role dan Nama Role wajib diisi.', 'Form Belum Lengkap');
        return;
    }

    const btn = document.getElementById('btnSubmitRole');
    const origHtml = btn ? btn.innerHTML : '';
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> Menyimpan...`;
    }

    try {
        const payload = { id, role_key, role_name, description };
        const res = await fetch('api/roles.php', {
            method: id ? 'PUT' : 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.success) {
            closeRoleModal();
            loadRoles();
            showToast('success', data.message || 'Role berhasil disimpan!', 'Role Tersimpan');
        } else {
            showToast('error', data.error || 'Gagal menyimpan role', 'Gagal');
        }
    } catch (err) {
        showToast('error', 'Koneksi error: ' + err.message, 'Gagal');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = origHtml;
        }
    }
};

window.deleteRole = async function (id, name) {
    if (!confirm(`Yakin ingin menghapus role "${name}"?\nPengguna yang menggunakan role ini tidak akan bisa login sampai role diganti.`)) return;

    try {
        const res = await fetch(`api/roles.php?id=${id}`, {
            method: 'DELETE'
        });
        const data = await res.json();
        if (data.success) {
            loadRoles();
            showToast('success', data.message || 'Role berhasil dihapus.', 'Role Dihapus');
        } else {
            showToast('error', data.error || 'Tidak dapat menghapus role', 'Gagal Hapus');
        }
    } catch (err) {
        showToast('error', 'Koneksi error: ' + err.message, 'Gagal');
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

        // Pengaturan Rekening Bank untuk Invoice Klaim
        if (data.bank_settings) {
            const elBank = document.getElementById('settingBankName');
            const elAcc = document.getElementById('settingBankAccountNumber');
            const elHolder = document.getElementById('settingBankAccountHolder');
            const elNotes = document.getElementById('settingBankPaymentNotes');

            if (elBank) elBank.value = data.bank_settings.bank_name || '';
            if (elAcc) elAcc.value = data.bank_settings.bank_account_number || '';
            if (elHolder) elHolder.value = data.bank_settings.bank_account_holder || '';
            if (elNotes) elNotes.value = data.bank_settings.bank_payment_notes || '';
        }

    } catch (err) {
        console.error("Gagal load status maintenance:", err);
    }
}

window.saveBankSettings = async function (e) {
    if (e) e.preventDefault();
    const btn = document.getElementById('btnSaveBankSettings');
    const bankName = document.getElementById('settingBankName')?.value?.trim();
    const accNum = document.getElementById('settingBankAccountNumber')?.value?.trim();
    const accHolder = document.getElementById('settingBankAccountHolder')?.value?.trim();
    const notes = document.getElementById('settingBankPaymentNotes')?.value?.trim();

    if (!bankName || !accNum || !accHolder) {
        showToast('warning', 'Nama Bank, No. Rekening, dan Atas Nama wajib diisi!', 'Data Belum Lengkap');
        return;
    }

    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Menyimpan...';
    }

    try {
        const res = await fetch('api/maintenance.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'update_bank_settings',
                bank_name: bankName,
                bank_account_number: accNum,
                bank_account_holder: accHolder,
                bank_payment_notes: notes
            })
        });

        const json = await res.json();
        if (res.ok && json.success) {
            showToast('success', json.message || 'No. Rekening berhasil diperbarui!', 'Berhasil Disimpan');
        } else {
            showToast('error', json.error || 'Gagal menyimpan pengaturan rekening bank', 'Gagal');
        }
    } catch (err) {
        console.error(err);
        showToast('error', 'Terjadi kesalahan koneksi ke server', 'Error');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> <span>Simpan No. Rekening</span>';
        }
    }
};

window.toggleMaintenanceMode = async function () {
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
            showToast('success', data.message, 'Mode Pemeliharaan Diperbarui');
        } else {
            showToast('error', data.error || 'Terjadi kesalahan', 'Gagal Mengubah Mode');
        }
    } catch (err) {
        hideGlobalLoading();
        showToast('error', 'Gagal koneksi ke server: ' + err.message, 'Koneksi Terputus');
    }
};

window.optimizeDatabaseTables = async function () {
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
            showToast('success', data.message || 'Optimasi tabel berhasil!', 'Optimasi Berhasil');
            loadMaintenanceStatus();
        } else {
            showToast('error', data.error || 'Terjadi kesalahan', 'Optimasi Gagal');
        }
    } catch (err) {
        hideGlobalLoading();
        showToast('error', 'Gagal koneksi ke server: ' + err.message, 'Koneksi Terputus');
    }
};

window.cleanTestTransactions = async function () {
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
            showToast('success', data.message, 'Pembersihan Data Berhasil');
            loadMaintenanceStatus();
            loadTransactions();
            loadMetrics();
        } else {
            showToast('error', data.error || 'Gagal membersihkan data', 'Pembersihan Gagal');
        }
    } catch (err) {
        hideGlobalLoading();
        showToast('error', 'Gagal koneksi ke server: ' + err.message, 'Koneksi Terputus');
    }
};

// -------------------------------------------------------------
// URL Parameter State Restoration (Deep Linking & Refresh)
// -------------------------------------------------------------
function initFromUrlParams() {
    const params = new URLSearchParams(window.location.search);
    const pageParam = params.get('page') || 'dashboard';
    const targetTab = SLUG_TAB_MAP[pageParam] || 'dashboard';
    const dateParam = params.get('date') || '';
    const searchParam = params.get('search') || '';
    const shopParam = params.get('shop') || '';

    // Pulihkan filter tanggal jika ada di URL
    if (dateParam) {
        if (targetTab === 'transactions') {
            activeInboundDateFilter = dateParam;
            if (flatpickrTransactionsInstance) {
                if (dateParam.includes(' to ')) {
                    const p = dateParam.split(' to ');
                    flatpickrTransactionsInstance.setDate([p[0], p[1]], false);
                } else {
                    flatpickrTransactionsInstance.setDate(dateParam, false);
                }
            }
            updateInboundDateUI();
        } else if (targetTab === 'receiving') {
            activeReceivingDateFilter = dateParam;
            if (flatpickrReceivingInstance) {
                if (dateParam.includes(' to ')) {
                    const p = dateParam.split(' to ');
                    flatpickrReceivingInstance.setDate([p[0], p[1]], false);
                } else {
                    flatpickrReceivingInstance.setDate(dateParam, false);
                }
            }
            const btnClearRec = document.getElementById('btnClearReceivingDate');
            if (btnClearRec) btnClearRec.classList.remove('hidden');
        } else if (targetTab === 'claims') {
            const claimInput = document.getElementById('filterClaimDate');
            if (claimInput) claimInput.value = dateParam;
        } else {
            activeDashboardDateFilter = dateParam;
            if (flatpickrDashboardInstance) {
                if (dateParam.includes(' to ')) {
                    const p = dateParam.split(' to ');
                    flatpickrDashboardInstance.setDate([p[0], p[1]], false);
                } else {
                    flatpickrDashboardInstance.setDate(dateParam, false);
                }
            }
            updateDashboardDateUI();
        }
    } else {
        // Default filter tanggal hari ini jika tidak dispesifikasikan di URL
        const todayStr = getTodayYMD();
        if (!activeDashboardDateFilter) activeDashboardDateFilter = todayStr;
        if (!activeInboundDateFilter) activeInboundDateFilter = todayStr;
        if (!activeReceivingDateFilter) activeReceivingDateFilter = todayStr;

        const claimInput = document.getElementById('filterClaimDate');
        if (claimInput && !claimInput.value) claimInput.value = todayStr;

        const orderDateSelect = document.getElementById('orderDateFilter');
        if (orderDateSelect && (!orderDateSelect.value || orderDateSelect.value === 'ALL')) {
            orderDateSelect.value = 'today';
        }
    }

    // Pulihkan filter search jika ada di URL
    if (targetTab === 'transactions' && searchParam) {
        const searchInput = document.getElementById('filterSearch');
        if (searchInput) searchInput.value = searchParam;
    } else if (targetTab === 'orders') {
        if (searchParam) {
            const ordSearch = document.getElementById('orderSearchInput');
            if (ordSearch) ordSearch.value = searchParam;
        }
        const platformParam = params.get('platform');
        if (platformParam) {
            const platSelect = document.getElementById('orderPlatformFilter');
            if (platSelect) platSelect.value = platformParam;
        }
        const periodParam = params.get('period');
        if (periodParam) {
            const periodSelect = document.getElementById('orderDateFilter');
            if (periodSelect) periodSelect.value = periodParam;
        }
    } else if (targetTab === 'products') {
        if (searchParam) {
            const prodSearchInput = document.getElementById('filterProductSearch');
            if (prodSearchInput) prodSearchInput.value = searchParam;
        }
        if (shopParam) {
            const shopSelect = document.getElementById('filterProductShop');
            if (shopSelect) shopSelect.value = shopParam;
        }
    }

    // Buka tab tujuan tanpa menambahkan riwayat baru ke history browser
    switchTab(targetTab, false);

    // Pastikan URL di address bar bersih tanpa .php
    updateBrowserUrl(false);
}

// Handler tombol browser Back / Forward (History Navigation)
window.addEventListener('popstate', () => {
    initFromUrlParams();
});

// Initial Load & Event Listeners
window.addEventListener('DOMContentLoaded', () => {
    initFlatpickr();
    initReceivingDatepicker();
    initClaimDatepicker();
    initFromUrlParams();

    // Event listener search transaction (Inbound Unboxing)
    const filterSearchInput = document.getElementById('filterSearch');
    if (filterSearchInput) {
        filterSearchInput.addEventListener('keyup', (e) => {
            if (e.key === 'Enter') {
                updateBrowserUrl(false);
                loadTransactions();
            }
        });
    }

    const btnApplyFilter = document.getElementById('btnApplyFilter');
    if (btnApplyFilter) {
        btnApplyFilter.addEventListener('click', () => {
            updateBrowserUrl(false);
            loadTransactions();
        });
    }

    // Event listener search master product
    const filterProductSearchInput = document.getElementById('filterProductSearch');
    if (filterProductSearchInput) {
        filterProductSearchInput.addEventListener('keyup', () => {
            updateBrowserUrl(false);
        });
    }

    // Refresh otomatis via frontend dinonaktifkan (refresh metrik dilakukan di backend / tombol Refresh)
});


// =============================================================
// MASTER KONDISI / TYPE - CRUD Functions
// =============================================================

let allConditions = [];

const CONDITION_COLOR_MAP = {
    emerald: { bg: 'bg-emerald-50', text: 'text-emerald-700', border: 'border-emerald-200' },
    red: { bg: 'bg-red-50', text: 'text-red-700', border: 'border-red-200' },
    amber: { bg: 'bg-amber-50', text: 'text-amber-700', border: 'border-amber-200' },
    orange: { bg: 'bg-orange-50', text: 'text-orange-700', border: 'border-orange-200' },
    purple: { bg: 'bg-purple-50', text: 'text-purple-700', border: 'border-purple-200' },
    blue: { bg: 'bg-blue-50', text: 'text-blue-700', border: 'border-blue-200' },
    slate: { bg: 'bg-slate-100', text: 'text-slate-700', border: 'border-slate-200' },
};

async function loadConditions() {
    const tbody = document.getElementById('fullConditionsTableBody');
    if (tbody) tbody.innerHTML = `<tr><td colspan="6" class="text-center py-10 text-slate-400 text-xs"><i class="fa-solid fa-spinner fa-spin mr-1.5 text-indigo-500 text-base"></i>Memuat kriteria kondisi...</td></tr>`;

    try {
        const res = await fetch('api/conditions.php');
        allConditions = await res.json();
        renderConditionsTable(allConditions);
        populateFilterConditionSelect(allConditions);
    } catch (e) {
        if (tbody) tbody.innerHTML = `<tr><td colspan="6" class="text-center py-8 text-rose-500 font-semibold">Gagal memuat data kondisi: ${e.message}</td></tr>`;
    }
}

function populateFilterConditionSelect(list) {
    const sel = document.getElementById('filterCondition');
    if (!sel || !Array.isArray(list)) return;
    const currentVal = sel.value;
    sel.innerHTML = '<option value="">Semua Kondisi</option>';
    list.forEach(c => {
        const opt = document.createElement('option');
        opt.value = c.code;
        opt.innerText = `${c.name} (${c.code})`;
        sel.appendChild(opt);
    });
    if (currentVal && Array.from(sel.options).some(o => o.value === currentVal)) {
        sel.value = currentVal;
    }
}

function renderConditionsTable(list) {
    const tbody = document.getElementById('fullConditionsTableBody');
    if (!tbody) return;
    tbody.innerHTML = '';

    if (!list || list.length === 0) {
        tbody.innerHTML = `<tr><td colspan="6" class="text-center py-10 text-slate-400">
            <i class="fa-solid fa-tag text-2xl mb-2 block text-slate-300"></i>
            Belum ada data kondisi.
        </td></tr>`;
        return;
    }

    list.forEach((c, idx) => {
        const clr = CONDITION_COLOR_MAP[c.color] || CONDITION_COLOR_MAP.slate;
        const tr = document.createElement('tr');
        tr.className = 'hover:bg-slate-50 border-b border-slate-100 transition condition-row';
        tr.dataset.search = `${c.code} ${c.name} ${c.description || ''}`.toLowerCase();
        tr.innerHTML = `
            <td class="p-3 text-center text-slate-400 font-mono text-xs">${idx + 1}</td>
            <td class="p-3">
                <span class="font-mono font-bold text-xs px-2 py-0.5 rounded ${clr.bg} ${clr.text} border ${clr.border}">${c.code}</span>
            </td>
            <td class="p-3 font-semibold text-slate-800 text-xs">${c.name}</td>
            <td class="p-3 text-slate-500 text-xs">${c.description || '<span class="italic text-slate-300">-</span>'}</td>
            <td class="p-3 text-center">
                <span class="inline-flex items-center gap-1.5 text-xs font-medium px-2.5 py-1 rounded-lg border ${clr.bg} ${clr.text} ${clr.border}">
                    <span class="w-2 h-2 rounded-full" style="background:currentColor;opacity:0.7"></span>
                    ${c.color.charAt(0).toUpperCase() + c.color.slice(1)}
                </span>
            </td>
            <td class="p-3 text-center">
                <div class="flex items-center justify-center gap-1.5">
                    <button onclick="openEditConditionModal(${c.id})"
                        class="bg-amber-50 hover:bg-amber-100 text-amber-700 border border-amber-200 text-[10px] px-2.5 py-1 rounded-lg font-bold transition flex items-center gap-1">
                        <i class="fa-solid fa-pen text-[9px]"></i> Edit
                    </button>
                    <button onclick="deleteCondition(${c.id}, '${c.code.replace(/'/g, "\\'")}', '${c.name.replace(/'/g, "\\'")}' )"
                        class="bg-red-50 hover:bg-red-100 text-red-600 border border-red-200 text-[10px] px-2.5 py-1 rounded-lg font-bold transition flex items-center gap-1">
                        <i class="fa-solid fa-trash-can text-[9px]"></i> Hapus
                    </button>
                </div>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

function filterConditionTable() {
    const q = (document.getElementById('filterConditionSearch')?.value || '').toLowerCase().trim();
    document.querySelectorAll('.condition-row').forEach(row => {
        row.style.display = !q || row.dataset.search.includes(q) ? '' : 'none';
    });
}

function openAddConditionModal() {
    document.getElementById('conditionModalTitle').textContent = 'Tambah Kondisi Baru';
    document.getElementById('conditionId').value = '';
    document.getElementById('conditionCode').value = '';
    document.getElementById('conditionName').value = '';
    document.getElementById('conditionDesc').value = '';
    document.getElementById('conditionColor').value = 'emerald';
    document.getElementById('conditionSort').value = '0';
    document.getElementById('conditionCode').disabled = false;
    document.getElementById('modalCondition').classList.remove('hidden');
}

function openEditConditionModal(id) {
    const c = allConditions.find(x => x.id == id);
    if (!c) return;
    document.getElementById('conditionModalTitle').textContent = 'Edit Kondisi';
    document.getElementById('conditionId').value = c.id;
    document.getElementById('conditionCode').value = c.code;
    document.getElementById('conditionName').value = c.name;
    document.getElementById('conditionDesc').value = c.description || '';
    document.getElementById('conditionColor').value = c.color || 'slate';
    document.getElementById('conditionSort').value = c.sort_order || 0;
    document.getElementById('modalCondition').classList.remove('hidden');
}

function closeConditionModal() {
    document.getElementById('modalCondition').classList.add('hidden');
}

async function saveCondition() {
    const id = document.getElementById('conditionId').value;
    const code = document.getElementById('conditionCode').value.trim().toUpperCase();
    const name = document.getElementById('conditionName').value.trim();
    const desc = document.getElementById('conditionDesc').value.trim();
    const color = document.getElementById('conditionColor').value;
    const sort = parseInt(document.getElementById('conditionSort').value) || 0;

    if (!code || !name) { showToast('warning', 'Kode dan Nama Kondisi wajib diisi!', 'Data Tidak Lengkap'); return; }

    const btn = document.getElementById('btnSaveCondition');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Menyimpan...';

    const isEdit = id && parseInt(id) > 0;
    const body = { code, name, description: desc, color, sort_order: sort, action: isEdit ? 'update' : 'create' };
    if (isEdit) body.id = parseInt(id);

    try {
        const res = await fetch('api/conditions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        });
        const data = await res.json();
        if (data.success) {
            closeConditionModal();
            await loadConditions();
            showToast('success', `Kondisi "${name}" berhasil disimpan!`, 'Kondisi Tersimpan');
        } else {
            showToast('error', data.error || 'Terjadi kesalahan', 'Gagal Simpan Kondisi');
        }
    } catch (e) {
        showToast('error', 'Error: ' + e.message, 'Koneksi Terputus');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Simpan Kondisi';
    }
}

async function deleteCondition(id, code, name) {
    if (!confirm(`Hapus kondisi "${code} - ${name}"?\n\nTindakan ini tidak bisa dibatalkan.`)) return;

    try {
        const res = await fetch('api/conditions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete', id })
        });
        const data = await res.json();
        if (data.success) {
            await loadConditions();
            showToast('success', `Kondisi "${code}" berhasil dihapus.`, 'Kondisi Dihapus');
        } else {
            showToast('error', data.error || 'Terjadi kesalahan', 'Gagal Menghapus Kondisi');
        }
    } catch (e) {
        showToast('error', 'Error: ' + e.message, 'Koneksi Terputus');
    }
}

// ==========================================
// RECEIVING INBOUND MANAGEMENT (ADMIN)
// ==========================================
let flatpickrReceivingInstance = null;
let cachedReceivingData = [];
let receivingSearchDebounceTimer = null;

// Inisialisasi Flatpickr Filter Tanggal Receiving
function initReceivingDatepicker() {
    const el = document.getElementById('filterReceivingDate');
    if (!el || flatpickrReceivingInstance) return;
    if (typeof flatpickr !== 'function') return;

    flatpickrReceivingInstance = flatpickr(el, {
        mode: "range",
        dateFormat: "Y-m-d",
        defaultDate: activeReceivingDateFilter || getTodayYMD(),
        altInput: true,
        altFormat: "j F Y",
        locale: "id",
        maxDate: "today",
        onChange: function (selectedDates) {
            const btnClear = document.getElementById('btnClearReceivingDate');
            if (selectedDates.length === 2) {
                const start = flatpickr.formatDate(selectedDates[0], "Y-m-d");
                const end = flatpickr.formatDate(selectedDates[1], "Y-m-d");
                activeReceivingDateFilter = `${start} to ${end}`;
                if (btnClear) btnClear.classList.remove('hidden');
                loadReceivingData();
            } else if (selectedDates.length === 1) {
                const single = flatpickr.formatDate(selectedDates[0], "Y-m-d");
                activeReceivingDateFilter = single;
                if (btnClear) btnClear.classList.remove('hidden');
            } else {
                activeReceivingDateFilter = '';
                if (btnClear) btnClear.classList.add('hidden');
            }
        },
        onClose: function (selectedDates) {
            if (selectedDates.length === 1) {
                loadReceivingData();
            }
        }
    });

    const btnClear = document.getElementById('btnClearReceivingDate');
    if (btnClear && activeReceivingDateFilter) {
        btnClear.classList.remove('hidden');
    }

    // Pasang listener search box dengan debounce
    const searchInput = document.getElementById('searchReceivingInput');
    if (searchInput) {
        searchInput.addEventListener('input', () => {
            if (receivingSearchDebounceTimer) clearTimeout(receivingSearchDebounceTimer);
            receivingSearchDebounceTimer = setTimeout(() => {
                loadReceivingData();
            }, 300);
        });
    }
}

window.clearReceivingDateFilter = function () {
    if (flatpickrReceivingInstance) {
        flatpickrReceivingInstance.clear();
    }
    activeReceivingDateFilter = '';
    const btnClear = document.getElementById('btnClearReceivingDate');
    if (btnClear) btnClear.classList.add('hidden');
    loadReceivingData();
};

// Muat data receiving dari API
window.loadReceivingData = async function (forceRefresh = false) {
    initReceivingDatepicker();

    const tbody = document.getElementById('receivingTableBody');
    if (tbody) {
        tbody.innerHTML = `<tr><td colspan="9" class="text-center py-12 text-slate-400"><i class="fa-solid fa-spinner fa-spin text-2xl text-emerald-500 mb-2 block"></i>Memuat data receiving inbound...</td></tr>`;
    }

    try {
        let url = 'api/reception.php?action=list';

        // Filter Tanggal
        if (activeReceivingDateFilter) {
            if (activeReceivingDateFilter.includes(' to ')) {
                const [start, end] = activeReceivingDateFilter.split(' to ');
                url += `&start_date=${encodeURIComponent(start)}&end_date=${encodeURIComponent(end)}`;
            } else {
                url += `&date=${encodeURIComponent(activeReceivingDateFilter)}`;
            }
        }

        // Filter Ekspedisi
        const expSelect = document.getElementById('filterReceivingExpedition');
        if (expSelect && expSelect.value) {
            url += `&expedition=${encodeURIComponent(expSelect.value)}`;
        }

        // Search Query
        const searchInput = document.getElementById('searchReceivingInput');
        if (searchInput && searchInput.value.trim()) {
            url += `&search=${encodeURIComponent(searchInput.value.trim())}`;
        }

        const res = await fetch(url);

        if (res.status === 401) {
            if (tbody) {
                tbody.innerHTML = `<tr><td colspan="9" class="text-center py-10 text-amber-600 font-bold"><i class="fa-solid fa-triangle-exclamation mr-1.5 text-lg"></i>Sesi login Anda telah berakhir. Silakan <a href="login" class="underline text-indigo-600 font-black">Login Kembali</a>.</td></tr>`;
            }
            return;
        }

        let json;
        try {
            json = await res.json();
        } catch (jsonErr) {
            const rawText = await res.text().catch(() => '');
            console.error('Non-JSON response from reception.php:', rawText);
            throw new Error(`Respon server tidak valid (${res.status}): ${rawText.slice(0, 100)}`);
        }

        if (json && json.success) {
            cachedReceivingData = json.data || [];
            renderReceivingTable(cachedReceivingData);
            populateReceivingExpeditionFilter(cachedReceivingData);
            if (forceRefresh) {
                showToast('success', 'Data receiving berhasil diperbarui.', 'Refresh Selesai');
            }
        } else {
            const errMsg = json ? (json.error || 'Kesalahan server') : 'Respon kosong';
            if (tbody) tbody.innerHTML = `<tr><td colspan="9" class="text-center py-10 text-rose-500 font-semibold"><i class="fa-solid fa-circle-exclamation mr-1.5"></i>Gagal memuat data: ${escapeHtml(errMsg)}</td></tr>`;
        }
    } catch (e) {
        console.error('Error loadReceivingData:', e);
        if (tbody) {
            tbody.innerHTML = `<tr><td colspan="9" class="text-center py-10 text-rose-500 font-semibold"><i class="fa-solid fa-circle-exclamation mr-1.5 text-lg block mb-1"></i>Terjadi kesalahan saat memuat data receiving.<br><span class="text-xs text-slate-500 font-normal mt-1 block">${escapeHtml(e.message || 'Kesalahan koneksi')}</span></td></tr>`;
        }
    }
};

// Render tabel receiving dan update kartu KPI
function renderReceivingTable(data) {
    const tbody = document.getElementById('receivingTableBody');
    if (!tbody) return;

    // Update KPI
    const totalBatches = data.length;
    let totalPackages = 0;
    const uniqueExpeditions = new Set();

    data.forEach(item => {
        totalPackages += parseInt(item.total_packages || 0);
        if (item.expedition) uniqueExpeditions.add(item.expedition);
    });

    const elTotalBatches = document.getElementById('summaryReceivingTotalBatches');
    const elTotalPackages = document.getElementById('summaryReceivingTotalPackages');
    const elTotalExpeditions = document.getElementById('summaryReceivingTotalExpeditions');

    if (elTotalBatches) elTotalBatches.innerText = totalBatches.toLocaleString('id-ID');
    if (elTotalPackages) elTotalPackages.innerText = totalPackages.toLocaleString('id-ID');
    if (elTotalExpeditions) elTotalExpeditions.innerText = uniqueExpeditions.size;

    if (!data || data.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="9" class="text-center py-12 text-slate-400">
                    <i class="fa-solid fa-box-open text-3xl mb-2 text-slate-300 block"></i>
                    Tidak ada data receiving inbound yang ditemukan untuk filter ini.
                </td>
            </tr>
        `;
        return;
    }

    let rowsHtml = '';
    data.forEach((item, idx) => {
        const timeStr = item.created_at || '-';
        rowsHtml += `
            <tr class="hover:bg-slate-50 transition border-b border-slate-100">
                <td class="py-3 px-4 font-bold text-slate-400 text-center">${idx + 1}</td>
                <td class="py-3 px-4 font-mono font-bold">
                    <button type="button" onclick="viewReceivingPackagesList(${item.id})" class="inline-flex items-center gap-1.5 font-bold font-mono text-emerald-700 hover:text-emerald-800 bg-emerald-50 hover:bg-emerald-100 px-2.5 py-1 rounded-lg border border-emerald-200/80 transition text-xs shadow-2xs group cursor-pointer" title="Klik untuk lihat detail paket history receiving & foto">
                        <i class="fa-solid fa-receipt text-emerald-600 group-hover:scale-110 transition"></i>
                        <span class="underline decoration-emerald-300 underline-offset-2">${escapeHtml(item.receipt_number)}</span>
                    </button>
                </td>
                <td class="py-3 px-4">
                    <span class="bg-emerald-50 text-emerald-700 font-bold px-2.5 py-1 rounded-lg border border-emerald-200 text-xs inline-block">
                        ${escapeHtml(item.expedition)}
                    </span>
                </td>
                <td class="py-3 px-4 font-medium text-slate-700">
                    ${escapeHtml(item.courier_name || '-')}
                </td>
                <td class="py-3 px-4">
                    ${item.sack_number ? `<span class="bg-amber-50 text-amber-800 text-xs font-bold px-2.5 py-1 rounded-lg border border-amber-200 inline-flex items-center gap-1.5"><i class="fa-solid fa-box-archive text-amber-600 text-[10px]"></i>${escapeHtml(item.sack_number)}</span>` : '<span class="text-slate-400 italic">-</span>'}
                </td>
                <td class="py-3 px-4 text-center">
                    <span class="bg-indigo-50 text-indigo-700 font-black px-2.5 py-1 rounded-lg border border-indigo-200 text-xs inline-block">
                        ${item.total_packages} Paket
                    </span>
                </td>
                <td class="py-3 px-4 font-semibold text-slate-700">${escapeHtml(item.operator_name || '-')}</td>
                <td class="py-3 px-4 text-slate-500 font-mono text-[11px]">${timeStr}</td>
                <td class="py-3 px-4 text-center">
                    <div class="flex items-center justify-center gap-1.5">
                        <button onclick="viewReceivingReceipt(${item.id})" class="bg-emerald-50 hover:bg-emerald-100 text-emerald-700 font-bold px-2.5 py-1.5 rounded-xl border border-emerald-200 text-xs flex items-center gap-1.5 transition shadow-2xs" title="Lihat & Cetak Bukti Serah Terima">
                            <i class="fa-solid fa-file-invoice text-emerald-600"></i>
                            <span>Bukti Serah Terima</span>
                        </button>
                        <button onclick="viewReceivingPackagesList(${item.id})" class="bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-bold px-2.5 py-1.5 rounded-xl border border-indigo-200 text-xs flex items-center gap-1.5 transition shadow-2xs" title="Lihat Detail Paket & Foto">
                            <i class="fa-solid fa-boxes-stacked"></i>
                            <span>Detail Paket</span>
                        </button>
                        <button onclick="deleteReceivingRecord(${item.id}, '${escapeHtml(item.receipt_number)}')" class="bg-rose-50 hover:bg-rose-100 text-rose-600 font-bold p-1.5 rounded-xl border border-rose-200 text-xs flex items-center transition" title="Hapus Data">
                            <i class="fa-solid fa-trash-can"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    });

    tbody.innerHTML = rowsHtml;
}

// Buka Modal Bukti Serah Terima Resmi
window.viewReceivingReceipt = async function (id) {
    showGlobalLoading("Memuat Bukti Serah Terima...", "Mengambil rincian nomor resi paket...");
    try {
        const res = await fetch(`api/reception.php?action=detail&id=${id}`);
        const data = await res.json();
        hideGlobalLoading();

        if (data && data.success && data.reception) {
            const r = data.reception;
            document.getElementById('adminSlipReceiptNo').innerText = r.receipt_number || '-';
            document.getElementById('adminSlipExpedition').innerText = r.expedition || '-';
            document.getElementById('adminSlipDateTime').innerText = r.created_at || '-';
            document.getElementById('adminSlipCourier').innerText = r.courier_name || '-';
            const elSack = document.getElementById('adminSlipSackNumber');
            if (elSack) elSack.innerText = r.sack_number || '-';
            document.getElementById('adminSlipOperator').innerText = r.operator_name || '-';
            const actualTotal = (data.packages && data.packages.length > 0) ? data.packages.length : (r.total_packages || 0);
            document.getElementById('adminSlipTotalPackages').innerText = actualTotal;
            document.getElementById('adminSlipSignOperator').innerText = r.operator_name || 'Gudang';

            // Rekap Total Paket Per Karung
            const sackSummary = {};
            const pkgList = data.packages || [];
            if (pkgList.length > 0) {
                pkgList.forEach(p => {
                    const s = (p.sack_number || r.sack_number || 'Karung 1').trim() || 'Karung 1';
                    sackSummary[s] = (sackSummary[s] || 0) + 1;
                });
            } else if (r.sack_number) {
                sackSummary[r.sack_number] = actualTotal;
            } else {
                sackSummary['Karung 1'] = actualTotal;
            }

            const sackBreakdownList = document.getElementById('adminSlipSackBreakdownList');
            const sackKeys = Object.keys(sackSummary);
            const elTotalSacks = document.getElementById('adminSlipTotalSacksCount');
            if (elTotalSacks) elTotalSacks.innerText = `${sackKeys.length} Karung`;

            if (sackBreakdownList) {
                let sHtml = '';
                sackKeys.forEach(sName => {
                    const count = sackSummary[sName];
                    sHtml += `
                        <div class="bg-white border border-amber-200/90 rounded-lg p-2 flex items-center justify-between shadow-2xs">
                            <span class="font-mono font-bold text-amber-950 text-xs truncate mr-1.5">${escapeHtml(sName)}</span>
                            <span class="bg-amber-100 text-amber-900 font-black text-xs px-2 py-0.5 rounded-md shrink-0">${count} <span class="text-[10px] font-medium font-sans">paket</span></span>
                        </div>
                    `;
                });
                sackBreakdownList.innerHTML = sHtml || '<div class="text-amber-800 text-xs py-1 col-span-2">Tidak ada data karung</div>';
            }

            const listEl = document.getElementById('adminSlipPackageList');
            let listHtml = '';
            (data.packages || []).forEach((bar, i) => {
                const sackTag = bar.sack_number ? `<span class="bg-amber-100 text-amber-800 text-[9px] font-bold px-1 py-0.2 rounded border border-amber-200 ml-1 shrink-0">${escapeHtml(bar.sack_number)}</span>` : '';
                listHtml += `
                    <div class="flex items-center justify-between bg-white rounded-lg border border-slate-200/90 py-1.5 px-2 text-xs">
                        <div class="flex items-center gap-1.5 flex-1 min-w-0 pr-1">
                            <span class="w-5 h-5 rounded-md bg-slate-200 text-slate-700 text-[10px] font-bold flex items-center justify-center shrink-0">${i + 1}</span>
                            <span class="font-mono font-bold text-slate-900 text-xs tracking-tight break-all select-all whitespace-normal leading-tight">${escapeHtml(bar.package_barcode)}</span>
                            ${sackTag}
                        </div>
                        <span class="text-[9px] font-bold text-emerald-700 bg-emerald-50 px-1.5 py-0.5 rounded border border-emerald-300 shrink-0">TERIMA OK</span>
                    </div>
                `;
            });
            listEl.innerHTML = listHtml || '<div class="text-slate-400 text-center py-2 col-span-2">Tidak ada rincian resi.</div>';

            document.getElementById('modalReceivingReceipt').classList.remove('hidden');
        } else {
            showToast('error', data.error || 'Data bukti serah terima tidak ditemukan', 'Gagal Memuat');
        }
    } catch (e) {
        hideGlobalLoading();
        showToast('error', 'Terjadi kesalahan: ' + e.message, 'Gagal Memuat');
    }
};

window.closeReceivingReceiptModal = function () {
    const modal = document.getElementById('modalReceivingReceipt');
    if (modal) modal.classList.add('hidden');
};

// Helper URL path gambar receiving yang aman
window.formatReceivingImgUrl = function (path) {
    if (!path || typeof path !== 'string') return '';
    path = path.trim();
    if (!path) return '';
    if (path.startsWith('data:image') || path.startsWith('http://') || path.startsWith('https://')) {
        return path;
    }
    let clean = path.replace(/^\/+/, '');
    clean = clean.replace(/^(retrun\.inboud|return\.inbound|inbound_return)\//i, '');

    // Deteksi subdirektori proyek dari window.location.pathname
    // Contoh: /inbound_return/admin -> /inbound_return/
    //         /retrun.inboud/admin -> /retrun.inboud/
    //         /admin -> /
    const pathname = window.location.pathname;
    const segments = pathname.split('/').filter(Boolean);
    const knownPages = ['admin', 'admin.php', 'login', 'login.php', 'menu', 'menu.php', 'reception', 'reception.php', 'index.php', 'scanner', 'dossier', 'claim_dossier', 'index'];

    if (segments.length > 0 && !knownPages.includes(segments[0].toLowerCase())) {
        return '/' + segments[0] + '/' + clean;
    }
    return '/' + clean;
};

// Buka Modal Detail Paket & Foto History Receiving
window.viewReceivingPackagesList = async function (id) {
    showGlobalLoading("Memuat Detail Paket...", "Mengambil rincian resi dan dokumentasi foto...");
    try {
        const res = await fetch(`api/reception.php?action=detail&id=${id}`);
        const data = await res.json();
        hideGlobalLoading();

        if (data && data.success && data.reception) {
            const r = data.reception;
            window._activeReceivingId = id;
            window._currentReceivingPackagesData = data.packages || [];
            window._currentReceivingPackages = (data.packages || []).map(p => p.package_barcode);

            const titleEl = document.getElementById('pkgModalTitle');
            if (titleEl) titleEl.innerText = `Detail History Receiving ${r.receipt_number}`;

            const subTitleEl = document.getElementById('pkgModalSubtitle');
            if (subTitleEl) subTitleEl.innerText = `Petugas: ${r.operator_name || '-'} • Diterima: ${r.created_at || '-'}`;

            const rcptNoEl = document.getElementById('pkgModalReceiptNo');
            if (rcptNoEl) rcptNoEl.innerText = r.receipt_number || '-';

            const expCourEl = document.getElementById('pkgModalExpeditionCourier');
            if (expCourEl) expCourEl.innerText = r.expedition || '-';

            const timeEl = document.getElementById('pkgModalTime');
            if (timeEl) timeEl.innerText = r.created_at || '-';

            const totalEl = document.getElementById('pkgModalTotal');
            if (totalEl) totalEl.innerText = `${data.packages ? data.packages.length : (r.total_packages || 0)} Paket`;

            // Kumpulkan foto-foto dokumentasi sesi penerimaan (1 foto per paket unik, tidak dobel)
            let sessionPhotos = [];
            const pkgPhotos = (data.packages || []).map(p => p.photo_path).filter(Boolean);
            if (pkgPhotos.length > 0) {
                sessionPhotos = [...pkgPhotos];
            } else if (r.package_photos) {
                let rawPhotos = [];
                if (Array.isArray(r.package_photos)) {
                    rawPhotos = [...r.package_photos];
                } else if (typeof r.package_photos === 'string') {
                    try {
                        const parsed = JSON.parse(r.package_photos);
                        if (Array.isArray(parsed)) rawPhotos = [...parsed];
                        else if (parsed) rawPhotos = [parsed];
                    } catch (e) {
                        if (r.package_photos.trim()) rawPhotos = [r.package_photos.trim()];
                    }
                }
                sessionPhotos = rawPhotos;
            }

            if (r.photo_path && !sessionPhotos.includes(r.photo_path)) {
                sessionPhotos.unshift(r.photo_path);
            }
            sessionPhotos = Array.from(new Set(sessionPhotos)).filter(p => !!p);
            window._currentSessionPhotos = sessionPhotos;

            // Foto Kurir, Nama Kurir & PIC Penerima Gudang
            const courierImg = document.getElementById('pkgModalCourierImg');
            const courierAvatarPlaceholder = document.getElementById('pkgModalCourierAvatarPlaceholder');
            const courierName = document.getElementById('pkgModalCourierName');
            const courierMeta = document.getElementById('pkgModalCourierMeta');
            const opName = document.getElementById('pkgModalOperatorName');

            if (opName) opName.innerText = r.operator_name || 'Petugas Gudang';
            if (courierName) courierName.innerText = r.courier_name || 'Kurir Ekspedisi';
            if (courierMeta) courierMeta.innerText = `${r.expedition || '-'} • Nopol: ${r.vehicle_no || '-'}`;

            if (r.courier_photo) {
                const formattedCourierPhoto = window.formatReceivingImgUrl(r.courier_photo);
                if (courierImg) {
                    courierImg.src = formattedCourierPhoto;
                    courierImg.classList.remove('hidden');
                    courierImg.onerror = function () {
                        this.classList.add('hidden');
                        if (courierAvatarPlaceholder) courierAvatarPlaceholder.classList.remove('hidden');
                    };
                }
                if (courierAvatarPlaceholder) courierAvatarPlaceholder.classList.add('hidden');
            } else {
                if (courierImg) {
                    courierImg.src = '';
                    courierImg.classList.add('hidden');
                }
                if (courierAvatarPlaceholder) courierAvatarPlaceholder.classList.remove('hidden');
            }

            // Dokumentasi Foto Sesi Penerimaan (Gallery dihapus, foto sudah ada di kartu masing-masing paket)
            const sessionSec = document.getElementById('pkgModalSessionPhotosSection');
            if (sessionSec) sessionSec.classList.add('hidden');

            const pkgPhotoCount = (data.packages || []).filter(p => !!p.photo_path).length;
            const totalAvailablePhotos = pkgPhotoCount > 0 ? pkgPhotoCount : sessionPhotos.length;
            const photoCountEl = document.getElementById('pkgModalPhotoCount');
            if (photoCountEl) photoCountEl.innerText = `${totalAvailablePhotos} Berfoto`;

            // Reset search input
            const searchInput = document.getElementById('pkgModalSearchInput');
            if (searchInput) searchInput.value = '';

            renderReceivingPackageCards(window._currentReceivingPackagesData);

            const modal = document.getElementById('modalReceivingPackages');
            if (modal) modal.classList.remove('hidden');
        } else {
            showToast('error', data.error || 'Gagal memuat detail receiving', 'Gagal');
        }
    } catch (e) {
        hideGlobalLoading();
        showToast('error', e.message, 'Gagal');
    }
};

window.renderReceivingPackageCards = function (packages) {
    const listEl = document.getElementById('pkgModalList');
    if (!listEl) return;

    if (!packages || packages.length === 0) {
        listEl.innerHTML = `
            <div class="col-span-full py-8 text-center text-slate-400">
                <i class="fa-solid fa-boxes-packing text-2xl mb-2 text-slate-300"></i>
                <p>Tidak ada paket yang cocok atau terdaftar dalam sesi ini.</p>
            </div>
        `;
        return;
    }

    let html = '';
    packages.forEach((p, idx) => {
        const barcode = escapeHtml(p.package_barcode || '-');
        let photoPath = p.photo_path ? window.formatReceivingImgUrl(p.photo_path) : '';
        let isSessionFallback = false;

        // Jika tidak ada foto individual per-barcode, gunakan foto dokumentasi sesi serah terima jika ada
        if (!photoPath && window._currentSessionPhotos && window._currentSessionPhotos.length > 0) {
            photoPath = window.formatReceivingImgUrl(window._currentSessionPhotos[0]);
            isSessionFallback = true;
        }

        const sackTag = p.sack_number ? `<span class="bg-amber-50 text-amber-800 text-[10px] font-bold px-1.5 py-0.5 rounded border border-amber-200 font-mono"><i class="fa-solid fa-box-archive text-[9px] mr-1"></i>${escapeHtml(p.sack_number)}</span>` : '';
        const scanTime = p.scanned_at ? (p.scanned_at.includes(' ') ? p.scanned_at.split(' ')[1] : p.scanned_at) : '';

        const photoHtml = photoPath ? `
            <div class="relative group w-14 h-14 sm:w-16 sm:h-16 rounded-xl overflow-hidden border border-slate-200 bg-slate-900 shrink-0 cursor-pointer shadow-2xs hover:border-emerald-500 transition" onclick="openClaimPhotoModal('${photoPath}', 'Foto Paket ${barcode}${isSessionFallback ? ' (Dokumentasi Serah Terima)' : ''}')" title="Klik untuk zoom foto paket">
                <img src="${photoPath}" alt="Foto Paket ${barcode}" class="w-full h-full object-cover group-hover:scale-110 transition duration-300" onerror="this.onerror=null; this.parentElement.classList.add('bg-slate-800'); this.style.display='none'; this.parentElement.insertAdjacentHTML('beforeend', '<div class=\\'flex flex-col items-center justify-center w-full h-full text-slate-400 text-[8px] p-1 text-center\\'><i class=\\'fa-solid fa-triangle-exclamation text-amber-400 text-xs mb-0.5\\'></i><span>Foto Error</span></div>');">
                <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 flex items-center justify-center transition text-white text-xs">
                    <i class="fa-solid fa-magnifying-glass-plus"></i>
                </div>
                ${isSessionFallback ? '<span class="absolute bottom-0 inset-x-0 bg-slate-900/80 text-[7px] text-white font-bold text-center py-0.5">FOTO SESI</span>' : ''}
            </div>
        ` : `
            <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-xl bg-slate-100 border border-dashed border-slate-300 flex flex-col items-center justify-center text-slate-400 shrink-0 select-none" title="Belum ada foto fisik paket">
                <i class="fa-solid fa-camera text-base text-slate-300"></i>
                <span class="text-[8px] font-semibold text-slate-400 mt-0.5">No Foto</span>
            </div>
        `;

        html += `
            <div class="bg-white rounded-2xl border border-slate-200/90 p-2.5 flex items-center gap-3 hover:border-emerald-300 hover:shadow-xs transition group">
                ${photoHtml}
                <div class="min-w-0 flex-1">
                    <div class="flex items-center justify-between gap-1 mb-1">
                        <div class="flex items-center gap-1.5 min-w-0">
                            <span class="w-5 h-5 rounded-md bg-slate-100 text-slate-700 text-[10px] font-black flex items-center justify-center shrink-0 border border-slate-200">${idx + 1}</span>
                            <span class="font-mono font-black text-slate-900 text-xs tracking-wider truncate select-all" title="${barcode}">${barcode}</span>
                        </div>
                        <button type="button" onclick="navigator.clipboard.writeText('${barcode}'); showToast('success', 'Nomor resi berhasil disalin: ' + '${barcode}', 'Tersalin');" class="text-slate-400 hover:text-indigo-600 p-1 rounded-lg hover:bg-slate-100 transition shrink-0" title="Salin No Resi">
                            <i class="fa-regular fa-copy text-xs"></i>
                        </button>
                    </div>
                    <div class="flex items-center gap-1.5 flex-wrap">
                        ${sackTag}
                        ${scanTime ? `<span class="text-[10px] text-slate-400 font-mono"><i class="fa-regular fa-clock text-[9px] mr-0.5"></i>${escapeHtml(scanTime)}</span>` : ''}
                        <span class="text-[9px] bg-emerald-50 text-emerald-700 font-bold px-1.5 py-0.2 rounded border border-emerald-200 shrink-0">TERIMA OK</span>
                    </div>
                </div>
            </div>
        `;
    });

    listEl.innerHTML = html;
};

window.filterReceivingPackagesModal = function (term) {
    if (!window._currentReceivingPackagesData) return;
    const query = (term || '').trim().toLowerCase();
    if (!query) {
        renderReceivingPackageCards(window._currentReceivingPackagesData);
        return;
    }

    const filtered = window._currentReceivingPackagesData.filter(p => {
        const b = (p.package_barcode || '').toLowerCase();
        const s = (p.sack_number || '').toLowerCase();
        return b.includes(query) || s.includes(query);
    });

    renderReceivingPackageCards(filtered);
};

window.printCurrentReceivingFromModal = function () {
    if (window._activeReceivingId) {
        closeReceivingPackagesModal();
        viewReceivingReceipt(window._activeReceivingId);
    }
};

window.closeReceivingPackagesModal = function () {
    const m = document.getElementById('modalReceivingPackages');
    if (m) m.classList.add('hidden');
};

window.copyAllReceivingBarcodes = function () {
    if (window._currentReceivingPackages && window._currentReceivingPackages.length) {
        navigator.clipboard.writeText(window._currentReceivingPackages.join('\n')).then(() => {
            showToast('success', `${window._currentReceivingPackages.length} nomor resi berhasil disalin ke clipboard!`, 'Berhasil Disalin');
        }).catch(() => {
            showToast('info', 'Gagal menyalin otomatis', 'Info');
        });
    }
};

// Hapus Data Penerimaan
window.deleteReceivingRecord = async function (id, receiptNumber) {
    if (!confirm(`Yakin ingin menghapus data penerimaan "${receiptNumber}" beserta seluruh resi di dalamnya?\n\nTindakan ini tidak bisa dibatalkan.`)) {
        return;
    }

    showGlobalLoading("Menghapus Penerimaan...", "Memproses penghapusan data...");
    try {
        const res = await fetch(`api/reception.php?action=delete&id=${id}`, {
            method: 'POST'
        });
        const json = await res.json();
        hideGlobalLoading();

        if (json && json.success) {
            showToast('success', `Penerimaan ${receiptNumber} berhasil dihapus.`, 'Data Dihapus');
            loadReceivingData();
        } else {
            showToast('error', json.error || 'Gagal menghapus data', 'Gagal Hapus');
        }
    } catch (e) {
        hideGlobalLoading();
        showToast('error', e.message, 'Gagal Hapus');
    }
};

// Export Excel Receiving
window.exportReceivingExcel = function () {
    if (!cachedReceivingData || cachedReceivingData.length === 0) {
        showToast('warning', 'Tidak ada data receiving untuk diekspor.', 'Data Kosong');
        return;
    }

    showGlobalLoading("Menyiapkan Excel...", "Mengolah data serah terima...");
    try {
        const excelData = cachedReceivingData.map((r, idx) => ({
            "No": idx + 1,
            "No. Tanda Terima": r.receipt_number || '-',
            "Ekspedisi": r.expedition || '-',
            "Driver / Kurir": r.courier_name || '-',
            "Nomor Karung": r.sack_number || '-',
            "Total Paket (Pieces)": parseInt(r.total_packages || 0),
            "Operator Penerima": r.operator_name || '-',
            "Waktu Penerimaan": r.created_at || '-'
        }));

        generateExcelFile([
            { name: "Receiving Inbound", data: excelData }
        ], "Receiving_Inbound_Ekspedisi");

        hideGlobalLoading();
        showToast('success', 'File Excel Receiving Inbound berhasil diunduh!', 'Ekspor Berhasil');
    } catch (e) {
        hideGlobalLoading();
        showToast('error', 'Gagal ekspor: ' + e.message, 'Gagal Ekspor');
    }
};

// Isi Opsi Filter Ekspedisi secara Dinamis
function populateReceivingExpeditionFilter(data) {
    const sel = document.getElementById('filterReceivingExpedition');
    if (!sel) return;
    const currentVal = sel.value;

    const setExp = new Set();
    data.forEach(item => {
        if (item.expedition) setExp.add(item.expedition);
    });

    let opts = '<option value="">Semua Ekspedisi</option>';
    setExp.forEach(name => {
        opts += `<option value="${escapeHtml(name)}" ${currentVal === name ? 'selected' : ''}>${escapeHtml(name)}</option>`;
    });
    sel.innerHTML = opts;
}

// =========================================================================
// PUSAT KLAIM & BANDING EKSPEDISI / MARKETPLACE (CLAIM DOSSIER)
// =========================================================================
window.currentClaimDossier = null;

window.executeClaimLookup = async function (e) {
    if (e && e.preventDefault) e.preventDefault();

    const input = document.getElementById('claimSearchInput');
    const query = input ? input.value.trim() : '';

    if (!query) {
        showToast('warning', 'Masukkan nomor resi atau Order ID terlebih dahulu', 'Input Kosong');
        return;
    }

    // Langsung buka modal popup detail berkas klaim!
    openClaimDetailModal(query);
};

function renderClaimDossier(data) {
    const emptyPlaceholder = document.getElementById('claimEmptyPlaceholder');
    const resultContainer = document.getElementById('claimResultContainer');

    if (emptyPlaceholder) emptyPlaceholder.classList.add('hidden');
    if (resultContainer) resultContainer.classList.remove('hidden');

    const order = data.order || {};
    const reception = data.reception || null;
    const unboxing = data.unboxing || null;
    const readiness = data.claim_readiness || {};
    const photos = data.photos || [];

    // Header Title
    const orderTitle = document.getElementById('claimOrderTitle');
    if (orderTitle) {
        orderTitle.innerText = `Order #${order.Id || '-'}  •  Resi #${order.TrackingNumber || reception?.package_barcode || data.query}`;
    }

    const shopSub = document.getElementById('claimShopSubtitle');
    if (shopSub) {
        shopSub.innerText = `Toko: ${order.ShopName || '-'} | Ekspedisi: ${order.ShippingProvider || reception?.expedition || '-'}`;
    }

    const platformBadge = document.getElementById('claimMarketplaceBadge');
    if (platformBadge) {
        platformBadge.innerText = (order.CommercePlatform || 'Marketplace').toUpperCase();
    }

    // Badge status sumber data OCS
    const ocsBadge = document.getElementById('claimOcsBadge');
    if (ocsBadge) {
        const src = data.ocs_source || data.ocs_found ? data.ocs_source : 'local_only';
        if (src === 'ocs_order_detail') {
            ocsBadge.className = 'inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-700 border border-emerald-300';
            ocsBadge.innerHTML = '<i class="fa-solid fa-circle-check"></i> Data OCS Live';
            ocsBadge.title = 'Data diambil langsung dari OCS IEG System (real-time)';
        } else if (src === 'local_cache') {
            ocsBadge.className = 'inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold bg-sky-100 text-sky-700 border border-sky-300';
            ocsBadge.innerHTML = '<i class="fa-solid fa-database"></i> Cache OCS';
            ocsBadge.title = 'Data dari cache OCS lokal. Klik refresh untuk update.';
        } else {
            ocsBadge.className = 'inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-700 border border-amber-300';
            ocsBadge.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> Lokal Saja';
            ocsBadge.title = 'Tidak ditemukan di OCS. Harga/detail mungkin tidak tersedia.';
        }
    }

    // Checklist Badges
    updateChecklistBadge('iconCheckOrder', 'labelCheckOrder', readiness.has_order || readiness.has_tracking, 'Terverifikasi', 'Tidak Ditemukan');
    updateChecklistBadge('iconCheckRec', 'labelCheckRec', readiness.has_reception, 'Diterima di Gudang', 'Belum Ada Tanda Terima');
    updateChecklistBadge('iconCheckUnbox', 'labelCheckUnbox', readiness.has_unbox_video, 'Terekam Lengkap', 'Belum Di-unboxing');
    updateChecklistBadge('iconCheckPhotos', 'labelCheckPhotos', photos.length > 0, `${photos.length} Foto Tersedia`, 'Belum Ada Foto');

    // Banner Status Kelayakan Klaim: HANYA PAKET DENGAN KONDISI BUKAN GOOD / BAGUS
    const eligBanner = document.getElementById('claimEligibilityBanner');
    const eligIcon = document.getElementById('claimEligibilityIcon');
    const eligTitle = document.getElementById('claimEligibilityTitle');
    const eligSubtitle = document.getElementById('claimEligibilitySubtitle');
    const eligTag = document.getElementById('claimEligibilityTag');

    if (eligBanner && eligTitle && eligSubtitle && eligTag) {
        if (data.is_claimable) {
            eligBanner.className = 'p-4 rounded-2xl border text-xs flex flex-col sm:flex-row sm:items-center justify-between gap-3 transition bg-rose-50 border-rose-200 text-rose-900 shadow-2xs';
            if (eligIcon) {
                eligIcon.className = 'w-9 h-9 rounded-xl flex items-center justify-center text-base shrink-0 bg-rose-100 text-rose-600';
                eligIcon.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i>';
            }
            eligTitle.innerText = '⚠️ PAKET LAYAK KLAIM (Kondisi Rusak / Cacat)';
            eligSubtitle.innerText = data.claim_eligibility_reason || 'Kondisi barang tercatat cacat/rusak saat unboxing retur. Memenuhi syarat untuk diajukan klaim atau banding ekspedisi.';
            eligTag.className = 'px-3 py-1 rounded-lg text-white font-black text-[10px] uppercase tracking-wider shrink-0 self-start sm:self-center bg-rose-600';
            eligTag.innerText = 'LAYAK KLAIM';
        } else if (!data.unboxing) {
            eligBanner.className = 'p-4 rounded-2xl border text-xs flex flex-col sm:flex-row sm:items-center justify-between gap-3 transition bg-amber-50 border-amber-200 text-amber-900 shadow-2xs';
            if (eligIcon) {
                eligIcon.className = 'w-9 h-9 rounded-xl flex items-center justify-center text-base shrink-0 bg-amber-100 text-amber-600';
                eligIcon.innerHTML = '<i class="fa-solid fa-box-open"></i>';
            }
            eligTitle.innerText = '⏳ BELUM DI-UNBOXING DI GUDANG';
            eligSubtitle.innerText = data.claim_eligibility_reason || 'Pemeriksaan fisik barang belum dilakukan di stasiun unboxing retur, sehingga status kerusakan belum dapat diverifikasi.';
            eligTag.className = 'px-3 py-1 rounded-lg text-white font-black text-[10px] uppercase tracking-wider shrink-0 self-start sm:self-center bg-amber-500';
            eligTag.innerText = 'BELUM UNBOXING';
        } else {
            eligBanner.className = 'p-4 rounded-2xl border text-xs flex flex-col sm:flex-row sm:items-center justify-between gap-3 transition bg-emerald-50 border-emerald-200 text-emerald-900 shadow-2xs';
            if (eligIcon) {
                eligIcon.className = 'w-9 h-9 rounded-xl flex items-center justify-center text-base shrink-0 bg-emerald-100 text-emerald-600';
                eligIcon.innerHTML = '<i class="fa-solid fa-circle-check"></i>';
            }
            eligTitle.innerText = '✓ BUKAN PAKET KLAIM (Kondisi Bagus / Good)';
            eligSubtitle.innerText = data.claim_eligibility_reason || 'Kondisi barang tercatat BAGUS (GOOD). Paket retur normal, BUKAN paket klaim kerusakan.';
            eligTag.className = 'px-3 py-1 rounded-lg text-white font-black text-[10px] uppercase tracking-wider shrink-0 self-start sm:self-center bg-emerald-600';
            eligTag.innerText = 'RETUR NORMAL';
        }
    }

    // Media 1: Video Packing Outbound (Synology NAS-IEG 192.168.30.5:5001 /PACKER)
    const packVideoEl = document.getElementById('playerPackingVideo');
    const noPackPlaceholder = document.getElementById('noPackingVideoPlaceholder');
    const packStatusText = document.getElementById('packingVideoStatusText');
    const packNameText = document.getElementById('packingFileNameText');
    const packDateText = document.getElementById('packingFileDateText');
    const packDirectBtn = document.getElementById('btnOpenNasStationDirect');

    const packingData = data.packing_video || null;
    if (packingData && packingData.has_video && packingData.primary_video) {
        const primary = packingData.primary_video;
        if (packVideoEl) {
            packVideoEl.src = primary.stream_url;
            packVideoEl.classList.remove('hidden');
        }
        if (noPackPlaceholder) noPackPlaceholder.classList.add('hidden');
        if (packNameText) packNameText.innerText = primary.name || '-';
        if (packDateText) packDateText.innerText = primary.size_formatted || 'NAS-IEG';
        if (packDirectBtn) packDirectBtn.href = primary.direct_nas_url || 'https://192.168.30.5:5001/#/signin';
    } else {
        if (packVideoEl) {
            packVideoEl.pause();
            packVideoEl.removeAttribute('src');
            packVideoEl.classList.add('hidden');
        }
        if (noPackPlaceholder) noPackPlaceholder.classList.remove('hidden');
        if (packStatusText) {
            if (packingData && packingData.requires_auth) {
                packStatusText.innerText = 'Kredensial Synology NAS (192.168.30.5) belum disimpan. Klik tombol di bawah untuk mengisi akun NAS atau buka langsung File Station.';
            } else if (packingData && packingData.message) {
                packStatusText.innerText = packingData.message;
            } else {
                packStatusText.innerText = `Video packing tidak ditemukan di folder /PACKER untuk: ${data.query}`;
            }
        }
        if (packNameText) packNameText.innerText = '-';
        if (packDateText) packDateText.innerText = 'NAS 192.168.30.5:5001';
        if (packDirectBtn) packDirectBtn.href = 'https://192.168.30.5:5001/#/signin';
    }

    // Media 2: Video Unboxing Retur
    const unboxVideoEl = document.getElementById('playerUnboxingVideo');
    const noUnboxPlaceholder = document.getElementById('noUnboxingVideoPlaceholder');
    const unboxOpText = document.getElementById('unboxingOperatorText');
    const unboxTimeText = document.getElementById('unboxingTimestampText');

    if (unboxing && unboxing.video_path) {
        unboxVideoEl.src = unboxing.video_path;
        unboxVideoEl.classList.remove('hidden');
        if (noUnboxPlaceholder) noUnboxPlaceholder.classList.add('hidden');
        if (unboxOpText) unboxOpText.innerText = unboxing.operator_name || 'Operator';
        if (unboxTimeText) unboxTimeText.innerText = unboxing.created_at || '-';
    } else {
        if (unboxVideoEl) {
            unboxVideoEl.pause();
            unboxVideoEl.removeAttribute('src');
            unboxVideoEl.classList.add('hidden');
        }
        if (noUnboxPlaceholder) noUnboxPlaceholder.classList.remove('hidden');
        if (unboxOpText) unboxOpText.innerText = '-';
        if (unboxTimeText) unboxTimeText.innerText = 'Belum ada rekaman';
    }

    // Media 2: Galeri Foto Bukti Retur & Serah Terima
    const galleryEl = document.getElementById('claimPhotoGallery');
    const noPhotosEl = document.getElementById('noPhotosPlaceholder');
    const countBadge = document.getElementById('claimPhotoCountBadge');
    const totalText = document.getElementById('claimPhotoTotalText');

    if (countBadge) countBadge.innerText = `${photos.length} Foto`;
    if (totalText) totalText.innerText = `${photos.length} Foto Bukti Tersimpan`;

    if (photos.length > 0 && galleryEl) {
        galleryEl.innerHTML = photos.map((p, idx) => {
            const isDmg = (p.is_damaged || p.badge === 'Barang Rusak' || (p.title && p.title.toLowerCase().includes('rusak')));
            const borderCls = isDmg ? 'border-2 border-rose-500 shadow-md shadow-rose-500/20' : 'border border-slate-200';
            const badgeBg = isDmg ? 'bg-rose-600 text-white font-black' : 'bg-slate-900/80 text-white font-bold';
            const badgeLabel = isDmg ? '⚠️ BARANG RUSAK' : (p.badge || 'Bukti');
            return `
            <div class="relative group rounded-xl overflow-hidden ${borderCls} bg-slate-100 aspect-video cursor-pointer shadow-2xs hover:shadow-md transition" onclick="openClaimPhotoModal('${encodeURI(p.url)}', '${encodeURIComponent(p.title || 'Foto Bukti')}')">
                <img src="${p.url}" alt="${p.title || 'Foto'}" class="w-full h-full object-cover group-hover:scale-105 transition duration-300">
                <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent opacity-0 group-hover:opacity-100 transition flex items-end p-2">
                    <span class="text-[10px] text-white font-semibold truncate"><i class="fa-solid fa-magnifying-glass-plus mr-1"></i>${p.title || 'Perbesar'}</span>
                </div>
                <span class="absolute top-1.5 left-1.5 px-1.5 py-0.5 rounded ${badgeBg} text-[9px] uppercase tracking-wider backdrop-blur-xs">
                    ${badgeLabel}
                </span>
            </div>
            `;
        }).join('');
        galleryEl.classList.remove('hidden');
        if (noPhotosEl) noPhotosEl.classList.add('hidden');
    } else {
        if (galleryEl) {
            galleryEl.innerHTML = '';
            galleryEl.classList.add('hidden');
        }
        if (noPhotosEl) noPhotosEl.classList.remove('hidden');
    }

    // Header Badges Biaya
    const priceBadgeText = document.getElementById('claimPriceText');
    if (priceBadgeText) {
        priceBadgeText.innerText = order.PackagePriceFormatted || (order.PackagePrice ? 'Rp ' + Number(order.PackagePrice).toLocaleString('id-ID') : 'Rp -');
    }
    const totalClaimBadge = document.getElementById('claimTotalClaimText');
    if (totalClaimBadge) {
        totalClaimBadge.innerText = order.TotalClaimAmountFormatted || (order.TotalClaimAmount ? 'Rp ' + Number(order.TotalClaimAmount).toLocaleString('id-ID') : (order.PackagePriceFormatted || 'Rp -'));
    }

    // Card 1: Rincian Biaya & Tuntutan Klaim
    setElText('detailPackagePrice', order.PackagePriceFormatted || (order.PackagePrice ? 'Rp ' + Number(order.PackagePrice).toLocaleString('id-ID') : 'Rp -'));
    setElText('detailShippingFee', order.ShippingFeeFormatted || (order.ShippingFee ? 'Rp ' + Number(order.ShippingFee).toLocaleString('id-ID') : 'Rp 0'));
    setElText('detailTotalClaim', order.TotalClaimAmountFormatted || (order.TotalClaimAmount ? 'Rp ' + Number(order.TotalClaimAmount).toLocaleString('id-ID') : (order.PackagePriceFormatted || 'Rp -')));
    setElText('detailGmvPrice', order.GMV ? 'Rp ' + Number(order.GMV).toLocaleString('id-ID') : (order.PackagePriceFormatted || '-'));

    // Card 2: Detail Ekspedisi & Serah Terima Kurir
    setElText('detailShippingProvider', order.ShippingProvider || reception?.expedition || '-');
    setElText('detailTrackingNumber', order.TrackingNumber || reception?.package_barcode || data.query || '-');
    setElText('detailCourier', reception ? `${reception.courier_name || '-'} (${reception.expedition || ''})` : '- (Belum discan serah terima)');
    setElText('detailReceiptNo', reception?.receipt_number || '- (Belum ada surat jalan)');
    setElText('detailReceivedAt', reception?.scanned_at || reception?.created_at || '-');

    // Card 3: Detail Paket & Hasil Unboxing
    setElText('detailOrderId', order.Id || '-');
    setElText('detailShopName', `${order.CommercePlatform || 'Marketplace'} • ${order.ShopName || '-'}`);
    setElText('detailUnboxStatus', unboxing ? `${unboxing.status} (${unboxing.total_damaged || 0} Rusak, ${unboxing.total_good || 0} Bagus)` : '- (Belum di-unboxing)');

    let prodItemsHtml = '';
    if (unboxing?.items && unboxing.items.length > 0) {
        prodItemsHtml = unboxing.items.map(i => {
            const cond = String(i.condition || '').toUpperCase().trim();
            const typ = String(i.type || '').toUpperCase().trim();
            const isItemDmg = (cond !== '' && cond !== 'GOOD' && cond !== 'BAGUS') ||
                (typ !== '' && typ !== 'GOOD' && typ !== 'BAGUS') ||
                Boolean(i.damage_reason);
            const badgeClass = isItemDmg ? 'bg-rose-100 text-rose-800 border-rose-300 font-bold' : 'bg-emerald-100 text-emerald-800 border-emerald-300';
            const condText = isItemDmg ? `⚠️ ${i.type || i.condition || 'RUSAK'}${i.damage_reason ? ' (' + i.damage_reason + ')' : ''}` : 'GOOD';
            return `
                <div class="flex items-center justify-between py-1.5 px-2 rounded-lg ${isItemDmg ? 'bg-rose-50/90 border border-rose-200' : 'bg-slate-50 border border-slate-100'} gap-2">
                    <div class="truncate flex-1 min-w-0">
                        <span class="font-bold text-slate-800 text-xs block truncate" title="${i.product_name || i.barcode}">${i.product_name || i.barcode}</span>
                        <div class="flex items-center gap-2 text-[10px] text-slate-400 font-mono">
                            ${i.seller_sku ? `<span>SKU: ${i.seller_sku}</span>` : ''}
                            ${i.batch_no ? `<span>Batch: ${i.batch_no}</span>` : ''}
                        </div>
                    </div>
                    <div class="flex items-center gap-1.5 shrink-0">
                        <span class="px-2 py-0.5 rounded-md font-mono font-bold text-xs bg-slate-900 text-white shadow-2xs">
                            ${i.qty || 1} Unit
                        </span>
                        <span class="px-2 py-0.5 rounded-md text-[10px] border ${badgeClass}">
                            ${condText}
                        </span>
                    </div>
                </div>
            `;
        }).join('');
    } else if (order.ProductName) {
        prodItemsHtml = `<div class="font-bold text-slate-800 text-xs p-1">${order.ProductName}</div>`;
    }
    const prodEl = document.getElementById('detailOrderProductName');
    if (prodEl) {
        prodEl.innerHTML = prodItemsHtml || '<span class="text-slate-400 italic">Data produk tidak tersedia</span>';
    }

    let notes = '-';
    if (unboxing) {
        const damageItems = (unboxing.items || []).filter(i => {
            const cond = String(i.condition || '').toUpperCase().trim();
            const typ = String(i.type || '').toUpperCase().trim();
            const rsn = String(i.damage_reason || '').trim();
            return (cond !== '' && cond !== 'GOOD' && cond !== 'BAGUS') ||
                (typ !== '' && typ !== 'GOOD' && typ !== 'BAGUS') ||
                rsn !== '';
        });
        if (damageItems.length > 0) {
            notes = damageItems.map(i => {
                const label = i.product_name || i.barcode || 'Produk';
                const detail = i.damage_reason || i.type || i.condition || 'Rusak';
                return `• <b class="text-rose-700">${label}</b>: <span class="text-rose-600 font-semibold">${detail}</span>`;
            }).join('<br>');
        } else if ((unboxing.total_damaged || 0) > 0) {
            notes = `• <b class="text-rose-700">${unboxing.total_damaged} item</b> tercatat RUSAK / CACAT saat unboxing.${unboxing.notes ? ' <br>Catatan: ' + unboxing.notes : ''}`;
        } else if (unboxing.notes && unboxing.notes.trim() !== '') {
            notes = unboxing.notes;
        } else {
            notes = 'Semua barang dalam kondisi baik (GOOD).';
        }
    } else if (order.ReturnReason || order.ReturnReasonText) {
        notes = `Alasan Retur Marketplace: [${order.ReturnReason || ''}] ${order.ReturnReasonText || ''}`;
    }
    const notesEl = document.getElementById('detailConditionNotes');
    if (notesEl) {
        notesEl.innerHTML = notes;
        if (data.is_claimable || (unboxing && (unboxing.total_damaged || 0) > 0)) {
            notesEl.className = 'font-semibold text-rose-900 bg-rose-50 p-2.5 rounded-lg border border-rose-200 text-xs max-h-24 overflow-y-auto space-y-1';
        } else {
            notesEl.className = 'font-semibold text-slate-800 bg-amber-50 p-2 rounded-lg border border-amber-200 text-xs max-h-20 overflow-y-auto';
        }
    }
}

window.openClaimPhotoModal = function (url, encodedTitle) {
    const modal = document.getElementById('claimPhotoModal');
    const img = document.getElementById('claimPhotoModalImg');
    const titleEl = document.getElementById('claimPhotoModalTitle');
    const dlBtn = document.getElementById('btnDownloadClaimPhoto');
    if (!modal || !img) return;

    const title = decodeURIComponent(encodedTitle || 'Foto Bukti Retur');
    img.src = url;
    if (titleEl) titleEl.innerText = title;
    if (dlBtn) {
        dlBtn.href = url;
        dlBtn.setAttribute('download', title.replace(/[^a-zA-Z0-9_-]/g, '_') + '.jpg');
    }
    modal.classList.remove('hidden');
};

window.closeClaimPhotoModal = function () {
    const modal = document.getElementById('claimPhotoModal');
    if (modal) modal.classList.add('hidden');
};

window.openNasConfigModal = function () {
    const modal = document.getElementById('modalNasConfig');
    if (modal) modal.classList.remove('hidden');
};

window.closeNasConfigModal = function () {
    const modal = document.getElementById('modalNasConfig');
    if (modal) modal.classList.add('hidden');
};

window.saveNasConfig = async function () {
    const user = document.getElementById('nasConfigUser')?.value || '';
    const pass = document.getElementById('nasConfigPass')?.value || '';
    const folder = document.getElementById('nasConfigFolder')?.value || '/PACKER';
    const resultBox = document.getElementById('nasTestResultBox');
    const btn = document.getElementById('btnSaveNasConfig');

    if (!user || !pass) {
        showToast('warning', 'Harap masukkan Username dan Password DSM Synology NAS', 'Input Kurang');
        return;
    }

    if (btn) btn.disabled = true;
    if (resultBox) {
        resultBox.className = 'p-2.5 rounded-xl text-[11px] bg-slate-100 text-slate-700 block';
        resultBox.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i> Menyimpan & menguji autentikasi NAS...';
    }

    try {
        const formData = new FormData();
        formData.append('action', 'save_config');
        formData.append('nas_user', user);
        formData.append('nas_pass', pass);
        formData.append('nas_folder', folder);

        const res = await fetch('api/nas_video.php', { method: 'POST', body: formData });
        const data = await res.json();

        if (data.success && data.authenticated) {
            if (resultBox) {
                resultBox.className = 'p-2.5 rounded-xl text-[11px] bg-emerald-50 text-emerald-800 border border-emerald-200 block';
                resultBox.innerHTML = '<i class="fa-solid fa-circle-check text-emerald-600 mr-1"></i> Berhasil terhubung ke Synology NAS 192.168.30.5!';
            }
            showToast('success', 'Konfigurasi NAS berhasil disimpan & terhubung!', 'NAS Terhubung');
            setTimeout(() => {
                closeNasConfigModal();
                if (window.currentClaimDossier) {
                    executeClaimLookup();
                }
            }, 1200);
        } else {
            if (resultBox) {
                resultBox.className = 'p-2.5 rounded-xl text-[11px] bg-rose-50 text-rose-800 border border-rose-200 block';
                resultBox.innerHTML = `<i class="fa-solid fa-triangle-exclamation text-rose-600 mr-1"></i> ${data.message || 'Login NAS ditolak. Periksa username & password.'}`;
            }
            showToast('error', data.message || 'Login NAS ditolak', 'Gagal Login NAS');
        }
    } catch (err) {
        if (resultBox) {
            resultBox.className = 'p-2.5 rounded-xl text-[11px] bg-rose-50 text-rose-800 border border-rose-200 block';
            resultBox.innerHTML = `<i class="fa-solid fa-circle-xmark text-rose-600 mr-1"></i> Error: ${err.message}`;
        }
        showToast('error', err.message, 'Koneksi Error');
    } finally {
        if (btn) btn.disabled = false;
    }
};

function updateChecklistBadge(iconId, labelId, isOk, textOk, textFail) {
    const icon = document.getElementById(iconId);
    const label = document.getElementById(labelId);
    if (!icon || !label) return;

    if (isOk) {
        icon.className = 'fa-solid fa-circle-check text-emerald-400 text-base';
        label.innerText = textOk;
        label.className = 'text-[10px] text-emerald-300 font-semibold';
    } else {
        icon.className = 'fa-solid fa-circle-xmark text-rose-400 text-base';
        label.innerText = textFail;
        label.className = 'text-[10px] text-rose-300 font-semibold';
    }
}

function setElText(id, text) {
    const el = document.getElementById(id);
    if (el) el.innerText = text;
}

window.copyClaimPacketSummary = function () {
    if (!window.currentClaimDossier) {
        showToast('warning', 'Belum ada data klaim yang dipilih', 'Peringatan');
        return;
    }
    const d = window.currentClaimDossier;
    const ord = d.order || {};
    const rec = d.reception || {};
    const unb = d.unboxing || {};
    const photos = d.photos || [];

    const summary = `=== BERKAS KLAIM & BANDING EKSPEDISI ===\n` +
        `No. Resi Pengiriman (AWB): ${ord.TrackingNumber || rec.package_barcode || d.query}\n` +
        `No. Order / Invoice      : ${ord.Id || '-'}\n` +
        `Platform / Toko          : ${ord.CommercePlatform || '-'} • ${ord.ShopName || '-'}\n` +
        `----------------------------------------\n` +
        `RINCIAN BIAYA & TUNTUTAN:\n` +
        `• Nilai / Harga Barang   : ${ord.PackagePriceFormatted || '-'}\n` +
        `• Biaya Kirim Ekspedisi  : ${ord.ShippingFeeFormatted || '-'}\n` +
        `• Total Estimasi Tuntutan: ${ord.TotalClaimAmountFormatted || ord.PackagePriceFormatted || '-'}\n` +
        `----------------------------------------\n` +
        `DETAIL EKSPEDISI & SERAH TERIMA:\n` +
        `• Jasa Ekspedisi         : ${ord.ShippingProvider || rec.expedition || '-'}\n` +
        `• Kurir / Driver         : ${rec.courier_name || '-'} (${rec.expedition || ''})\n` +
        `• No. Tanda Terima       : ${rec.receipt_number || '-'}\n` +
        `• Waktu Diterima Fisik   : ${rec.scanned_at || rec.created_at || '-'}\n` +
        `----------------------------------------\n` +
        `DETAIL PAKET & UNBOXING:\n` +
        `• Produk                 : ${ord.ProductName || '-'}\n` +
        `• Status Unboxing        : ${unb.status || '-'} (${unb.total_damaged || 0} Rusak, ${unb.total_good || 0} Bagus)\n` +
        `• Video Unboxing Retur   : ${unb.video_path ? 'TEREKAM LENGKAP' : 'BELUM ADA'}\n` +
        `• Foto Bukti Fisik       : ${photos.length} foto tersedia\n` +
        `Waktu Generate           : ${new Date().toLocaleString('id-ID')}\n` +
        `========================================`;

    navigator.clipboard.writeText(summary).then(() => {
        showToast('success', 'Ringkasan bukti klaim berhasil disalin ke clipboard!', 'Tersalin');
    }).catch(() => {
        showToast('info', 'Gagal menyalin otomatis, silakan salin manual.', 'Info');
    });
};

window.printClaimDossier = function () {
    if (!window.currentClaimDossier) {
        showToast('warning', 'Belum ada data klaim untuk dicetak', 'Peringatan');
        return;
    }
    const d = window.currentClaimDossier;
    const ord = d.order || {};
    const rec = d.reception || {};
    const unb = d.unboxing || {};
    const photos = d.photos || [];

    const printWin = window.open('', '_blank', 'width=950,height=800');
    if (!printWin) {
        showToast('error', 'Popup diblokir oleh browser. Izinkan popup untuk mencetak.', 'Popup Diblokir');
        return;
    }

    const html = `<!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <title>Berkas Klaim - ${ord.TrackingNumber || ord.Id || 'Dossier'}</title>
        <style>
            @page { size: A4 portrait; margin: 12mm; }
            body { font-family: Arial, sans-serif; font-size: 10pt; color: #1e293b; margin: 0; padding: 15px; line-height: 1.35; }
            .header { display: flex; align-items: center; justify-content: space-between; border-bottom: 2px solid #0f172a; padding-bottom: 10px; margin-bottom: 12px; }
            .title { text-align: center; margin-bottom: 14px; }
            .title h2 { margin: 0; font-size: 14pt; text-transform: uppercase; color: #0f172a; }
            .title p { margin: 3px 0 0 0; font-size: 8.5pt; color: #64748b; }
            .box { border: 1px solid #cbd5e1; border-radius: 6px; padding: 10px; margin-bottom: 10px; }
            .box-title { font-weight: bold; font-size: 9.5pt; text-transform: uppercase; color: #334155; border-bottom: 1px solid #e2e8f0; padding-bottom: 5px; margin-bottom: 6px; }
            table { width: 100%; border-collapse: collapse; font-size: 9.5pt; }
            td { padding: 3px 5px; vertical-align: top; }
            .label { width: 35%; color: #64748b; font-weight: normal; }
            .val { font-weight: bold; color: #0f172a; }
            .badge-ok { background: #dcfce7; color: #15803d; padding: 2px 6px; border-radius: 4px; font-weight: bold; font-size: 8.5pt; display: inline-block; }
            .badge-no { background: #fee2e2; color: #b91c1c; padding: 2px 6px; border-radius: 4px; font-weight: bold; font-size: 8.5pt; display: inline-block; }
            .photos-grid { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 6px; }
            .photo-item { width: 31%; border: 1px solid #e2e8f0; border-radius: 4px; padding: 4px; text-align: center; box-sizing: border-box; }
            .photo-item img { width: 100%; height: 110px; object-fit: cover; border-radius: 3px; }
            .photo-item span { display: block; font-size: 7.5pt; color: #475569; margin-top: 3px; font-weight: bold; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
            .signatures { display: flex; justify-content: space-between; margin-top: 30px; text-align: center; }
            .sig-box { width: 45%; }
            .sig-line { margin-top: 50px; border-bottom: 1px solid #0f172a; font-weight: bold; }
        </style>
    </head>
    <body>
        <div class="header">
            <div style="display: flex; align-items: center; gap: 12px;">
                <img src="assets/image/logo-IEG.png" alt="Logo IEG" style="height: 48px; width: auto; object-fit: contain;">
                <div>
                    <b style="font-size: 12pt; color: #0f172a; text-transform: uppercase;">IEG Inovasi Eka Gemilang</b><br>
                    <span style="font-size: 8.5pt; color: #475569; font-weight: 600;">Warehouse Return &amp; Dispute</span><br>
                    <span style="font-size: 7.5pt; color: #64748b;">Expedition Dispute &amp; Insurance Claim Management</span>
                </div>
            </div>
            <div style="text-align: right; font-size: 8.5pt; color: #64748b;">
                Tanggal: <b>${new Date().toLocaleDateString('id-ID')}</b><br>
                Status: <span class="badge-ok">VERIFIKASI SISTEM</span>
            </div>
        </div>

        <div class="title">
            <h2>BERITA ACARA BUKTI BANDING / KLAIM EKSPEDISI</h2>
            <p>Lampiran Resmi Bukti Cross-Lookup Ekspedisi, Biaya & Rekaman Inbound Warehouse</p>
        </div>

        ${d.is_claimable ? `
        <div style="background: #fee2e2; border: 1.5px solid #ef4444; border-radius: 6px; padding: 8px 12px; margin-bottom: 10px; color: #991b1b;">
            <b style="font-size: 10pt;">⚠️ STATUS: PAKET LAYAK KLAIM / BANDING EKSPEDISI (Kondisi Rusak / Cacat)</b><br>
            <span style="font-size: 8.5pt;">${d.claim_eligibility_reason || 'Kondisi barang tercatat cacat/rusak saat unboxing retur.'}</span>
        </div>
        ` : (!unb ? `
        <div style="background: #fef3c7; border: 1.5px solid #f59e0b; border-radius: 6px; padding: 8px 12px; margin-bottom: 10px; color: #92400e;">
            <b style="font-size: 10pt;">⏳ STATUS: BELUM DI-UNBOXING DI GUDANG</b><br>
            <span style="font-size: 8.5pt;">${d.claim_eligibility_reason || 'Pemeriksaan fisik barang belum dilakukan di stasiun unboxing retur.'}</span>
        </div>
        ` : `
        <div style="background: #dcfce7; border: 1.5px solid #22c55e; border-radius: 6px; padding: 8px 12px; margin-bottom: 10px; color: #166534;">
            <b style="font-size: 10pt;">✓ STATUS: BUKAN PAKET KLAIM (Kondisi Good / Retur Normal)</b><br>
            <span style="font-size: 8.5pt;">${d.claim_eligibility_reason || 'Barang diterima dalam kondisi baik. Tidak memenuhi syarat klaim kerusakan.'}</span>
        </div>
        `)}

        <div class="box">
            <div class="box-title">I. IDENTITAS PESANAN & DETAIL EKSPEDISI</div>
            <table>
                <tr><td class="label">Nomor Resi Paket (AWB):</td><td class="val" style="font-size: 11pt; font-family: monospace;">${ord.TrackingNumber || rec.package_barcode || d.query}</td></tr>
                <tr><td class="label">Nomor Order / Invoice:</td><td class="val">${ord.Id || '-'}</td></tr>
                <tr><td class="label">Jasa Ekspedisi Pengiriman:</td><td class="val" style="color: #4338ca;">${ord.ShippingProvider || rec.expedition || '-'}</td></tr>
                <tr><td class="label">Marketplace / Platform:</td><td class="val">${ord.CommercePlatform || '-'}</td></tr>
                <tr><td class="label">Nama Official Shop / Toko:</td><td class="val">${ord.ShopName || '-'}</td></tr>
                <tr><td class="label">Keterangan Produk:</td><td class="val">${ord.ProductName || '-'}</td></tr>
            </table>
        </div>

        <div class="box">
            <div class="box-title">II. RINCIAN BIAYA & ESTIMASI TUNTUTAN GANTI RUGI</div>
            <table>
                <tr><td class="label">Nilai / Harga Barang (NMV):</td><td class="val" style="color: #047857; font-size: 10.5pt; font-weight: 900;">${ord.PackagePriceFormatted || '-'}</td></tr>
                <tr><td class="label">Biaya / Ongkos Kirim:</td><td class="val">${ord.ShippingFeeFormatted || 'Rp 0'}</td></tr>
                <tr><td class="label">Total Estimasi Tuntutan Klaim:</td><td class="val" style="color: #b91c1c; font-size: 11pt; font-weight: 900;">${ord.TotalClaimAmountFormatted || ord.PackagePriceFormatted || '-'}</td></tr>
            </table>
        </div>

        <div class="box">
            <div class="box-title">III. BUKTI FISIK SERAH TERIMA DARI EKSPEDISI (RECEIVING INBOUND)</div>
            <table>
                <tr><td class="label">No. Tanda Terima Ekspedisi:</td><td class="val">${rec.receipt_number || '-'}</td></tr>
                <tr><td class="label">Kurir / Driver Pengantar:</td><td class="val">${rec.courier_name || '-'} (${rec.expedition || ''})</td></tr>
                <tr><td class="label">Waktu Fisik Diterima:</td><td class="val">${rec.scanned_at || rec.created_at || '-'}</td></tr>
                <tr><td class="label">Operator Penerima:</td><td class="val">${rec.operator_name || '-'}</td></tr>
            </table>
        </div>

        <div class="box">
            <div class="box-title">IV. HASIL PEMERIKSAAN & UNBOXING RETUR DI GUDANG</div>
            <table>
                <tr><td class="label">Status Hasil Unboxing:</td><td class="val">${unb.status || '-'} (${unb.total_damaged || 0} Rusak, ${unb.total_good || 0} Bagus)</td></tr>
                <tr><td class="label">Waktu Unboxing:</td><td class="val">${unb.created_at || '-'}</td></tr>
                <tr><td class="label">Operator Pemeriksa:</td><td class="val">${unb.operator_name || '-'}</td></tr>
                <tr><td class="label">Catatan Kerusakan:</td><td class="val">${unb.notes || (ord.ReturnReasonText ? '[' + ord.ReturnReason + '] ' + ord.ReturnReasonText : 'Lihat rincian fisik')}</td></tr>
                <tr><td class="label">Video Unboxing Retur:</td><td class="val">${unb.video_path ? '<span class="badge-ok">✓ TEREKAM LENGKAP</span>' : '<span class="badge-no">✕ BELUM DIREKAM</span>'}</td></tr>
                ${(unb.items && unb.items.length > 0) ? `
                <tr>
                    <td colspan="2" style="padding-top: 8px;">
                        <b style="font-size: 8.5pt; text-transform: uppercase; color: #475569;">Rincian Item Produk &amp; Kondisi Fisik:</b>
                        <table style="margin-top: 4px; border: 1px solid #cbd5e1; font-size: 8.5pt;">
                            <tr style="background: #f1f5f9; font-weight: bold; border-bottom: 1px solid #cbd5e1;">
                                <td style="padding: 4px; width: 5%;">#</td>
                                <td style="padding: 4px; width: 45%;">Nama Produk &amp; SKU</td>
                                <td style="padding: 4px; width: 12%; text-align: center;">Qty</td>
                                <td style="padding: 4px; width: 18%; text-align: center;">Kondisi / Tipe</td>
                                <td style="padding: 4px; width: 20%;">Alasan / Catatan</td>
                            </tr>
                            ${unb.items.map((it, iIdx) => {
        const isD = (String(it.condition || '').toUpperCase() !== 'GOOD' && String(it.condition || '').toUpperCase() !== 'BAGUS') ||
            (String(it.type || '').toUpperCase() !== 'GOOD' && String(it.type || '').toUpperCase() !== 'BAGUS') ||
            Boolean(it.damage_reason);
        const rowBg = isD ? 'background: #fff1f2; font-weight: bold;' : '';
        const badgeC = isD ? 'badge-no' : 'badge-ok';
        return `
                                <tr style="border-bottom: 1px solid #e2e8f0; ${rowBg}">
                                    <td style="padding: 4px;">${iIdx + 1}</td>
                                    <td style="padding: 4px;">${it.product_name || it.barcode} ${it.seller_sku ? '<br><small style="color: #64748b;">SKU: ' + it.seller_sku + '</small>' : ''}</td>
                                    <td style="padding: 4px; text-align: center; font-family: monospace; font-size: 9pt;">${it.qty || 1} pcs</td>
                                    <td style="padding: 4px; text-align: center;"><span class="${badgeC}">${it.type || it.condition || 'GOOD'}</span></td>
                                    <td style="padding: 4px; color: ${isD ? '#991b1b' : '#64748b'};">${it.damage_reason || (isD ? 'Barang Rusak' : '-')}</td>
                                </tr>
                                `;
    }).join('')}
                        </table>
                    </td>
                </tr>
                ` : ''}
            </table>
        </div>

        ${photos.length > 0 ? `
        <div class="box">
            <div class="box-title">V. DOKUMENTASI FOTO BUKTI FISIK (${photos.length} FOTO)</div>
            <div class="photos-grid">
                ${photos.slice(0, 6).map(p => {
        const isPhotoDmg = (p.is_damaged || p.badge === 'Barang Rusak' || (p.title && p.title.toLowerCase().includes('rusak')));
        const borderStyle = isPhotoDmg ? 'border: 2px solid #ef4444; background: #fff1f2;' : 'border: 1px solid #e2e8f0;';
        const tagStyle = isPhotoDmg ? 'color: #dc2626; font-weight: 900;' : 'color: #475569;';
        return `
                    <div class="photo-item" style="${borderStyle}">
                        <img src="${p.url}" alt="${p.title || 'Foto Bukti'}">
                        <span style="${tagStyle}">${isPhotoDmg ? '⚠️ ' : ''}${p.title || p.badge || 'Bukti Retur'}</span>
                    </div>
                    `;
    }).join('')}
            </div>
        </div>
        ` : ''}

        <div class="signatures">
            <div class="sig-box">
                <span>Diverifikasi Oleh:</span>
                <div class="sig-line">Operator / Petugas Inbound</div>
            </div>
            <div class="sig-box">
                <span>Mengetahui / Disetujui:</span>
                <div class="sig-line">Supervisor Gudang & Klaim</div>
            </div>
        </div>

        <script>
            window.onload = function() {
                window.print();
            };
        </script>
    </body>
    </html>`;

    printWin.document.open();
    printWin.document.write(html);
    printWin.document.close();
};

// ==========================================
// KANDIDAT PAKET KLAIM (KONDISI BUKAN GOOD) & DETAIL MODAL POPUP
// ==========================================
let cachedClaimCandidates = [];
let activeClaimCandidateInvoice = '';
let selectedClaimInvoices = new Set();
let flatpickrClaimInstance = null;
let activeClaimDateFilter = '';

function initClaimDatepicker() {
    const el = document.getElementById('filterClaimDate');
    if (!el || flatpickrClaimInstance) return;
    if (typeof flatpickr !== 'function') return;

    flatpickrClaimInstance = flatpickr(el, {
        mode: "range",
        dateFormat: "Y-m-d",
        altInput: true,
        altFormat: "j F Y",
        locale: "id",
        maxDate: "today",
        onChange: function (selectedDates) {
            const btnClear = document.getElementById('btnClearClaimDate');
            if (selectedDates.length === 2) {
                const start = flatpickr.formatDate(selectedDates[0], "Y-m-d");
                const end = flatpickr.formatDate(selectedDates[1], "Y-m-d");
                activeClaimDateFilter = `${start} to ${end}`;
                if (btnClear) btnClear.classList.remove('hidden');
                applyClaimCandidatesFilter();
            } else if (selectedDates.length === 1) {
                const single = flatpickr.formatDate(selectedDates[0], "Y-m-d");
                activeClaimDateFilter = single;
                if (btnClear) btnClear.classList.remove('hidden');
            } else {
                activeClaimDateFilter = '';
                if (btnClear) btnClear.classList.add('hidden');
                applyClaimCandidatesFilter();
            }
        },
        onClose: function (selectedDates) {
            if (selectedDates.length === 1) {
                applyClaimCandidatesFilter();
            }
        }
    });

    const btnClear = document.getElementById('btnClearClaimDate');
    if (btnClear) {
        if (activeClaimDateFilter) btnClear.classList.remove('hidden');
        else btnClear.classList.add('hidden');
    }
}

window.clearClaimDateFilter = function () {
    if (flatpickrClaimInstance) {
        flatpickrClaimInstance.clear();
    }
    activeClaimDateFilter = '';
    const btnClear = document.getElementById('btnClearClaimDate');
    if (btnClear) btnClear.classList.add('hidden');
    applyClaimCandidatesFilter();
};

window.loadClaimCandidates = async function (force = false) {
    initClaimDatepicker();

    const tbody = document.getElementById('claimCandidatesTableBody');
    const refreshIcon = document.getElementById('iconRefreshCandidates');
    if (!tbody) return;

    if (refreshIcon) refreshIcon.classList.add('fa-spin');
    tbody.innerHTML = `<tr><td colspan="9" class="py-12 text-center text-slate-400">
        <div class="inline-flex items-center gap-2.5 px-4 py-2 bg-indigo-50 text-indigo-600 rounded-xl">
            <i class="fa-solid fa-spinner fa-spin text-lg"></i>
            <span class="text-xs font-semibold">Memuat kandidat paket klaim...</span>
        </div>
    </td></tr>`;

    try {
        const res = await fetch('api/ocs_lookup.php?action=list_claimable');
        const data = await res.json();

        if (!data.success) {
            tbody.innerHTML = `<tr><td colspan="9" class="text-center py-8 text-rose-500 font-semibold">${data.message || 'Gagal memuat kandidat klaim'}</td></tr>`;
            return;
        }

        cachedClaimCandidates = data.candidates || [];

        // Isi dropdown ekspedisi secara dinamis
        populateClaimExpeditionFilter(cachedClaimCandidates);

        // Render tabel berdasarkan filter saat ini
        applyClaimCandidatesFilter();

        if (force) {
            showToast('success', `Berhasil memuat ${cachedClaimCandidates.length} paket rusak / layak klaim`, 'Daftar Diperbarui');
        }
    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="9" class="text-center py-8 text-rose-500">Error: ${err.message}</td></tr>`;
    } finally {
        if (refreshIcon) refreshIcon.classList.remove('fa-spin');
    }
};

function populateClaimExpeditionFilter(candidates) {
    const sel = document.getElementById('filterClaimExpedition');
    if (!sel) return;
    const currentVal = sel.value;
    const expeditions = new Set();
    candidates.forEach(c => {
        if (c.expedition && c.expedition.trim()) {
            expeditions.add(c.expedition.trim());
        }
    });

    let optHtml = '<option value="">Semua Ekspedisi</option>';
    Array.from(expeditions).sort().forEach(exp => {
        optHtml += `<option value="${escapeHtml(exp)}" ${exp === currentVal ? 'selected' : ''}>${escapeHtml(exp)}</option>`;
    });
    sel.innerHTML = optHtml;
}

// State Mode Ekspedisi Klaim: 'jnt' (Approval Accounting via Web) vs 'other' (Klaim Manual Admin)
let claimExpeditionMode = 'jnt';

window.setClaimExpeditionMode = function (mode) {
    claimExpeditionMode = mode;
    const btnJnt = document.getElementById('btnClaimModeJnt');
    const btnOther = document.getElementById('btnClaimModeOther');

    if (mode === 'jnt') {
        if (btnJnt) {
            btnJnt.className = 'px-4 py-2 rounded-t-xl font-bold text-xs transition border-t-2 border-rose-500 bg-white text-rose-700 shadow-2xs flex items-center gap-1.5 cursor-pointer';
        }
        if (btnOther) {
            btnOther.className = 'px-4 py-2 rounded-t-xl font-bold text-xs transition text-slate-500 hover:text-slate-800 hover:bg-slate-200/60 flex items-center gap-1.5 cursor-pointer';
        }
    } else {
        if (btnOther) {
            btnOther.className = 'px-4 py-2 rounded-t-xl font-bold text-xs transition border-t-2 border-indigo-500 bg-white text-indigo-700 shadow-2xs flex items-center gap-1.5 cursor-pointer';
        }
        if (btnJnt) {
            btnJnt.className = 'px-4 py-2 rounded-t-xl font-bold text-xs transition text-slate-500 hover:text-slate-800 hover:bg-slate-200/60 flex items-center gap-1.5 cursor-pointer';
        }
    }

    clearSelectedClaims();
    applyClaimCandidatesFilter();
};

window.applyClaimCandidatesFilter = function () {
    const tbody = document.getElementById('claimCandidatesTableBody');
    if (!tbody) return;

    const searchInput = document.getElementById('filterClaimSearch');
    const expSelect = document.getElementById('filterClaimExpedition');
    const statusSelect = document.getElementById('filterClaimStatus');

    const q = searchInput ? searchInput.value.trim().toLowerCase() : '';
    const exp = expSelect ? expSelect.value.trim().toLowerCase() : '';
    const stFilter = statusSelect ? statusSelect.value.trim().toUpperCase() : '';

    let filtered = cachedClaimCandidates.filter(c => {
        // Filter Berdasarkan Mode Tab Ekspedisi (JNT Khusus Approval Accounting vs Ekspedisi Lain Klaim Manual)
        const cExpUpper = String(c.expedition || '').toUpperCase();
        const isJntExp = (cExpUpper.includes('JNT') || cExpUpper.includes('J&T'));
        if (claimExpeditionMode === 'jnt' && !isJntExp) return false;
        if (claimExpeditionMode === 'other' && isJntExp) return false;

        // Filter Search (Invoice / Resi, Produk, SKU, Alasan Rusak, Operator)
        if (q) {
            const inv = String(c.invoice_number || '').toLowerCase();
            const prod = String(c.product_names || '').toLowerCase();
            const sku = String(c.sku || '').toLowerCase();
            const rsn = String(c.damage_reasons || c.notes || '').toLowerCase();
            const op = String(c.operator_name || '').toLowerCase();
            if (!inv.includes(q) && !prod.includes(q) && !sku.includes(q) && !rsn.includes(q) && !op.includes(q)) {
                return false;
            }
        }

        // Filter Ekspedisi Spesifik Dropdown
        if (exp) {
            const cExp = String(c.expedition || '').toLowerCase();
            if (cExp !== exp) return false;
        }

        // Filter Status Klaim
        if (stFilter && (c.claim_status || 'PENDING') !== stFilter) return false;

        // Filter Tanggal Rentang (Flatpickr)
        if (activeClaimDateFilter) {
            const cDate = String(c.created_at || '').substring(0, 10);
            if (activeClaimDateFilter.includes(' to ')) {
                const [start, end] = activeClaimDateFilter.split(' to ');
                if (cDate < start || cDate > end) return false;
            } else {
                if (cDate !== activeClaimDateFilter) return false;
            }
        }

        return true;
    });

    // Hitung berapa klaim JNT yang perlu tindakan / pending approval
    const jntPendingCount = cachedClaimCandidates.filter(c => {
        const expU = String(c.expedition || '').toUpperCase();
        return (expU.includes('JNT') || expU.includes('J&T')) && (!c.accounting_status || c.accounting_status === 'PENDING' || c.accounting_status === 'PENDING_APPROVAL');
    }).length;
    const badgeJnt = document.getElementById('badgeJntPendingApproval');
    if (badgeJnt) {
        badgeJnt.innerText = jntPendingCount;
        badgeJnt.classList.toggle('hidden', jntPendingCount === 0);
    }

    // Hitung akumulasi total real-time dari seluruh item yang lolos filter
    let sumFilteredNominal = 0;
    let sumFilteredDamaged = 0;
    filtered.forEach(c => {
        sumFilteredNominal += Number(c.package_price || 0);
        const dQty = Number(c.damaged_qty || (c.total_damaged > 0 ? c.total_damaged : (c.damaged_items_count || 1)));
        sumFilteredDamaged += dQty;
    });

    // Update Counter & Rekap Finansial Hasil Filter di Toolbar
    const countEl = document.getElementById('countClaimFiltered');
    const totalEl = document.getElementById('countClaimTotal');
    const sumDmgEl = document.getElementById('sumClaimFilteredDamaged');
    const sumNomEl = document.getElementById('sumClaimFilteredNominal');

    if (countEl) countEl.innerText = filtered.length;
    if (totalEl) totalEl.innerText = cachedClaimCandidates.length;
    if (sumDmgEl) sumDmgEl.innerText = `${sumFilteredDamaged} pcs`;
    if (sumNomEl) sumNomEl.innerText = 'Rp ' + sumFilteredNominal.toLocaleString('id-ID');

    if (filtered.length === 0) {
        tbody.innerHTML = `<tr><td colspan="9" class="text-center py-10 text-slate-400">
            <i class="fa-solid fa-filter-circle-xmark text-2xl text-slate-300 mb-2 block"></i>
            Tidak ada paket rusak yang sesuai dengan filter pencarian yang diterapkan.
        </td></tr>`;
        const masterCb = document.getElementById('checkAllClaimCandidates');
        if (masterCb) masterCb.checked = false;
        updateSelectedClaimsBar();
        return;
    }

    let html = '';
    filtered.forEach((c, idx) => {
        const hasVideo = !!c.video_path;
        const damagedCount = c.damaged_qty || (c.total_damaged > 0 ? c.total_damaged : (c.damaged_items_count || 1));
        const reason = c.damage_reasons || c.notes || 'Kondisi Rusak / Bukan Good';
        const prodName = c.product_names || '<span class="text-slate-400 italic">Produk Retur</span>';
        const priceVal = Number(c.package_price || 0);
        const priceFormatted = c.package_price_formatted && c.package_price_formatted !== '-' ? c.package_price_formatted : (priceVal > 0 ? 'Rp ' + priceVal.toLocaleString('id-ID') : '-');

        let priceHtml = '';
        if (priceVal > 0) {
            const estBadge = c.price_is_estimated ? `<span class="block text-[9px] text-amber-600 font-semibold" title="Estimasi berdasarkan harga SKU sejenis di OCS">(Estimasi SKU)</span>` : '';
            priceHtml = `<span class="font-mono font-bold text-emerald-700">${priceFormatted}</span>${estBadge}`;
        } else {
            priceHtml = `
                <div class="inline-flex flex-col items-end gap-0.5">
                    <span class="text-slate-400 font-mono text-[11px]">-</span>
                    <button type="button" onclick="syncSingleClaimPrice('${escapeHtml(c.invoice_number)}', this)" 
                        class="px-1.5 py-0.5 bg-amber-50 hover:bg-amber-100 text-amber-700 border border-amber-300 rounded text-[10px] font-bold transition flex items-center gap-1 shadow-2xs" 
                        title="Ambil harga langsung dari server OCS">
                        <i class="fa-solid fa-arrows-rotate text-[9px]"></i> Cek OCS
                    </button>
                </div>
            `;
        }

        const isChecked = selectedClaimInvoices.has(c.invoice_number);
        html += `
            <tr class="hover:bg-rose-50/40 transition border-b border-slate-100 ${isChecked ? 'bg-amber-50/60' : ''}">
                <td class="py-3 px-3 text-center">
                    <div class="flex items-center justify-center gap-1.5">
                        <input type="checkbox" class="claim-item-checkbox w-4 h-4 rounded text-amber-600 focus:ring-amber-500 border-slate-300 cursor-pointer" 
                            value="${escapeHtml(c.invoice_number)}" 
                            ${isChecked ? 'checked' : ''}
                            onchange="handleClaimCheckboxChange('${escapeHtml(c.invoice_number)}', this)">
                        <span class="font-bold text-slate-400 text-xs">${idx + 1}</span>
                    </div>
                </td>
                <td class="py-3 px-3">
                    <button onclick="openClaimDetailModal('${c.invoice_number}')" class="font-mono font-bold text-indigo-600 hover:text-indigo-800 text-left block hover:underline text-xs" title="Klik untuk melihat berkas detail klaim">
                        ${escapeHtml(c.invoice_number)}
                    </button>
                    <div class="flex items-center gap-1.5 mt-1 text-[11px] text-slate-600">
                        <span class="inline-flex items-center gap-1 font-semibold bg-slate-100 border border-slate-200/80 px-1.5 py-0.5 rounded text-[10px]" title="Ekspedisi">
                            <i class="fa-solid fa-truck-fast text-[9px] text-amber-500"></i> ${escapeHtml(c.expedition || '-')}
                        </span>
                        <span class="text-[10px] text-slate-400">(${escapeHtml(c.operator_name || 'Operator')})</span>
                    </div>
                </td>
                <td class="py-3 px-3">
                    <div class="font-bold text-slate-800 text-xs whitespace-normal break-words leading-snug min-w-[220px]">
                        ${prodName}
                    </div>
                    ${c.sku ? `
                        <div class="mt-0.5">
                            <span class="inline-flex items-center gap-1 font-mono text-[10px] font-bold text-indigo-700 bg-indigo-50 border border-indigo-200/80 px-1.5 py-0.5 rounded tracking-tight whitespace-normal break-all" title="Seller SKU">
                                <i class="fa-solid fa-tag text-[8px] text-indigo-400"></i> SKU: ${escapeHtml(c.sku)}
                            </span>
                        </div>
                    ` : ''}
                </td>
                <td class="py-3 px-3 text-center">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-rose-100 text-rose-700 border border-rose-200">
                        ${damagedCount} Rusak
                    </span>
                    ${c.total_items ? `<span class="block text-[9px] text-slate-400 font-medium mt-0.5">Total: ${c.total_items} pcs</span>` : ''}
                </td>
                <td class="py-3 px-3">
                    <span class="text-slate-800 font-medium block max-w-xs truncate" title="${escapeHtml(reason)}">
                        ${escapeHtml(reason)}
                    </span>
                </td>
                <td class="py-3 px-3 text-right whitespace-nowrap">
                    ${priceHtml}
                </td>
                <td class="py-3 px-3 text-slate-500 text-[11px] whitespace-nowrap">${escapeHtml(c.created_at || '-')}</td>
                <td class="py-3 px-3 text-center">
                    ${hasVideo ?
                `<span class="inline-flex items-center gap-1 text-[11px] text-emerald-600 font-bold bg-emerald-50 px-2 py-0.5 rounded-md border border-emerald-200">
                            <i class="fa-solid fa-video"></i> Ada
                         </span>` :
                `<span class="inline-flex items-center gap-1 text-[11px] text-slate-400 bg-slate-100 px-2 py-0.5 rounded-md">
                            <i class="fa-solid fa-video-slash"></i> -
                         </span>`
            }
                </td>
                <td class="py-3 px-3 text-center">
                    ${renderClaimStatusAction(c)}
                </td>
            </tr>
        `;
    });
    tbody.innerHTML = html;

    // Sinkronisasi status checkbox master (Select All)
    const masterCb = document.getElementById('checkAllClaimCandidates');
    if (masterCb) {
        masterCb.checked = (filtered.length > 0 && filtered.every(c => selectedClaimInvoices.has(c.invoice_number)));
    }
    updateSelectedClaimsBar();
};

window.resetClaimCandidatesFilter = function () {
    const searchInput = document.getElementById('filterClaimSearch');
    const expSelect = document.getElementById('filterClaimExpedition');
    const statusSelect = document.getElementById('filterClaimStatus');

    if (searchInput) searchInput.value = '';
    if (expSelect) expSelect.value = '';
    if (statusSelect) statusSelect.value = '';
    clearClaimDateFilter();
};

// -------------------------------------------------------------
// STATUS KLAIM: PENDING (Belum Klaim) -> PROCESS (Proses Klaim) -> DONE (Done Claim)
// -------------------------------------------------------------
function renderClaimStatusAction(c) {
    const inv = escapeHtml(c.invoice_number);
    const expUpper = String(c.expedition || '').toUpperCase();
    const isJnt = (expUpper.includes('JNT') || expUpper.includes('J&T'));

    // JIKA EKSPEDISI J&T: STATUS BERGANTUNG PADA APPROVAL ACCOUNTING VIA WEB
    if (isJnt) {
        const accSt = c.accounting_status || 'PENDING';
        if (accSt === 'APPROVED') {
            return `
                <div class="inline-flex flex-col items-center gap-0.5">
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[10px] font-black bg-emerald-100 text-emerald-800 border border-emerald-300 shadow-2xs">
                        <i class="fa-solid fa-stamp text-emerald-600"></i> Approved Acc
                    </span>
                    ${c.accounting_approved_by ? `<span class="text-[9px] text-slate-400 font-medium">by ${escapeHtml(c.accounting_approved_by)}</span>` : ''}
                </div>`;
        } else if (accSt === 'PENDING_APPROVAL') {
            return `
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-300">
                    <i class="fa-solid fa-clock text-amber-600"></i> Menunggu Acc
                </span>`;
        } else if (accSt === 'REJECTED') {
            return `
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-[10px] font-bold bg-rose-100 text-rose-800 border border-rose-300">
                    <i class="fa-solid fa-xmark text-rose-600"></i> Ditolak Acc
                </span>`;
        } else {
            return `
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-[10px] font-semibold bg-slate-100 text-slate-600 border border-slate-200">
                    <i class="fa-solid fa-paper-plane text-slate-400"></i> Siap Kirim
                </span>`;
        }
    }

    // JIKA EKSPEDISI LAIN: KLAIM MANUAL OLEH ADMIN (PENDING -> PROCESS -> DONE)
    const st = c.claim_status || 'PENDING';

    if (st === 'PENDING') {
        return `
            <button onclick="openClaimDetailModal('${inv}')" class="px-2.5 py-1 bg-amber-500 hover:bg-amber-600 text-white rounded-lg text-[11px] font-bold transition shadow-2xs flex items-center gap-1.5 mx-auto cursor-pointer" title="Belum diklaim - buka berkas detail klaim">
                <i class="fa-solid fa-shield-halved"></i> Klaim
            </button>`;
    }

    const isDone = st === 'DONE';
    const cls = isDone
        ? 'bg-emerald-50 text-emerald-700 border-emerald-300'
        : 'bg-blue-50 text-blue-700 border-blue-300';
    return `
        <div class="relative inline-flex items-center mx-auto" title="Ubah status klaim secara manual">
            <i class="fa-solid ${isDone ? 'fa-circle-check' : 'fa-hourglass-half'} absolute left-2 text-[10px] pointer-events-none ${isDone ? 'text-emerald-600' : 'text-blue-600'}"></i>
            <select onchange="changeSingleClaimStatus('${inv}', this)" class="appearance-none pl-6 pr-6 py-1 rounded-lg text-[11px] font-bold border cursor-pointer focus:outline-none focus:ring-2 focus:ring-amber-500 ${cls}">
                <option value="PROCESS" ${!isDone ? 'selected' : ''}>Proses Klaim</option>
                <option value="DONE" ${isDone ? 'selected' : ''}>Done Claim</option>
                <option value="PENDING">Batalkan Klaim</option>
            </select>
            <i class="fa-solid fa-chevron-down absolute right-2 text-[8px] pointer-events-none text-slate-500"></i>
        </div>`;
}

async function updateClaimStatusRequest(invoices, status) {
    const res = await fetch('api/claim_status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ invoices, status })
    });
    const json = await res.json().catch(() => ({}));
    if (!res.ok || !json.success) throw new Error(json.error || 'Gagal memperbarui status klaim');

    // Update cache lokal agar UI langsung berubah tanpa reload penuh
    const invSet = new Set(invoices);
    cachedClaimCandidates.forEach(c => {
        if (invSet.has(c.invoice_number)) c.claim_status = status;
    });
    return json;
}

window.processSelectedClaims = async function () {
    const pending = Array.from(selectedClaimInvoices).filter(inv => {
        const c = cachedClaimCandidates.find(x => x.invoice_number === inv);
        return c && (c.claim_status || 'PENDING') === 'PENDING';
    });
    if (pending.length === 0) {
        showToast('info', 'Semua paket terpilih sudah dalam status Proses / Done Claim.', 'Info');
        return;
    }
    if (!confirm(`Ajukan klaim untuk ${pending.length} paket terpilih?\nStatus akan berubah menjadi "Proses Klaim" dan tombol Print Invoice akan muncul.`)) return;

    const btn = document.getElementById('btnBulkClaimProcess');
    if (btn) btn.disabled = true;
    try {
        const json = await updateClaimStatusRequest(pending, 'PROCESS');
        showToast('success', json.message || 'Paket berhasil diubah ke Proses Klaim', 'Proses Klaim');
        applyClaimCandidatesFilter();
    } catch (err) {
        showToast('error', err.message, 'Gagal');
    } finally {
        if (btn) btn.disabled = false;
    }
};

window.markSelectedClaimsDone = async function () {
    const targets = Array.from(selectedClaimInvoices).filter(inv => {
        const c = cachedClaimCandidates.find(x => x.invoice_number === inv);
        return c && c.claim_status === 'PROCESS';
    });
    if (targets.length === 0) {
        showToast('info', 'Tidak ada paket berstatus Proses Klaim pada pilihan Anda.', 'Info');
        return;
    }
    if (!confirm(`Tandai ${targets.length} paket sebagai "Done Claim"?`)) return;
    try {
        const json = await updateClaimStatusRequest(targets, 'DONE');
        showToast('success', json.message || 'Paket ditandai Done Claim', 'Done Claim');
        applyClaimCandidatesFilter();
    } catch (err) {
        showToast('error', err.message, 'Gagal');
    }
};

window.changeSingleClaimStatus = async function (invoice, selectEl) {
    const newStatus = selectEl.value;
    const c = cachedClaimCandidates.find(x => x.invoice_number === invoice);
    const prev = c ? (c.claim_status || 'PENDING') : 'PENDING';
    if (newStatus === prev) return;

    const labels = { PENDING: 'Belum Klaim (batalkan klaim)', PROCESS: 'Proses Klaim', DONE: 'Done Claim' };
    if (!confirm(`Ubah status klaim [${invoice}] menjadi "${labels[newStatus]}"?`)) {
        selectEl.value = prev;
        return;
    }
    selectEl.disabled = true;
    try {
        const json = await updateClaimStatusRequest([invoice], newStatus);
        showToast('success', json.message || 'Status klaim diperbarui', 'Status Klaim');
        applyClaimCandidatesFilter();
    } catch (err) {
        selectEl.value = prev;
        selectEl.disabled = false;
        showToast('error', err.message, 'Gagal');
    }
};

window.syncSingleClaimPrice = async function (invoice, btnEl) {
    if (!invoice) return;
    const origText = btnEl ? btnEl.innerHTML : '';
    if (btnEl) {
        btnEl.disabled = true;
        btnEl.innerHTML = '<i class="fa-solid fa-spinner fa-spin text-[9px]"></i>';
    }

    try {
        const res = await fetch(`api/ocs_lookup.php?query=${encodeURIComponent(invoice)}&refresh=1`);
        const json = await res.json();
        if (json.success && json.data) {
            const d = json.data;
            const price = Number(d.PackagePrice || d.TotalClaimAmount || d.Payment?.TotalAmount || 0);
            if (price > 0) {
                const found = cachedClaimCandidates.find(x => x.invoice_number === invoice);
                if (found) {
                    found.package_price = price;
                    found.package_price_formatted = 'Rp ' + price.toLocaleString('id-ID');
                    found.price_is_estimated = false;
                    if (d.ProductName && !found.product_names) found.product_names = d.ProductName;
                    if (d.SellerSku && !found.sku) found.sku = d.SellerSku;
                }
                showToast('success', `Harga paket ${invoice} berhasil disinkronkan: Rp ${price.toLocaleString('id-ID')}`, 'Harga Ditemukan');
                applyClaimCandidatesFilter();
                return;
            }
        }
        showToast('warning', `Harga untuk nomor ${invoice} tidak ditemukan di sistem OCS`, 'Data Belum Ada');
        if (btnEl) {
            btnEl.disabled = false;
            btnEl.innerHTML = '<span class="text-rose-500"><i class="fa-solid fa-circle-exclamation text-[9px]"></i> N/A</span>';
        }
    } catch (e) {
        showToast('error', `Gagal menghubungkan ke OCS: ${e.message}`, 'Koneksi Error');
        if (btnEl) {
            btnEl.disabled = false;
            btnEl.innerHTML = origText;
        }
    }
};

// ==========================================
// POPUP MODAL DETAIL KLAIM (BERKAS LENGKAP)
// ==========================================
window.openClaimDetailModal = async function (identifier) {
    if (!identifier) return;
    activeClaimCandidateInvoice = identifier;

    const modal = document.getElementById('modalClaimDetail');
    const loading = document.getElementById('mClaimLoading');
    const body = document.getElementById('mClaimBody');
    if (!modal) {
        // Fallback jika modal belum terpasang
        window.open(`claim_dossier.php?q=${encodeURIComponent(identifier)}`, '_blank');
        return;
    }

    modal.classList.remove('hidden');
    if (loading) {
        loading.innerHTML = `
            <div class="py-12 text-center text-slate-500 space-y-3">
                <i class="fa-solid fa-spinner fa-spin text-3xl text-amber-500"></i>
                <p class="font-bold">Memuat berkas dossier klaim...</p>
            </div>
        `;
        loading.classList.remove('hidden');
    }
    if (body) body.classList.add('hidden');

    try {
        const res = await fetch(`api/ocs_lookup.php?q=${encodeURIComponent(identifier)}`);
        const data = await res.json();

        if (!data || !data.success) {
            showToast('error', data?.message || 'Gagal memuat detail berkas klaim', 'Data Tidak Ditemukan');
            if (loading) {
                loading.innerHTML = `
                    <div class="text-rose-500 space-y-2 py-8">
                        <i class="fa-solid fa-triangle-exclamation text-3xl"></i>
                        <p class="font-bold">${escapeHtml(data?.message || 'Data berkas klaim tidak ditemukan')}</p>
                    </div>
                `;
            }
            return;
        }

        renderClaimDetailModalContent(data);

        if (loading) loading.classList.add('hidden');
        if (body) body.classList.remove('hidden');

    } catch (err) {
        console.error("openClaimDetailModal error:", err);
        showToast('error', 'Terjadi kesalahan saat memuat berkas klaim: ' + err.message, 'Gagal');
        if (loading) {
            loading.innerHTML = `
                <div class="text-rose-500 space-y-2 py-8">
                    <i class="fa-solid fa-circle-exclamation text-3xl"></i>
                    <p class="font-bold">Gagal memuat: ${escapeHtml(err.message)}</p>
                </div>
            `;
        }
    }
};

function renderClaimDetailModalContent(data) {
    const order = data.order || {};
    const unboxing = data.unboxing || {};
    const reception = data.reception || {};
    const photos = data.photos || [];
    const items = unboxing.items || data.items || [];

    // Header info
    const invTitle = document.getElementById('mClaimInvoiceTitle');
    const subtitle = document.getElementById('mClaimSubtitle');
    const statusBadge = document.getElementById('mClaimStatusBadge');

    const invNo = order.Id || unboxing.invoice_number || data.query || '-';
    const trackingNo = order.TrackingNumber || reception.package_barcode || invNo;

    if (invTitle) invTitle.innerText = `Invoice #${invNo} • Resi #${trackingNo}`;
    if (subtitle) subtitle.innerText = `Toko: ${order.ShopName || '-'} | Ekspedisi: ${order.ShippingProvider || unboxing.expedition || '-'}`;

    if (statusBadge) {
        if (data.is_claimable) {
            statusBadge.className = 'px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wider bg-rose-600 text-white';
            statusBadge.innerText = 'RUSAK / LAYAK KLAIM';
        } else {
            statusBadge.className = 'px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wider bg-emerald-600 text-white';
            statusBadge.innerText = 'BAGUS / NORMAL';
        }
    }

    // 1. Banner Kelayakan
    const bannerTitle = document.getElementById('mClaimBannerTitle');
    const bannerDesc = document.getElementById('mClaimBannerDesc');
    const badgeTag = document.getElementById('mClaimBadgeTag');
    if (bannerTitle) {
        bannerTitle.innerText = data.is_claimable ? '⚠️ PAKET LAYAK KLAIM (Kondisi Rusak / Cacat)' : '✓ BUKAN PAKET KLAIM (Kondisi Baik/Good)';
    }
    if (bannerDesc) {
        bannerDesc.innerText = data.claim_eligibility_reason || (data.is_claimable ? 'Kerusakan produk terkonfirmasi saat proses unboxing di gudang retur.' : 'Semua item dalam kondisi baik.');
    }
    if (badgeTag) {
        badgeTag.className = data.is_claimable ? 'px-2.5 py-1 rounded-lg bg-rose-600 text-white font-black text-[10px] uppercase tracking-wider' : 'px-2.5 py-1 rounded-lg bg-emerald-600 text-white font-black text-[10px] uppercase tracking-wider';
        badgeTag.innerText = data.is_claimable ? 'LAYAK KLAIM' : 'NORMAL';
    }

    // 2. Finansial & Data Ekspedisi
    const priceText = order.PackagePriceFormatted || (order.PackagePrice > 0 ? 'Rp ' + Number(order.PackagePrice).toLocaleString('id-ID') : 'Rp -');
    const totalClaimText = order.TotalClaimAmountFormatted || (order.TotalClaimAmount > 0 ? 'Rp ' + Number(order.TotalClaimAmount).toLocaleString('id-ID') : priceText);

    const priceEl = document.getElementById('mClaimPrice');
    const totalClaimEl = document.getElementById('mClaimTotalClaim');
    const expEl = document.getElementById('mClaimExpedition');
    const trackingEl = document.getElementById('mClaimTrackingNo');
    const shopEl = document.getElementById('mClaimShopName');
    const platformEl = document.getElementById('mClaimPlatform');

    if (priceEl) priceEl.innerText = priceText;
    if (totalClaimEl) totalClaimEl.innerText = totalClaimText;
    if (expEl) expEl.innerText = order.ShippingProvider || unboxing.expedition || reception.expedition || '-';
    if (trackingEl) trackingEl.innerText = `AWB: ${trackingNo}`;
    if (shopEl) shopEl.innerText = order.ShopName || '-';
    if (platformEl) platformEl.innerText = order.CommercePlatform || 'Marketplace';

    // 3. Serah Terima & Unboxing Info
    const courierEl = document.getElementById('mClaimCourierInfo');
    const receiptEl = document.getElementById('mClaimReceiptNo');
    const opEl = document.getElementById('mClaimOperatorInfo');
    const unboxTimeEl = document.getElementById('mClaimUnboxTime');

    if (courierEl) courierEl.innerText = reception.courier_name ? `${reception.courier_name} (${reception.expedition || '-'})` : (unboxing.expedition || '-');
    if (receiptEl) receiptEl.innerText = reception.receipt_number ? `Surat Jalan: ${reception.receipt_number}` : 'Tanda Terima: -';
    if (opEl) opEl.innerText = `Petugas: ${unboxing.operator_name || 'Operator Gudang'}`;
    if (unboxTimeEl) unboxTimeEl.innerText = unboxing.created_at || '-';

    // 4. Video Unboxing Player
    const videoEl = document.getElementById('mClaimVideoPlayer');
    const noVideoPlaceholder = document.getElementById('mClaimNoVideoPlaceholder');
    const videoOp = document.getElementById('mClaimVideoOperator');

    if (videoOp) videoOp.innerText = unboxing.operator_name ? `Operator: ${unboxing.operator_name}` : 'Stasiun Unboxing';

    if (unboxing.video_path) {
        if (videoEl) {
            videoEl.src = unboxing.video_path;
            videoEl.classList.remove('hidden');
        }
        if (noVideoPlaceholder) noVideoPlaceholder.classList.add('hidden');
    } else {
        if (videoEl) {
            videoEl.pause();
            videoEl.src = '';
            videoEl.classList.add('hidden');
        }
        if (noVideoPlaceholder) noVideoPlaceholder.classList.remove('hidden');
    }

    // 5. Galeri Foto Bukti
    const photosGrid = document.getElementById('mClaimPhotosGrid');
    const noPhotosEl = document.getElementById('mClaimNoPhotosPlaceholder');
    const photoCountEl = document.getElementById('mClaimPhotoCount');

    if (photoCountEl) photoCountEl.innerText = photos.length;

    if (photos && photos.length > 0) {
        if (noPhotosEl) noPhotosEl.classList.add('hidden');
        if (photosGrid) {
            photosGrid.classList.remove('hidden');
            photosGrid.innerHTML = photos.map((p, idx) => {
                const isDmg = (p.is_damaged || p.badge === 'Barang Rusak' || (p.title && p.title.toLowerCase().includes('rusak')));
                const borderCls = isDmg ? 'border-2 border-rose-500 shadow-sm shadow-rose-500/25 ring-2 ring-rose-200' : 'border border-slate-200';
                const badgeBg = isDmg ? 'bg-rose-600 text-white font-black' : 'bg-slate-900/80 text-white font-bold';
                const badgeLabel = isDmg ? '⚠️ RUSAK' : (p.badge || 'FOTO');
                return `
                    <div class="relative group rounded-xl overflow-hidden ${borderCls} bg-slate-100 aspect-square cursor-pointer hover:shadow-md transition" onclick="openClaimPhotoModal('${encodeURI(p.url)}', '${encodeURIComponent(p.title || 'Foto Bukti')}')" title="${escapeHtml(p.title || 'Klik perbesar foto')}">
                        <img src="${p.url}" alt="${escapeHtml(p.title || 'Foto')}" class="w-full h-full object-cover group-hover:scale-105 transition duration-300">
                        <div class="absolute bottom-0 inset-x-0 bg-black/60 text-white text-[9px] px-1.5 py-0.5 truncate group-hover:bg-black/80 transition">
                            <span class="truncate">${escapeHtml(p.title || 'Foto')}</span>
                        </div>
                        <span class="absolute top-1 left-1 px-1.5 py-0.5 rounded ${badgeBg} text-[8px] uppercase tracking-wider backdrop-blur-xs">
                            ${badgeLabel}
                        </span>
                    </div>
                `;
            }).join('');
        }
    } else {
        if (photosGrid) {
            photosGrid.innerHTML = '';
            photosGrid.classList.add('hidden');
        }
        if (noPhotosEl) noPhotosEl.classList.remove('hidden');
    }

    // 6. Tabel Rincian Produk
    const itemsTbody = document.getElementById('mClaimItemsTableBody');
    const itemsSummary = document.getElementById('mClaimItemsSummary');

    if (itemsSummary) itemsSummary.innerText = `${items.length} Produk Tercatat`;

    if (itemsTbody) {
        if (items.length === 0) {
            itemsTbody.innerHTML = `<tr><td colspan="5" class="py-4 text-center text-slate-400">Tidak ada data rincian produk</td></tr>`;
        } else {
            itemsTbody.innerHTML = items.map(item => {
                const cond = String(item.condition || '').toUpperCase().trim();
                const typ = String(item.type || '').toUpperCase().trim();
                const isDmg = (cond !== '' && cond !== 'GOOD' && cond !== 'BAGUS') ||
                    (typ !== '' && typ !== 'GOOD' && typ !== 'BAGUS') ||
                    Boolean(item.damage_reason);

                const badgeCond = isDmg ?
                    `<span class="bg-rose-100 text-rose-800 font-bold px-2 py-0.5 rounded text-[10px] border border-rose-200">⚠️ ${escapeHtml(item.type || item.condition || 'RUSAK')}</span>` :
                    `<span class="bg-emerald-100 text-emerald-800 font-bold px-2 py-0.5 rounded text-[10px] border border-emerald-200">GOOD</span>`;

                const reason = item.damage_reason || (isDmg ? (item.type || 'Kondisi Cacat/Rusak') : '-');

                return `
                    <tr class="hover:bg-slate-50 transition border-b border-slate-100 ${isDmg ? 'bg-rose-50/30' : ''}">
                        <td class="py-2.5 px-3 font-mono font-bold text-slate-700">
                            <div>${escapeHtml(item.barcode || '-')}</div>
                            ${item.seller_sku ? `<span class="text-[10px] text-slate-400 font-normal">SKU: ${escapeHtml(item.seller_sku)}</span>` : ''}
                        </td>
                        <td class="py-2.5 px-3 font-semibold text-slate-800">
                            ${escapeHtml(item.product_name || '-')}
                        </td>
                        <td class="py-2.5 px-3 text-center font-bold text-slate-800">
                            ${item.qty || 1}
                        </td>
                        <td class="py-2.5 px-3 text-center">
                            ${badgeCond}
                        </td>
                        <td class="py-2.5 px-3 text-slate-700 font-medium">
                            ${isDmg ? `<span class="text-rose-700 font-semibold"><i class="fa-solid fa-triangle-exclamation mr-1 text-[10px]"></i>${escapeHtml(reason)}</span>` : '<span class="text-slate-400">-</span>'}
                        </td>
                    </tr>
                `;
            }).join('');
        }
    }
}

window.closeClaimDetailModal = function () {
    const modal = document.getElementById('modalClaimDetail');
    if (modal) modal.classList.add('hidden');
    const video = document.getElementById('mClaimVideoPlayer');
    if (video) {
        video.pause();
        video.src = '';
    }
};

window.printClaimFromModal = function () {
    const inv = activeClaimCandidateInvoice || document.getElementById('claimSearchInput')?.value?.trim();
    if (!inv) {
        showToast('warning', 'Pilih berkas klaim terlebih dahulu', 'Invoice Kosong');
        return;
    }
    const url = `claim_dossier.php?q=${encodeURIComponent(inv)}&autoprint=1`;
    const win = window.open(url, '_blank');
    if (!win || win.closed || typeof win.closed === 'undefined') {
        window.location.href = url;
    }
};

window.openClaimDossierFullTab = function () {
    const inv = activeClaimCandidateInvoice || document.getElementById('claimSearchInput')?.value?.trim();
    if (!inv) {
        showToast('warning', 'Pilih berkas klaim terlebih dahulu', 'Invoice Kosong');
        return;
    }
    const url = `claim_dossier.php?q=${encodeURIComponent(inv)}`;
    const win = window.open(url, '_blank');
    if (!win || win.closed || typeof win.closed === 'undefined') {
        window.location.href = url;
    }
};

// ==========================================
// FITUR MULTIPLE SELECT & INVOICE TAGIHAN KLAIM KOLEKTIF
// ==========================================
window.handleClaimCheckboxChange = function (invoiceNumber, cbEl) {
    if (cbEl.checked) {
        selectedClaimInvoices.add(invoiceNumber);
    } else {
        selectedClaimInvoices.delete(invoiceNumber);
    }

    // Update highlight baris
    const tr = cbEl.closest('tr');
    if (tr) {
        if (cbEl.checked) tr.classList.add('bg-amber-50/60');
        else tr.classList.remove('bg-amber-50/60');
    }

    const masterCb = document.getElementById('checkAllClaimCandidates');
    if (masterCb) {
        const allCheckboxes = document.querySelectorAll('.claim-item-checkbox');
        masterCb.checked = (allCheckboxes.length > 0 && Array.from(allCheckboxes).every(c => c.checked));
    }
    updateSelectedClaimsBar();
};

window.toggleSelectAllClaims = function (masterCb) {
    const isChecked = masterCb.checked;
    const checkboxes = document.querySelectorAll('.claim-item-checkbox');
    checkboxes.forEach(cb => {
        cb.checked = isChecked;
        const inv = cb.value;
        const tr = cb.closest('tr');
        if (isChecked) {
            selectedClaimInvoices.add(inv);
            if (tr) tr.classList.add('bg-amber-50/60');
        } else {
            selectedClaimInvoices.delete(inv);
            if (tr) tr.classList.remove('bg-amber-50/60');
        }
    });
    updateSelectedClaimsBar();
};

window.clearSelectedClaims = function () {
    selectedClaimInvoices.clear();
    const checkboxes = document.querySelectorAll('.claim-item-checkbox');
    checkboxes.forEach(cb => {
        cb.checked = false;
        const tr = cb.closest('tr');
        if (tr) tr.classList.remove('bg-amber-50/60');
    });
    const masterCb = document.getElementById('checkAllClaimCandidates');
    if (masterCb) masterCb.checked = false;
    updateSelectedClaimsBar();
};

window.updateSelectedClaimsBar = function () {
    const bar = document.getElementById('selectedClaimsActionCard');
    const countEl = document.getElementById('selectedClaimsCount');
    const dmgQtyEl = document.getElementById('selectedClaimsDamagedQty');
    const priceEl = document.getElementById('selectedClaimsTotalPrice');
    const btnCountEl = document.getElementById('btnSelectedCount');
    const btnPrintCostEl = document.getElementById('btnPrintTotalCost');

    if (!bar) return;

    if (selectedClaimInvoices.size === 0) {
        bar.classList.add('hidden');
        return;
    }

    bar.classList.remove('hidden');

    let totalNominal = 0;
    let totalDmg = 0;

    selectedClaimInvoices.forEach(inv => {
        const item = cachedClaimCandidates.find(c => c.invoice_number === inv);
        if (item) {
            totalNominal += Number(item.package_price || 0);
            totalDmg += Number(item.damaged_qty || item.total_damaged || item.damaged_items_count || 1);
        }
    });

    const formattedPrice = 'Rp ' + totalNominal.toLocaleString('id-ID');
    if (countEl) countEl.innerText = `${selectedClaimInvoices.size} Paket`;
    if (dmgQtyEl) dmgQtyEl.innerText = `(${totalDmg} pcs rusak)`;
    if (priceEl) priceEl.innerText = formattedPrice;
    if (btnCountEl) btnCountEl.innerText = selectedClaimInvoices.size;
    if (btnPrintCostEl) btnPrintCostEl.innerText = formattedPrice;

    // Alur tombol: Terpisah antara Mode JNT (Approval Accounting) vs Ekspedisi Lain (Klaim Manual)
    let pendingCount = 0, processCount = 0, approvedCount = 0;
    selectedClaimInvoices.forEach(inv => {
        const item = cachedClaimCandidates.find(c => c.invoice_number === inv);
        const st = item ? (item.claim_status || 'PENDING') : 'PENDING';
        if (st === 'PENDING') pendingCount++;
        else if (st === 'PROCESS') processCount++;

        if (item && item.accounting_status === 'APPROVED') {
            approvedCount++;
        }
    });

    const btnSendAcc = document.getElementById('btnSendToAccounting');
    const btnSendAccCount = document.getElementById('btnSendAccountingCount');
    const btnPullAcc = document.getElementById('btnPullAccounting');
    const btnClaim = document.getElementById('btnBulkClaimProcess');
    const btnClaimCount = document.getElementById('btnBulkClaimCount');
    const btnDone = document.getElementById('btnBulkClaimDone');
    const btnPrint = document.getElementById('btnPrintClaimInvoice');

    if (claimExpeditionMode === 'jnt') {
        // MODE JNT: Kirim ke Accounting & Tarik Approval
        if (btnSendAcc) {
            btnSendAcc.classList.remove('hidden');
            if (btnSendAccCount) btnSendAccCount.innerText = selectedClaimInvoices.size;
        }
        if (btnPullAcc) btnPullAcc.classList.remove('hidden');

        // Sembunyikan tombol klaim manual admin
        if (btnClaim) btnClaim.classList.add('hidden');
        if (btnDone) btnDone.classList.add('hidden');

        // Tombol Print Invoice muncul jika ada item terpilih yang sudah di-approve oleh Accounting
        if (btnPrint) {
            btnPrint.classList.toggle('hidden', approvedCount === 0);
        }
    } else {
        // MODE EKSPEDISI LAIN: Klaim Manual Admin
        if (btnSendAcc) btnSendAcc.classList.add('hidden');
        if (btnPullAcc) btnPullAcc.classList.add('hidden');

        if (btnClaimCount) btnClaimCount.innerText = pendingCount;
        if (btnClaim) btnClaim.classList.toggle('hidden', pendingCount === 0);
        if (btnPrint) btnPrint.classList.toggle('hidden', pendingCount > 0);
        if (btnDone) btnDone.classList.toggle('hidden', pendingCount > 0 || processCount === 0);
    }
};

window.sendSelectedToAccounting = async function () {
    if (selectedClaimInvoices.size === 0) {
        showToast('warning', 'Pilih minimal 1 paket klaim J&T terlebih dahulu.', 'Peringatan');
        return;
    }

    const invoices = Array.from(selectedClaimInvoices);
    if (!confirm(`Kirim data tabel untuk ${invoices.length} klaim J&T ke Accounting via InfinityFree untuk di-approval?\n\nSesuai instruksi: Data yang dikirim hanya berupa DATA TABEL (Resi, Order ID OCS, Produk Rusak, Total Tagihan) TANPA FOTO.`)) {
        return;
    }

    const btn = document.getElementById('btnSendToAccounting');
    const origHtml = btn ? btn.innerHTML : '';
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> Mengirim...`;
    }

    try {
        const res = await fetch('api/sync_jnt_claims.php?action=send_to_cloud', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ invoices })
        });
        const data = await res.json();

        if (data.success) {
            showToast('success', data.message || `${invoices.length} klaim J&T berhasil dikirim ke Accounting!`, 'Terkirim ke Accounting');
            const invSet = new Set(invoices);
            cachedClaimCandidates.forEach(c => {
                if (invSet.has(c.invoice_number)) {
                    c.accounting_status = 'PENDING_APPROVAL';
                }
            });
            applyClaimCandidatesFilter();
        } else {
            showToast('error', data.error || 'Gagal mengirim data klaim ke Accounting.', 'Gagal Kirim');
        }
    } catch (err) {
        showToast('error', 'Koneksi error: ' + err.message, 'Gagal');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = origHtml;
        }
    }
};

window.pullAccountingApproval = async function () {
    const btn = document.getElementById('btnPullAccounting');
    const origHtml = btn ? btn.innerHTML : '';
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> Menarik...`;
    }

    try {
        const res = await fetch('api/sync_jnt_claims.php?action=pull_from_cloud', {
            method: 'POST'
        });
        const data = await res.json();

        if (data.success) {
            showToast('success', data.message || 'Status approval dari Accounting berhasil ditarik!', 'Approval Diterima');
            loadClaimCandidates(true);
        } else {
            showToast('error', data.error || 'Gagal menarik status approval dari cloud.', 'Gagal Tarik');
        }
    } catch (err) {
        showToast('error', 'Koneksi error: ' + err.message, 'Gagal');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = origHtml;
        }
    }
};

window.openCollectiveClaimInvoiceModal = function () {
    if (selectedClaimInvoices.size === 0) {
        showToast('warning', 'Pilih minimal 1 paket klaim untuk membuat invoice tagihan.', 'Peringatan');
        return;
    }

    const modal = document.getElementById('modalCollectiveClaimInvoice');
    const container = document.getElementById('mColClaimPreviewContainer');
    const badgeCount = document.getElementById('mColClaimBadgeCount');

    if (badgeCount) badgeCount.innerText = `${selectedClaimInvoices.size} Paket`;

    // Ambil data terpilih dari cachedClaimCandidates
    const selectedList = [];
    selectedClaimInvoices.forEach(inv => {
        const found = cachedClaimCandidates.find(c => c.invoice_number === inv);
        if (found) {
            selectedList.push(found);
        } else {
            selectedList.push({ invoice_number: inv, expedition: '-', package_price: 0, damaged_qty: 1 });
        }
    });

    let grandTotal = 0;
    let totalQty = 0;
    const expSet = new Set();

    selectedList.forEach(it => {
        grandTotal += Number(it.package_price || 0);
        totalQty += Number(it.damaged_qty || it.total_damaged || it.damaged_items_count || 1);
        if (it.expedition) expSet.add(it.expedition);
    });

    const expText = expSet.size > 0 ? Array.from(expSet).join(', ') : 'Ekspedisi Terkait';
    const docNo = 'INV-CLM-' + new Date().toISOString().slice(0, 10).replace(/-/g, '') + '-' + String(selectedList.length).padStart(3, '0');

    let tableRows = '';
    selectedList.forEach((it, idx) => {
        const pVal = Number(it.package_price || 0);
        const pText = pVal > 0 ? ('Rp ' + pVal.toLocaleString('id-ID')) : '<span class="text-slate-400 italic">Rp 0</span>';
        const qDmg = Number(it.damaged_qty || it.total_damaged || it.damaged_items_count || 1);
        const rsn = it.damage_reasons || it.notes || 'Kondisi Rusak Saat Unboxing';

        tableRows += `
            <tr class="hover:bg-slate-50 border-b border-slate-100 text-xs">
                <td class="p-2.5 text-center text-slate-400 font-bold">${idx + 1}</td>
                <td class="p-2.5 font-mono font-bold text-slate-800">
                    ${escapeHtml(it.invoice_number)}
                    <div class="text-[10px] text-amber-600 font-semibold">${escapeHtml(it.expedition || '-')}</div>
                </td>
                <td class="p-2.5">
                    <div class="font-bold text-slate-900">${escapeHtml(it.product_names || 'Produk Retur')}</div>
                    ${it.sku ? `<div class="text-[10px] text-slate-500 font-mono">SKU: ${escapeHtml(it.sku)}</div>` : ''}
                </td>
                <td class="p-2.5 text-center font-bold text-rose-600 font-mono">${qDmg}</td>
                <td class="p-2.5 text-slate-600">${escapeHtml(rsn)}</td>
                <td class="p-2.5 text-right font-mono font-bold text-slate-900 whitespace-nowrap">${pText}</td>
            </tr>
        `;
    });

    if (container) {
        container.innerHTML = `
            <div class="bg-slate-50 p-4 sm:p-5 rounded-2xl border border-slate-200/90 space-y-4">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 border-b border-slate-200 pb-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="w-6 h-6 rounded bg-emerald-600 text-white flex items-center justify-center text-xs font-black">IEG</span>
                            <span class="font-black text-sm text-slate-900">PT. INOVASI EKA GEMILANG</span>
                        </div>
                        <div class="text-[11px] text-slate-500">Reverse Logistics & Claims Department</div>
                    </div>
                    <div class="sm:text-right">
                        <div class="text-[11px] font-mono font-bold text-rose-600">No: ${docNo}</div>
                        <div class="text-[10px] text-slate-400">Ekspedisi: <b class="text-slate-700">${escapeHtml(expText)}</b></div>
                    </div>
                </div>

                <div class="overflow-x-auto rounded-xl border border-slate-200 shadow-2xs">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="bg-slate-800 text-white font-bold text-[10px] uppercase">
                                <th class="p-2.5 text-center w-8">#</th>
                                <th class="p-2.5">Resi / Invoice</th>
                                <th class="p-2.5">Produk & SKU</th>
                                <th class="p-2.5 text-center w-14">Qty Rusak</th>
                                <th class="p-2.5">Alasan Kerusakan</th>
                                <th class="p-2.5 text-right w-28">Nilai Tagihan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            ${tableRows}
                        </tbody>
                        <tfoot>
                            <tr class="bg-slate-100 font-bold border-t-2 border-slate-800 text-xs">
                                <td colspan="3" class="p-2.5 text-right text-slate-700 uppercase">Total (${selectedList.length} Paket):</td>
                                <td class="p-2.5 text-center font-mono text-rose-600 text-sm">${totalQty}</td>
                                <td class="p-2.5 text-right text-slate-700 uppercase">Grand Total:</td>
                                <td class="p-2.5 text-right font-mono font-black text-emerald-700 text-sm whitespace-nowrap">
                                    Rp ${grandTotal.toLocaleString('id-ID')}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="p-3 bg-emerald-50 border border-emerald-200 rounded-xl text-xs flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 shadow-2xs">
                    <div>
                        <span class="text-emerald-800 font-bold block">Total Penggantian Tagihan Klaim:</span>
                        <span class="text-[11px] text-emerald-600">Siap diajukan secara resmi ke pihak ekspedisi terkait</span>
                    </div>
                    <span class="font-mono font-black text-emerald-700 text-base sm:text-lg">Rp ${grandTotal.toLocaleString('id-ID')}</span>
                </div>
            </div>
        `;
    }

    if (modal) modal.classList.remove('hidden');
};

window.closeCollectiveClaimModal = function () {
    const modal = document.getElementById('modalCollectiveClaimInvoice');
    if (modal) modal.classList.add('hidden');
};

function submitInvoicePostForm(invoices, autoPrint = false) {
    if (!invoices || (Array.isArray(invoices) && invoices.length === 0)) return;
    const invList = Array.isArray(invoices) ? invoices.join(',') : String(invoices);
    if (!invList) return;

    // Gunakan dynamic POST form dengan target="_blank" untuk mencegah batasan panjang URL (GET) dan error 403 Forbidden
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = autoPrint ? 'claim_invoice.php?autoprint=1' : 'claim_invoice.php';
    form.target = '_blank';
    form.style.display = 'none';

    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'invoices';
    input.value = invList;
    form.appendChild(input);

    document.body.appendChild(form);
    form.submit();
    setTimeout(() => {
        if (form.parentNode) form.parentNode.removeChild(form);
    }, 1200);
}

window.printCollectiveClaimInvoice = function () {
    if (selectedClaimInvoices.size === 0) {
        showToast('warning', 'Pilih minimal 1 paket klaim untuk dicetak invoice tagihannya.', 'Peringatan');
        return;
    }
    const hasPending = Array.from(selectedClaimInvoices).some(inv => {
        const c = cachedClaimCandidates.find(x => x.invoice_number === inv);
        return !c || (c.claim_status || 'PENDING') === 'PENDING';
    });
    if (hasPending) {
        showToast('warning', 'Klik tombol "Klaim" terlebih dahulu sebelum mencetak invoice tagihan.', 'Wajib Klaim Dulu');
        return;
    }
    submitInvoicePostForm(Array.from(selectedClaimInvoices), false);
};

window.openCollectiveClaimFullTab = function () {
    if (selectedClaimInvoices.size === 0) {
        showToast('warning', 'Pilih minimal 1 paket klaim untuk melihat invoice tagihannya.', 'Peringatan');
        return;
    }
    submitInvoicePostForm(Array.from(selectedClaimInvoices), false);
};

window.copyCollectiveClaimText = function () {
    if (selectedClaimInvoices.size === 0) return;
    const selectedList = [];
    selectedClaimInvoices.forEach(inv => {
        const found = cachedClaimCandidates.find(c => c.invoice_number === inv);
        if (found) selectedList.push(found);
    });

    let grandTotal = 0;
    const expSet = new Set();
    selectedList.forEach(it => {
        grandTotal += Number(it.package_price || 0);
        if (it.expedition) expSet.add(it.expedition);
    });

    let text = `*SURAT TAGIHAN KLAIM BARANG RUSAK EKSPEDISI*\n`;
    text += `Ekspedisi: ${Array.from(expSet).join(', ') || '-'}\n`;
    text += `Total Paket: ${selectedList.length} Paket\n`;
    text += `Total Nominal Tagihan: *Rp ${grandTotal.toLocaleString('id-ID')}*\n\n`;
    text += `*Daftar Resi & Kerusakan:*\n`;

    selectedList.forEach((it, idx) => {
        const pText = it.package_price > 0 ? ('Rp ' + Number(it.package_price).toLocaleString('id-ID')) : '-';
        const dQty = it.damaged_qty || it.total_damaged || it.damaged_items_count || 1;
        text += `${idx + 1}. Resi: ${it.invoice_number} (${it.expedition || '-'})\n`;
        if (it.sku) text += `   SKU: ${it.sku}\n`;
        text += `   Barang: ${it.product_names || 'Produk Retur'}\n`;
        text += `   Qty Rusak: ${dQty} pcs\n`;
        text += `   Kondisi/Alasan: ${it.damage_reasons || it.notes || 'Rusak'}\n`;
        text += `   Harga Paket: ${pText}\n\n`;
    });

    text += `Mohon segera diverifikasi dan diproses penggantian klaimnya. Terima kasih.\n`;
    text += `_PT. Inovasi Eka Gemilang - Reverse Logistics_`;

    navigator.clipboard.writeText(text).then(() => {
        showToast('success', 'Format rekap tagihan berhasil disalin ke clipboard!', 'Tersalin');
    }).catch(() => {
        prompt('Salin teks tagihan:', text);
    });
};

window.lookupClaimCandidate = function (identifier) {
    if (!identifier) return;
    openClaimDetailModal(identifier);
};

window.searchClaimDossier = function (e) {
    if (window.executeClaimLookup) return window.executeClaimLookup(e);
};

// -------------------------------------------------------------
// AUTO-SYNC CLOUD (INFINITYFREE -> LOCALHOST) BACKGROUND POLLER
// -------------------------------------------------------------
let isSyncingBackground = false;
async function triggerBackgroundCloudSync() {
    // Hanya jalankan jika diakses di PC Localhost / server lokal
    const isLocalhost = (location.hostname === 'localhost' || location.hostname === '127.0.0.1' || location.hostname.endsWith('.test') || location.hostname.startsWith('192.168.'));
    if (!isLocalhost || isSyncingBackground) return;

    isSyncingBackground = true;
    try {
        const res = await fetch('sync_worker.php');
        const data = await res.json();
        if (data && data.success) {
            const numRet = data.synced_returns || 0;
            const numRec = data.synced_receptions || 0;
            if (numRet > 0 || numRec > 0) {
                if (typeof showToast === 'function') {
                    showToast('info', `Tersinkron ${numRet} retur & ${numRec} receiving dari Cloud InfinityFree.`, 'Auto-Sync Berhasil');
                }
                // Refresh data dashboard / tabel aktif
                if (typeof currentTab !== 'undefined') {
                    if (currentTab === 'dashboard' && typeof loadMetrics === 'function') loadMetrics();
                    if (currentTab === 'transactions' && typeof loadTransactions === 'function') loadTransactions();
                    if (currentTab === 'receiving' && typeof loadReceivingData === 'function') loadReceivingData();
                }
            }
        }
    } catch (e) {
        // Silent error agar tidak mengganggu operasional jika cloud offline
        console.debug('Background sync status:', e.message);
    } finally {
        isSyncingBackground = false;
    }
}

// Jalankan sync pertama 5 detik setelah admin terbuka, lalu ulang tiap 45 detik
setTimeout(() => {
    triggerBackgroundCloudSync();
    setInterval(triggerBackgroundCloudSync, 45000);
}, 5000);

// -------------------------------------------------------------
// SINKRONISASI ORDERS OCS (RESI, INVOICE, SKU, BIAYA, KLAIM)
// -------------------------------------------------------------
window.openOcsSyncModal = function (mode = null) {
    const modal = document.getElementById('modalOcsSync');
    if (modal) {
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
    if (mode === 'month') {
        const radios = document.getElementsByName('syncPeriodType');
        for (const r of radios) {
            r.checked = (r.value === 'month');
        }
        if (typeof toggleSyncDateInput === 'function') {
            toggleSyncDateInput();
        }
    } else if (mode === 'picklist') {
        const radios = document.getElementsByName('syncPeriodType');
        for (const r of radios) {
            r.checked = (r.value === 'picklist');
        }
        if (typeof toggleSyncDateInput === 'function') {
            toggleSyncDateInput();
        }
        setTimeout(() => {
            const input = document.getElementById('syncPicklistKeywordInput');
            if (input) input.focus();
        }, 150);
    }
};

window.closeOcsSyncModal = function () {
    const modal = document.getElementById('modalOcsSync');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = '';
    }
};

window.toggleSyncDateInput = function () {
    const radios = document.getElementsByName('syncPeriodType');
    let selected = 'yesterday';
    for (const r of radios) {
        if (r.checked) selected = r.value;
    }

    const customContainer = document.getElementById('syncCustomDateContainer');
    const picklistContainer = document.getElementById('syncPicklistContainer');
    const labelYesterday = document.getElementById('labelSyncYesterday');
    const labelToday = document.getElementById('labelSyncToday');
    const labelMonth = document.getElementById('labelSyncMonth');
    const labelCustom = document.getElementById('labelSyncCustom');
    const labelPicklist = document.getElementById('labelSyncPicklist');

    [labelYesterday, labelToday, labelMonth, labelCustom, labelPicklist].forEach(l => {
        if (l) {
            l.classList.remove('border-indigo-600', 'bg-indigo-50/50', 'border-blue-600', 'bg-blue-50/50');
            l.classList.add('border-slate-200', 'bg-white');
        }
    });

    if (customContainer) customContainer.classList.add('hidden');
    if (picklistContainer) picklistContainer.classList.add('hidden');

    if (selected === 'yesterday' && labelYesterday) {
        labelYesterday.classList.add('border-indigo-600', 'bg-indigo-50/50');
        labelYesterday.classList.remove('border-slate-200', 'bg-white');
    } else if (selected === 'today' && labelToday) {
        labelToday.classList.add('border-indigo-600', 'bg-indigo-50/50');
        labelToday.classList.remove('border-slate-200', 'bg-white');
    } else if (selected === 'month' && labelMonth) {
        labelMonth.classList.add('border-indigo-600', 'bg-indigo-50/50');
        labelMonth.classList.remove('border-slate-200', 'bg-white');
    } else if (selected === 'custom' && labelCustom) {
        labelCustom.classList.add('border-indigo-600', 'bg-indigo-50/50');
        labelCustom.classList.remove('border-slate-200', 'bg-white');
        if (customContainer) customContainer.classList.remove('hidden');
    } else if (selected === 'picklist' && labelPicklist) {
        labelPicklist.classList.add('border-blue-600', 'bg-blue-50/50');
        labelPicklist.classList.remove('border-slate-200', 'bg-white');
        if (picklistContainer) {
            picklistContainer.classList.remove('hidden');
            const inp = document.getElementById('syncPicklistKeywordInput');
            if (inp) inp.focus();
        }
    }
};

window.executeOcsOrderSync = async function () {
    const btnStart = document.getElementById('btnStartOcsSync');
    const btnCancel = document.getElementById('btnCancelOcsSync');
    const progressContainer = document.getElementById('syncProgressContainer');
    const progressTitle = document.getElementById('syncProgressTitle');
    const progressBadge = document.getElementById('syncProgressBadge');
    const progressDetail = document.getElementById('syncProgressDetail');
    const syncTrafficLoader = document.getElementById('syncTrafficLoader');
    const statsBox = document.getElementById('syncStatsBox');
    const statTotalOrders = document.getElementById('statTotalOrders');
    const statWithResi = document.getElementById('statWithResi');
    const statTotalClaim = document.getElementById('statTotalClaim');

    const radios = document.getElementsByName('syncPeriodType');
    let selected = 'yesterday';
    for (const r of radios) {
        if (r.checked) selected = r.value;
    }

    // Helper format YYYY-MM-DD
    const formatDateYMD = (d) => {
        const year = d.getFullYear();
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    };

    // Bangun antrian chunk (agar anti 504 Gateway Timeout pada rentang besar)
    let chunks = [];
    let isSinglePicklist = false;
    let singlePicklistKeyword = '';

    if (selected === 'picklist') {
        const inputKey = document.getElementById('syncPicklistKeywordInput');
        singlePicklistKeyword = inputKey ? inputKey.value.trim() : '';
        if (!singlePicklistKeyword) {
            showToast('warning', 'Harap masukkan No. Resi atau Order ID terlebih dahulu.', 'Peringatan');
            if (inputKey) inputKey.focus();
            return;
        }
        isSinglePicklist = true;
        chunks.push({
            url: `api/sync_ocs_orders.php?keyword=${encodeURIComponent(singlePicklistKeyword)}`,
            label: `No. Resi / Order ID: ${singlePicklistKeyword}`
        });
    } else if (selected === 'month') {
        // Bagi 30 hari ke dalam 6 batch (masing-masing 5 hari) agar responsif & bebas gateway timeout
        const now = new Date();
        const totalDays = 30;
        const stepDays = 5;
        for (let i = totalDays; i > 0; i -= stepDays) {
            const startD = new Date(now);
            startD.setDate(now.getDate() - i);
            const endD = new Date(now);
            endD.setDate(now.getDate() - (i - stepDays));
            if (endD > now) endD.setTime(now.getTime());

            const sStr = formatDateYMD(startD);
            const eStr = formatDateYMD(endD);
            chunks.push({
                url: `api/sync_ocs_orders.php?start=${encodeURIComponent(sStr)}&end=${encodeURIComponent(eStr)}&details=0`,
                label: `${sStr} s/d ${eStr}`,
                start: sStr,
                end: eStr
            });
        }
    } else if (selected === 'custom') {
        const startDateInput = document.getElementById('syncStartDateInput');
        const endDateInput = document.getElementById('syncEndDateInput');
        const startDate = startDateInput ? startDateInput.value : '';
        const endDate = endDateInput ? endDateInput.value : '';
        if (!startDate || !endDate) {
            showToast('warning', 'Harap tentukan tanggal mulai dan tanggal selesai terlebih dahulu.', 'Peringatan');
            return;
        }
        const d1 = new Date(startDate);
        const d2 = new Date(endDate);
        if (d1 > d2) {
            showToast('warning', 'Tanggal mulai tidak boleh lebih besar dari tanggal selesai.', 'Peringatan');
            return;
        }
        const diffDays = Math.ceil((d2 - d1) / (1000 * 60 * 60 * 24));
        if (diffDays > 5) {
            // Bagi per 5 hari jika rentang lebih dari 5 hari
            let cur = new Date(d1);
            while (cur < d2) {
                const chunkStart = new Date(cur);
                const chunkEnd = new Date(cur);
                chunkEnd.setDate(chunkEnd.getDate() + 5);
                if (chunkEnd > d2) chunkEnd.setTime(d2.getTime());

                const sStr = formatDateYMD(chunkStart);
                const eStr = formatDateYMD(chunkEnd);
                chunks.push({
                    url: `api/sync_ocs_orders.php?start=${encodeURIComponent(sStr)}&end=${encodeURIComponent(eStr)}&details=0`,
                    label: `${sStr} s/d ${eStr}`,
                    start: sStr,
                    end: eStr
                });
                cur.setDate(cur.getDate() + 5);
            }
        } else {
            chunks.push({
                url: `api/sync_ocs_orders.php?start=${encodeURIComponent(startDate)}&end=${encodeURIComponent(endDate)}&details=0`,
                label: `${startDate} s/d ${endDate}`,
                start: startDate,
                end: endDate
            });
        }
    } else {
        // Kemarin / Hari ini (1 hari = 1 request cepat)
        chunks.push({
            url: `api/sync_ocs_orders.php?date=${encodeURIComponent(selected)}`,
            label: selected === 'yesterday' ? 'Kemarin' : 'Hari Ini'
        });
    }

    // Persiapkan UI Loading
    if (progressContainer) progressContainer.classList.remove('hidden');
    if (syncTrafficLoader) syncTrafficLoader.classList.remove('hidden');
    if (statsBox) statsBox.classList.add('hidden');
    if (progressBadge) {
        progressBadge.className = 'text-[10px] px-2 py-0.5 rounded-full bg-indigo-500/30 text-indigo-300 font-mono';
        progressBadge.innerText = 'PROSES';
    }

    if (btnStart) {
        btnStart.disabled = true;
        btnStart.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> Menyinkron...`;
        btnStart.classList.add('opacity-60', 'cursor-not-allowed');
    }
    if (btnCancel) btnCancel.disabled = true;

    let accumulatedOrders = 0;
    let accumulatedWithResi = 0;
    let accumulatedClaim = 0;
    let lastResult = null;

    try {
        const totalSteps = chunks.length;

        for (let i = 0; i < totalSteps; i++) {
            const chunk = chunks[i];
            const currentStep = i + 1;
            const progressPercent = Math.round((currentStep / totalSteps) * 100);

            if (progressTitle) {
                progressTitle.innerHTML = totalSteps > 1
                    ? `Sinkronisasi Bagian ${currentStep}/${totalSteps} (${chunk.label})...`
                    : `Sinkronisasi Orders Sedang Berjalan...`;
            }
            if (progressDetail) {
                progressDetail.innerHTML = totalSteps > 1
                    ? `Menghubungkan ke OCS untuk rentang <b>${chunk.label}</b>...<br>Tersinkron sejauh ini: <b>${accumulatedOrders.toLocaleString('id-ID')}</b> orders.`
                    : `Menghubungkan ke OCS IEG System untuk ${chunk.label}...`;
            }

            if (typeof showGlobalLoading === 'function') {
                showGlobalLoading(
                    totalSteps > 1 ? `Sinkronisasi Orders OCS (${progressPercent}%)...` : 'Sinkronisasi Orders OCS...',
                    totalSteps > 1
                        ? `Sedang memproses bagian ${currentStep} dari ${totalSteps} (${chunk.label}). Total tersimpan: ${accumulatedOrders.toLocaleString('id-ID')} orders.`
                        : `Sedang menarik dan menyinkronkan data orders dari OCS IEG System. Mohon tunggu sebentar...`
                );
            }

            const response = await fetch(chunk.url);
            const rawText = await response.text();

            if (!rawText || rawText.trim() === '') {
                throw new Error(`Server mengembalikan respons kosong pada tahap ${currentStep}. Kemungkinan koneksi internet terputus.`);
            }

            let result;
            try {
                result = JSON.parse(rawText);
            } catch (jsonErr) {
                console.error('[sync_ocs_orders] Non-JSON response on step', currentStep, rawText.substring(0, 500));
                throw new Error(`Server mengembalikan respons tidak valid pada tahap ${currentStep}. Preview: ${rawText.substring(0, 120)}`);
            }

            if (!result || !result.success) {
                throw new Error(result?.error || result?.message || `Gagal menyinkron data orders pada tahap ${currentStep}`);
            }

            lastResult = result;
            accumulatedOrders += (result.total_synced || result.total_orders_found || 1);
            accumulatedWithResi += (result.total_with_resi || (result.tracking_number ? 1 : 0));
            accumulatedClaim += (result.total_claim_amount || 0);

            // Update stats realtime
            if (statsBox) statsBox.classList.remove('hidden');
            if (statTotalOrders) statTotalOrders.innerText = accumulatedOrders.toLocaleString('id-ID');
            if (statWithResi) statWithResi.innerText = accumulatedWithResi.toLocaleString('id-ID');
            if (statTotalClaim) statTotalClaim.innerText = `Rp ${accumulatedClaim.toLocaleString('id-ID')}`;
        }

        // SEMUA CHUNK SELESAI
        if (progressTitle) {
            progressTitle.innerHTML = `<i class="fa-solid fa-circle-check text-emerald-400"></i> Sinkronisasi Berhasil Selesai!`;
        }
        if (progressBadge) {
            progressBadge.className = 'text-[10px] px-2 py-0.5 rounded-full bg-emerald-500/30 text-emerald-300 font-mono';
            progressBadge.innerText = 'SELESAI';
        }
        if (progressDetail) {
            if (isSinglePicklist && lastResult) {
                progressDetail.innerHTML = `Order <b>${lastResult.order_id}</b> (Resi: <b>${lastResult.tracking_number || '-'}</b>) dari <b>${lastResult.shop_name || '-'}</b> (${lastResult.platform || '-'}) berhasil disinkronisasi lengkap dengan ${lastResult.skus_count || 1} SKU!`;
            } else {
                const labelSummary = selected === 'month' ? '1 Bulan Terakhir (30 Hari)' : (selected === 'yesterday' ? 'Kemarin' : (selected === 'today' ? 'Hari Ini' : 'Rentang Tanggal'));
                progressDetail.innerHTML = `Periode: <b>${labelSummary}</b><br>Total <b>${accumulatedOrders.toLocaleString('id-ID')}</b> pesanan tersimpan ke MySQL secara aman tanpa timeout.`;
            }
        }

        showToast('success', `Berhasil menyinkronkan total ${accumulatedOrders.toLocaleString('id-ID')} orders dari OCS!`, 'Sinkronisasi Selesai');

        // Refresh data setelah sync sukses
        if (typeof loadClaimCandidates === 'function') loadClaimCandidates(true);
        if (typeof loadOrdersStats === 'function') loadOrdersStats();
        if (typeof loadOrdersTable === 'function') loadOrdersTable(1);

    } catch (err) {
        if (progressTitle) {
            progressTitle.innerHTML = `<i class="fa-solid fa-circle-xmark text-rose-400"></i> Gagal Menyinkron Orders`;
        }
        if (progressBadge) {
            progressBadge.className = 'text-[10px] px-2 py-0.5 rounded-full bg-rose-500/30 text-rose-300 font-mono';
            progressBadge.innerText = 'ERROR';
        }
        if (progressDetail) {
            progressDetail.innerText = err.message;
        }
        showToast('error', err.message, 'Gagal Sinkronisasi');
    } finally {
        if (typeof hideGlobalLoading === 'function') {
            hideGlobalLoading();
        }
        if (btnStart) {
            btnStart.disabled = false;
            btnStart.innerHTML = `<i class="fa-solid fa-rotate"></i> Sinkron Ulang`;
            btnStart.classList.remove('opacity-60', 'cursor-not-allowed');
        }
        if (btnCancel) btnCancel.disabled = false;
    }
};

// =============================================================
// MODUL DATA ORDERS OCS (SINKRONISASI PESANAN MARKETPLACE)
// =============================================================
let currentOrdersPage = 1;
let orderSearchDebounceTimer = null;

// 1. Memuat Statistik KPI Orders
window.loadOrdersStats = async function () {
    try {
        const res = await fetch('api/orders.php?action=stats');
        const data = await res.json();
        if (data && data.success && data.stats) {
            const s = data.stats;
            const elTotal = document.getElementById('orderStatTotal');
            const elResi = document.getElementById('orderStatWithResi');
            const elClaim = document.getElementById('orderStatClaim');
            const elLast = document.getElementById('orderStatLastSync');

            if (elTotal) elTotal.innerText = Number(s.total_orders || 0).toLocaleString('id-ID');
            if (elResi) elResi.innerText = Number(s.total_with_resi || 0).toLocaleString('id-ID');
            if (elClaim) elClaim.innerText = s.total_claim_fmt || ('Rp ' + Number(s.total_claim || 0).toLocaleString('id-ID'));
            if (elLast) elLast.innerText = s.last_sync || 'Belum Ada';
        }
    } catch (e) {
        console.error('Gagal memuat statistik orders:', e);
    }
};

// 2. Debounce Pencarian Orders
window.debounceOrderSearch = function () {
    const input = document.getElementById('orderSearchInput');
    const clearBtn = document.getElementById('btnClearOrderSearch');
    if (clearBtn) {
        if (input && input.value.trim().length > 0) {
            clearBtn.classList.remove('hidden');
        } else {
            clearBtn.classList.add('hidden');
        }
    }
    clearTimeout(orderSearchDebounceTimer);
    orderSearchDebounceTimer = setTimeout(() => {
        loadOrdersTable(1);
    }, 350);
};

window.clearOrderSearch = function () {
    const input = document.getElementById('orderSearchInput');
    const clearBtn = document.getElementById('btnClearOrderSearch');
    if (input) input.value = '';
    if (clearBtn) clearBtn.classList.add('hidden');
    loadOrdersTable(1);
};

// 3. Handler Perubahan Filter Tanggal
window.onOrderDateFilterChanged = function () {
    const filter = document.getElementById('orderDateFilter');
    const customBox = document.getElementById('orderCustomDateBox');
    if (!filter) return;

    if (filter.value === 'custom') {
        if (customBox) customBox.classList.remove('hidden');
    } else {
        if (customBox) customBox.classList.add('hidden');
        loadOrdersTable(1);
    }
};

// Helper: Populate dropdown options dinamis
function populateOrderFilterDropdown(selectId, options, defaultLabel, currentValue) {
    const select = document.getElementById(selectId);
    if (!select || !Array.isArray(options)) return;

    const curr = currentValue !== undefined ? currentValue : select.value;
    const cleanOptions = options.filter(o => o && String(o).trim() !== '');

    // Update jika belum ada atau opsi berubah
    const existingCount = select.options.length;
    if (existingCount <= 1 && cleanOptions.length > 0) {
        let html = `<option value="ALL">${defaultLabel}</option>`;
        cleanOptions.forEach(opt => {
            html += `<option value="${escapeHtml(String(opt))}">${escapeHtml(String(opt))}</option>`;
        });
        select.innerHTML = html;
        if (curr && select.querySelector(`option[value="${curr}"]`)) {
            select.value = curr;
        }
    }
}

// 3b. Reset Semua Filter Orders ke Default
window.resetOrderFilters = function () {
    const searchInput = document.getElementById('orderSearchInput');
    const clearBtn = document.getElementById('btnClearOrderSearch');
    const platformSelect = document.getElementById('orderPlatformFilter');
    const shopSelect = document.getElementById('orderShopFilter');
    const shippingSelect = document.getElementById('orderShippingFilter');
    const statusSelect = document.getElementById('orderStatusFilter');
    const resiSelect = document.getElementById('orderResiFilter');
    const claimSelect = document.getElementById('orderClaimFilter');
    const dateSelect = document.getElementById('orderDateFilter');
    const customBox = document.getElementById('orderCustomDateBox');
    const startDate = document.getElementById('orderStartDate');
    const endDate = document.getElementById('orderEndDate');
    const sortSelect = document.getElementById('orderSortSelect');

    if (searchInput) searchInput.value = '';
    if (clearBtn) clearBtn.classList.add('hidden');
    if (platformSelect) platformSelect.value = 'ALL';
    if (shopSelect) shopSelect.value = 'ALL';
    if (shippingSelect) shippingSelect.value = 'ALL';
    if (statusSelect) statusSelect.value = 'ALL';
    if (resiSelect) resiSelect.value = 'ALL';
    if (claimSelect) claimSelect.value = 'ALL';
    if (dateSelect) dateSelect.value = '';
    if (customBox) customBox.classList.add('hidden');
    if (startDate) startDate.value = '';
    if (endDate) endDate.value = '';
    if (sortSelect) sortSelect.value = 'date_desc';

    showToast('info', 'Semua filter pesanan telah direset ke default', 'Filter Direset');
    loadOrdersTable(1);
};

// 4. Memuat Data Tabel Orders
window.loadOrdersTable = async function (page = 1) {
    currentOrdersPage = page;
    const tbody = document.getElementById('ordersTableBody');
    if (!tbody) return;

    // Tampilkan Loader
    tbody.innerHTML = `
        <tr>
            <td colspan="8" class="text-center py-12 text-slate-400">
                <div class="flex flex-col items-center justify-center space-y-3">
                    <div class="traffic-loader">
                        <div class="traffic-ball traffic-ball-red"></div>
                        <div class="traffic-ball traffic-ball-yellow"></div>
                        <div class="traffic-ball traffic-ball-green"></div>
                    </div>
                    <span class="text-xs font-semibold text-slate-500">Memuat data pesanan OCS...</span>
                </div>
            </td>
        </tr>
    `;

    try {
        const searchInput = document.getElementById('orderSearchInput');
        const platformSelect = document.getElementById('orderPlatformFilter');
        const shopSelect = document.getElementById('orderShopFilter');
        const shippingSelect = document.getElementById('orderShippingFilter');
        const statusSelect = document.getElementById('orderStatusFilter');
        const resiSelect = document.getElementById('orderResiFilter');
        const claimSelect = document.getElementById('orderClaimFilter');
        const dateSelect = document.getElementById('orderDateFilter');
        const sortSelect = document.getElementById('orderSortSelect');
        const limitSelect = document.getElementById('orderLimitSelect');

        const search = searchInput ? searchInput.value.trim() : '';
        const platform = platformSelect ? platformSelect.value : 'ALL';
        const shop = shopSelect ? shopSelect.value : 'ALL';
        const shipping = shippingSelect ? shippingSelect.value : 'ALL';
        const status = statusSelect ? statusSelect.value : 'ALL';
        const resiStatus = resiSelect ? resiSelect.value : 'ALL';
        const claimStatus = claimSelect ? claimSelect.value : 'ALL';
        const dateType = dateSelect ? dateSelect.value : '';
        const sort = sortSelect ? sortSelect.value : 'date_desc';
        const limit = limitSelect ? parseInt(limitSelect.value) || 25 : 25;

        let url = `api/orders.php?action=list&page=${page}&limit=${limit}`;
        if (search) url += `&search=${encodeURIComponent(search)}`;
        if (platform && platform !== 'ALL') url += `&platform=${encodeURIComponent(platform)}`;
        if (shop && shop !== 'ALL') url += `&shop=${encodeURIComponent(shop)}`;
        if (shipping && shipping !== 'ALL') url += `&shipping=${encodeURIComponent(shipping)}`;
        if (status && status !== 'ALL') url += `&status=${encodeURIComponent(status)}`;
        if (resiStatus && resiStatus !== 'ALL') url += `&resi_status=${encodeURIComponent(resiStatus)}`;
        if (claimStatus && claimStatus !== 'ALL') url += `&claim_status=${encodeURIComponent(claimStatus)}`;
        if (dateType) url += `&date=${encodeURIComponent(dateType)}`;
        if (sort && sort !== 'date_desc') url += `&sort=${encodeURIComponent(sort)}`;

        if (dateType === 'custom') {
            const startDate = document.getElementById('orderStartDate')?.value || '';
            const endDate = document.getElementById('orderEndDate')?.value || '';
            if (startDate) url += `&start_date=${encodeURIComponent(startDate)}`;
            if (endDate) url += `&end_date=${encodeURIComponent(endDate)}`;
        }

        const res = await fetch(url);
        const data = await res.json();

        if (!data || !data.success) {
            tbody.innerHTML = `<tr><td colspan="8" class="text-center py-8 text-rose-500 font-semibold">${data.message || data.error || 'Gagal mengambil data pesanan'}</td></tr>`;
            return;
        }

        // Isi opsi filter dropdown dinamis jika data tersedia dari backend (bersih tanpa icon)
        if (data.shops) populateOrderFilterDropdown('orderShopFilter', data.shops, 'Semua Toko', shop);
        if (data.shipping_providers) populateOrderFilterDropdown('orderShippingFilter', data.shipping_providers, 'Semua Ekspedisi', shipping);
        if (data.statuses) populateOrderFilterDropdown('orderStatusFilter', data.statuses, 'Semua Status', status);

        renderOrdersTable(data.orders || [], data.pagination || {});
    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="8" class="text-center py-8 text-rose-500 font-semibold">Terjadi kesalahan koneksi: ${err.message}</td></tr>`;
    }
};

// 5. Render Tabel Data Orders
window.renderOrdersTable = function (orders, pagination) {
    const tbody = document.getElementById('ordersTableBody');
    const infoEl = document.getElementById('orderPaginationInfo');
    const controlsEl = document.getElementById('orderPaginationControls');
    if (!tbody) return;

    if (!orders || orders.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8" class="text-center py-12 text-slate-400">
                    <i class="fa-solid fa-cart-flatbed text-3xl text-slate-300 mb-2 block"></i>
                    <span class="font-semibold text-slate-600 block">Tidak ada pesanan yang sesuai</span>
                    <span class="text-xs text-slate-400 mt-0.5">Coba ubah kata kunci pencarian atau filter platform / periode.</span>
                </td>
            </tr>
        `;
        if (infoEl) infoEl.innerText = 'Menampilkan 0 dari 0 data';
        if (controlsEl) controlsEl.innerHTML = '';
        return;
    }

    let html = '';
    orders.forEach((o) => {
        const orderId = o.order_id || '-';
        const tracking = o.tracking_number || '-';
        const platform = (o.platform || 'OTHER').toUpperCase();
        const shop = o.shop_name || '-';
        const shipping = o.shipping_provider || '-';
        const claimFmt = o.total_claim_amount_fmt || ('Rp ' + Number(o.total_claim_amount || 0).toLocaleString('id-ID'));
        const totalPriceFmt = o.total_price_fmt || o.total_amount_fmt || ('Rp ' + Number(o.total_price || o.total_amount || o.package_price || 0).toLocaleString('id-ID'));
        const orderDate = o.order_date || '-';

        // Badge Platform Warna-warni
        let platBadge = `<span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-slate-100 text-slate-700">${platform}</span>`;
        if (platform.includes('SHOPEE')) {
            platBadge = `<span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-orange-100 text-orange-700 border border-orange-200"><i class="fa-solid fa-bag-shopping mr-1"></i>Shopee</span>`;
        } else if (platform.includes('TIKTOK')) {
            platBadge = `<span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-slate-900 text-white"><i class="fa-brands fa-tiktok mr-1"></i>TikTok</span>`;
        } else if (platform.includes('TOKOPEDIA')) {
            platBadge = `<span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-emerald-100 text-emerald-700 border border-emerald-200">Tokopedia</span>`;
        } else if (platform.includes('LAZADA')) {
            platBadge = `<span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-blue-100 text-blue-700 border border-blue-200">Lazada</span>`;
        }

        // Preview Ringkasan SKU Produk
        let skuSummary = '-';
        if (Array.isArray(o.items_detail) && o.items_detail.length > 0) {
            const first = o.items_detail[0];
            const name = first.product_name || first.sku || 'Item';
            const extra = o.items_detail.length > 1 ? ` <span class="text-[10px] font-bold text-blue-600 bg-blue-50 px-1.5 py-0.5 rounded ml-1">+${o.items_detail.length - 1} item</span>` : '';
            skuSummary = `<div class="truncate text-slate-800 font-medium" title="${escapeHtml(name)}">${escapeHtml(name)}</div><div class="text-[10px] text-slate-400 font-mono">SKU: ${escapeHtml(first.sku || '-')} (x${first.quantity || 1})${extra}</div>`;
        } else if (o.sku || o.product_name) {
            skuSummary = `<div class="truncate text-slate-800 font-medium">${escapeHtml(o.product_name || o.sku)}</div><div class="text-[10px] text-slate-400 font-mono">SKU: ${escapeHtml(o.sku || '-')}</div>`;
        }

        html += `
            <tr class="hover:bg-slate-50/80 transition border-b border-slate-100 text-xs">
                <td class="p-3">
                    <div class="flex items-center gap-1.5">
                        <button type="button" onclick="openOrderDetailModal('${escapeHtml(orderId)}')" class="font-mono font-bold text-blue-600 hover:text-blue-800 hover:underline text-left">
                            ${escapeHtml(orderId)}
                        </button>
                        <button type="button" onclick="copyOrderText('${escapeHtml(orderId)}', 'No. Pesanan')" class="text-slate-400 hover:text-slate-600 p-0.5" title="Salin No. Pesanan">
                            <i class="fa-regular fa-copy text-[11px]"></i>
                        </button>
                    </div>
                    <span class="text-[10px] text-slate-400 block">${o.customer_name ? escapeHtml(o.customer_name) : 'Customer'}</span>
                </td>
                <td class="p-3">
                    ${tracking !== '-' ? `
                        <div class="flex items-center gap-1.5">
                            <span class="font-mono font-bold text-slate-800 bg-slate-100 px-2 py-0.5 rounded">${escapeHtml(tracking)}</span>
                            <button type="button" onclick="copyOrderText('${escapeHtml(tracking)}', 'No. Resi')" class="text-slate-400 hover:text-slate-600 p-0.5" title="Salin Resi">
                                <i class="fa-regular fa-copy text-[11px]"></i>
                            </button>
                        </div>
                    ` : `<span class="text-slate-400 font-mono text-[11px]">- Belum ada resi -</span>`}
                </td>
                <td class="p-3">
                    <div class="space-y-0.5">
                        <div class="flex items-center gap-1">${platBadge} <span class="font-bold text-slate-700 truncate max-w-[130px]">${escapeHtml(shop)}</span></div>
                        <span class="text-[10px] text-slate-400 block"><i class="fa-solid fa-truck-fast text-[9px] mr-1"></i>${escapeHtml(shipping)}</span>
                    </div>
                </td>
                <td class="p-3 min-w-[200px] max-w-[320px]">
                    ${skuSummary}
                </td>
                <td class="p-3 text-right font-mono font-bold text-slate-800 text-xs">
                    ${totalPriceFmt}
                </td>
                <td class="p-3 text-right">
                    <span class="font-mono font-black text-emerald-600 text-xs">${claimFmt}</span>
                </td>
                <td class="p-3 whitespace-nowrap text-slate-500 text-[11px]">
                    ${orderDate}
                </td>
                <td class="p-3 text-center whitespace-nowrap">
                    <div class="flex items-center justify-center gap-1.5">
                        <button type="button" onclick="openOrderDetailModal('${escapeHtml(orderId)}')" class="px-2.5 py-1.5 bg-blue-50 hover:bg-blue-100 text-blue-700 font-bold rounded-lg text-[11px] transition flex items-center gap-1" title="Lihat Detail SKU & Biaya">
                            <i class="fa-solid fa-eye"></i> Detail
                        </button>
                        ${tracking !== '-' ? `
                            <button type="button" onclick="viewOrderInClaims('${escapeHtml(tracking)}')" class="px-2 py-1.5 bg-amber-50 hover:bg-amber-100 text-amber-700 font-bold rounded-lg text-[11px] transition flex items-center gap-1" title="Cek di Pusat Klaim">
                                <i class="fa-solid fa-shield-halved"></i> Klaim
                            </button>
                        ` : ''}
                    </div>
                </td>
            </tr>
        `;
    });

    tbody.innerHTML = html;

    // Render Pagination Bar
    const total = pagination.total || 0;
    const page = pagination.page || 1;
    const limit = pagination.limit || 25;
    const totalPages = pagination.total_pages || 1;
    const startIdx = total === 0 ? 0 : (page - 1) * limit + 1;
    const endIdx = Math.min(page * limit, total);

    if (infoEl) {
        infoEl.innerText = `Menampilkan ${startIdx.toLocaleString('id-ID')} - ${endIdx.toLocaleString('id-ID')} dari ${total.toLocaleString('id-ID')} pesanan`;
    }

    if (controlsEl) {
        let pageBtns = '';
        pageBtns += `
            <button onclick="loadOrdersTable(${page - 1})" ${page <= 1 ? 'disabled' : ''} class="px-2.5 py-1.5 rounded-lg border border-slate-300 text-slate-600 hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed transition text-xs font-bold">
                <i class="fa-solid fa-chevron-left"></i>
            </button>
        `;

        // Range halaman
        let startP = Math.max(1, page - 2);
        let endP = Math.min(totalPages, page + 2);
        if (startP > 1) {
            pageBtns += `<button onclick="loadOrdersTable(1)" class="px-2.5 py-1 rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-100 transition text-xs font-semibold">1</button>`;
            if (startP > 2) pageBtns += `<span class="px-1 text-slate-400">...</span>`;
        }

        for (let p = startP; p <= endP; p++) {
            if (p === page) {
                pageBtns += `<button class="px-3 py-1 rounded-lg bg-blue-600 text-white font-bold text-xs shadow-xs">${p}</button>`;
            } else {
                pageBtns += `<button onclick="loadOrdersTable(${p})" class="px-2.5 py-1 rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-100 transition text-xs font-semibold">${p}</button>`;
            }
        }

        if (endP < totalPages) {
            if (endP < totalPages - 1) pageBtns += `<span class="px-1 text-slate-400">...</span>`;
            pageBtns += `<button onclick="loadOrdersTable(${totalPages})" class="px-2.5 py-1 rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-100 transition text-xs font-semibold">${totalPages}</button>`;
        }

        pageBtns += `
            <button onclick="loadOrdersTable(${page + 1})" ${page >= totalPages ? 'disabled' : ''} class="px-2.5 py-1.5 rounded-lg border border-slate-300 text-slate-600 hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed transition text-xs font-bold">
                <i class="fa-solid fa-chevron-right"></i>
            </button>
        `;

        controlsEl.innerHTML = pageBtns;
    }
};

// 6. Modal Detail Order & SKU Breakdown
window.openOrderDetailModal = async function (orderId) {
    const modal = document.getElementById('modalOrderDetail');
    if (!modal) return;

    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';

    // Reset isi modal
    document.getElementById('dtlOrderId').innerText = orderId;
    document.getElementById('dtlTrackingNo').innerText = 'Memuat...';
    document.getElementById('dtlOrderDate').innerText = '-';
    document.getElementById('dtlPlatform').innerText = '-';
    document.getElementById('dtlShop').innerText = '-';
    document.getElementById('dtlShipping').innerText = '-';
    document.getElementById('dtlCustomer').innerText = '-';
    document.getElementById('dtlSkuTableBody').innerHTML = `<tr><td colspan="5" class="p-4 text-center text-slate-400"><i class="fa-solid fa-spinner fa-spin mr-1"></i> Memuat detail produk...</td></tr>`;

    try {
        const res = await fetch(`api/orders.php?action=detail&order_id=${encodeURIComponent(orderId)}`);
        const data = await res.json();

        if (!data || !data.success || !data.order) {
            throw new Error(data.message || 'Data order tidak ditemukan');
        }

        const o = data.order;
        document.getElementById('dtlOrderId').innerText = o.order_id || '-';
        document.getElementById('dtlTrackingNo').innerText = o.tracking_number || '- Belum Ada -';
        document.getElementById('dtlOrderDate').innerText = o.order_date || '-';
        document.getElementById('dtlPlatform').innerText = o.platform || '-';
        document.getElementById('dtlShop').innerText = o.shop_name || '-';
        document.getElementById('dtlShipping').innerText = o.shipping_provider || '-';
        document.getElementById('dtlCustomer').innerText = (o.customer_name || '-') + (o.customer_phone ? ` (${o.customer_phone})` : '');

        // Render Finansial
        document.getElementById('dtlOrigPrice').innerText = o.original_price_fmt || ('Rp ' + Number(o.original_price || 0).toLocaleString('id-ID'));
        document.getElementById('dtlShipFee').innerText = o.shipping_fee_fmt || ('Rp ' + Number(o.shipping_fee || 0).toLocaleString('id-ID'));
        document.getElementById('dtlDiscount').innerText = '- ' + (o.total_discount_fmt || ('Rp ' + Number(o.total_discount || 0).toLocaleString('id-ID')));
        document.getElementById('dtlTotalClaim').innerText = o.total_claim_amount_fmt || ('Rp ' + Number(o.total_claim_amount || 0).toLocaleString('id-ID'));

        // Render SKU Items Table
        const skuTbody = document.getElementById('dtlSkuTableBody');
        const items = o.items_detail || [];

        if (items.length === 0) {
            skuTbody.innerHTML = `<tr><td colspan="5" class="p-4 text-center text-slate-400">Tidak ada detail varian item terdaftar</td></tr>`;
        } else {
            let skuHtml = '';
            items.forEach((it) => {
                const subtotal = Number(it.subtotal || ((it.unit_price || 0) * (it.quantity || 1)));
                skuHtml += `
                    <tr class="hover:bg-slate-50 transition border-b border-slate-100">
                        <td class="p-2.5">
                            <div class="font-bold text-slate-800">${escapeHtml(it.product_name || '-')}</div>
                            <div class="font-mono text-[10px] text-slate-500">SKU: ${escapeHtml(it.sku || '-')} ${it.barcode ? `&bull; Barcode: ${escapeHtml(it.barcode)}` : ''}</div>
                        </td>
                        <td class="p-2.5 text-center font-bold text-slate-700">${it.quantity || 1}</td>
                        <td class="p-2.5 text-right font-mono text-slate-700">Rp ${Number(it.unit_price || 0).toLocaleString('id-ID')}</td>
                        <td class="p-2.5 text-right font-mono text-rose-600">- Rp ${Number(it.discount || 0).toLocaleString('id-ID')}</td>
                        <td class="p-2.5 text-right font-mono font-bold text-emerald-600">Rp ${subtotal.toLocaleString('id-ID')}</td>
                    </tr>
                `;
            });
            skuTbody.innerHTML = skuHtml;
        }

        // Setup Tombol Cek Bukti di Pusat Klaim
        const btnClaim = document.getElementById('btnDtlCheckClaimDossier');
        if (btnClaim) {
            const resiToFind = o.tracking_number || o.order_id;
            btnClaim.onclick = function () {
                viewOrderInClaims(resiToFind);
            };
        }
    } catch (err) {
        showToast('error', err.message, 'Gagal Memuat Detail');
    }
};

window.closeOrderDetailModal = function () {
    const modal = document.getElementById('modalOrderDetail');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = '';
    }
};

// 7. Buka Berkas Klaim di Halaman Baru
window.viewOrderInClaims = function (identifier) {
    if (!identifier || identifier === '-') return;
    closeOrderDetailModal();
    window.open(`claim_dossier.php?q=${encodeURIComponent(identifier)}`, '_blank');
};

// 8. Salin Teks ke Clipboard
window.copyOrderText = function (text, label = 'Teks') {
    if (!text || text === '-') return;
    navigator.clipboard.writeText(text).then(() => {
        showToast('info', `${label} "${text}" berhasil disalin ke clipboard!`, 'Disalin');
    }).catch(() => {
        // Fallback jika permission blocked
        const input = document.createElement('input');
        input.value = text;
        document.body.appendChild(input);
        input.select();
        document.execCommand('copy');
        document.body.removeChild(input);
        showToast('info', `${label} disalin!`, 'Disalin');
    });
};

// 9. Ekspor Data Orders ke Excel Native (.xlsx via SheetJS)
window.exportOrdersExcel = async function () {
    showGlobalLoading("Mengekspor Excel...", "Mengambil seluruh data pesanan tersinkron dari server...");
    try {
        const platform = document.getElementById('orderPlatformFilter')?.value || 'ALL';
        const shop = document.getElementById('orderShopFilter')?.value || 'ALL';
        const shipping = document.getElementById('orderShippingFilter')?.value || 'ALL';
        const status = document.getElementById('orderStatusFilter')?.value || 'ALL';
        const resiStatus = document.getElementById('orderResiFilter')?.value || 'ALL';
        const claimStatus = document.getElementById('orderClaimFilter')?.value || 'ALL';
        const dateType = document.getElementById('orderDateFilter')?.value || '';
        const sort = document.getElementById('orderSortSelect')?.value || 'date_desc';
        const search = document.getElementById('orderSearchInput')?.value.trim() || '';

        let url = `api/orders.php?action=list&page=1&limit=10000`;
        if (search) url += `&search=${encodeURIComponent(search)}`;
        if (platform && platform !== 'ALL') url += `&platform=${encodeURIComponent(platform)}`;
        if (shop && shop !== 'ALL') url += `&shop=${encodeURIComponent(shop)}`;
        if (shipping && shipping !== 'ALL') url += `&shipping=${encodeURIComponent(shipping)}`;
        if (status && status !== 'ALL') url += `&status=${encodeURIComponent(status)}`;
        if (resiStatus && resiStatus !== 'ALL') url += `&resi_status=${encodeURIComponent(resiStatus)}`;
        if (claimStatus && claimStatus !== 'ALL') url += `&claim_status=${encodeURIComponent(claimStatus)}`;
        if (dateType) url += `&date=${encodeURIComponent(dateType)}`;
        if (sort && sort !== 'date_desc') url += `&sort=${encodeURIComponent(sort)}`;

        if (dateType === 'custom') {
            const start = document.getElementById('orderStartDate')?.value || '';
            const end = document.getElementById('orderEndDate')?.value || '';
            if (start) url += `&start_date=${encodeURIComponent(start)}`;
            if (end) url += `&end_date=${encodeURIComponent(end)}`;
        }

        const res = await fetch(url);
        const json = await res.json();
        hideGlobalLoading();

        if (!json || !json.success || !json.orders || json.orders.length === 0) {
            showToast('warning', 'Tidak ada data pesanan yang cocok untuk diekspor.', 'Data Kosong');
            return;
        }

        const rows = json.orders.map((o, idx) => {
            let skuDetail = '';
            if (Array.isArray(o.items_detail) && o.items_detail.length > 0) {
                skuDetail = o.items_detail.map(i => `${i.product_name || i.sku || 'Item'} (SKU: ${i.sku || '-'}, Qty: ${i.quantity || 1})`).join('; ');
            } else {
                skuDetail = o.product_name || o.sku || '-';
            }

            return {
                "No": idx + 1,
                "No. Pesanan": o.order_id,
                "No. Resi": o.tracking_number || '-',
                "Platform": o.platform || '-',
                "Nama Toko": o.shop_name || '-',
                "Jasa Ekspedisi": o.shipping_provider || '-',
                "Nama Customer": o.customer_name || '-',
                "Rincian Produk SKU": skuDetail,
                "Total Qty Item": o.total_items || 1,
                "Harga Asli Produk (Rp)": Number(o.original_price || 0),
                "Ongkir Ekspedisi (Rp)": Number(o.shipping_fee || 0),
                "Total Diskon (Rp)": Number(o.total_discount || 0),
                "Total Nilai Klaim (Rp)": Number(o.total_claim_amount || 0),
                "Tanggal Order": o.order_date || '-'
            };
        });

        if (typeof XLSX === 'undefined') {
            throw new Error('Pustaka SheetJS XLSX belum dimuat di halaman');
        }

        const ws = XLSX.utils.json_to_sheet(rows);
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, "Orders_OCS");

        const todayStr = new Date().toISOString().slice(0, 10);
        const fileName = `Data_Orders_OCS_${todayStr}.xlsx`;
        XLSX.writeFile(wb, fileName);

        showToast('success', `Berhasil mengekspor ${rows.length} data order ke file ${fileName}!`, 'Export Berhasil');
    } catch (err) {
        hideGlobalLoading();
        showToast('error', 'Gagal mengekspor file Excel: ' + err.message, 'Gagal');
    }
};

// Helper HTML escape
function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}



