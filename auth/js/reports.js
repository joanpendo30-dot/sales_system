function fmt(n) {
    return 'KES ' + Number(n).toLocaleString('en-KE', { minimumFractionDigits: 2 });
}

function showError(msg) {
    const box = document.getElementById('errorBox');
    if (box) {
        box.textContent = msg;
        box.style.display = 'block';
    }
}

function loadReport() {
    const fromEl = document.getElementById('fromDate');
    const toEl   = document.getElementById('toDate');
    const bizEl  = document.getElementById('bizFilter');

    if (!fromEl || !toEl || !bizEl) {
        showError('Page elements missing — check report.php structure.');
        return;
    }

    const from = fromEl.value;
    const to   = toEl.value;
    const biz  = bizEl.value;

    fetch(`/sales-system/auth/api/sales_by_business.php?from=${from}&to=${to}&biz=${biz}`)
        .then(res => {
            if (!res.ok) throw new Error('Server returned status ' + res.status);
            return res.json();
        })
        .then(json => {
            if (json.status === 'success') {
                renderMetrics(json.data);
                if (typeof IS_ADMIN !== 'undefined' && IS_ADMIN) {
                    renderTable(json.data);
                }
            } else {
                showError('API error: ' + (json.message || 'Unknown error'));
            }
        })
        .catch(err => {
            console.error('Failed to load report:', err);
            showError('Failed to load report: ' + err.message);
            const tbody = document.getElementById('bizTableBody');
            if (tbody) {
                tbody.innerHTML = '<tr><td colspan="8" class="text-center text-danger py-4">Failed to load data.</td></tr>';
            }
        });
}

function renderMetrics(data) {
    document.getElementById('mOrders').textContent    = data.reduce((s, d) => s + d.orders, 0);
    document.getElementById('mRevenue').textContent   = fmt(data.reduce((s, d) => s + d.revenue, 0));
    document.getElementById('mCompleted').textContent = fmt(data.reduce((s, d) => s + d.completed, 0));
    document.getElementById('mPending').textContent   = data.reduce((s, d) => s + d.pending, 0);
    document.getElementById('mCancelled').textContent = data.reduce((s, d) => s + d.cancelled, 0);
    document.getElementById('mBiz').textContent       = data.length;
}

function renderTable(data) {
    const tbody = document.getElementById('bizTableBody');
    if (!tbody) return;

    const sorted = [...data].sort((a, b) => b.revenue - a.revenue);

    if (sorted.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">No orders in this date range.</td></tr>';
        return;
    }

    tbody.innerHTML = sorted.map((d, i) => {
        let badge = '<span class="badge bg-success">Good</span>';
        if (d.cancelled > 3)    badge = '<span class="badge bg-danger">Review</span>';
        else if (d.pending > 7) badge = '<span class="badge bg-warning text-dark">Monitor</span>';

        return `<tr>
            <td class="text-muted">${i + 1}</td>
            <td><strong>${d.name}</strong></td>
            <td class="text-end">${d.orders}</td>
            <td class="text-end fw-semibold">${fmt(d.revenue)}</td>
            <td class="text-end text-success">${fmt(d.completed)}</td>
            <td class="text-end">${d.pending}</td>
            <td class="text-end text-danger">${d.cancelled}</td>
            <td class="text-center">${badge}</td>
        </tr>`;
    }).join('');
}

function exportPDF() {
    const { jsPDF } = window.jspdf;
    const doc  = new jsPDF();
    const from = document.getElementById('fromDate').value;
    const to   = document.getElementById('toDate').value;

    doc.setFontSize(16);
    doc.setFont('helvetica', 'bold');
    doc.text('Sales Report', 14, 20);
    doc.setFontSize(10);
    doc.setFont('helvetica', 'normal');
    doc.setTextColor(100);
    doc.text(`Period: ${from} to ${to}`, 14, 28);
    doc.text(`Generated: ${new Date().toLocaleDateString()}`, 14, 34);
    doc.setTextColor(0);

    doc.autoTable({
        startY: 42,
        head: [['Metric', 'Value']],
        body: [
            ['Total Orders',      document.getElementById('mOrders').textContent],
            ['Total Revenue',     document.getElementById('mRevenue').textContent],
            ['Completed Revenue', document.getElementById('mCompleted').textContent],
            ['Pending Orders',    document.getElementById('mPending').textContent],
            ['Cancelled Orders',  document.getElementById('mCancelled').textContent],
            ['Active Businesses', document.getElementById('mBiz').textContent],
        ],
        styles: { fontSize: 9, cellPadding: 4 },
        headStyles: { fillColor: [37, 99, 235], textColor: 255 },
        columnStyles: { 1: { halign: 'right' } }
    });

    const tbody = document.getElementById('bizTableBody');
    if (tbody) {
        const rows = [];
        tbody.querySelectorAll('tr').forEach(tr => {
            const cells = tr.querySelectorAll('td');
            if (cells.length >= 7) {
                rows.push([
                    cells[0].textContent.trim(),
                    cells[1].textContent.trim(),
                    cells[2].textContent.trim(),
                    cells[3].textContent.trim(),
                    cells[4].textContent.trim(),
                    cells[5].textContent.trim(),
                    cells[6].textContent.trim(),
                ]);
            }
        });
        if (rows.length > 0) {
            const y = doc.lastAutoTable.finalY + 12;
            doc.setFontSize(12);
            doc.setFont('helvetica', 'bold');
            doc.text('Sales by Business', 14, y);
            doc.autoTable({
                startY: y + 4,
                head: [['#', 'Business', 'Orders', 'Total Revenue', 'Completed', 'Pending', 'Cancelled']],
                body: rows,
                styles: { fontSize: 8, cellPadding: 3 },
                headStyles: { fillColor: [37, 99, 235], textColor: 255 },
                alternateRowStyles: { fillColor: [246, 248, 255] }
            });
        }
    }

    doc.save(`sales-report-${from}-to-${to}.pdf`);
}