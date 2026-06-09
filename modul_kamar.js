// ============================================================
// FILE   : modul_kamar.js
// WBS    : 3.4 - Coding Modul Data Kamar dan Status Hunian
// Sistem : SiKosKu - Sistem Informasi Keuangan Kos
// Fungsi : Menampilkan grid status kamar (terisi/kosong/tunggakan)
//          beserta info penyewa, dan memperbarui status otomatis
//          setiap kali ada transaksi sewa baru masuk (SCP-05)
// ============================================================

// Render grid kartu kamar dengan status terkini
function renderKamar(){
  const grid = document.getElementById('kamarGrid');

  if(kamarData.length === 0){
    grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;color:var(--muted);padding:24px;border:1px dashed var(--border);border-radius:12px;background:var(--card);">Belum ada data kamar</div>';
  } else {
    grid.innerHTML = kamarData.map(k => {
    // Tentukan kelas warna border atas kartu
    const cls = k.status === 'kosong' ? 'kosong' : (k.lunas ? 'terisi' : 'tunggakan');
    const no = escapeHtml(k.no);
    const tenant = escapeHtml(k.tenant || '-');
    const harga = k.harga === null ? '-' : `${fmt(k.harga)}/bln`;

    // Label status dengan indikator warna
    const statusLabel = k.status === 'kosong'
      ? '<span class="kamar-status" style="color:var(--muted)">Kosong</span>'
      : k.lunas
        ? '<span class="kamar-status green">Lunas</span>'
        : k.sebagian
          ? `<span class="kamar-status amber">Sebagian - Sisa ${fmt(k.sisaTagihan || 0)}</span>`
        : '<span class="kamar-status red">Belum Bayar</span>';

    return `<div class="kamar-card ${cls}">
      <div class="kamar-card-head">
        <div class="kamar-no">K.${no}</div>
        <div class="kamar-actions">
          <button class="btn btn-outline btn-sm kamar-action-btn" type="button" onclick="openEditKamarModal(${Number(k.id)}); event.stopPropagation();" title="Edit kamar" aria-label="Edit kamar ${no}">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 013 3L7 19l-4 1 1-4 12.5-12.5z"/></svg>
          </button>
          <button class="btn btn-danger btn-sm kamar-action-btn" type="button" onclick="deleteKamar(${Number(k.id)}); event.stopPropagation();" title="Hapus kamar" aria-label="Hapus kamar ${no}">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 011-1h4a1 1 0 011 1v2"/></svg>
          </button>
        </div>
      </div>
      ${statusLabel}
      <div class="kamar-tenant">${tenant}</div>
      <div class="kamar-harga">${harga}</div>
    </div>`;
    }).join('');
  }

  // Update ringkasan statistik kamar
  const total = kamarData.length;
  const terisi  = kamarData.filter(k => k.status === 'terisi').length;
  const kosong  = kamarData.filter(k => k.status === 'kosong').length;
  const tunggak = kamarData.filter(k => k.status === 'terisi' && !k.lunas).length;

  document.getElementById('kamarTotal').textContent = total;
  document.getElementById('kamarTerisi').textContent = terisi;
  document.getElementById('kamarKosong').textContent = kosong;
  document.getElementById('kamarTunggak').textContent = tunggak;
}

function openCreateKamarModal(){
  document.getElementById('kamarModalTitle').textContent = 'Tambah Kamar';
  document.getElementById('kamarSubmitBtn').textContent = 'Simpan';
  document.getElementById('kamarForm').reset();
  document.getElementById('kamarId').value = '';
  document.getElementById('kamarStatus').value = 'terisi';
  document.getElementById('kamarPembayaran').value = 'belum_bayar';
  syncKamarFormState();
  document.getElementById('kamarModal').classList.add('show');
  document.getElementById('kamarNo').focus();
}

function openEditKamarModal(id){
  const kamar = kamarData.find(k => Number(k.id) === Number(id));
  if(!kamar){
    showToast('Data kamar tidak ditemukan.');
    return;
  }

  document.getElementById('kamarModalTitle').textContent = 'Edit Kamar';
  document.getElementById('kamarSubmitBtn').textContent = 'Update';
  document.getElementById('kamarId').value = kamar.id;
  document.getElementById('kamarNo').value = kamar.no || '';
  document.getElementById('kamarStatus').value = kamar.status || 'terisi';
  document.getElementById('kamarTenant').value = kamar.tenant === '-' ? '' : (kamar.tenant || '');
  document.getElementById('kamarHarga').value = kamar.harga || '';
  document.getElementById('kamarPembayaran').value = kamar.lunas ? 'lunas' : 'belum_bayar';
  syncKamarFormState();
  if(kamar.sebagian){
    document.getElementById('kamarPembayaranInfo').textContent = 'Sebagian - dihitung dari transaksi sewa';
  }
  document.getElementById('kamarModal').classList.add('show');
  document.getElementById('kamarNo').focus();
}

function closeKamarModal(){
  document.getElementById('kamarModal').classList.remove('show');
}

function handleKamarModalBackdrop(event){
  if(event.target.id === 'kamarModal') closeKamarModal();
}

function syncKamarFormState(){
  const status = document.getElementById('kamarStatus').value;
  const tenant = document.getElementById('kamarTenant');
  const pembayaran = document.getElementById('kamarPembayaran');

  tenant.required = status === 'terisi';
  tenant.disabled = status === 'kosong';

  if(status === 'kosong'){
    tenant.value = '';
    pembayaran.value = 'belum_bayar';
  }

  updateKamarPaymentInfo(status);
}

function updateKamarPaymentInfo(status){
  const info = document.getElementById('kamarPembayaranInfo');
  const pembayaran = document.getElementById('kamarPembayaran');
  if(!info || !pembayaran) return;

  if(status === 'kosong'){
    info.textContent = 'Kosong - tidak ada tagihan aktif';
    pembayaran.value = 'belum_bayar';
    return;
  }

  info.textContent = pembayaran.value === 'lunas'
    ? 'Lunas - dihitung dari transaksi sewa'
    : 'Belum Bayar - dihitung dari transaksi sewa';
}

async function submitKamarForm(event){
  event.preventDefault();

  const id = document.getElementById('kamarId').value;
  const status = document.getElementById('kamarStatus').value;
  const submitBtn = document.getElementById('kamarSubmitBtn');
  const originalText = submitBtn.textContent;
  const payload = {
    nomor_kamar: document.getElementById('kamarNo').value.trim(),
    status_kamar: status,
    nama_penyewa: status === 'kosong' ? '' : document.getElementById('kamarTenant').value.trim(),
    harga_sewa: document.getElementById('kamarHarga').value,
    status_pembayaran: document.getElementById('kamarPembayaran').value
  };

  if(id) payload.id_kamar = Number(id);

  try {
    submitBtn.disabled = true;
    submitBtn.textContent = 'Menyimpan...';

    await apiRequest(id ? '/kamar/update' : '/kamar', {
      method: 'POST',
      body: payload
    });

    closeKamarModal();
    await refreshKamarViews();
    showToast(id ? 'Kamar berhasil diupdate.' : 'Kamar berhasil ditambahkan.');
  } catch (err) {
    showToast(err.message || 'Gagal menyimpan kamar.');
  } finally {
    submitBtn.disabled = false;
    submitBtn.textContent = originalText;
  }
}

async function deleteKamar(id){
  const kamar = kamarData.find(k => Number(k.id) === Number(id));
  if(!kamar){
    showToast('Data kamar tidak ditemukan.');
    return;
  }

  if(!confirm(`Hapus kamar K.${kamar.no}?`)) return;

  try {
    await apiRequest('/kamar/delete', {
      method: 'POST',
      body: { id_kamar: Number(id) }
    });

    await refreshKamarViews();
    showToast('Kamar berhasil dihapus.');
  } catch (err) {
    showToast(err.message || 'Gagal menghapus kamar.');
  }
}

async function refreshKamarViews(){
  await loadMasterData();
  renderKamar();
  renderDashboard();
  renderLaporan();
}
