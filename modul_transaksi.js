// ============================================================
// FILE   : modul_transaksi.js
// WBS    : 3.1 - Coding Modul Transaksi & Fitur Kalkulasi Otomatis
// Sistem : SiKosKu - Sistem Informasi Keuangan Kos
// Fungsi : Input transaksi baru, validasi form, kalkulasi saldo
//          otomatis real-time, dan ringkasan transaksi harian
// ============================================================

// Tampilkan/sembunyikan field Kamar berdasarkan jenis transaksi
const RIWAYAT_LIMIT = 8;
let rentPreviewSeq = 0;

function updateTxForm(){
  const t = document.getElementById('txType').value;
  document.getElementById('kamarFieldWrap').style.display = (t === 'pengeluaran') ? 'none' : 'flex';
  const expenseHint = document.getElementById('txExpenseHint');
  if(expenseHint) expenseHint.style.display = t === 'pengeluaran' ? 'block' : 'none';
  populateTransaksiKategoriOptions(t);
  updateTxNotePlaceholder();
  updateRentPreview();
}

// Simpan transaksi baru ke MySQL via API
async function submitTransaksi(){
  const date = document.getElementById('txDate').value;
  const type = document.getElementById('txType').value;
  const kategoriEl = document.getElementById('txKategori');
  const kategori = kategoriEl.value;
  const kategoriId = Number(kategoriEl.selectedOptions[0]?.dataset.id || 0);
  const amount = parseFloat(document.getElementById('txAmount').value) || 0;
  const noteEl = document.getElementById('txNote');
  const note = noteEl.value.trim() || buildTxNote();
  const metode = document.getElementById('txMetode').value;
  const kamarEl = document.getElementById('txKamar');
  const selectedKamarId = Number(kamarEl.selectedOptions[0]?.dataset.id || 0);
  const kamarId = type === 'pengeluaran' || !selectedKamarId ? null : selectedKamarId;
  const isRentPayment = type === 'pemasukan' && kategori === 'Sewa Bulanan';
  const btn = document.querySelector('button[onclick="submitTransaksi()"]');
  const originalText = btn ? btn.textContent : '';

  if(!currentUser?.id_user){
    showTxAlert('Sesi login tidak ditemukan. Silakan login ulang.', 'warning');
    return;
  }

  const validationErrors = validateTxForm({ date, type, kategori, kategoriId, amount, isRentPayment, kamarId });
  if(validationErrors.length > 0){
    showTxAlert(validationErrors.join(' '), 'warning');
    return;
  }

  if(!noteEl.value.trim()){
    noteEl.value = note;
  }

  if(btn){
    btn.disabled = true;
    btn.textContent = 'Menyimpan...';
  }

  try {
    await apiRequest('/transaksi', {
      method: 'POST',
      body: {
        id_user: Number(currentUser.id_user),
        id_kamar: kamarId,
        id_kategori: kategoriId,
        tanggal: date,
        tipe_transaksi: type,
        nominal: amount,
        metode_pembayaran: metode,
        keterangan: note
      }
    });

    resetTxForm(date);
    await loadMasterData();
    renderKamar();
    await updateTodaySummary();
    await renderDashboard();
    resetRiwayatFilters();
    await renderRiwayat(1);
    await loadLaporanBulanOptions(date.slice(0, 7));
    await renderLaporan();
    showTxAlert('Catatan berhasil disimpan.', 'success');
    showToast('Catatan disimpan!');
  } catch (err) {
    showTxAlert(err.message || 'Transaksi gagal disimpan.', 'warning');
  } finally {
    if(btn){
      btn.disabled = false;
      btn.textContent = originalText;
    }
  }
}

// Reset form ke kondisi awal
function resetTxForm(summaryDate = today()){
  document.getElementById('txDate').value = summaryDate;
  document.getElementById('txType').value = '';
  document.getElementById('txKategori').value = '';
  document.getElementById('txAmount').value = '';
  document.getElementById('txNote').value = '';
  document.getElementById('txKamar').value = '';
  document.getElementById('txMetode').value = 'Tunai';
  updateTxForm();
}

function validateTxForm({ date, type, kategori, kategoriId, amount, isRentPayment, kamarId }){
  const errors = [];

  if(!date) errors.push('Pilih tanggal transaksi.');
  if(!type) errors.push('Pilih jenis catatan.');
  if(!kategori || !kategoriId) errors.push('Pilih kategori.');
  if(amount <= 0) errors.push('Masukkan jumlah lebih dari Rp 0.');
  if(isRentPayment && !kamarId) errors.push('Pilih kamar untuk pembayaran sewa.');

  return errors;
}

function updateTxNotePlaceholder(){
  const noteEl = document.getElementById('txNote');
  if(!noteEl) return;

  noteEl.placeholder = buildTxNotePlaceholder();
}

function buildTxNotePlaceholder(){
  const note = buildTxNote();
  return note ? `Contoh: ${note}` : 'Keterangan boleh dikosongkan, sistem akan mengisi otomatis.';
}

function buildTxNote(){
  const date = document.getElementById('txDate')?.value || today();
  const type = document.getElementById('txType')?.value || '';
  const kategori = document.getElementById('txKategori')?.value || '';
  const kamarEl = document.getElementById('txKamar');
  const kamarText = kamarEl?.selectedOptions[0]?.textContent || '';
  const kamarMatch = kamarText.match(/Kamar\s+([^\s-]+)/i);
  const kamar = kamarMatch ? `Kamar ${kamarMatch[1]}` : '';
  const month = formatTxMonthId(date.slice(0, 7));

  if(!type || !kategori) return '';
  if(type === 'pemasukan' && kategori === 'Sewa Bulanan'){
    return kamar ? `Sewa Bulanan ${kamar} - ${month}` : `Sewa Bulanan - ${month}`;
  }
  if(type === 'pemasukan'){
    return `Uang masuk ${kategori}`;
  }
  return `Biaya ${kategori}`;
}

async function updateRentPreview(){
  const wrap = document.getElementById('txBillingPreview');
  if(!wrap) return;

  updateTxNotePlaceholder();

  const date = document.getElementById('txDate')?.value || today();
  const type = document.getElementById('txType')?.value || '';
  const kategori = document.getElementById('txKategori')?.value || '';
  const kamarEl = document.getElementById('txKamar');
  const kamarId = Number(kamarEl?.selectedOptions[0]?.dataset.id || 0);

  if(type !== 'pemasukan' || kategori !== 'Sewa Bulanan' || !date || !kamarId){
    wrap.style.display = 'none';
    wrap.innerHTML = '';
    return;
  }

  const seq = ++rentPreviewSeq;
  wrap.style.display = 'block';
  wrap.innerHTML = '<div style="font-size:13px;color:var(--muted);">Memuat tagihan sewa...</div>';

  try {
    const data = await apiRequest('/sewa/status?id_kamar=' + encodeURIComponent(kamarId) + '&tanggal=' + encodeURIComponent(date));
    if(seq !== rentPreviewSeq) return;

    const billing = data.billing || {};
    renderRentPreview(billing);
  } catch (err) {
    if(seq !== rentPreviewSeq) return;
    wrap.innerHTML = `<div style="font-size:13px;color:var(--danger);">${escapeHtml(err.message || 'Gagal memuat tagihan sewa.')}</div>`;
  }
}

function renderRentPreview(billing){
  const wrap = document.getElementById('txBillingPreview');
  const amount = parseFloat(document.getElementById('txAmount')?.value) || 0;
  const rent = Number(billing.harga_sewa || 0);
  const paid = Number(billing.total_bayar || 0);
  const existingCredit = Number(billing.kredit_bulan_depan || 0);
  const availableAfterInput = paid + existingCredit + amount;
  const paidAfterInput = Math.min(rent, availableAfterInput);
  const remainingAfterInput = Math.max(0, rent - paidAfterInput);
  const creditAfterInput = Math.max(0, availableAfterInput - rent);
  const statusAfterInput = rent > 0 && paidAfterInput >= rent
    ? 'lunas'
    : paidAfterInput > 0
      ? 'sebagian'
      : 'belum_bayar';
  const month = billing.bulan ? formatTxMonthId(billing.bulan) : '-';
  const room = billing.nomor_kamar ? `K.${escapeHtml(billing.nomor_kamar)}` : '-';
  const paidLabel = amount > 0 ? 'Dibayar Setelah Input' : 'Sudah Dibayar';
  const remainingLabel = amount > 0 ? 'Sisa Setelah Input' : 'Sisa Tagihan';
  const badgeClass = rentStatusBadgeClass(statusAfterInput);

  wrap.innerHTML = `<div class="tx-billing-head">
    <strong>Tagihan Sewa ${room} - Periode ${escapeHtml(month)}</strong>
    <span class="badge ${badgeClass}">${rentStatusLabel(statusAfterInput)}</span>
  </div>
  <div class="form-hint" style="margin-bottom:10px;">Periode tagihan dihitung dari bulan pada Tanggal Transaksi.</div>
  <div class="tx-billing-grid">
    <div class="tx-billing-item"><span>Harga Sewa</span><b>${fmt(rent)}</b></div>
    <div class="tx-billing-item"><span>${paidLabel}</span><b>${fmt(paidAfterInput)}</b></div>
    <div class="tx-billing-item"><span>${remainingLabel}</span><b>${fmt(remainingAfterInput)}</b></div>
    <div class="tx-billing-item"><span>Titipan Bulan Berikutnya</span><b>${fmt(creditAfterInput)}</b></div>
    <div class="tx-billing-item"><span>Status</span><b>${rentStatusLabel(statusAfterInput)}</b></div>
  </div>`;
}

function rentStatusLabel(status){
  if(status === 'lunas') return 'Lunas';
  if(status === 'sebagian') return 'Sebagian';
  if(status === 'kosong') return 'Kosong';
  return 'Belum Bayar';
}

function rentStatusBadgeClass(status){
  if(status === 'lunas') return 'badge-income';
  if(status === 'sebagian' || status === 'kosong') return 'badge-pending';
  return 'badge-expense';
}

// Hitung & tampilkan ringkasan transaksi untuk hari ini.
async function updateTodaySummary(){
  try {
    const data = await apiRequest('/ringkasan-hari-ini?tanggal=' + encodeURIComponent(today()));
    const s = data.summary || { income: 0, expense: 0, laba: 0 };

    document.getElementById('todayIn').textContent = fmt(Number(s.income || 0));
    document.getElementById('todayOut').textContent = fmt(Number(s.expense || 0));

    const laba = Number(s.laba || 0);
    const el = document.getElementById('todaySaldo');
    el.textContent = fmt(laba);
    el.className = 'value ' + (laba >= 0 ? 'blue' : 'red');
  } catch (err) {
    showToast(err.message || 'Gagal memuat ringkasan hari ini.');
  }
}

function showTxAlert(message, type){
  const al = document.getElementById('txAlert');
  al.style.display = 'flex';
  al.className = type === 'success' ? 'alert alert-success' : 'alert alert-warning';
  al.textContent = message;
  setTimeout(() => al.style.display = 'none', 3000);
}

function resetRiwayatFilters(){
  const filterType = document.getElementById('filterType');
  const filterKat = document.getElementById('filterKat');
  if(filterType) filterType.value = '';
  if(filterKat) filterKat.value = '';
}

// Render tabel riwayat transaksi dengan filter dan paginasi dari database
async function renderRiwayat(page = 1){
  const ft = document.getElementById('filterType').value;
  const filterKat = document.getElementById('filterKat');
  const kategoriId = filterKat.selectedOptions[0]?.dataset.id || '';
  const currentPage = Math.max(1, Number(page) || 1);
  const params = new URLSearchParams({
    page: String(currentPage),
    limit: String(RIWAYAT_LIMIT)
  });

  if(ft) params.set('jenis', ft);
  if(kategoriId) params.set('kategori', kategoriId);

  const tb = document.getElementById('riwayatTbl');
  tb.innerHTML = '<tr><td colspan="8" style="text-align:center;color:var(--muted);padding:24px;">Memuat catatan...</td></tr>';

  try {
    const data = await apiRequest('/transaksi?' + params.toString());
    const list = data.transaksi || [];
    const pagination = data.pagination || { page: currentPage, limit: RIWAYAT_LIMIT, total: 0, total_pages: 1 };
    const s = data.summary || { income: 0, expense: 0, laba: 0 };
    const total = Number(pagination.total || 0);
    const activePage = Number(pagination.page || currentPage);
    const activeLimit = Number(pagination.limit || RIWAYAT_LIMIT);
    const start = total === 0 ? 0 : ((activePage - 1) * activeLimit) + 1;
    const end = Math.min(activePage * activeLimit, total);

    document.getElementById('txCount').textContent = total === 0
      ? '0 catatan'
      : `Menampilkan ${start}-${end} dari ${total} catatan`;

    if(list.length === 0){
      tb.innerHTML = '<tr><td colspan="8" style="text-align:center;color:var(--muted);padding:24px;">Tidak ada catatan</td></tr>';
    } else {
      const startNo = ((pagination.page || 1) - 1) * (pagination.limit || RIWAYAT_LIMIT);
      tb.innerHTML = list.map((t,i) => `<tr>
        <td>${startNo + i + 1}</td>
        <td>${formatDateId(t.date)}</td>
        <td><span class="badge ${t.type === 'pemasukan' ? 'badge-income' : 'badge-expense'}">${escapeHtml(formatTxTypeLabel(t.type))}</span></td>
        <td>${escapeHtml(t.kategori)}</td>
        <td>${escapeHtml(t.kamar)}</td>
        <td>${escapeHtml(t.note)}</td>
        <td class="${t.type === 'pemasukan' ? 'green' : 'red'}" style="font-weight:700;">${t.type === 'pemasukan' ? '+' : '-'}${fmt(Number(t.amount || 0))}</td>
        <td>${escapeHtml(t.metode)}</td>
      </tr>`).join('');
    }

    document.getElementById('rwIn').textContent = fmt(Number(s.income || 0));
    document.getElementById('rwOut').textContent = fmt(Number(s.expense || 0));
    const el = document.getElementById('rwSaldo');
    const saldo = Number(s.laba || 0);
    el.textContent = fmt(saldo);
    el.className = 'value ' + (saldo >= 0 ? 'blue' : 'red');

    renderRiwayatPagination(pagination);
  } catch (err) {
    tb.innerHTML = '<tr><td colspan="8" style="text-align:center;color:var(--muted);padding:24px;">Gagal memuat catatan</td></tr>';
    showToast(err.message || 'Gagal memuat daftar transaksi.');
  }
}

function formatTxTypeLabel(type){
  return type === 'pemasukan' ? 'Uang Masuk' : 'Uang Keluar';
}

function formatTxMonthId(month){
  const [year, monthNumber] = String(month).split('-').map(Number);
  const date = new Date(year, (monthNumber || 1) - 1, 1);
  return date.toLocaleDateString('id-ID', { month: 'long', year: 'numeric' });
}

function renderRiwayatPagination(pagination){
  const wrap = document.getElementById('riwayatPagination');
  if(!wrap) return;

  const page = Number(pagination.page || 1);
  const total = Number(pagination.total || 0);
  const limit = Number(pagination.limit || RIWAYAT_LIMIT);
  const totalPages = Math.max(1, Number(pagination.total_pages || Math.ceil(total / limit) || 1));

  if(totalPages <= 1){
    wrap.innerHTML = '';
    return;
  }

  const pages = getRiwayatPageNumbers(page, totalPages).map(item => {
    if(item === '...'){
      return '<span style="font-size:13px;color:var(--muted);padding:0 2px;">...</span>';
    }

    const activeStyle = item === page ? 'background:var(--accent);color:#fff;border-color:var(--accent);' : '';
    return `<button class="btn btn-outline" style="min-width:36px;justify-content:center;${activeStyle}" onclick="renderRiwayat(${item})">${item}</button>`;
  }).join('');

  wrap.innerHTML = `<span style="font-size:13px;color:var(--muted);margin-right:auto;">${total} catatan, ${limit} per halaman</span>
    <button class="btn btn-outline" ${page <= 1 ? 'disabled' : ''} onclick="renderRiwayat(${page - 1})">Sebelumnya</button>
    ${pages}
    <button class="btn btn-outline" ${page >= totalPages ? 'disabled' : ''} onclick="renderRiwayat(${page + 1})">Berikutnya</button>`;
}

function getRiwayatPageNumbers(page, totalPages){
  if(totalPages <= 5){
    return Array.from({ length: totalPages }, (_, index) => index + 1);
  }

  const pages = [1];
  const start = Math.max(2, page - 1);
  const end = Math.min(totalPages - 1, page + 1);

  if(start > 2) pages.push('...');
  for(let i = start; i <= end; i++) pages.push(i);
  if(end < totalPages - 1) pages.push('...');
  pages.push(totalPages);

  return pages;
}

function formatDateId(date){
  return new Date(date).toLocaleDateString('id-ID',{day:'2-digit',month:'short',year:'numeric'});
}
