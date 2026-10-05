<?php
/**
 * accounting_approval.php
 * Portal Khusus Web Approval Klaim Ekspedisi J&T untuk Tim Accounting & Management.
 * Dapat diakses online di InfinityFree (https://returninboundieg.great-site.net/accounting_approval)
 * maupun di PC Localhost.
 */
require_once __DIR__ . '/config.php';

$user = getSessionUser();
if (!$user) {
    header('Location: login');
    exit;
}

// Hanya Accounting, Management, Superadmin, dan Admin yang dapat mengakses
$allowedRoles = ['accounting', 'management', 'superadmin', 'admin'];
if (!in_array($user['role'] ?? '', $allowedRoles, true)) {
    echo "<script>alert('Akses Ditolak: Halaman ini khusus untuk tim Accounting & Management.'); window.location.href = 'menu';</script>";
    exit;
}

ensureClaimStatusColumn($pdo);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal Approval Klaim J&amp;T - Accounting IEG</title>
    <link rel="icon" type="image/svg+xml" href="assets/image/favicon.svg">
    <link rel="icon" type="image/png" href="assets/image/favicon.png">
    <link rel="shortcut icon" href="favicon.ico">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/custom.css?v=<?= file_exists(__DIR__ . '/assets/css/custom.css') ? filemtime(__DIR__ . '/assets/css/custom.css') : time() ?>">
</head>
<body class="bg-slate-100 min-h-screen text-slate-800 flex flex-col font-sans">

    <!-- Top Navigation Header -->
    <header class="bg-white border-b border-slate-200 sticky top-0 z-30 shadow-xs">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-xl bg-slate-50 border border-slate-200 p-1.5 flex items-center justify-center shrink-0">
                    <img src="assets/image/logo-IEG.png" alt="Logo IEG" class="w-full h-full object-contain">
                </div>
                <div>
                    <div class="flex items-center space-x-2">
                        <h1 class="font-bold text-slate-900 text-base leading-tight">Portal Approval Klaim J&amp;T</h1>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-rose-100 text-rose-700 border border-rose-200">KHUSUS J&amp;T</span>
                    </div>
                    <p class="text-xs text-slate-500 font-medium">Divisi Accounting &amp; Management • Verifikasi Tagihan</p>
                </div>
            </div>

            <!-- User Info & Logout -->
            <div class="flex items-center space-x-3">
                <div class="hidden sm:flex flex-col text-right">
                    <span class="text-xs font-bold text-slate-800"><?= htmlspecialchars($user['name']) ?></span>
                    <span class="text-[10px] font-semibold text-emerald-600 uppercase"><?= htmlspecialchars($user['role']) ?></span>
                </div>
                <div class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-600 border border-emerald-200 flex items-center justify-center font-bold text-sm">
                    <i class="fa-solid fa-calculator"></i>
                </div>
                <?php if (in_array($user['role'], ['admin', 'superadmin', 'management'])): ?>
                <a href="admin" class="hidden md:inline-flex items-center space-x-1.5 px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-xs font-semibold transition border border-slate-300">
                    <i class="fa-solid fa-arrow-left"></i>
                    <span>Dashboard Admin</span>
                </a>
                <?php endif; ?>
                <a href="logout" onclick="return confirm('Keluar dari portal approval?')" class="p-2 text-rose-500 hover:bg-rose-50 rounded-lg transition" title="Logout">
                    <i class="fa-solid fa-arrow-right-from-bracket"></i>
                </a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">

        <!-- Top Banner / Metrics -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Total Pengajuan -->
            <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs flex items-center space-x-3.5">
                <div class="w-12 h-12 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-xl shrink-0">
                    <i class="fa-solid fa-file-invoice"></i>
                </div>
                <div>
                    <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Total Klaim J&amp;T</div>
                    <div class="text-xl sm:text-2xl font-black text-slate-900" id="statTotalAll">0</div>
                </div>
            </div>

            <!-- Menunggu Approval -->
            <div class="bg-white p-4 rounded-2xl border border-amber-200/80 bg-amber-50/20 shadow-xs flex items-center space-x-3.5">
                <div class="w-12 h-12 rounded-xl bg-amber-100 text-amber-600 flex items-center justify-center text-xl shrink-0">
                    <i class="fa-solid fa-clock"></i>
                </div>
                <div>
                    <div class="text-[11px] font-bold text-amber-700 uppercase tracking-wider">Menunggu Approval</div>
                    <div class="text-xl sm:text-2xl font-black text-amber-700" id="statPending">0</div>
                </div>
            </div>

            <!-- Disetujui (Approved) -->
            <div class="bg-white p-4 rounded-2xl border border-emerald-200/80 bg-emerald-50/20 shadow-xs flex items-center space-x-3.5">
                <div class="w-12 h-12 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center text-xl shrink-0">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
                <div>
                    <div class="text-[11px] font-bold text-emerald-700 uppercase tracking-wider">Disetujui (Approved)</div>
                    <div class="text-xl sm:text-2xl font-black text-emerald-700" id="statApproved">0</div>
                </div>
            </div>

            <!-- Total Nilai Klaim Disetujui -->
            <div class="bg-white p-4 rounded-2xl border border-blue-200/80 bg-blue-50/20 shadow-xs flex items-center space-x-3.5">
                <div class="w-12 h-12 rounded-xl bg-blue-100 text-blue-600 flex items-center justify-center text-xl shrink-0">
                    <i class="fa-solid fa-coins"></i>
                </div>
                <div class="truncate">
                    <div class="text-[11px] font-bold text-blue-700 uppercase tracking-wider">Total Nilai Disetujui</div>
                    <div class="text-lg sm:text-xl font-black text-blue-800 truncate" id="statApprovedAmount">Rp 0</div>
                </div>
            </div>
        </div>

        <!-- Filter & Action Toolbar -->
        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-3">
            <!-- Filter Tabs -->
            <div class="flex items-center space-x-1.5 overflow-x-auto pb-1 md:pb-0">
                <button onclick="setFilterStatus('ALL')" id="tabFilterALL" class="status-tab-btn px-3 py-1.5 rounded-xl text-xs font-bold transition bg-slate-900 text-white">
                    Semua
                </button>
                <button onclick="setFilterStatus('PENDING')" id="tabFilterPENDING" class="status-tab-btn px-3 py-1.5 rounded-xl text-xs font-bold transition text-slate-600 hover:bg-slate-100">
                    Menunggu Approval (<span id="tabCountPending">0</span>)
                </button>
                <button onclick="setFilterStatus('APPROVED')" id="tabFilterAPPROVED" class="status-tab-btn px-3 py-1.5 rounded-xl text-xs font-bold transition text-slate-600 hover:bg-slate-100">
                    Disetujui (<span id="tabCountApproved">0</span>)
                </button>
                <button onclick="setFilterStatus('REJECTED')" id="tabFilterREJECTED" class="status-tab-btn px-3 py-1.5 rounded-xl text-xs font-bold transition text-slate-600 hover:bg-slate-100">
                    Ditolak
                </button>
            </div>

            <!-- Search & Actions -->
            <div class="flex items-center space-x-2">
                <div class="relative flex-1 md:w-64">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-2.5 text-slate-400 text-xs"></i>
                    <input type="text" id="searchInput" placeholder="Cari Resi, Order, Produk..." oninput="handleSearch(this.value)" class="w-full pl-8 pr-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-indigo-500">
                </div>

                <button onclick="loadClaimsData()" title="Muat Ulang Data" class="p-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs transition border border-slate-200">
                    <i class="fa-solid fa-arrows-rotate"></i>
                </button>

                <button onclick="openBulkApproveModal()" id="btnBulkApprove" class="inline-flex items-center space-x-1.5 px-3.5 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-xs transition disabled:opacity-50 disabled:pointer-events-none" disabled>
                    <i class="fa-solid fa-stamp"></i>
                    <span>Setujui Terpilih</span>
                </button>
            </div>
        </div>

        <!-- Table Card -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-700">
                    <thead class="bg-slate-50 border-b border-slate-200 text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                        <tr>
                            <th class="py-3 px-3.5 text-center w-10">
                                <input type="checkbox" id="checkAll" onchange="toggleSelectAll(this)" class="w-4 h-4 rounded text-indigo-600 focus:ring-indigo-500">
                            </th>
                            <th class="py-3 px-3.5">No. Resi J&amp;T / Order ID</th>
                            <th class="py-3 px-3.5">Tgl Unboxing / Petugas</th>
                            <th class="py-3 px-3.5">Rincian Barang Rusak &amp; Alasan</th>
                            <th class="py-3 px-3.5 text-right">Nilai Tagihan (OCS)</th>
                            <th class="py-3 px-3.5 text-center">Status Approval</th>
                            <th class="py-3 px-3.5 text-center w-28">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="claimsTableBody" class="divide-y divide-slate-100">
                        <tr>
                            <td colspan="7" class="py-12 text-center text-slate-400">
                                <i class="fa-solid fa-spinner fa-spin text-2xl mb-2 text-indigo-500"></i>
                                <div>Memuat data klaim J&amp;T...</div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Footer summary -->
            <div class="p-3.5 bg-slate-50/70 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                <div id="selectedCountText">0 baris dipilih</div>
                <div id="tableTotalText">Total: 0 klaim</div>
            </div>
        </div>
    </main>

    <!-- MODAL APPROVAL / DIGITAL E-SIGN -->
    <div id="modalApproval" class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-slate-200 space-y-5 animate-scaleIn">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <div class="flex items-center space-x-2.5">
                    <div class="w-10 h-10 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center text-lg">
                        <i class="fa-solid fa-stamp"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-900 text-base" id="modalApprovalTitle">Persetujuan Klaim (e-Sign)</h3>
                        <p class="text-xs text-slate-400">Verifikasi Resmi Divisi Accounting</p>
                    </div>
                </div>
                <button onclick="closeApprovalModal()" class="text-slate-400 hover:text-slate-600 p-1">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <div class="space-y-4 text-xs">
                <!-- Info Summary -->
                <div class="bg-slate-50 p-3 rounded-xl border border-slate-200 space-y-1">
                    <div class="flex justify-between text-slate-600">
                        <span>Jumlah Paket yang Dipilih:</span>
                        <b class="text-slate-900 font-mono" id="modalClaimCount">0 Paket</b>
                    </div>
                    <div class="flex justify-between text-slate-600">
                        <span>Total Nilai Tagihan:</span>
                        <b class="text-emerald-700 font-bold" id="modalClaimAmount">Rp 0</b>
                    </div>
                </div>

                <!-- Input Nama Petugas Approver -->
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Nama Petugas Accounting (Approver) <span class="text-rose-500">*</span></label>
                    <input type="text" id="inputApproverName" value="<?= htmlspecialchars($user['name'] ?: $user['username']) ?>" class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl font-medium focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500">
                </div>

                <!-- Digital e-Signature Stamp Badge Preview -->
                <div class="border border-dashed border-emerald-300 bg-emerald-50/40 p-3.5 rounded-2xl text-center space-y-1.5">
                    <div class="inline-flex items-center space-x-1.5 px-2.5 py-0.5 rounded-full bg-emerald-600 text-white font-black text-[10px] tracking-wider uppercase">
                        <i class="fa-solid fa-certificate"></i>
                        <span>Digital Verified e-Sign</span>
                    </div>
                    <div class="text-[11px] font-bold text-slate-800">
                        Dokumen Tagihan Invoice akan otomatis dibubuhi Cap &amp; Tanda Tangan Digital Resmi
                    </div>
                    <div class="text-[10px] text-slate-500 font-mono" id="previewEsignCode">
                        Token: ESIGN-JNT-<?= date('Ymd') ?>-AUTO
                    </div>
                </div>

                <!-- Catatan Verifikasi -->
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Catatan Verifikasi Accounting (Opsional)</label>
                    <textarea id="inputApprovalNotes" rows="2" placeholder="Contoh: Telah diverifikasi fisik barang rusak dan sesuai dengan data OCS..." class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl text-xs focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500"></textarea>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex items-center justify-end space-x-2 pt-2">
                <button type="button" onclick="closeApprovalModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold transition">
                    Batal
                </button>
                <button type="button" onclick="submitApproval('APPROVE')" id="btnSubmitApproval" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-md shadow-emerald-600/30 transition flex items-center space-x-1.5">
                    <i class="fa-solid fa-check-double"></i>
                    <span>Setujui &amp; Terbitkan e-Sign</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div id="toastNotification" class="fixed bottom-5 right-5 z-50 hidden transition-all duration-300 transform translate-y-3">
        <div class="flex items-center space-x-3 px-4 py-3 rounded-2xl shadow-xl text-xs font-bold text-white bg-slate-900 border border-slate-700" id="toastBox">
            <i id="toastIcon" class="fa-solid fa-circle-check text-emerald-400 text-base"></i>
            <span id="toastMessage">Pesan notifikasi</span>
        </div>
    </div>

    <script>
        let allClaims = [];
        let currentStatusFilter = 'ALL';
        let currentSearchQuery = '';
        let selectedInvoices = new Set();
        let targetSingleInvoice = null;

        document.addEventListener('DOMContentLoaded', () => {
            loadClaimsData();
        });

        function showToast(msg, isError = false) {
            const t = document.getElementById('toastNotification');
            const box = document.getElementById('toastBox');
            const icon = document.getElementById('toastIcon');
            const text = document.getElementById('toastMessage');

            text.innerText = msg;
            if (isError) {
                box.className = "flex items-center space-x-3 px-4 py-3 rounded-2xl shadow-xl text-xs font-bold text-white bg-rose-900 border border-rose-700";
                icon.className = "fa-solid fa-circle-exclamation text-rose-300 text-base";
            } else {
                box.className = "flex items-center space-x-3 px-4 py-3 rounded-2xl shadow-xl text-xs font-bold text-white bg-slate-900 border border-slate-700";
                icon.className = "fa-solid fa-circle-check text-emerald-400 text-base";
            }

            t.classList.remove('hidden', 'translate-y-3');
            setTimeout(() => {
                t.classList.add('hidden', 'translate-y-3');
            }, 3500);
        }

        async function loadClaimsData() {
            const tbody = document.getElementById('claimsTableBody');
            tbody.innerHTML = `
                <tr>
                    <td colspan="7" class="py-12 text-center text-slate-400">
                        <i class="fa-solid fa-spinner fa-spin text-2xl mb-2 text-indigo-500"></i>
                        <div>Memuat data klaim J&amp;T...</div>
                    </td>
                </tr>
            `;

            try {
                const res = await fetch(`api/sync_claim_approval.php?action=list_for_accounting&status=${encodeURIComponent(currentStatusFilter)}&search=${encodeURIComponent(currentSearchQuery)}`);
                const json = await res.json();

                if (!json.success) {
                    throw new Error(json.error || 'Gagal memuat data');
                }

                allClaims = json.items || [];
                const stats = json.stats || {};

                // Update Stats
                document.getElementById('statTotalAll').innerText = stats.total_all || 0;
                document.getElementById('statPending').innerText = stats.total_pending || 0;
                document.getElementById('statApproved').innerText = stats.total_approved || 0;
                document.getElementById('tabCountPending').innerText = stats.total_pending || 0;
                document.getElementById('tabCountApproved').innerText = stats.total_approved || 0;

                const approvedAmt = parseFloat(stats.sum_approved_amount || 0);
                document.getElementById('statApprovedAmount').innerText = 'Rp ' + approvedAmt.toLocaleString('id-ID');

                renderTable();
            } catch (err) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="7" class="py-8 text-center text-rose-500 font-semibold">
                            <i class="fa-solid fa-triangle-exclamation text-xl mb-1 block"></i>
                            ${err.message}
                        </td>
                    </tr>
                `;
            }
        }

        function renderTable() {
            const tbody = document.getElementById('claimsTableBody');
            const totalText = document.getElementById('tableTotalText');
            totalText.innerText = `Total: ${allClaims.length} klaim`;

            if (allClaims.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="7" class="py-12 text-center text-slate-400">
                            <i class="fa-solid fa-folder-open text-3xl mb-2 text-slate-300"></i>
                            <div>Tidak ada data pengajuan klaim J&amp;T dengan filter ini.</div>
                        </td>
                    </tr>
                `;
                updateSelectionState();
                return;
            }

            let html = '';
            allClaims.forEach(item => {
                const inv = item.invoice_number;
                const isChecked = selectedInvoices.has(inv);
                const isPending = (item.status === 'PENDING');
                const isApproved = (item.status === 'APPROVED');
                const isRejected = (item.status === 'REJECTED');

                // Badge Status
                let badgeHtml = '';
                if (isApproved) {
                    badgeHtml = `
                        <div class="inline-flex flex-col items-center">
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black bg-emerald-100 text-emerald-700 border border-emerald-200">
                                <i class="fa-solid fa-check mr-0.5"></i> APPROVED
                            </span>
                            <span class="text-[9px] text-slate-400 font-mono mt-0.5 truncate max-w-[120px]" title="${item.esign_token || ''}">
                                ${item.approved_by || 'Accounting'}
                            </span>
                        </div>
                    `;
                } else if (isRejected) {
                    badgeHtml = `
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black bg-rose-100 text-rose-700 border border-rose-200">
                            <i class="fa-solid fa-xmark mr-0.5"></i> REJECTED
                        </span>
                    `;
                } else {
                    badgeHtml = `
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black bg-amber-100 text-amber-700 border border-amber-200 animate-pulse">
                            <i class="fa-solid fa-clock mr-0.5"></i> MENUNGGU
                        </span>
                    `;
                }

                // Nilai Klaim
                const amt = parseFloat(item.total_claim_amount || 0);
                const amtFormatted = item.total_claim_amount_fmt || ('Rp ' + amt.toLocaleString('id-ID'));

                // Action buttons
                let actionHtml = '';
                if (isPending) {
                    actionHtml = `
                        <div class="flex items-center justify-center space-x-1.5">
                            <button onclick="approveSingle('${inv}', ${amt})" class="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-[11px] font-bold transition shadow-xs" title="Setujui Klaim">
                                <i class="fa-solid fa-check"></i> Approve
                            </button>
                            <button onclick="rejectSingle('${inv}')" class="p-1 text-slate-400 hover:text-rose-600 rounded transition" title="Tolak Klaim">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        </div>
                    `;
                } else {
                    actionHtml = `
                        <span class="text-[10px] text-slate-400 font-semibold italic">Selesai</span>
                    `;
                }

                html += `
                    <tr class="hover:bg-slate-50/80 transition ${isChecked ? 'bg-indigo-50/40' : ''}">
                        <td class="py-3 px-3.5 text-center">
                            <input type="checkbox" value="${inv}" ${isChecked ? 'checked' : ''} onchange="toggleSelectRow('${inv}', this.checked)" class="row-checkbox w-4 h-4 rounded text-indigo-600 focus:ring-indigo-500">
                        </td>
                        <td class="py-3 px-3.5">
                            <div class="font-bold text-slate-900 font-mono text-xs">${inv}</div>
                            <div class="text-[10px] text-slate-400 flex items-center space-x-1 mt-0.5">
                                <span class="px-1.5 py-0.2 bg-rose-50 text-rose-700 rounded font-bold">${item.expedition || 'J&T'}</span>
                                ${item.order_id && item.order_id !== inv ? `<span>Order: ${item.order_id}</span>` : ''}
                            </div>
                        </td>
                        <td class="py-3 px-3.5 whitespace-nowrap">
                            <div class="font-semibold text-slate-800 text-[11px]">${item.unboxing_date ? item.unboxing_date.substring(0, 16) : '-'}</div>
                            <div class="text-[10px] text-slate-400">Petugas: ${item.operator_name || '-'}</div>
                        </td>
                        <td class="py-3 px-3.5">
                            <div class="font-medium text-slate-800 text-[11px] line-clamp-1" title="${item.items_summary || ''}">${item.items_summary || '-'}</div>
                            <div class="text-[10px] text-amber-700 italic mt-0.5">${item.damaged_reason || 'Kondisi rusak unboxing'}</div>
                        </td>
                        <td class="py-3 px-3.5 text-right whitespace-nowrap">
                            <div class="font-bold text-slate-900 text-xs font-mono">${amtFormatted}</div>
                            <div class="text-[9px] text-slate-400">Tarikan OCS</div>
                        </td>
                        <td class="py-3 px-3.5 text-center whitespace-nowrap">
                            ${badgeHtml}
                        </td>
                        <td class="py-3 px-3.5 text-center whitespace-nowrap">
                            ${actionHtml}
                        </td>
                    </tr>
                `;
            });

            tbody.innerHTML = html;
            updateSelectionState();
        }

        function setFilterStatus(st) {
            currentStatusFilter = st;
            document.querySelectorAll('.status-tab-btn').forEach(btn => {
                btn.className = "status-tab-btn px-3 py-1.5 rounded-xl text-xs font-bold transition text-slate-600 hover:bg-slate-100";
            });
            const activeBtn = document.getElementById(`tabFilter${st}`);
            if (activeBtn) {
                activeBtn.className = "status-tab-btn px-3 py-1.5 rounded-xl text-xs font-bold transition bg-slate-900 text-white";
            }
            loadClaimsData();
        }

        let searchDebounce = null;
        function handleSearch(val) {
            clearTimeout(searchDebounce);
            searchDebounce = setTimeout(() => {
                currentSearchQuery = val.trim();
                loadClaimsData();
            }, 300);
        }

        function toggleSelectAll(cb) {
            if (cb.checked) {
                allClaims.forEach(it => selectedInvoices.add(it.invoice_number));
            } else {
                selectedInvoices.clear();
            }
            renderTable();
        }

        function toggleSelectRow(inv, isChecked) {
            if (isChecked) {
                selectedInvoices.add(inv);
            } else {
                selectedInvoices.delete(inv);
            }
            updateSelectionState();
        }

        function updateSelectionState() {
            const count = selectedInvoices.size;
            document.getElementById('selectedCountText').innerText = `${count} baris dipilih`;
            document.getElementById('btnBulkApprove').disabled = (count === 0);

            const checkAll = document.getElementById('checkAll');
            if (checkAll && allClaims.length > 0) {
                checkAll.checked = (selectedInvoices.size === allClaims.length);
            }
        }

        function approveSingle(inv, amount) {
            targetSingleInvoice = inv;
            document.getElementById('modalApprovalTitle').innerText = 'Setujui Klaim J&T Resi: ' + inv;
            document.getElementById('modalClaimCount').innerText = '1 Paket';
            document.getElementById('modalClaimAmount').innerText = 'Rp ' + (amount || 0).toLocaleString('id-ID');
            document.getElementById('modalApproval').classList.remove('hidden');
        }

        function openBulkApproveModal() {
            if (selectedInvoices.size === 0) return;
            targetSingleInvoice = null;

            let totalAmt = 0;
            allClaims.forEach(c => {
                if (selectedInvoices.has(c.invoice_number)) {
                    totalAmt += parseFloat(c.total_claim_amount || 0);
                }
            });

            document.getElementById('modalApprovalTitle').innerText = `Setujui ${selectedInvoices.size} Klaim J&T Terpilih`;
            document.getElementById('modalClaimCount').innerText = `${selectedInvoices.size} Paket`;
            document.getElementById('modalClaimAmount').innerText = 'Rp ' + totalAmt.toLocaleString('id-ID');
            document.getElementById('modalApproval').classList.remove('hidden');
        }

        function closeApprovalModal() {
            document.getElementById('modalApproval').classList.add('hidden');
            targetSingleInvoice = null;
        }

        async function submitApproval(decision) {
            const approver = document.getElementById('inputApproverName').value.trim();
            const notes = document.getElementById('inputApprovalNotes').value.trim();

            if (!approver) {
                showToast('Nama Petugas Accounting wajib diisi!', true);
                return;
            }

            const invoices = targetSingleInvoice ? [targetSingleInvoice] : Array.from(selectedInvoices);
            if (invoices.length === 0) {
                showToast('Tidak ada paket yang dipilih', true);
                return;
            }

            const btn = document.getElementById('btnSubmitApproval');
            btn.disabled = true;
            btn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> Memproses e-Sign...`;

            try {
                const res = await fetch('api/sync_claim_approval.php?action=approve_reject', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        invoices: invoices,
                        decision: decision,
                        approved_by: approver,
                        notes: notes
                    })
                });

                const json = await res.json();
                if (!json.success) {
                    throw new Error(json.error || 'Gagal memproses approval');
                }

                showToast(json.message || 'Klaim JNT berhasil di-approve!');
                closeApprovalModal();
                selectedInvoices.clear();
                loadClaimsData();
            } catch (err) {
                showToast(err.message, true);
            } finally {
                btn.disabled = false;
                btn.innerHTML = `<i class="fa-solid fa-check-double"></i> <span>Setujui &amp; Terbitkan e-Sign</span>`;
            }
        }

        async function rejectSingle(inv) {
            const reason = prompt(`Masukkan alasan penolakan klaim resi ${inv}:`);
            if (reason === null) return;

            try {
                const res = await fetch('api/sync_claim_approval.php?action=approve_reject', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        invoices: [inv],
                        decision: 'REJECT',
                        notes: reason || 'Ditolak oleh Accounting'
                    })
                });
                const json = await res.json();
                if (!json.success) throw new Error(json.error || 'Gagal menolak klaim');

                showToast(`Klaim ${inv} ditolak.`);
                loadClaimsData();
            } catch (err) {
                showToast(err.message, true);
            }
        }
    </script>
</body>
</html>
