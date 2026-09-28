let ratioChartInstance = null;

async function loadMetrics() {
    try {
        const res = await fetch('/api/admin/metrics');
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

function renderChart(good, damaged) {
    const ctx = document.getElementById('ratioChart').getContext('2d');
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
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });
}

async function loadTransactions() {
    const search = document.getElementById('filterSearch').value;
    const date = document.getElementById('filterDate').value;
    
    let url = `/api/admin/transactions?`;
    if (search) url += `search=${encodeURIComponent(search)}&`;
    if (date) url += `date=${encodeURIComponent(date)}&`;

    try {
        const res = await fetch(url);
        const rows = await res.json();
        const tbody = document.getElementById('transactionsTableBody');
        tbody.innerHTML = '';

        if (rows.length === 0) {
            tbody.innerHTML = `<tr><td colspan="8" class="text-center py-8 text-slate-400">Tidak ada transaksi ditemukan.</td></tr>`;
            return;
        }

        rows.forEach(r => {
            const tr = document.createElement('tr');
            tr.className = 'hover:bg-slate-50 transition';
            tr.innerHTML = `
                <td class="p-3 text-slate-500">${new Date(r.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</td>
                <td class="p-3 font-mono font-bold text-indigo-700">${r.invoice_number}</td>
                <td class="p-3 font-semibold text-slate-700">${r.operator_name}</td>
                <td class="p-3 text-center font-bold">${r.total_items}</td>
                <td class="p-3 text-center text-emerald-600 font-bold">${r.total_good}</td>
                <td class="p-3 text-center text-rose-600 font-bold">${r.total_damaged}</td>
                <td class="p-3 text-slate-600 truncate max-w-xs">${r.items_summary || '-'}</td>
                <td class="p-3 text-center">
                    <button onclick="viewDetails(${r.id}, '${r.invoice_number}')" class="text-indigo-600 hover:text-indigo-800 font-semibold text-xs">
                        <i class="fa-solid fa-eye"></i> Detail
                    </button>
                </td>
            `;
            tbody.appendChild(tr);
        });
    } catch (err) {
        console.error("Gagal load transaksi:", err);
    }
}

window.viewDetails = async function(sessionId, invoiceNumber) {
    try {
        const res = await fetch(`/api/admin/session/${sessionId}/items`);
        const items = await res.json();

        document.getElementById('modalInvoiceTitle').innerText = `Detail Invoice: ${invoiceNumber}`;
        document.getElementById('modalInvoiceSubtitle').innerText = `Total ${items.length} item terdaftar`;

        const tbody = document.getElementById('modalItemsBody');
        tbody.innerHTML = '';

        items.forEach(it => {
            const badge = it.condition === 'GOOD'
                ? `<span class="bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded font-bold text-[10px]">GOOD</span>`
                : `<span class="bg-rose-100 text-rose-700 px-2 py-0.5 rounded font-bold text-[10px]">RUSAK</span>`;

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="p-2.5 font-mono text-slate-600">${it.barcode}</td>
                <td class="p-2.5 font-bold text-slate-800">${it.product_name}</td>
                <td class="p-2.5 text-center font-bold">${it.qty}</td>
                <td class="p-2.5 text-center">${badge}</td>
                <td class="p-2.5 text-slate-500">${it.damage_reason || '-'}</td>
            `;
            tbody.appendChild(tr);
        });

        document.getElementById('detailModal').classList.remove('hidden');
    } catch (err) {
        alert("Gagal membuka detail item: " + err.message);
    }
};

document.getElementById('btnCloseModal').addEventListener('click', () => {
    document.getElementById('detailModal').classList.add('hidden');
});

document.getElementById('btnApplyFilter').addEventListener('click', loadTransactions);

// Export CSV
document.getElementById('btnExportCsv').addEventListener('click', async () => {
    const res = await fetch('/api/admin/transactions');
    const rows = await res.json();
    if (!rows.length) return alert("Tidak ada data untuk diekspor");

    let csv = "ID,Tanggal,Invoice,Operator,Total Unit,Total Good,Total Rusak,Ringkasan Item\n";
    rows.forEach(r => {
        csv += `"${r.id}","${r.created_at}","${r.invoice_number}","${r.operator_name}","${r.total_items}","${r.total_good}","${r.total_damaged}","${r.items_summary || ''}"\n`;
    });

    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `Inbound_Return_Report_${new Date().toISOString().slice(0,10)}.csv`;
    a.click();
});

window.addEventListener('DOMContentLoaded', () => {
    loadMetrics();
    loadTransactions();
    // Auto refresh data transaksi tiap 15 detik
    setInterval(() => {
        loadMetrics();
        loadTransactions();
    }, 15000);
});
