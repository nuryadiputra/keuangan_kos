// ============================================================
// FILE   : app.js
// WBS    : SCP-01 - Logika Utama Aplikasi & Navigasi
// Sistem : SiKuKos - Sistem Informasi Keuangan Kos
// Fungsi : Login/logout, navigasi halaman, inisialisasi app,
//          dan render dashboard utama
// ============================================================

// Login - validasi username & password melalui API
async function doLogin(){
  const u = document.getElementById('loginUser').value;
  const p = document.getElementById('loginPass').value;
  const btn = document.querySelector('.btn-login');
  const originalText = btn ? btn.textContent : '';

  if(btn){
    btn.disabled = true;
    btn.textContent = 'Memproses...';
  }

  try {
    const data = await apiRequest('/login', {
      method: 'POST',
      body: { username: u, password: p }
    });

    currentUser = data.user;
    await loadMasterData();

    document.getElementById('loginPage').style.display = 'none';
    document.getElementById('appShell').style.display = 'flex';
    await initApp();
  } catch (err) {
    alert(err.message || 'Login gagal. Pastikan MySQL dan API sudah berjalan.');
  } finally {
    if(btn){
      btn.disabled = false;
      btn.textContent = originalText;
    }
  }
}

// Logout
function doLogout(){
  document.getElementById('appShell').style.display = 'none';
  document.getElementById('loginPage').style.display = 'flex';
  document.getElementById('loginPass').value = 'kos_123';
  resetPasswordToggles(document.getElementById('loginPage'));
}

// Inisialisasi semua modul setelah login berhasil
async function initApp(){
  document.getElementById('curDate').textContent = new Date().toLocaleDateString('id-ID',{weekday:'long',day:'numeric',month:'long',year:'numeric'});
  renderCurrentUser();
  const d = document.getElementById('txDate');
  if(d) d.value = today();
  await loadLaporanBulanOptions(currentMonth());
  renderDashboard();
  renderKamar();
  renderRiwayat();
  renderLaporan();
  updateTodaySummary();
}

function renderCurrentUser(){
  const name = currentUser?.nama || 'Pemilik Kos';
  const userNameEl = document.querySelector('.user-info p');
  const avatarEl = document.querySelector('.user-avatar');

  if(userNameEl) userNameEl.textContent = name;
  if(avatarEl) avatarEl.textContent = name.split(' ').map(part => part[0] || '').join('').slice(0, 2).toUpperCase() || 'PK';
}

// Navigasi antar halaman
function showPage(name, el){
  document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
  document.getElementById('page-' + name).classList.add('active');
  el.classList.add('active');
  const titles = {
    dashboard : 'Dashboard',
    kamar     : 'Data Kamar',
    transaksi : 'Input Transaksi',
    riwayat   : 'Riwayat Transaksi',
    laporan   : 'Laporan Keuangan'
  };
  document.getElementById('topbarTitle').textContent = titles[name] || name;
}

function goToTransaksiPage(){
  const nav = Array.from(document.querySelectorAll('.nav-item')).find(item => item.textContent.includes('Input Transaksi'));
  showPage('transaksi', nav || document.querySelector('.nav-item'));
}

// Render dashboard: statistik + grafik + transaksi terbaru dari database
async function renderDashboard(){
  try {
    const data = await apiRequest('/dashboard?bulan=' + encodeURIComponent(currentMonth()));
    const s = data.summary || { income: 0, expense: 0, laba: 0 };
    const rooms = data.rooms || { terisi: 0, total: 0 };
    const laba = Number(s.laba || 0);

    document.getElementById('dashIncome').textContent = fmt(Number(s.income || 0));
    document.getElementById('dashExpense').textContent = fmt(Number(s.expense || 0));

    const labaEl = document.getElementById('dashLaba');
    labaEl.textContent = fmt(laba);
    labaEl.className = 'value ' + (laba >= 0 ? 'green' : 'red');

    document.getElementById('dashKamar').textContent = `${rooms.terisi || 0} / ${rooms.total || 0}`;
    renderDashboardActionHint();

    renderDashboardChart(data.chart || []);
    renderDashboardRecent(data.recent || []);
  } catch (err) {
    showToast(err.message || 'Gagal memuat dashboard.');
  }
}

function renderDashboardChart(chartData){
  const bc = document.getElementById('barChart');
  const maxV = Math.max(...chartData.map(d => Math.max(Number(d.income || 0), Number(d.expense || 0))), 1);

  if(chartData.length === 0){
    bc.innerHTML = '<div style="width:100%;text-align:center;color:var(--muted);font-size:13px;">Belum ada data grafik</div>';
    return;
  }

  bc.innerHTML = chartData.map(d => {
    const inc = Number(d.income || 0);
    const exp = Number(d.expense || 0);
    const pI = Math.round((inc / maxV) * 140);
    const pE = Math.round((exp / maxV) * 140);
    const label = escapeHtml(d.label);

    return `<div class="bar-col">
      <div style="display:flex;align-items:flex-end;gap:3px;height:140px;">
        <div class="bar" style="height:${pI}px;background:#2563eb;width:14px;" title="Uang Masuk: ${fmt(inc)}"></div>
        <div class="bar" style="height:${pE}px;background:#ef4444;width:14px;" title="Uang Keluar: ${fmt(exp)}"></div>
      </div>
      <div class="bar-label">${label}</div>
    </div>`;
  }).join('');
}

function renderDashboardActionHint(){
  const target = document.getElementById('dashActionHint');
  if(!target) return;

  const belumLunas = kamarData.filter(k => k.status === 'terisi' && !k.lunas).length;
  target.innerHTML = belumLunas > 0
    ? `<strong>${belumLunas} kamar</strong> belum lunas bulan ini.`
    : '<strong>Semua kamar terisi</strong> sudah lunas bulan ini.';
}

function renderDashboardRecent(recent){
  const tbl = document.getElementById('dashRecentTbl');

  if(recent.length === 0){
    tbl.innerHTML = '<tr><td colspan="3" style="text-align:center;color:var(--muted);padding:24px;">Belum ada catatan bulan ini</td></tr>';
    return;
  }

  tbl.innerHTML = recent.slice(0, 5).map(t => {
    const date = String(t.date || '').slice(5).replace('-', '/');
    const note = escapeHtml(shortText(t.note || '-', 30));
    const amount = Number(t.amount || 0);
    const type = t.type === 'pemasukan' ? 'pemasukan' : 'pengeluaran';

    return `<tr>
      <td>${escapeHtml(date)}</td>
      <td>${note}</td>
      <td class="${type === 'pemasukan' ? 'green' : 'red'}" style="font-weight:700;">${type === 'pemasukan' ? '+' : '-'}${fmt(amount)}</td>
    </tr>`;
  }).join('');
}

function shortText(text, max){
  return text.length > max ? text.slice(0, max) + '...' : text;
}

function togglePasswordVisibility(inputId, button){
  const input = document.getElementById(inputId);
  if(!input) return;

  const shouldShow = input.type === 'password';
  input.type = shouldShow ? 'text' : 'password';

  if(button){
    button.classList.toggle('is-visible', shouldShow);
    button.setAttribute('aria-pressed', String(shouldShow));
    button.setAttribute('aria-label', shouldShow ? 'Sembunyikan password' : 'Tampilkan password');
  }
}

function resetPasswordToggles(scope = document){
  scope.querySelectorAll('.password-toggle').forEach(button => {
    const input = button.closest('.password-field')?.querySelector('input');
    if(input) input.type = 'password';

    button.classList.remove('is-visible');
    button.setAttribute('aria-pressed', 'false');
    button.setAttribute('aria-label', 'Tampilkan password');
  });
}

let passwordModalMode = 'session';

function openPasswordModal(){
  passwordModalMode = 'session';
  document.getElementById('passwordForm').reset();
  document.getElementById('usernameBaru').value = '';
  hidePasswordAlert();
  resetPasswordToggles(document.getElementById('passwordModal'));
  document.getElementById('passwordModalTitle').textContent = 'Ubah Username & Password';
  document.getElementById('passwordUsernameWrap').style.display = 'none';
  document.getElementById('passwordUsername').required = false;
  document.getElementById('passwordModal').classList.add('show');
  document.getElementById('oldPassword').focus();
}

function openLoginPasswordModal(){
  passwordModalMode = 'login';
  document.getElementById('passwordForm').reset();
  document.getElementById('usernameBaru').value = '';
  hidePasswordAlert();
  resetPasswordToggles(document.getElementById('passwordModal'));
  document.getElementById('passwordModalTitle').textContent = 'Ganti Username & Password';

  const usernameInput = document.getElementById('passwordUsername');
  usernameInput.value = document.getElementById('loginUser').value.trim();
  usernameInput.required = true;
  document.getElementById('passwordUsernameWrap').style.display = 'flex';
  document.getElementById('oldPassword').value = document.getElementById('loginPass').value;

  document.getElementById('passwordModal').classList.add('show');
  (usernameInput.value ? document.getElementById('oldPassword') : usernameInput).focus();
}

function closePasswordModal(){
  document.getElementById('passwordModal').classList.remove('show');
}

function handlePasswordModalBackdrop(event){
  if(event.target.id === 'passwordModal') closePasswordModal();
}

async function submitPasswordForm(event){
  event.preventDefault();

  if(passwordModalMode === 'session' && !currentUser?.id_user){
    showPasswordAlert('Sesi login tidak ditemukan. Silakan login ulang.', 'warning');
    return;
  }

  const username = document.getElementById('passwordUsername').value.trim();
  const oldPassword = document.getElementById('oldPassword').value;
  const newPassword = document.getElementById('newPassword').value;
  const confirmPassword = document.getElementById('confirmPassword').value;
  const btn = document.getElementById('passwordSubmitBtn');
  const originalText = btn.textContent;

  if(passwordModalMode === 'login' && !username){
    showPasswordAlert('Username wajib diisi.', 'warning');
    return;
  }

  if(newPassword.length < 6){
    showPasswordAlert('Password baru minimal 6 karakter.', 'warning');
    return;
  }

  if(newPassword !== confirmPassword){
    showPasswordAlert('Konfirmasi password tidak sama.', 'warning');
    return;
  }

  try {
    btn.disabled = true;
    btn.textContent = 'Menyimpan...';

    const usernameBaru = document.getElementById('usernameBaru').value.trim();

    const body = {
      password_lama: oldPassword,
      password_baru: newPassword,
      konfirmasi_password: confirmPassword
    };

    if(usernameBaru) body.username_baru = usernameBaru;

    if(passwordModalMode === 'login'){
      body.username = username;
    } else {
      body.id_user = Number(currentUser.id_user);
    }

    const result = await apiRequest('/password/change', {
      method: 'POST',
      body
    });

    const usernameAkhir = result.username || usernameBaru || username || currentUser?.username;

    closePasswordModal();
    if(passwordModalMode === 'login'){
      document.getElementById('loginUser').value = usernameAkhir || '';
      document.getElementById('loginPass').value = '';
      const pesanLogin = usernameBaru
        ? 'Username & password berhasil diubah. Silakan login dengan data baru.'
        : 'Password berhasil diubah. Silakan login dengan password baru.';
      showToast(pesanLogin);
    } else {
      // Update currentUser & tampilan sidebar jika username berubah
      if(usernameBaru && currentUser) {
        currentUser.username = usernameAkhir;
        const elUsername = document.getElementById('sidebarUsername');
        if(elUsername) elUsername.textContent = usernameAkhir;
      }
      const pesanSession = usernameBaru
        ? 'Username & password berhasil diubah.'
        : 'Password berhasil diubah.';
      showToast(pesanSession);
    }
  } catch (err) {
    showPasswordAlert(err.message || 'Gagal mengubah password.', 'warning');
  } finally {
    btn.disabled = false;
    btn.textContent = originalText;
  }
}

async function resetPasswordDefault(){
  if(!currentUser?.id_user){
    showToast('Sesi login tidak ditemukan.');
    return;
  }

  if(!confirm('Reset password ke default kos_123?')) return;

  try {
    await apiRequest('/password/reset-default', {
      method: 'POST',
      body: { id_user: Number(currentUser.id_user) }
    });

    document.getElementById('loginPass').value = 'kos_123';
  } catch (err) {
    showToast(err.message || 'Gagal reset password.');
  }
}

function showPasswordAlert(message, type){
  const alertEl = document.getElementById('passwordAlert');
  alertEl.style.display = 'flex';
  alertEl.className = type === 'success' ? 'alert alert-success' : 'alert alert-warning';
  alertEl.textContent = message;
}

function hidePasswordAlert(){
  const alertEl = document.getElementById('passwordAlert');
  alertEl.style.display = 'none';
  alertEl.textContent = '';
}

// Notifikasi toast pojok kanan bawah
function showToast(msg){
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), 2500);
}

// Enter key untuk login
document.getElementById('loginPass').addEventListener('keydown', e => {
  if(e.key === 'Enter') doLogin();
});
