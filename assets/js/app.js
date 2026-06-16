/**
 * RFID Payment Gateway - Main JavaScript
 * Handles AJAX, modals, toasts, and UI interactions
 */

const App = {
    // ── Toast Notifications ────────────────────
    toast(message, type = 'info') {
        let container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            container.className = 'toast-container';
            document.body.appendChild(container);
        }

        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        
        const icons = { success: '✅', error: '❌', info: 'ℹ️' };
        toast.innerHTML = `<span>${icons[type] || 'ℹ️'}</span> ${message}`;
        
        container.appendChild(toast);
        
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(50px)';
            toast.style.transition = 'all 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, 3500);
    },

    // ── Modal Management ───────────────────────
    openModal(id) {
        const overlay = document.getElementById(id);
        if (overlay) {
            overlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
    },

    closeModal(id) {
        const overlay = document.getElementById(id);
        if (overlay) {
            overlay.classList.remove('active');
            document.body.style.overflow = '';
        }
    },

    closeAllModals() {
        document.querySelectorAll('.modal-overlay').forEach(m => {
            m.classList.remove('active');
        });
        document.body.style.overflow = '';
    },

    // ── AJAX Helper ────────────────────────────
    async request(url, options = {}) {
        const defaults = {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        };

        const config = { ...defaults, ...options };

        try {
            const response = await fetch(url, config);
            const data = await response.json();
            return data;
        } catch (error) {
            console.error('Request failed:', error);
            App.toast('Koneksi ke server gagal', 'error');
            return { status: 'error', message: 'Network error' };
        }
    },

    // ── Form to URLSearchParams ────────────────
    formData(formId) {
        const form = document.getElementById(formId);
        if (!form) return '';
        const data = new FormData(form);
        return new URLSearchParams(data).toString();
    },

    // ── Confirm Dialog ─────────────────────────
    async confirm(message) {
        return new Promise(resolve => {
            // Create custom confirm modal
            const overlay = document.createElement('div');
            overlay.className = 'modal-overlay active';
            overlay.innerHTML = `
                <div class="modal" style="max-width:380px">
                    <div class="modal-body text-center" style="padding:2rem">
                        <div style="font-size:2.5rem;margin-bottom:1rem">⚠️</div>
                        <h3 style="margin-bottom:0.75rem">${message}</h3>
                        <div style="display:flex;gap:0.75rem;justify-content:center;margin-top:1.5rem">
                            <button class="btn btn-ghost" id="confirm-no">Batal</button>
                            <button class="btn btn-danger" id="confirm-yes">Ya, Lanjutkan</button>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(overlay);

            overlay.querySelector('#confirm-yes').onclick = () => {
                overlay.remove();
                resolve(true);
            };
            overlay.querySelector('#confirm-no').onclick = () => {
                overlay.remove();
                resolve(false);
            };
        });
    },

    // ── Format Rupiah ──────────────────────────
    formatRupiah(angka) {
        return 'Rp ' + Number(angka).toLocaleString('id-ID');
    },

    // ── Sidebar Toggle (Mobile) ────────────────
    toggleSidebar() {
        const sidebar = document.querySelector('.sidebar');
        if (sidebar) {
            sidebar.classList.toggle('open');
        }
    },

    // ── Web NFC API Scanner ────────────────────
    async scanNfc(inputElementId) {
        if (!('NDEFReader' in window)) {
            App.toast('Web NFC tidak didukung di browser ini. Gunakan Chrome di Android dengan koneksi HTTPS.', 'error');
            return;
        }

        try {
            const ndef = new NDEFReader();
            App.toast('Mulai memindai NFC... Dekatkan kartu RFID/NFC ke bagian belakang HP Anda.', 'info');
            await ndef.scan();
            
            ndef.addEventListener("readingerror", () => {
                App.toast("Gagal membaca tag NFC. Silakan coba lagi.", "error");
            });

            ndef.addEventListener("reading", ({ message, serialNumber }) => {
                if (serialNumber) {
                    const cleanedSerial = serialNumber.replace(/:/g, "").toUpperCase();
                    const inputEl = document.getElementById(inputElementId);
                    if (inputEl) {
                        inputEl.value = cleanedSerial;
                        App.toast(`Berhasil membaca NFC: ${cleanedSerial}`, 'success');
                        inputEl.dispatchEvent(new Event('input', { bubbles: true }));
                        inputEl.dispatchEvent(new Event('change', { bubbles: true }));
                    } else {
                        App.toast(`Hasil Scan NFC: ${cleanedSerial}`, 'success');
                    }
                } else {
                    App.toast("Tag NFC terbaca tetapi tidak memiliki serial number.", "error");
                }
            });
        } catch (error) {
            App.toast("Gagal mengaktifkan NFC: " + error.message, "error");
        }
    },

    // ── Initialize ─────────────────────────────
    init() {
        // Close modal on overlay click
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) {
                    overlay.classList.remove('active');
                    document.body.style.overflow = '';
                }
            });
        });

        // Close modal on Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                App.closeAllModals();
            }
        });

        // Auto-hide alerts after 5 seconds
        document.querySelectorAll('.alert').forEach(alert => {
            setTimeout(() => {
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-10px)';
                alert.style.transition = 'all 0.3s ease';
                setTimeout(() => alert.remove(), 300);
            }, 5000);
        });

        // Animate elements on scroll
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animate-in');
                }
            });
        }, { threshold: 0.1 });

        document.querySelectorAll('.stat-card, .card, .pm-card').forEach(el => {
            observer.observe(el);
        });
    }
};

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', () => App.init());
