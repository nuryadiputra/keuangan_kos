// ============================================================
// FILE   : database.js
// WBS    : 3.3 - Integrasi Database & Koneksi Data
// Sistem : SiKosKu - Sistem Informasi Keuangan Kos
// Fungsi : Helper API, state data master dari MySQL,
//          serta fungsi utilitas global
// ============================================================

// --- KONFIGURASI API ---
const API_BASE_URL = 'api/index.php';

let currentUser = null;
let kategoriData = [];
let kamarData = [];

// --- FUNGSI UTILITAS GLOBAL ---

// Request API backend PHP
async function apiRequest(route, options = {}){
  const headers = {
    Accept: 'application/json',
    ...(options.headers || {})
  };
  const config = { ...options, headers };

  if(config.body && typeof config.body !== 'string'){
    headers['Content-Type'] = 'application/json';
    config.body = JSON.stringify(config.body);
  }

  const res = await fetch(API_BASE_URL + route, config);
  const payload = await res.json().catch(() => null);

  if(!res.ok || !payload || payload.success === false){
    throw new Error(payload?.message || 'Gagal menghubungi server.');
  }

  return payload.data || {};
}

// Ubah format row tabel kamar dari API agar cocok dengan renderer lama
function mapKamarRow(row){
  return {
    id: Number(row.id_kamar),
    no: row.nomor_kamar,
    status: row.status_kamar,
    tenant: row.nama_penyewa || '-',
    harga: row.harga_sewa === null || row.harga_sewa === undefined ? null : Number(row.harga_sewa),
    pembayaran: row.status_pembayaran,
    lunas: row.status_pembayaran === 'lunas',
    sebagian: row.status_pembayaran === 'sebagian',
    totalBayar: Number(row.total_bayar || 0),
    sisaTagihan: Number(row.sisa_tagihan || 0),
    kreditBulanDepan: Number(row.kredit_bulan_depan || 0)
  };
}

function mapKategoriRow(row){
  return {
    id: Number(row.id_kategori),
    nama: row.nama_kategori,
    tipe: row.tipe_kategori
  };
}

// Ambil data master dari MySQL via API
async function loadMasterData(){
  const [kamarResp, kategoriResp] = await Promise.all([
    apiRequest('/kamar'),
    apiRequest('/kategori')
  ]);

  kamarData = (kamarResp.kamar || []).map(mapKamarRow);
  kategoriData = (kategoriResp.kategori || []).map(mapKategoriRow);

  populateKamarOptions();
  populateKategoriOptions();
}

function populateKamarOptions(){
  const select = document.getElementById('txKamar');
  if(!select) return;

  select.innerHTML = '<option value="">&mdash; Pilih Kamar &mdash;</option>' + kamarData.map(k => {
    const nomor = escapeHtml(k.no);
    const tenant = escapeHtml(k.tenant === '-' ? 'Kosong' : k.tenant);
    const label = `Kamar ${nomor} - ${tenant}`;
    return `<option value="Kamar ${nomor}" data-id="${k.id || ''}">${label}</option>`;
  }).join('');
}

function populateKategoriOptions(){
  const txType = document.getElementById('txType')?.value || '';
  populateTransaksiKategoriOptions(txType);

  const filterSelect = document.getElementById('filterKat');
  if(filterSelect){
    filterSelect.innerHTML = '<option value="">Kategori: Semua</option>' + kategoriData.map(k => (
      `<option value="${escapeHtml(k.nama)}" data-id="${k.id}">${escapeHtml(k.nama)}</option>`
    )).join('');
  }
}

function populateTransaksiKategoriOptions(tipeTransaksi = ''){
  const txSelect = document.getElementById('txKategori');
  if(!txSelect) return;

  if(!tipeTransaksi){
    txSelect.innerHTML = '<option value="">&mdash; Pilih Jenis dahulu &mdash;</option>';
    txSelect.disabled = true;
    return;
  }

  const options = kategoriData.filter(k => k.tipe === tipeTransaksi || k.tipe === 'keduanya');
  txSelect.disabled = false;
  txSelect.innerHTML = '<option value="">&mdash; Pilih Kategori &mdash;</option>' + options.map(k => (
      `<option value="${escapeHtml(k.nama)}" data-id="${k.id}">${escapeHtml(k.nama)}</option>`
    )).join('');
}

// Format angka ke format Rupiah
function fmt(n){
  return 'Rp ' + Math.round(n).toLocaleString('id-ID');
}

// Ambil tanggal hari ini format YYYY-MM-DD
function today(){
  const d = new Date();
  return d.getFullYear() + '-' +
    String(d.getMonth() + 1).padStart(2, '0') + '-' +
    String(d.getDate()).padStart(2, '0');
}

// Ambil bulan lokal format YYYY-MM
function currentMonth(){
  const d = new Date();
  return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');
}

// Escape teks sebelum masuk template HTML
function escapeHtml(value){
  return String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}
