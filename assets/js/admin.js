// State Management & Instances
let ratioChartInstance = null;
let currentTab = 'dashboard';
let cachedProducts = [];
let activeDateFilter = '';
let flatpickrTransactionsInstance = null;
let flatpickrDashboardInstance = null;

// Tab & Page Slug Mappings (URL Friendly)
const TAB_SLUG_MAP = {
    'dashboard': 'dashboard',
    'transactions': 'inbound-unboxing',
    'products': 'master-produk',
    'expeditions': 'master-ekspedisi',
    'users': 'kelola-pengguna',
    'maintenance': 'pemeliharaan'
};

const SLUG_TAB_MAP = {
    'dashboard': 'dashboard',
    'inbound-unboxing': 'transactions',
    'transactions': 'transactions',
    'master-produk': 'products',
    'products': 'products',
    'master-ekspedisi': 'expeditions',
    'expeditions': 'expeditions',
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

    // Cantumkan filter tanggal jika sedang aktif
    if (activeDateFilter) {
        params.set('date', activeDateFilter);
    }

    // Filter tambahan per-tab
    if (currentTab === 'transactions') {
        const searchInput = document.getElementById('filterSearch');
        if (searchInput && searchInput.value.trim()) {
            params.set('search', searchInput.value.trim());
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
    if (titleEl) {
        if (tabName === 'dashboard') titleEl.innerText = 'Dashboard Monitoring Retur';
        else if (tabName === 'transactions') titleEl.innerText = 'Inbound Unboxing';
        else if (tabName === 'products') titleEl.innerText = 'Master Data Produk & Barcode';
        else if (tabName === 'expeditions') titleEl.innerText = 'Master Data Ekspedisi & Kurir';
        else if (tabName === 'users') titleEl.innerText = 'Kelola Akun Pengguna';
        else if (tabName === 'maintenance') titleEl.innerText = 'Pemeliharaan Sistem & Database';
    }

    // Auto close sidebar on mobile after click
    if (window.innerWidth < 1024) closeMobileSidebar();

    // Trigger tab-specific refresh if needed
    if (tabName === 'dashboard') {
        loadMetrics();
        loadTransactions();
    }
    if (tabName === 'products') loadProducts();
    if (tabName === 'transactions') loadTransactions();
    if (tabName === 'expeditions') loadExpeditions();
    if (tabName === 'conditions') loadConditions();
    if (tabName === 'users') loadUsers();
    if (tabName === 'maintenance') loadMaintenanceStatus();

    // Sinkronisasikan URL browser
    if (updateUrl) {
        updateBrowserUrl(true);
    }
};

// 1. Load Metrics KPI
async function loadMetrics() {
    try {
        let url = 'api/admin/metrics';
        if (activeDateFilter) {
            url += `?date=${encodeURIComponent(activeDateFilter)}`;
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

// Sinkronisasi Filter Tanggal antara Flatpickr Inbound Unboxing & Dashboard
function syncDateFilter(dateVal) {
    activeDateFilter = dateVal ? dateVal.trim() : '';

    // 1. Update elemen Inbound Unboxing
    const trDateEl = document.getElementById('filterDate');
    const trClearBtn = document.getElementById('btnClearDate');
    if (trDateEl) {
        trDateEl.value = activeDateFilter;
        if (flatpickrTransactionsInstance) {
            if (activeDateFilter) {
                if (activeDateFilter.includes(' to ')) {
                    const p = activeDateFilter.split(' to ');
                    flatpickrTransactionsInstance.setDate([p[0], p[1]], false);
                } else {
                    flatpickrTransactionsInstance.setDate(activeDateFilter, false);
                }
            } else {
                flatpickrTransactionsInstance.clear();
            }
        }
    }
    if (trClearBtn) {
        if (activeDateFilter) trClearBtn.classList.remove('hidden');
        else trClearBtn.classList.add('hidden');
    }

    // 2. Update elemen Dashboard
    const dbDateEl = document.getElementById('dashboardFilterDate');
    const dbClearBtn = document.getElementById('btnClearDashboardDate');
    const dbBadge = document.getElementById('dashboardDateBadge');
    if (dbDateEl) {
        dbDateEl.value = activeDateFilter;
        if (flatpickrDashboardInstance) {
            if (activeDateFilter) {
                if (activeDateFilter.includes(' to ')) {
                    const p = activeDateFilter.split(' to ');
                    flatpickrDashboardInstance.setDate([p[0], p[1]], false);
                } else {
                    flatpickrDashboardInstance.setDate(activeDateFilter, false);
                }
            } else {
                flatpickrDashboardInstance.clear();
            }
        }
    }
    if (dbClearBtn) {
        if (activeDateFilter) dbClearBtn.classList.remove('hidden');
        else dbClearBtn.classList.add('hidden');
    }
    if (dbBadge) {
        if (activeDateFilter) {
            dbBadge.innerText = activeDateFilter;
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

// Inisialisasi Datepicker Flatpickr Premium untuk Inbound Unboxing & Dashboard
function initFlatpickr() {
    if (typeof flatpickr === 'undefined') return;

    // 1. Inbound Unboxing
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
                const val = getDateStrFromInstance(instance);
                syncDateFilter(val);
                updateBrowserUrl(false);
                loadTransactions();
                loadMetrics();
            }
        });
    }

    // 2. Dashboard
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
                const val = getDateStrFromInstance(instance);
                syncDateFilter(val);
                updateBrowserUrl(false);
                loadMetrics();
                loadTransactions();
            }
        });
    }

    if (activeDateFilter) {
        syncDateFilter(activeDateFilter);
    }
}

window.clearDateFilter = function() {
    if (flatpickrTransactionsInstance) flatpickrTransactionsInstance.clear();
    if (flatpickrDashboardInstance) flatpickrDashboardInstance.clear();
    syncDateFilter('');
    updateBrowserUrl(false);
    loadTransactions();
    loadMetrics();
};

window.applyDashboardDateFilter = function() {
    // Baca langsung dari Flatpickr instance (altInput menyembunyikan input asli)
    const val = getDateStrFromInstance(flatpickrDashboardInstance)
             || document.getElementById('dashboardFilterDate')?.value?.trim()
             || '';
    syncDateFilter(val);
    updateBrowserUrl(false);
    loadMetrics();
    loadTransactions();
};

window.clearDashboardDateFilter = function() {
    if (flatpickrDashboardInstance) flatpickrDashboardInstance.clear();
    if (flatpickrTransactionsInstance) flatpickrTransactionsInstance.clear();
    syncDateFilter('');
    updateBrowserUrl(false);
    loadMetrics();
    loadTransactions();
};

// 2. Load Transaksi (Full & Preview)
async function loadTransactions() {
    const searchInput = document.getElementById('filterSearch');
    const search = searchInput ? searchInput.value.trim() : '';
    const date = activeDateFilter;
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

        // Render di tabel preview (5 teratas) pada tab dashboard
        const previewTbody = document.getElementById('previewTransactionsTableBody');
        if (previewTbody) {
            previewTbody.innerHTML = '';
            if (!rows || rows.length === 0) {
                previewTbody.innerHTML = `<tr><td colspan="12" class="text-center py-6 text-slate-400">Belum ada transaksi retur hari ini.</td></tr>`;
            } else {
                rows.slice(0, 5).forEach(r => previewTbody.appendChild(createTransactionRow(r, true)));
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
            alert(`Produk "${name}" berhasil ditambahkan ke database!`);
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



// Filter Transaksi
const btnApply = document.getElementById('btnApplyFilter');
if (btnApply) btnApply.addEventListener('click', loadTransactions);

// ==============================================================
// MODUL EKSPOR EXCEL NATIVE (.XLSX ASLI - 100% BEBAS CORRUPT)
// MENGGUNAKAN SHEETJS (STANDAR OPENXML MS EXCEL, WPS & G-SHEETS)
// ==============================================================
function generateExcelFile(sheets, defaultFileName) {
    if (typeof XLSX === 'undefined') {
        alert("Library Excel sedang diunduh oleh browser, mohon coba kembali dalam 2 detik.");
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
        alert("Terjadi kesalahan saat membuat file Excel: " + err.message);
    }
}

// 1. Export Excel: Dashboard Overview (KPI + Transaksi Terkini)
window.exportDashboardExcel = async function() {
    showGlobalLoading("Menyiapkan Excel...", "Mengumpulkan data dashboard dan transaksi...");
    try {
        const resMetrics = await fetch('api/admin/metrics');
        const kpi = await resMetrics.json();

        const resTrans = await fetch('api/admin/transactions.php');
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
        alert("Gagal mengunduh Excel Dashboard: " + err.message);
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
            alert("Tidak ada data transaksi inbound unboxing untuk diekspor.");
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
        alert("Gagal mengunduh Excel Inbound Unboxing: " + err.message);
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
            alert("Tidak ada data produk untuk diekspor.");
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
        alert("Gagal mengunduh Excel Master Produk: " + err.message);
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
            alert("Tidak ada data ekspedisi untuk diekspor.");
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
        alert("Gagal mengunduh Excel Ekspedisi: " + err.message);
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
        syncDateFilter(dateParam);
    }

    // Pulihkan filter search jika ada di URL
    if (targetTab === 'transactions' && searchParam) {
        const searchInput = document.getElementById('filterSearch');
        if (searchInput) searchInput.value = searchParam;
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

    // Auto refresh data tiap 15 detik
    setInterval(() => {
        loadMetrics();
        loadTransactions();
    }, 15000);
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

    if (!code || !name) { alert('Kode dan Nama Kondisi wajib diisi!'); return; }

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
        } else {
            alert('Gagal: ' + (data.error || 'Terjadi kesalahan'));
        }
    } catch (e) {
        alert('Error: ' + e.message);
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
        } else {
            alert('Gagal menghapus: ' + (data.error || 'Terjadi kesalahan'));
        }
    } catch (e) {
        alert('Error: ' + e.message);
    }
}
