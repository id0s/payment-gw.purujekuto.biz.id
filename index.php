<?php
/**
 * RFID Payment Gateway - Public Portal
 * Halaman utama untuk user melakukan cek saldo dan request top-up QRIS.
 */
require_once __DIR__ . '/config.php';

// Jika admin sudah login dan ingin masuk ke portal publik, biarkan saja.
// Tapi sediakan link kembali ke dashboard.
$isAdminLoggedIn = isset($_SESSION['admin_id']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal Publik - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__ . '/assets/css/style.css') ?>">
    <style>
        /* ── Style Tambahan untuk Portal Publik ── */
        .portal-container {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 2rem 1.5rem;
        }

        .portal-card {
            width: 100%;
            max-width: 520px;
            background: var(--bg-card);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-xl);
            padding: 2rem;
            box-shadow: var(--shadow-lg);
            animation: slideUp 0.5s ease;
        }

        .portal-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .portal-logo {
            width: 56px;
            height: 56px;
            background: var(--accent-gradient);
            border-radius: var(--radius-md);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            margin-bottom: 0.75rem;
            box-shadow: var(--shadow-glow);
            animation: pulse 2s ease-in-out infinite;
        }

        .portal-header h1 {
            font-size: 1.4rem;
            font-weight: 800;
            background: var(--accent-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .portal-header p {
            color: var(--text-secondary);
            font-size: 0.85rem;
            margin-top: 0.25rem;
        }

        /* Tabs Navigation */
        .portal-tabs {
            display: flex;
            background: rgba(255, 255, 255, 0.04);
            padding: 4px;
            border-radius: var(--radius-md);
            margin-bottom: 1.75rem;
            border: 1px solid var(--border-color);
        }

        .portal-tab-btn {
            flex: 1;
            padding: 0.75rem;
            background: none;
            border: none;
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 0.85rem;
            border-radius: var(--radius-sm);
            cursor: pointer;
            transition: all var(--transition-fast);
        }

        .portal-tab-btn:hover {
            color: var(--text-primary);
        }

        .portal-tab-btn.active {
            background: var(--bg-glass-hover);
            color: var(--text-primary);
            box-shadow: var(--shadow-sm);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* Balance Info Box */
        .balance-result-box {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 1.25rem;
            margin-top: 1.5rem;
            display: none;
            animation: fadeIn 0.3s ease;
        }

        .balance-value {
            font-size: 2rem;
            font-weight: 800;
            color: var(--success);
            margin: 0.5rem 0;
        }

        /* Nominal Grid Helper */
        .nominal-pills {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.5rem;
            margin-bottom: 1.25rem;
        }

        .nominal-pill {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            padding: 0.6rem;
            font-size: 0.8rem;
            font-weight: 600;
            text-align: center;
            cursor: pointer;
            transition: all var(--transition-fast);
            color: var(--text-secondary);
        }

        .nominal-pill:hover {
            background: rgba(255, 255, 255, 0.08);
            color: var(--text-primary);
        }

        .nominal-pill.active {
            background: var(--accent-primary-glow);
            border-color: var(--accent-primary);
            color: var(--text-primary);
        }

        /* QRIS Area */
        .qris-display-box {
            text-align: center;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            margin-top: 1.5rem;
            display: none;
            animation: fadeIn 0.3s ease;
        }

        .qris-image-container {
            background: white;
            padding: 1rem;
            border-radius: var(--radius-md);
            display: inline-block;
            margin: 1rem 0;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
        }

        .qris-image-container img {
            display: block;
            max-width: 100%;
            height: auto;
        }

        .countdown-timer {
            font-weight: 700;
            color: var(--danger);
            font-size: 1.1rem;
            margin: 0.5rem 0;
        }

        .navbar-top {
            position: absolute;
            top: 1.5rem;
            right: 1.5rem;
            display: flex;
            gap: 0.75rem;
        }

        /* Success screen modal replacement */
        .payment-status-box {
            text-align: center;
            padding: 2rem 1rem;
        }

        .success-icon {
            font-size: 3.5rem;
            color: var(--success);
            margin-bottom: 1rem;
            animation: bounce 1s ease infinite;
        }

        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-8px); }
        }
    </style>
</head>
<body>

    <!-- Navtop login admin / dashboard -->
    <div class="navbar-top">
        <?php if ($isAdminLoggedIn): ?>
            <a href="pages/dashboard.php" class="btn btn-ghost btn-sm">📊 Dashboard Admin</a>
        <?php else: ?>
            <a href="pages/login.php" class="btn btn-ghost btn-sm">🔒 Login Admin</a>
        <?php endif; ?>
    </div>

    <div class="portal-container">
        <div class="portal-card">
            
            <div class="portal-header">
                <div class="portal-logo">⚡</div>
                <h1><?= APP_NAME ?></h1>
                <p>Cek saldo dan isi ulang kartu RFID Anda secara instan</p>
            </div>

            <!-- Portal Tabs -->
            <div class="portal-tabs">
                <button class="portal-tab-btn active" onclick="switchTab('check')">🔍 Cek Saldo</button>
                <button class="portal-tab-btn" onclick="switchTab('topup')">💳 Isi Saldo</button>
            </div>

            <!-- TAB 1: CEK SALDO -->
            <div id="tab-check" class="tab-content active">
                <form id="check-balance-form" onsubmit="handleCheckBalance(event)">
                    <div class="form-group">
                        <label class="form-label" for="check_rfid_uid">Nomor Kartu (RFID UID)</label>
                        <div style="display: flex; gap: 0.5rem;">
                            <input type="text" id="check_rfid_uid" class="form-control" placeholder="Scan kartu atau masukkan UID..." required autocomplete="off">
                            <button type="button" class="btn btn-ghost" onclick="App.scanNfc('check_rfid_uid')" style="white-space: nowrap; padding: 0.7rem 1rem;">📱 NFC</button>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">🔍 Periksa Saldo</button>
                </form>

                <!-- Box Hasil Cek Saldo -->
                <div id="check-result" class="balance-result-box">
                    <div style="font-size: 0.85rem; color: var(--text-secondary);">Pemegang Kartu:</div>
                    <div style="font-weight: 700; font-size: 1.1rem; color: var(--text-primary);" id="result-name">-</div>
                    
                    <div style="margin-top: 1rem; font-size: 0.85rem; color: var(--text-secondary);">Saldo Saat Ini:</div>
                    <div class="balance-value" id="result-balance">Rp 0</div>
                    
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 1rem; border-top: 1px solid var(--border-color); padding-top: 0.75rem;">
                        <span style="font-size: 0.8rem; color: var(--text-muted);" id="result-uid">UID: -</span>
                        <span id="result-status" class="badge">-</span>
                    </div>
                </div>
            </div>

            <!-- TAB 2: ISI SALDO -->
            <div id="tab-topup" class="tab-content">
                <form id="request-topup-form" onsubmit="handleRequestTopup(event)">
                    <div class="form-group">
                        <label class="form-label" for="topup_rfid_uid">Nomor Kartu (RFID UID)</label>
                        <div style="display: flex; gap: 0.5rem;">
                            <input type="text" id="topup_rfid_uid" class="form-control" placeholder="Scan kartu atau masukkan UID..." required autocomplete="off">
                            <button type="button" class="btn btn-ghost" onclick="App.scanNfc('topup_rfid_uid')" style="white-space: nowrap; padding: 0.7rem 1rem;">📱 NFC</button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Pilih Nominal Top-up (Rupiah)</label>
                        <div class="nominal-pills">
                            <div class="nominal-pill" onclick="selectNominal(10000)">10k</div>
                            <div class="nominal-pill" onclick="selectNominal(20000)">20k</div>
                            <div class="nominal-pill active" onclick="selectNominal(50000)">50k</div>
                            <div class="nominal-pill" onclick="selectNominal(100000)">100k</div>
                            <div class="nominal-pill" onclick="selectNominal(200000)">200k</div>
                            <div class="nominal-pill" onclick="selectNominal(0)">Kustom</div>
                        </div>
                        <input type="number" id="topup_nominal" class="form-control" placeholder="Masukkan nominal lainnya..." value="50000" min="10000" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Metode Pembayaran</label>
                        <select id="topup_gateway" class="form-control">
                            <option value="QRIS" selected>QRIS Dinamis (Otomatis)</option>
                            <optgroup label="Virtual Account (Transfer Bank)">
                                <option value="BCAVA">BCA Virtual Account</option>
                                <option value="BNIVA">BNI Virtual Account</option>
                                <option value="BRIVA">BRI Virtual Account</option>
                                <option value="MANDIRIVA">Mandiri Virtual Account</option>
                                <option value="BSIVA">BSI Virtual Account</option>
                                <option value="PERMATAVA">Permata Virtual Account</option>
                                <option value="CIMBVA">CIMB Virtual Account</option>
                                <option value="DANAMONVA">Danamon Virtual Account</option>
                                <option value="MUAMALATVA">Muamalat Virtual Account</option>
                                <option value="MAYBANKVA">Maybank Virtual Account</option>
                                <option value="SINARMASVA">Sinarmas Virtual Account</option>
                                <option value="OCBCVA">OCBC Virtual Account</option>
                            </optgroup>
                            <optgroup label="Gerai Retail (Kasir)">
                                <option value="ALFAMART">Alfamart</option>
                                <option value="INDOMARET">Indomaret</option>
                            </optgroup>
                        </select>
                    </div>

                    <button type="submit" id="submit-topup-btn" class="btn btn-primary btn-block">💳 Lanjutkan ke Pembayaran</button>
                </form>

                <!-- Box Tampilan Pembayaran -->
                <div id="qris-box" class="qris-display-box">
                    <h3 id="payment-title" style="font-weight: 700;">Selesaikan Pembayaran</h3>
                    <p id="payment-instructions-sub" style="font-size: 0.8rem; color: var(--text-secondary); margin-top: 0.25rem;">Silakan lakukan transfer sesuai rincian di bawah ini</p>
                    
                    <div class="qris-image-container" id="qris-qr-container">
                        <!-- QR Code Image Rendered Here -->
                    </div>

                    <!-- Container VA & Retail -->
                    <div id="va-retail-container" style="display: none; margin: 1.5rem 0;">
                        <div style="font-size: 0.85rem; color: var(--text-secondary);" id="va-retail-label">Nomor Virtual Account:</div>
                        <div style="font-size: 1.8rem; font-weight: 800; color: var(--accent-primary); letter-spacing: 1px; margin: 0.5rem 0;" id="va-retail-code">1234567890</div>
                        <button type="button" class="btn btn-ghost btn-sm" onclick="copyPaymentCode()" style="margin-top: 0.25rem;">📋 Salin Kode</button>
                    </div>

                    <div style="font-size: 0.9rem; margin-bottom: 0.5rem;">
                        Nominal Transfer: <strong style="color: var(--success); font-size: 1.1rem;" id="qris-amount">Rp 0</strong>
                    </div>

                    <div class="countdown-timer" id="qris-timer">Sisa Waktu: 15:00</div>
                    <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.5rem;">
                        Order ID: <span id="qris-order-id" class="font-mono">WJP-XXX</span><br>
                        Status: <span style="color: var(--warning); font-weight: 600;">Menunggu Pembayaran...</span>
                    </div>

                    <!-- Container Petunjuk Pembayaran -->
                    <div id="payment-tutorial-container" style="display: none; text-align: left; background: rgba(255, 255, 255, 0.02); border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 1rem; margin-top: 1rem; font-size: 0.8rem; max-height: 180px; overflow-y: auto;">
                        <strong style="color: var(--text-primary);">Petunjuk Pembayaran:</strong>
                        <ol id="payment-tutorial-list" style="margin-left: 1.2rem; margin-top: 0.5rem; color: var(--text-secondary); line-height: 1.4;"></ol>
                    </div>

                    <button type="button" onclick="cancelPayment()" class="btn btn-ghost btn-sm btn-block" style="margin-top: 1.25rem;">❌ Batalkan Transaksi</button>
                </div>
            </div>

        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toast-root"></div>

    <!-- Modal Success / Status -->
    <div class="modal-overlay" id="success-modal">
        <div class="modal" style="max-width: 400px;">
            <div class="modal-body">
                <div class="payment-status-box">
                    <div class="success-icon">🎉</div>
                    <h3 style="font-size: 1.3rem; font-weight: 700; margin-bottom: 0.5rem;">Pembayaran Berhasil!</h3>
                    <p style="color: var(--text-secondary); font-size: 0.85rem;" id="success-msg">Saldo Anda telah berhasil ditambahkan.</p>
                    <div style="margin: 1.5rem 0; font-size: 1.8rem; font-weight: 800; color: var(--success);" id="success-amount">Rp 50.000</div>
                    <button class="btn btn-primary btn-block" onclick="closeSuccessModal()">Selesai</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="assets/js/app.js?v=<?= filemtime(__DIR__ . '/assets/js/app.js') ?>"></script>
    <script>
        // Switch Tabs
        function switchTab(tab) {
            document.querySelectorAll('.portal-tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));

            if (tab === 'check') {
                event.target.classList.add('active');
                document.getElementById('tab-check').classList.add('active');
            } else {
                event.target.classList.add('active');
                document.getElementById('tab-topup').classList.add('active');
            }
        }

        // Handle Nominal Pill Clicks
        function selectNominal(val) {
            document.querySelectorAll('.nominal-pill').forEach(pill => pill.classList.remove('active'));
            event.target.classList.add('active');

            const nominalInput = document.getElementById('topup_nominal');
            if (val > 0) {
                nominalInput.value = val;
                nominalInput.style.display = 'none'; // Sembunyikan input manual jika pilih pill
            } else {
                nominalInput.value = '';
                nominalInput.style.display = 'block'; // Tampilkan input manual jika pilih "Kustom"
                nominalInput.focus();
            }
        }
        // Pastikan nominal input awal disembunyikan
        document.getElementById('topup_nominal').style.display = 'none';

        // Toast Notification Helper
        function showToast(message, type = 'info') {
            const container = document.getElementById('toast-root');
            const toast = document.createElement('div');
            toast.className = `alert alert-${type === 'error' ? 'danger' : type}`;
            
            let icon = 'ℹ️';
            if (type === 'success') icon = '✅';
            if (type === 'error') icon = '❌';
            if (type === 'warning') icon = '⚠️';

            toast.innerHTML = `<span>${icon}</span> <span>${message}</span>`;
            container.appendChild(toast);

            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateY(-20px)';
                toast.style.transition = 'all 0.3s ease';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        // Format Rupiah Javascript Helper
        function formatRupiah(number) {
            return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(number);
        }

        // TAB 1: Handler Cek Saldo
        async function handleCheckBalance(event) {
            event.preventDefault();
            const uid = document.getElementById('check_rfid_uid').value.trim();
            if (!uid) return;

            try {
                const res = await fetch(`api/rfid.php?action=check&uid=${encodeURIComponent(uid)}&token=rfid-hw-token-change-me`);
                const data = await res.json();
                
                const resultBox = document.getElementById('check-result');
                if (data.status === 'success') {
                    document.getElementById('result-name').textContent = data.nama;
                    document.getElementById('result-balance').textContent = formatRupiah(data.saldo);
                    document.getElementById('result-uid').textContent = 'UID: ' + uid;
                    
                    const statusBadge = document.getElementById('result-status');
                    statusBadge.textContent = data.kartu_status === 'active' ? '● Aktif' : '● Diblokir';
                    statusBadge.className = `badge ${data.kartu_status === 'active' ? 'badge-success' : 'badge-danger'}`;
                    
                    resultBox.style.display = 'block';
                    showToast('Kartu ditemukan', 'success');
                } else {
                    resultBox.style.display = 'none';
                    showToast(data.message || 'Kartu tidak terdaftar', 'error');
                }
            } catch (e) {
                showToast('Gagal terhubung ke server', 'error');
            }
        }

        // Polling status pembayaran
        let pollInterval = null;
        let countdownInterval = null;

        // TAB 2: Handler Request Top-up WijayaPay
        async function handleRequestTopup(event) {
            event.preventDefault();
            const uid = document.getElementById('topup_rfid_uid').value.trim();
            const nominal = document.getElementById('topup_nominal').value;
            const gateway = document.getElementById('topup_gateway').value;

            if (!uid || nominal <= 0) {
                showToast('Isi UID dan nominal dengan benar', 'warning');
                return;
            }

            const submitBtn = document.getElementById('submit-topup-btn');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Memproses Pembayaran...';

            try {
                const formData = new FormData();
                formData.append('action', 'request_wijayapay');
                formData.append('rfid_uid', uid);
                formData.append('nominal', nominal);
                formData.append('code_payment', gateway);

                const res = await fetch('api/topup.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await res.json();
                if (data.status === 'success') {
                    showToast('Transaksi Berhasil Dibuat!', 'success');
                    
                    document.getElementById('request-topup-form').style.display = 'none';
                    const qrisBox = document.getElementById('qris-box');
                    qrisBox.style.display = 'block';

                    document.getElementById('qris-amount').textContent = formatRupiah(data.nominal);
                    document.getElementById('qris-order-id').textContent = data.order_id;

                    // Reset display containers
                    document.getElementById('qris-qr-container').style.display = 'none';
                    document.getElementById('va-retail-container').style.display = 'none';
                    document.getElementById('payment-tutorial-container').style.display = 'none';

                    if (data.qr_data) {
                        // QRIS Mode
                        document.getElementById('payment-title').textContent = 'Scan QRIS Untuk Membayar';
                        document.getElementById('payment-instructions-sub').textContent = 'Gunakan DANA, OVO, GoPay, LinkAja, atau Mobile Banking';
                        document.getElementById('qris-qr-container').style.display = 'block';
                        
                        const qrContainer = document.getElementById('qris-qr-container');
                        qrContainer.innerHTML = '';
                        const img = document.createElement('img');
                        img.src = `https://api.qrserver.com/v1/create-qr-code/?size=240x240&data=${encodeURIComponent(data.qr_data)}`;
                        img.alt = 'QRIS Code';
                        qrContainer.appendChild(img);
                    } else if (data.nomor_va || data.nomor_pembayaran) {
                        // VA or Retail Mode
                        document.getElementById('payment-title').textContent = data.payment_name;
                        document.getElementById('payment-instructions-sub').textContent = 'Silakan lakukan pembayaran sesuai rincian di bawah ini';
                        document.getElementById('va-retail-container').style.display = 'block';
                        
                        const codeVal = data.nomor_va || data.nomor_pembayaran;
                        document.getElementById('va-retail-code').textContent = codeVal;
                        document.getElementById('va-retail-label').textContent = data.nomor_va ? 'Nomor Virtual Account:' : 'Kode Pembayaran:';
                        
                        // Render tutorial
                        if (data.tutorial) {
                            document.getElementById('payment-tutorial-container').style.display = 'block';
                            const list = document.getElementById('payment-tutorial-list');
                            list.innerHTML = '';
                            const steps = data.tutorial.split('\n');
                            steps.forEach(step => {
                                if (step.trim()) {
                                    const li = document.createElement('li');
                                    li.textContent = step.trim();
                                    list.appendChild(li);
                                }
                            });
                        }
                    } else if (data.payment_url) {
                        // Redirect / Web link Mode
                        document.getElementById('payment-title').textContent = 'Buka Link Pembayaran';
                        document.getElementById('payment-instructions-sub').textContent = 'Silakan selesaikan pembayaran di halaman luar';
                        document.getElementById('qris-qr-container').style.display = 'block';
                        
                        const qrContainer = document.getElementById('qris-qr-container');
                        qrContainer.innerHTML = '';
                        const linkBtn = document.createElement('a');
                        linkBtn.href = data.payment_url;
                        linkBtn.target = '_blank';
                        linkBtn.className = 'btn btn-primary';
                        linkBtn.textContent = '🔗 Buka Link Pembayaran';
                        qrContainer.appendChild(linkBtn);
                    }

                    // Mulai Countdown Timer 15 menit
                    startTimer(15 * 60);

                    // Mulai polling status transaksi
                    startPolling(data.order_id);

                } else {
                    showToast(data.message || 'Gagal membuat transaksi', 'error');
                }
            } catch (e) {
                console.error(e);
                showToast('Gagal terhubung ke server', 'error');
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = '💳 Lanjutkan ke Pembayaran';
            }
        }

        function copyPaymentCode() {
            const code = document.getElementById('va-retail-code').textContent;
            navigator.clipboard.writeText(code).then(() => {
                showToast('Kode pembayaran berhasil disalin!', 'success');
            }).catch(() => {
                showToast('Gagal menyalin kode', 'error');
            });
        }

        // Timer countdown function
        function startTimer(duration) {
            let timer = duration, minutes, seconds;
            if (countdownInterval) clearInterval(countdownInterval);

            const display = document.getElementById('qris-timer');
            countdownInterval = setInterval(() => {
                minutes = parseInt(timer / 60, 10);
                seconds = parseInt(timer % 60, 10);

                minutes = minutes < 10 ? "0" + minutes : minutes;
                seconds = seconds < 10 ? "0" + seconds : seconds;

                display.textContent = "Sisa Waktu: " + minutes + ":" + seconds;

                if (--timer < 0) {
                    clearInterval(countdownInterval);
                    cancelPayment('Waktu pembayaran habis');
                }
            }, 1000);
        }

        // Polling status transaksi
        function startPolling(orderId) {
            if (pollInterval) clearInterval(pollInterval);
            pollInterval = setInterval(async () => {
                try {
                    const res = await fetch(`api/transactions.php?action=check_status&order_id=${encodeURIComponent(orderId)}`);
                    const data = await res.json();
                    
                    if (data.status === 'success') {
                        if (data.trx_status === 'success') {
                            clearInterval(pollInterval);
                            clearInterval(countdownInterval);
                            
                            // Tampilkan modal sukses
                            document.getElementById('success-amount').textContent = formatRupiah(data.amount);
                            document.getElementById('success-modal').classList.add('active');
                            
                            // Reset form
                            cancelPayment(null, false);
                        } else if (data.trx_status === 'failed' || data.trx_status === 'expired') {
                            clearInterval(pollInterval);
                            clearInterval(countdownInterval);
                            showToast('Pembayaran gagal atau kedaluwarsa', 'error');
                            cancelPayment();
                        }
                    }
                } catch (e) {
                    console.error('Polling error:', e);
                }
            }, 3000);
        }

        // Batal / Reset Pembayaran
        function cancelPayment(reason = null, showToastMsg = true) {
            if (pollInterval) clearInterval(pollInterval);
            if (countdownInterval) clearInterval(countdownInterval);
            
            if (reason && showToastMsg) {
                showToast(reason, 'warning');
            } else if (showToastMsg) {
                showToast('Transaksi dibatalkan', 'info');
            }

            // Tampilkan kembali form input, sembunyikan QRIS
            document.getElementById('qris-box').style.display = 'none';
            document.getElementById('request-topup-form').style.display = 'block';
        }

        // Tutup Modal Sukses
        function closeSuccessModal() {
            document.getElementById('success-modal').classList.remove('active');
            location.reload();
        }
    </script>
</body>
</html>
