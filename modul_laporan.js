// ============================================================
// FILE   : modul_laporan.js
// WBS    : 3.2 - Coding Modul Laporan Rekapitulasi Saldo & Laba-Rugi
// Sistem : SiKosKu - Sistem Informasi Keuangan Kos
// Fungsi : Generate laporan laba-rugi otomatis per kategori,
//          rekapitulasi saldo awal-akhir bulan, dan cetak laporan
// ============================================================

// Render laporan laba-rugi & rekapitulasi saldo dari database
async function loadLaporanBulanOptions(selectedMonth = currentMonth()){
  const select = document.getElementById('laporanBulan');
  if(!select) return selectedMonth;

  try {
    const data = await apiRequest('/laporan/bulan');
    const months = data.months || [];
    const activeMonth = months.includes(selectedMonth) ? selectedMonth : (months[0] || selectedMonth);

    select.innerHTML = months.map(month => (
      `<option value="${escapeHtml(month)}" ${month === activeMonth ? 'selected' : ''}>${formatMonthId(month)}</option>`
    )).join('');

    return activeMonth;
  } catch (err) {
    showToast(err.message || 'Gagal memuat daftar bulan laporan.');
    return select.value || selectedMonth;
  }
}

async function renderLaporan(){
  const bulan = document.getElementById('laporanBulan').value || currentMonth();

  try {
    const data = await apiRequest('/laporan?bulan=' + encodeURIComponent(bulan));
    const summary = data.summary || { income: 0, expense: 0, laba: 0 };
    const balance = data.balance || {
      saldo_awal: 0,
      income: summary.income || 0,
      expense: summary.expense || 0,
      saldo_akhir: summary.laba || 0
    };
    const roomStatus = data.room_status || [];
    const monthLabel = formatMonthId(data.bulan || bulan);

    document.getElementById('laporanSubtitle').textContent = `Rekapitulasi saldo dan laba-rugi bulan ${monthLabel}`;

    renderCategoryRows('laporanIncome', data.income_categories || [], 'Belum ada pendapatan');
    renderCategoryRows('laporanExpense', data.expense_categories || [], 'Belum ada pengeluaran');

    document.getElementById('lapTotalIn').textContent = fmt(Number(summary.income || 0));
    document.getElementById('lapTotalOut').textContent = fmt(Number(summary.expense || 0));

    const laba = Number(summary.laba || 0);
    document.getElementById('labaRugiResult').innerHTML =
      `<div class="report-row ${laba >= 0 ? 'total-laba' : 'total-rugi'}">
        <span>${laba >= 0 ? 'Laba Bersih Bulan Ini' : 'Rugi Bersih Bulan Ini'}</span>
        <span>${fmt(Math.abs(laba))}</span>
      </div>`;

    document.getElementById('rekSaldoAwal').textContent = fmt(Number(balance.saldo_awal || 0));
    document.getElementById('rekIn').textContent = fmt(Number(balance.income || 0));
    document.getElementById('rekOut').textContent = fmt(Number(balance.expense || 0));
    document.getElementById('rekSaldoAkhir').textContent = fmt(Number(balance.saldo_akhir || 0));

    renderStatusKamarReport(roomStatus);
  } catch (err) {
    showToast(err.message || 'Gagal memuat laporan.');
  }
}

function renderCategoryRows(targetId, rows, emptyText){
  const target = document.getElementById(targetId);

  if(rows.length === 0){
    target.innerHTML = `<div class="report-row"><span>${emptyText}</span><span>${fmt(0)}</span></div>`;
    return;
  }

  target.innerHTML = rows.map(row => `<div class="report-row">
    <span>${escapeHtml(row.kategori)}</span>
    <span>${fmt(Number(row.total || 0))}</span>
  </div>`).join('');
}

function renderStatusKamarReport(rows){
  const target = document.getElementById('statusKamarTbl');

  if(rows.length === 0){
    target.innerHTML = '<tr><td colspan="3" style="text-align:center;color:var(--muted);padding:18px;">Belum ada data kamar</td></tr>';
    return;
  }

  target.innerHTML = rows.map(k => {
    const status = k.status_pembayaran;
    const remaining = Number(k.sisa_tagihan || 0);
    const badge = status === 'kosong'
      ? '<span class="badge badge-pending">Kosong</span>'
      : status === 'lunas'
        ? '<span class="badge badge-income">Lunas</span>'
        : status === 'sebagian'
          ? '<span class="badge badge-pending">Sebagian</span>'
          : '<span class="badge badge-expense">Belum Bayar</span>';
    const note = status === 'sebagian' || status === 'belum_bayar'
      ? `<span class="payment-status-note">Sisa ${fmt(remaining)}</span>`
      : '';

    return `<tr>
      <td style="font-weight:700;">K.${escapeHtml(k.nomor)}</td>
      <td>${escapeHtml(k.penyewa || '-')}</td>
      <td><div class="payment-status-cell">${badge}${note}</div></td>
    </tr>`;
  }).join('');
}

function formatMonthId(month){
  const [year, monthNumber] = String(month).split('-').map(Number);
  const date = new Date(year, (monthNumber || 1) - 1, 1);
  return date.toLocaleDateString('id-ID', { month: 'long', year: 'numeric' });
}

// Cetak laporan - hanya tampilkan halaman laporan, sidebar disembunyikan via CSS print
function printLaporan(){
  window.print();
}
