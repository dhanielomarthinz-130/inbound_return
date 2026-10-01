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

// State Management & Instances
let ratioChartInstance = null;
let currentTab = 'dashboard';
let cachedProducts = [];
let activeInboundDateFilter = '';
let activeDashboardDateFilter = '';
let activeDateFilter = ''; // alias mundur untuk kompatibilitas
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
window.switchTab = function(tabName, updateUrl = true) {
    currentTab = tabName;

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
    if (tabName === 'maintenance') loadMaintenanceStatus();

    // Sinkronisasikan URL browser
    if (updateUrl) {
        updateBrowserUrl(true);
    }
};

// 1. Load Metrics KPI (Refresh di backend via cache atau query)
async function loadMetrics(forceRefresh = false) {
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

        document.getElementById('kpiTotalInvoice').innerText = (data.total_invoices ?? 0).toLocaleString('id-ID');
        document.getElementById('kpiTotalItems').innerText   = (data.total_items    ?? 0).toLocaleString('id-ID');
        document.getElementById('kpiTotalGood').innerText    = (data.total_good     ?? 0).toLocaleString('id-ID');
        document.getElementById('kpiTotalDamaged').innerText = (data.total_damaged  ?? 0).toLocaleString('id-ID');

        renderRatioChart(data.total_good || 0, data.total_damaged || 0);
        renderTrendChart(data.trend_7days || []);
        renderDashExpeditionTable(data.by_expedition || []);
        renderDashConditionBreakdown(data.by_condition || {});
    } catch (err) {
        console.error("Gagal memuat metrics:", err);
    }
}

let trendChartInstance = null;

function renderRatioChart(good, damaged) {
    const canvas = document.getElementById('ratioChart');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    if (ratioChartInstance) ratioChartInstance.destroy();
    const total = good + damaged;
    const dataVals = total === 0 ? [1] : [good, damaged];
    const bgColors = total === 0 ? ['#e2e8f0'] : ['#10b981', '#f43f5e'];
    const lbls     = total === 0 ? ['Tidak ada data'] : ['Good / Baik', 'Rusak / Defect'];
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

function renderDashExpeditionTable(list) {
    const tbody = document.getElementById('dashExpeditionTableBody');
    if (!tbody) return;
    tbody.innerHTML = '';
    if (!list || list.length === 0) { tbody.innerHTML = `<tr><td colspan="5" class="p-6 text-center text-slate-400 text-xs">Tidak ada data.</td></tr>`; return; }
    const totalAll = list.reduce((s, r) => s + parseInt(r.total_qty || 0), 0) || 1;
    const colors = ['indigo','blue','violet','cyan','teal','emerald','amber'];
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
    GOOD:    { dot: 'bg-emerald-500', badge: 'bg-emerald-50 text-emerald-700 border-emerald-200' },
    DAMAGED: { dot: 'bg-red-500',     badge: 'bg-red-50 text-red-700 border-red-200' },
    MISSING: { dot: 'bg-amber-500',   badge: 'bg-amber-50 text-amber-700 border-amber-200' },
    EXPIRED: { dot: 'bg-orange-500',  badge: 'bg-orange-50 text-orange-700 border-orange-200' },
    WRONG:   { dot: 'bg-purple-500',  badge: 'bg-purple-50 text-purple-700 border-purple-200' },
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
            altInput: true,
            altFormat: 'j M Y',
            altInputClass: 'bg-slate-50 hover:bg-white focus:bg-white border border-slate-300 rounded-xl pl-8 pr-8 py-2 text-xs font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 shadow-2xs transition w-56 sm:w-64 cursor-pointer',
            locale: (flatpickr.l10ns && flatpickr.l10ns.id) ? flatpickr.l10ns.id : 'default',
            allowInput: false,
            onClose: function(selectedDates, dateStr, instance) {
                activeInboundDateFilter = getDateStrFromInstance(instance);
                updateInboundDateUI();
                updateBrowserUrl(false);
                loadTransactions();
            }
        });
    }

    // 2. Dashboard (Hanya mengontrol metrik Dashboard - Bebas dari Inbound)
    const dbEl = document.getElementById('dashboardFilterDate');
    if (dbEl) {
        if (flatpickrDashboardInstance) flatpickrDashboardInstance.destroy();
        flatpickrDashboardInstance = flatpickr(dbEl, {
            mode: 'range',
            dateFormat: 'Y-m-d',
            altInput: true,
            altFormat: 'j M Y',
            altInputClass: 'bg-slate-50 hover:bg-white focus:bg-white border border-slate-300 rounded-xl pl-8 pr-8 py-2 text-xs font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 shadow-2xs transition w-full sm:w-64 cursor-pointer',
            locale: (flatpickr.l10ns && flatpickr.l10ns.id) ? flatpickr.l10ns.id : 'default',
            allowInput: false,
            onClose: function(selectedDates, dateStr, instance) {
                activeDashboardDateFilter = getDateStrFromInstance(instance);
                updateDashboardDateUI();
                updateBrowserUrl(false);
                loadMetrics();
            }
        });
    }
}

// Reset filter tanggal Inbound Unboxing
window.clearDateFilter = function() {
    if (flatpickrTransactionsInstance) flatpickrTransactionsInstance.clear();
    activeInboundDateFilter = '';
    updateInboundDateUI();
    updateBrowserUrl(false);
    loadTransactions();
};

// Terapkan filter tanggal Dashboard
window.applyDashboardDateFilter = function() {
    const val = getDateStrFromInstance(flatpickrDashboardInstance)
             || document.getElementById('dashboardFilterDate')?.value?.trim()
             || '';
    activeDashboardDateFilter = val;
    updateDashboardDateUI();
    updateBrowserUrl(false);
    loadMetrics();
};

// Reset filter tanggal Dashboard
window.clearDashboardDateFilter = function() {
    if (flatpickrDashboardInstance) flatpickrDashboardInstance.clear();
    activeDashboardDateFilter = '';
    updateDashboardDateUI();
    updateBrowserUrl(false);
    loadMetrics();
};

// Refresh Metrik Dashboard di Backend
window.refreshDashboardMetrics = async function() {
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

    try {
        const res = await fetch(url);
        const rows = await res.json();
        cachedTransactions = Array.isArray(rows) ? rows : [];

        // Isi opsi filter dropdown ekspedisi & operator secara dinamis
        populateTransactionFilterDropdowns(cachedTransactions);
        
        // Render di tabel transaksi penuh (Tab Inbound Unboxing)
        const tbody = document.getElementById('transactionsTableBody');
        if (tbody) {
            tbody.innerHTML = '';
            if (!rows || rows.length === 0) {
                tbody.innerHTML = `<tr><td colspan="12" class="text-center py-8 text-slate-400">Tidak ada riwayat transaksi unboxing ditemukan.</td></tr>`;
            } else {
                rows.forEach(r => tbody.appendChild(createTransactionRow(r, false)));
            }
        }
    } catch (err) {
        console.error("Gagal load transaksi:", err);
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

    // Type / Kondisi (GOOD vs RUSAK)
    const isGood = (r.condition_type === 'GOOD');
    const typeBadge = isGood ?
        `<span class="bg-emerald-100 text-emerald-800 border border-emerald-200 px-2.5 py-1 rounded-xl font-bold text-[10px] inline-flex items-center gap-1 shadow-2xs">
            <i class="fa-solid fa-circle-check text-emerald-600"></i> GOOD
         </span>` :
        `<span class="bg-rose-100 text-rose-800 border border-rose-200 px-2.5 py-1 rounded-xl font-bold text-[10px] inline-flex items-center gap-1 shadow-2xs">
            <i class="fa-solid fa-triangle-exclamation text-rose-600"></i> RUSAK${r.raw_type && r.raw_type !== 'RUSAK' ? ` (${r.raw_type})` : ''}
         </span>`;

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
        <td class="p-3 font-mono font-bold text-indigo-700 whitespace-nowrap">${r.invoice_number}</td>
        <td class="p-3 whitespace-nowrap">${expBadge}</td>
        <td class="p-3 font-semibold text-slate-700 whitespace-nowrap">${r.operator_name}</td>
        <td class="p-3 whitespace-nowrap">${skuBadge}</td>
        <td class="p-3 min-w-[200px] max-w-[340px]">
            <div class="text-xs font-semibold text-slate-800 leading-snug whitespace-normal break-words">${r.product_name}</div>
            <div class="text-[10px] font-mono text-slate-400 mt-0.5">${r.barcode || ''}</div>
        </td>
        <td class="p-3 whitespace-nowrap">${batchDisplay}</td>
        <td class="p-3 whitespace-nowrap">${expDisplay}</td>
        <td class="p-3 text-center whitespace-nowrap">
            <span class="font-black text-slate-800 text-xs px-2.5 py-1 bg-slate-100 border border-slate-200 rounded-lg inline-block text-center min-w-[28px]">${r.qty || 1}</span>
        </td>
        <td class="p-3 text-center whitespace-nowrap">${typeBadge}</td>
        <td class="p-3 text-center whitespace-nowrap">${videoActionsHtml}</td>
        <td class="p-3 text-center whitespace-nowrap">${detailBtn}</td>
    `;
    return tr;
}

// 2b. Putar Video Unboxing Langsung (Play Video Action)
window.playTransactionVideo = async function(id) {
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

// 2b. View Details & Video Player Modal
window.viewDetails = async function(id) {
    const r = cachedTransactions.find(t => (t.session_id && t.session_id == id) || t.id == id);
    if (!r) return;

    const modal = document.getElementById('transactionDetailModal');
    if (!modal) return;

    const targetSessionId = r.session_id || r.id;

    document.getElementById('modalDetailInvoice').innerText = r.invoice_number;
    document.getElementById('modalDetailExpedition').innerText = r.expedition || 'Reguler';
    document.getElementById('modalDetailMeta').innerText = `Operator: ${r.operator_name} • ${new Date(r.created_at).toLocaleString('id-ID')}`;
    document.getElementById('modalTotalUnit').innerText = r.qty || 1;
    document.getElementById('modalTotalGood').innerText = (r.condition_type === 'GOOD') ? (r.qty || 1) : 0;
    document.getElementById('modalTotalDamaged').innerText = (r.condition_type === 'RUSAK') ? (r.qty || 1) : 0;
    document.getElementById('modalNotes').innerText = r.notes || 'Tidak ada catatan.';

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
                <td class="p-2.5 text-slate-500 font-mono text-[11px] whitespace-nowrap">${it.batch_no || '-'} / ${formatExpDate(it.exp_date)}</td>
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
window.exportDashboardExcel = async function() {
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
window.exportInboundUnboxingExcel = async function() {
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
window.exportProductsExcel = async function() {
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
window.exportExpeditionsExcel = async function() {
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
    const sub = document.getElementById('expeditionModalSubtitle');
    if (sub) sub.innerText = 'Simpan data armada/kurir ke database MySQL';
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

window.deleteExpedition = async function(id, name) {
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
window.refreshAllData = function() {
    const icon = document.getElementById('refreshIcon');
    if (icon) icon.classList.add('fa-spin');

    const promises = [loadMetrics(), loadTransactions(), loadProducts(), loadExpeditions(), loadConditions(), loadUsers()];
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
            window.location.href = 'login';
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

window.filterUserTable = function() {
    const q = (document.getElementById('filterUserSearch')?.value || '').toLowerCase().trim();
    const filtered = cachedUsers.filter(u => 
        u.username.toLowerCase().includes(q) || 
        u.name.toLowerCase().includes(q) ||
        u.role.toLowerCase().includes(q) ||
        (u.pin && u.pin.toLowerCase().includes(q))
    );
    renderUsersTable(filtered);
};

window.openAddUserModal = function() {
    document.getElementById('userModalTitle').innerText = 'Tambah Pengguna Baru';
    const sub = document.getElementById('userModalSubtitle');
    if (sub) sub.innerText = 'Daftarkan akun operator atau admin';
    document.getElementById('formUser').reset();
    document.getElementById('userId').value = '';
    document.getElementById('userPasswordLabel').innerText = 'Password *';
    document.getElementById('userPassword').required = true;
    document.getElementById('userPasswordHelp').innerText = 'Wajib diisi saat membuat akun baru.';
    document.getElementById('userPin').value = '123456';
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
    const sub = document.getElementById('userModalSubtitle');
    if (sub) sub.innerText = `Perbarui akun ${u.name}`;
    document.getElementById('userId').value = u.id;
    document.getElementById('userUsername').value = u.username;
    document.getElementById('userName').value = u.name;
    document.getElementById('userRole').value = u.role;
    document.getElementById('userStatus').value = u.status;
    document.getElementById('userPin').value = u.pin || '123456';
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

window.deleteUser = async function(id, name) {
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
            showToast('success', data.message, 'Mode Pemeliharaan Diperbarui');
        } else {
            showToast('error', data.error || 'Terjadi kesalahan', 'Gagal Mengubah Mode');
        }
    } catch (err) {
        hideGlobalLoading();
        showToast('error', 'Gagal koneksi ke server: ' + err.message, 'Koneksi Terputus');
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
    initFromUrlParams();
    refreshAllData();

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
    red:     { bg: 'bg-red-50',     text: 'text-red-700',     border: 'border-red-200'     },
    amber:   { bg: 'bg-amber-50',   text: 'text-amber-700',   border: 'border-amber-200'   },
    orange:  { bg: 'bg-orange-50',  text: 'text-orange-700',  border: 'border-orange-200'  },
    purple:  { bg: 'bg-purple-50',  text: 'text-purple-700',  border: 'border-purple-200'  },
    blue:    { bg: 'bg-blue-50',    text: 'text-blue-700',    border: 'border-blue-200'    },
    slate:   { bg: 'bg-slate-100',  text: 'text-slate-700',   border: 'border-slate-200'   },
};

async function loadConditions() {
    try {
        const res = await fetch('api/conditions.php');
        allConditions = await res.json();
        renderConditionsTable(allConditions);
    } catch (e) {
        const tbody = document.getElementById('fullConditionsTableBody');
        if (tbody) tbody.innerHTML = `<tr><td colspan="6" class="text-center py-8 text-red-400">Gagal memuat data kondisi: ${e.message}</td></tr>`;
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
                    <button onclick="deleteCondition(${c.id}, '${c.code.replace(/'/g,"\\'")}', '${c.name.replace(/'/g,"\\'")}' )"
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
    const id   = document.getElementById('conditionId').value;
    const code = document.getElementById('conditionCode').value.trim().toUpperCase();
    const name = document.getElementById('conditionName').value.trim();
    const desc = document.getElementById('conditionDesc').value.trim();
    const color= document.getElementById('conditionColor').value;
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
let activeReceivingDateFilter = '';
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
        altInput: true,
        altFormat: "j F Y",
        locale: "id",
        maxDate: "today",
        onChange: function(selectedDates) {
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
        onClose: function(selectedDates) {
            if (selectedDates.length === 1) {
                loadReceivingData();
            }
        }
    });

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

window.clearReceivingDateFilter = function() {
    if (flatpickrReceivingInstance) {
        flatpickrReceivingInstance.clear();
    }
    activeReceivingDateFilter = '';
    const btnClear = document.getElementById('btnClearReceivingDate');
    if (btnClear) btnClear.classList.add('hidden');
    loadReceivingData();
};

// Muat data receiving dari API
window.loadReceivingData = async function(forceRefresh = false) {
    initReceivingDatepicker();

    const tbody = document.getElementById('receivingTableBody');
    if (tbody) {
        tbody.innerHTML = `<tr><td colspan="8" class="text-center py-12 text-slate-400"><i class="fa-solid fa-spinner fa-spin text-2xl text-emerald-500 mb-2 block"></i>Memuat data receiving inbound...</td></tr>`;
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
                tbody.innerHTML = `<tr><td colspan="8" class="text-center py-10 text-amber-600 font-bold"><i class="fa-solid fa-triangle-exclamation mr-1.5 text-lg"></i>Sesi login Anda telah berakhir. Silakan <a href="login" class="underline text-indigo-600 font-black">Login Kembali</a>.</td></tr>`;
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
            if (tbody) tbody.innerHTML = `<tr><td colspan="8" class="text-center py-10 text-rose-500 font-semibold"><i class="fa-solid fa-circle-exclamation mr-1.5"></i>Gagal memuat data: ${escapeHtml(errMsg)}</td></tr>`;
        }
    } catch (e) {
        console.error('Error loadReceivingData:', e);
        if (tbody) {
            tbody.innerHTML = `<tr><td colspan="8" class="text-center py-10 text-rose-500 font-semibold"><i class="fa-solid fa-circle-exclamation mr-1.5 text-lg block mb-1"></i>Terjadi kesalahan saat memuat data receiving.<br><span class="text-xs text-slate-500 font-normal mt-1 block">${escapeHtml(e.message || 'Kesalahan koneksi')}</span></td></tr>`;
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
                <td colspan="8" class="text-center py-12 text-slate-400">
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
                <td class="py-3 px-4 font-mono font-bold text-slate-900">${escapeHtml(item.receipt_number)}</td>
                <td class="py-3 px-4">
                    <span class="bg-emerald-50 text-emerald-700 font-bold px-2.5 py-1 rounded-lg border border-emerald-200 text-xs inline-block">
                        ${escapeHtml(item.expedition)}
                    </span>
                </td>
                <td class="py-3 px-4 font-medium text-slate-700">${escapeHtml(item.courier_name || '-')}</td>
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
                        <button onclick="viewReceivingPackagesList(${item.id})" class="bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-bold px-2.5 py-1.5 rounded-xl border border-indigo-200 text-xs flex items-center gap-1 transition" title="Lihat Daftar Resi Paket">
                            <i class="fa-solid fa-list-check"></i>
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
window.viewReceivingReceipt = async function(id) {
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
            document.getElementById('adminSlipOperator').innerText = r.operator_name || '-';
            document.getElementById('adminSlipTotalPackages').innerText = r.total_packages || 0;
            document.getElementById('adminSlipSignOperator').innerText = r.operator_name || 'Gudang';

            const listEl = document.getElementById('adminSlipPackageList');
            let listHtml = '';
            (data.packages || []).forEach((bar, i) => {
                listHtml += `<div class="flex justify-between border-b border-slate-100 py-1"><span>${i + 1}. ${escapeHtml(bar.package_barcode)}</span><span class="text-[9px] text-emerald-600 font-bold">TERIMA OK</span></div>`;
            });
            listEl.innerHTML = listHtml || '<div class="text-slate-400 text-center py-2">Tidak ada rincian resi.</div>';

            document.getElementById('modalReceivingReceipt').classList.remove('hidden');
        } else {
            showToast('error', data.error || 'Data bukti serah terima tidak ditemukan', 'Gagal Memuat');
        }
    } catch (e) {
        hideGlobalLoading();
        showToast('error', 'Terjadi kesalahan: ' + e.message, 'Gagal Memuat');
    }
};

window.closeReceivingReceiptModal = function() {
    const modal = document.getElementById('modalReceivingReceipt');
    if (modal) modal.classList.add('hidden');
};

// Buka Modal Daftar Resi Paket Lengkap
window.viewReceivingPackagesList = async function(id) {
    showGlobalLoading("Memuat Daftar Resi...", "Mengambil nomor resi...");
    try {
        const res = await fetch(`api/reception.php?action=detail&id=${id}`);
        const data = await res.json();
        hideGlobalLoading();

        if (data && data.success && data.reception) {
            const r = data.reception;
            document.getElementById('pkgModalTitle').innerText = `Daftar Resi ${r.receipt_number}`;
            document.getElementById('pkgModalTotal').innerText = data.packages ? data.packages.length : 0;

            window._currentReceivingPackages = (data.packages || []).map(p => p.package_barcode);

            const listEl = document.getElementById('pkgModalList');
            let html = '';
            (data.packages || []).forEach((p, idx) => {
                html += `
                    <div class="py-1.5 px-2 flex justify-between items-center hover:bg-slate-100/80 transition">
                        <span>${idx + 1}. <b class="text-slate-800">${escapeHtml(p.package_barcode)}</b></span>
                        <span class="text-[10px] text-slate-400">${(p.scanned_at || '').split(' ')[1] || ''}</span>
                    </div>
                `;
            });
            listEl.innerHTML = html || '<div class="text-slate-400 text-center py-4">Belum ada barcode paket.</div>';
            document.getElementById('modalReceivingPackages').classList.remove('hidden');
        } else {
            showToast('error', data.error || 'Gagal memuat daftar resi', 'Gagal');
        }
    } catch (e) {
        hideGlobalLoading();
        showToast('error', e.message, 'Gagal');
    }
};

window.closeReceivingPackagesModal = function() {
    const m = document.getElementById('modalReceivingPackages');
    if (m) m.classList.add('hidden');
};

window.copyAllReceivingBarcodes = function() {
    if (window._currentReceivingPackages && window._currentReceivingPackages.length) {
        navigator.clipboard.writeText(window._currentReceivingPackages.join('\n')).then(() => {
            showToast('success', `${window._currentReceivingPackages.length} nomor resi berhasil disalin ke clipboard!`, 'Berhasil Disalin');
        }).catch(() => {
            showToast('info', 'Gagal menyalin otomatis', 'Info');
        });
    }
};

// Hapus Data Penerimaan
window.deleteReceivingRecord = async function(id, receiptNumber) {
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
window.exportReceivingExcel = function() {
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

window.executeClaimLookup = async function(e) {
    if (e && e.preventDefault) e.preventDefault();

    const input = document.getElementById('claimSearchInput');
    const btn = document.getElementById('btnClaimSearch');
    const query = input ? input.value.trim() : '';

    if (!query) {
        showToast('warning', 'Masukkan nomor resi atau Order ID terlebih dahulu', 'Input Kosong');
        return;
    }

    const originalBtnHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Mencari...';

    try {
        const [ocsResult, nasResult] = await Promise.allSettled([
            fetch(`api/ocs_lookup.php?q=${encodeURIComponent(query)}`).then(r => r.json()),
            fetch(`api/nas_video.php?action=search&q=${encodeURIComponent(query)}`).then(r => r.json())
        ]);

        const data = ocsResult.status === 'fulfilled' ? ocsResult.value : null;

        if (!data || !data.success) {
            showToast('error', data?.message || 'Gagal mencari data bukti klaim di OCS/Database', 'Pencarian Gagal');
            return;
        }

        if (nasResult.status === 'fulfilled' && nasResult.value) {
            data.packing_video = nasResult.value;
        }

        window.currentClaimDossier = data;
        renderClaimDossier(data);
        showToast('success', 'Data bukti berhasil ditemukan & diverifikasi!', 'Dossier Ditemukan');
    } catch (err) {
        showToast('error', 'Terjadi kesalahan: ' + err.message, 'Gagal');
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalBtnHtml;
    }
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
        galleryEl.innerHTML = photos.map((p, idx) => `
            <div class="relative group rounded-xl overflow-hidden border border-slate-200 bg-slate-100 aspect-video cursor-pointer shadow-2xs hover:shadow-md transition" onclick="openClaimPhotoModal('${encodeURI(p.url)}', '${encodeURIComponent(p.title || 'Foto Bukti')}')">
                <img src="${p.url}" alt="${p.title || 'Foto'}" class="w-full h-full object-cover group-hover:scale-105 transition duration-300">
                <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent opacity-0 group-hover:opacity-100 transition flex items-end p-2">
                    <span class="text-[10px] text-white font-semibold truncate"><i class="fa-solid fa-magnifying-glass-plus mr-1"></i>${p.title || 'Perbesar'}</span>
                </div>
                <span class="absolute top-1.5 left-1.5 px-1.5 py-0.5 rounded bg-slate-900/80 text-white font-bold text-[9px] uppercase tracking-wider backdrop-blur-xs">
                    ${p.badge || 'Bukti'}
                </span>
            </div>
        `).join('');
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

    let prodNames = order.ProductName || '';
    if (!prodNames && unboxing?.items && unboxing.items.length > 0) {
        prodNames = unboxing.items.map(i => `${i.product_name || i.barcode} (x${i.qty || 1})`).join(', ');
    }
    setElText('detailOrderProductName', prodNames || '-');

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

window.openClaimPhotoModal = function(url, encodedTitle) {
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

window.closeClaimPhotoModal = function() {
    const modal = document.getElementById('claimPhotoModal');
    if (modal) modal.classList.add('hidden');
};

window.openNasConfigModal = function() {
    const modal = document.getElementById('modalNasConfig');
    if (modal) modal.classList.remove('hidden');
};

window.closeNasConfigModal = function() {
    const modal = document.getElementById('modalNasConfig');
    if (modal) modal.classList.add('hidden');
};

window.saveNasConfig = async function() {
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

window.copyClaimPacketSummary = function() {
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

window.printClaimDossier = function() {
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
            <div>
                <b style="font-size: 12pt;">IEG RETURN INBOUND & CLAIMS</b><br>
                <span style="font-size: 8.5pt; color: #64748b;">Warehouse Management & Expedition Dispute Department</span>
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
            </table>
        </div>

        ${photos.length > 0 ? `
        <div class="box">
            <div class="box-title">V. DOKUMENTASI FOTO BUKTI FISIK (${photos.length} FOTO)</div>
            <div class="photos-grid">
                ${photos.slice(0, 6).map(p => `
                    <div class="photo-item">
                        <img src="${p.url}" alt="${p.title || 'Foto Bukti'}">
                        <span>${p.title || p.badge || 'Bukti Retur'}</span>
                    </div>
                `).join('')}
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
// KANDIDAT PAKET KLAIM (KONDISI BUKAN GOOD)
// ==========================================
window.loadClaimCandidates = async function(force = false) {
    const tbody = document.getElementById('claimCandidatesTableBody');
    const refreshIcon = document.getElementById('iconRefreshCandidates');
    if (!tbody) return;

    if (refreshIcon) refreshIcon.classList.add('fa-spin');

    try {
        const res = await fetch('api/ocs_lookup.php?action=list_claimable');
        const data = await res.json();

        if (!data.success) {
            tbody.innerHTML = `<tr><td colspan="8" class="text-center py-8 text-rose-500 font-semibold">${data.message || 'Gagal memuat kandidat klaim'}</td></tr>`;
            return;
        }

        const candidates = data.candidates || [];
        if (candidates.length === 0) {
            tbody.innerHTML = `<tr><td colspan="8" class="text-center py-10 text-slate-400">
                <i class="fa-solid fa-box-open text-2xl text-slate-300 mb-2 block"></i>
                Tidak ada paket unboxing yang berkondisi rusak / cacat saat ini. Semua paket berkondisi BAGUS (GOOD).
            </td></tr>`;
            return;
        }

        let html = '';
        candidates.forEach((c, idx) => {
            const hasVideo = !!c.video_path;
            const damagedCount = c.total_damaged > 0 ? c.total_damaged : (c.damaged_items_count || 1);
            const reason = c.damage_reasons || c.notes || 'Kondisi Rusak / Bukan Good';

            html += `
                <tr class="hover:bg-rose-50/40 transition border-b border-slate-100">
                    <td class="py-3 px-4 font-bold text-slate-500">${idx + 1}</td>
                    <td class="py-3 px-4">
                        <button onclick="lookupClaimCandidate('${c.invoice_number}')" class="font-mono font-bold text-indigo-600 hover:text-indigo-800 text-left block hover:underline">
                            ${c.invoice_number}
                        </button>
                        <span class="text-[10px] text-slate-400 block">${c.operator_name || 'Operator'}</span>
                    </td>
                    <td class="py-3 px-4 font-semibold text-slate-700">${c.expedition || '-'}</td>
                    <td class="py-3 px-4 text-slate-500 text-[11px] whitespace-nowrap">${c.created_at || '-'}</td>
                    <td class="py-3 px-4 text-center">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-rose-100 text-rose-700 border border-rose-200">
                            ${damagedCount} Rusak
                        </span>
                    </td>
                    <td class="py-3 px-4">
                        <span class="text-slate-800 font-medium block max-w-xs truncate" title="${reason}">
                            ${reason}
                        </span>
                    </td>
                    <td class="py-3 px-4 text-center">
                        ${hasVideo ? 
                            `<span class="inline-flex items-center gap-1 text-[11px] text-emerald-600 font-bold bg-emerald-50 px-2 py-0.5 rounded-md border border-emerald-200">
                                <i class="fa-solid fa-video"></i> Ada
                             </span>` : 
                            `<span class="inline-flex items-center gap-1 text-[11px] text-slate-400 bg-slate-100 px-2 py-0.5 rounded-md">
                                <i class="fa-solid fa-video-slash"></i> -
                             </span>`
                        }
                    </td>
                    <td class="py-3 px-4 text-center">
                        <button onclick="lookupClaimCandidate('${c.invoice_number}')" class="px-2.5 py-1 bg-amber-500 hover:bg-amber-600 text-white rounded-lg text-[11px] font-bold transition shadow-2xs flex items-center gap-1.5 mx-auto">
                            <i class="fa-solid fa-file-shield"></i> Berkas Klaim
                        </button>
                    </td>
                </tr>
            `;
        });
        tbody.innerHTML = html;
        if (force) {
            showToast('success', `Berhasil memuat ${candidates.length} paket rusak / layak klaim`, 'Daftar Diperbarui');
        }
    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="8" class="text-center py-8 text-rose-500">Error: ${err.message}</td></tr>`;
    } finally {
        if (refreshIcon) refreshIcon.classList.remove('fa-spin');
    }
};

window.searchClaimDossier = function(e) {
    if (window.executeClaimLookup) return window.executeClaimLookup(e);
};

window.lookupClaimCandidate = function(identifier) {
    const input = document.getElementById('claimSearchInput');
    if (input) {
        input.value = identifier;
        if (window.executeClaimLookup) {
            window.executeClaimLookup();
        }
        const resEl = document.getElementById('claimResultContainer');
        if (resEl) {
            resEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }
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
window.openOcsSyncModal = function() {
    const modal = document.getElementById('modalOcsSync');
    if (modal) {
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
};

window.closeOcsSyncModal = function() {
    const modal = document.getElementById('modalOcsSync');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = '';
    }
};

window.toggleSyncDateInput = function() {
    const radios = document.getElementsByName('syncPeriodType');
    let selected = 'yesterday';
    for (const r of radios) {
        if (r.checked) selected = r.value;
    }

    const customContainer = document.getElementById('syncCustomDateContainer');
    const labelYesterday = document.getElementById('labelSyncYesterday');
    const labelToday = document.getElementById('labelSyncToday');
    const labelCustom = document.getElementById('labelSyncCustom');

    [labelYesterday, labelToday, labelCustom].forEach(l => {
        if (l) {
            l.classList.remove('border-indigo-600', 'bg-indigo-50/50');
            l.classList.add('border-slate-200', 'bg-white');
        }
    });

    if (selected === 'yesterday' && labelYesterday) {
        labelYesterday.classList.add('border-indigo-600', 'bg-indigo-50/50');
        labelYesterday.classList.remove('border-slate-200', 'bg-white');
        if (customContainer) customContainer.classList.add('hidden');
    } else if (selected === 'today' && labelToday) {
        labelToday.classList.add('border-indigo-600', 'bg-indigo-50/50');
        labelToday.classList.remove('border-slate-200', 'bg-white');
        if (customContainer) customContainer.classList.add('hidden');
    } else if (selected === 'custom' && labelCustom) {
        labelCustom.classList.add('border-indigo-600', 'bg-indigo-50/50');
        labelCustom.classList.remove('border-slate-200', 'bg-white');
        if (customContainer) customContainer.classList.remove('hidden');
    }
};

window.executeOcsOrderSync = async function() {
    const btnStart = document.getElementById('btnStartOcsSync');
    const btnCancel = document.getElementById('btnCancelOcsSync');
    const progressContainer = document.getElementById('syncProgressContainer');
    const progressTitle = document.getElementById('syncProgressTitle');
    const progressBadge = document.getElementById('syncProgressBadge');
    const progressDetail = document.getElementById('syncProgressDetail');
    const statsBox = document.getElementById('syncStatsBox');
    const statTotalOrders = document.getElementById('statTotalOrders');
    const statWithResi = document.getElementById('statWithResi');
    const statTotalClaim = document.getElementById('statTotalClaim');

    const radios = document.getElementsByName('syncPeriodType');
    let selected = 'yesterday';
    for (const r of radios) {
        if (r.checked) selected = r.value;
    }

    let dateParam = selected;
    if (selected === 'custom') {
        const customDateInput = document.getElementById('syncCustomDateInput');
        dateParam = customDateInput ? customDateInput.value : '';
        if (!dateParam) {
            showToast('warning', 'Harap pilih tanggal sinkronisasi terlebih dahulu.', 'Peringatan');
            return;
        }
    }

    // Tampilkan progress UI
    if (progressContainer) progressContainer.classList.remove('hidden');
    if (statsBox) statsBox.classList.add('hidden');
    if (progressBadge) {
        progressBadge.className = 'text-[10px] px-2 py-0.5 rounded-full bg-indigo-500/30 text-indigo-300 font-mono';
        progressBadge.innerText = 'PROSES';
    }
    if (progressTitle) {
        progressTitle.innerHTML = `<i class="fa-solid fa-circle-notch fa-spin text-indigo-400"></i> Sinkronisasi Orders Sedang Berjalan...`;
    }
    if (progressDetail) {
        progressDetail.innerText = `Menghubungkan ke OCS IEG System untuk mengambil data orders ${selected === 'yesterday' ? 'hari kemarin (00:00 - 23:59 WIB)' : (selected === 'today' ? 'hari ini' : dateParam)}...`;
    }

    if (btnStart) {
        btnStart.disabled = true;
        btnStart.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> Menyinkron...`;
        btnStart.classList.add('opacity-60', 'cursor-not-allowed');
    }
    if (btnCancel) btnCancel.disabled = true;

    try {
        const response = await fetch(`api/sync_ocs_orders.php?date=${encodeURIComponent(dateParam)}`);
        const result = await response.json();

        if (result && result.success) {
            if (progressTitle) {
                progressTitle.innerHTML = `<i class="fa-solid fa-circle-check text-emerald-400"></i> Sinkronisasi Berhasil Selesai!`;
            }
            if (progressBadge) {
                progressBadge.className = 'text-[10px] px-2 py-0.5 rounded-full bg-emerald-500/30 text-emerald-300 font-mono';
                progressBadge.innerText = 'SELESAI';
            }
            if (progressDetail) {
                progressDetail.innerHTML = `Periode: <b>${result.target_date || dateParam}</b> (${result.start_wib} s/d ${result.end_wib})<br>Semua invoice, resi, detail item SKU & biaya klaim telah tersimpan ke sistem.`;
            }

            if (statsBox) statsBox.classList.remove('hidden');
            if (statTotalOrders) statTotalOrders.innerText = (result.total_synced || result.total_orders_found || 0).toLocaleString('id-ID');
            if (statWithResi) statWithResi.innerText = (result.total_with_resi || 0).toLocaleString('id-ID');
            if (statTotalClaim) statTotalClaim.innerText = result.total_claim_amount_fmt || `Rp ${(result.total_claim_amount || 0).toLocaleString('id-ID')}`;

            showToast('success', `Berhasil menyinkron ${result.total_synced || 0} orders dengan total nilai klaim ${result.total_claim_amount_fmt || 'Rp 0'}!`, 'Sinkronisasi Selesai');

            // Refresh data setelah sync sukses
            if (typeof loadClaimCandidates === 'function') {
                loadClaimCandidates(true);
            }
            if (typeof loadOrdersStats === 'function') {
                loadOrdersStats();
            }
            if (typeof loadOrdersTable === 'function') {
                loadOrdersTable(1);
            }
        } else {
            throw new Error(result.error || result.message || 'Gagal menyinkron data orders dari OCS');
        }
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
window.loadOrdersStats = async function() {
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
window.debounceOrderSearch = function() {
    clearTimeout(orderSearchDebounceTimer);
    orderSearchDebounceTimer = setTimeout(() => {
        loadOrdersTable(1);
    }, 350);
};

// 3. Handler Perubahan Filter Tanggal
window.onOrderDateFilterChanged = function() {
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

// 4. Memuat Data Tabel Orders
window.loadOrdersTable = async function(page = 1) {
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
        const dateSelect = document.getElementById('orderDateFilter');
        const limitSelect = document.getElementById('orderLimitSelect');

        const search = searchInput ? searchInput.value.trim() : '';
        const platform = platformSelect ? platformSelect.value : 'ALL';
        const dateType = dateSelect ? dateSelect.value : '';
        const limit = limitSelect ? parseInt(limitSelect.value) || 25 : 25;

        let url = `api/orders.php?action=list&page=${page}&limit=${limit}`;
        if (search) url += `&search=${encodeURIComponent(search)}`;
        if (platform && platform !== 'ALL') url += `&platform=${encodeURIComponent(platform)}`;
        if (dateType) url += `&date=${encodeURIComponent(dateType)}`;

        if (dateType === 'custom') {
            const startDate = document.getElementById('orderStartDate')?.value || '';
            const endDate = document.getElementById('orderEndDate')?.value || '';
            if (startDate) url += `&start_date=${encodeURIComponent(startDate)}`;
            if (endDate) url += `&end_date=${encodeURIComponent(endDate)}`;
        }

        const res = await fetch(url);
        const data = await res.json();

        if (!data || !data.success) {
            tbody.innerHTML = `<tr><td colspan="8" class="text-center py-8 text-rose-500 font-semibold">${data.message || 'Gagal mengambil data pesanan'}</td></tr>`;
            return;
        }

        renderOrdersTable(data.orders || [], data.pagination || {});
    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="8" class="text-center py-8 text-rose-500 font-semibold">Terjadi kesalahan koneksi: ${err.message}</td></tr>`;
    }
};

// 5. Render Tabel Data Orders
window.renderOrdersTable = function(orders, pagination) {
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
window.openOrderDetailModal = async function(orderId) {
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
            btnClaim.onclick = function() {
                viewOrderInClaims(resiToFind);
            };
        }
    } catch (err) {
        showToast('error', err.message, 'Gagal Memuat Detail');
    }
};

window.closeOrderDetailModal = function() {
    const modal = document.getElementById('modalOrderDetail');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = '';
    }
};

// 7. Beralih ke Halaman Klaim & Cari Resi
window.viewOrderInClaims = function(identifier) {
    closeOrderDetailModal();
    switchTab('claims');
    const claimInput = document.getElementById('claimSearchInput');
    if (claimInput) {
        claimInput.value = identifier;
        if (typeof executeClaimLookup === 'function') {
            executeClaimLookup();
        }
        const resEl = document.getElementById('claimResultContainer');
        if (resEl) {
            resEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }
};

// 8. Salin Teks ke Clipboard
window.copyOrderText = function(text, label = 'Teks') {
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
window.exportOrdersExcel = async function() {
    showGlobalLoading("Mengekspor Excel...", "Mengambil seluruh data pesanan tersinkron dari server...");
    try {
        const platform = document.getElementById('orderPlatformFilter')?.value || 'ALL';
        const dateType = document.getElementById('orderDateFilter')?.value || '';
        const search = document.getElementById('orderSearchInput')?.value.trim() || '';

        let url = `api/orders.php?action=list&page=1&limit=10000`;
        if (search) url += `&search=${encodeURIComponent(search)}`;
        if (platform && platform !== 'ALL') url += `&platform=${encodeURIComponent(platform)}`;
        if (dateType) url += `&date=${encodeURIComponent(dateType)}`;

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



