/**
 * Mutiara SkyTrack — Central Toast & Modal Manager
 */

class SkyTrackToastManager {
    constructor() {
        this.portalId = 'sk-toast-portal';
        this.loadingToastId = 'sk-loading-toast';
        this.timeoutTimer = null;
        this.lastToastTime = 0;
        this.lastToastTitle = '';
    }

    injectStyles() {
        if (document.getElementById('sk-toast-core-css')) return;

        const style = document.createElement('style');
        style.id = 'sk-toast-core-css';
        style.textContent = `
            /* 1. Modal Konfirmasi Putih: Presisi di Tengah Horizontal & Vertikal */
            @media (min-width: 1024px) {
                .fi-modal-window-ctn {
                    padding-left: 16rem !important; /* Kompensasi sidebar 256px */
                    padding-top: 0 !important;
                    display: flex !important;
                    align-items: center !important; /* Tengah vertikal */
                    justify-content: center !important;
                }

                .fi-modal-window {
                    margin: auto !important;
                }
            }

            @media (max-width: 1023px) {
                .fi-modal-window-ctn {
                    padding-left: 0 !important;
                    display: flex !important;
                    align-items: center !important;
                    justify-content: center !important;
                }
                .fi-modal-window {
                    margin: auto !important;
                }
            }

            /* 2. Posisi Toast Loading & Toast Hasil di Tengah Area Kerja */
            #sk-toast-portal,
            .fi-no-notification-ctn {
                position: fixed !important;
                top: 1.5rem !important;
                left: calc(50% + 8rem) !important;
                right: auto !important;
                bottom: auto !important;
                transform: translateX(-50%) !important;
                display: flex !important;
                flex-direction: column !important;
                align-items: center !important;
                justify-content: flex-start !important;
                z-index: 999999 !important;
                pointer-events: none !important;
                width: auto !important;
                max-width: 90vw !important;
            }

            @media (max-width: 1023px) {
                #sk-toast-portal,
                .fi-no-notification-ctn {
                    left: 50% !important;
                }
            }

            /* 3. Kartu Toast Dark Glassmorphism */
            .sk-toast-card {
                pointer-events: auto;
                position: relative;
                overflow: hidden;
                background: linear-gradient(135deg, rgba(15, 23, 42, 0.98), rgba(30, 41, 59, 0.98));
                border-radius: 1rem;
                padding: 1rem 1.4rem;
                display: flex;
                align-items: center;
                gap: 1.1rem;
                min-width: 360px;
                max-width: 520px;
                backdrop-filter: blur(16px);
                animation: skToastPopIn 0.35s cubic-bezier(0.16, 1, 0.3, 1) forwards;
            }

            .sk-toast-loading {
                border: 1.5px solid rgba(56, 189, 248, 0.55);
                box-shadow: 0 25px 40px -5px rgba(0, 0, 0, 0.7), 0 0 25px rgba(56, 189, 248, 0.3);
            }

            .sk-toast-success {
                border: 1.5px solid rgba(34, 197, 94, 0.6);
                box-shadow: 0 25px 40px -5px rgba(0, 0, 0, 0.7), 0 0 25px rgba(34, 197, 94, 0.3);
            }

            .sk-toast-danger {
                border: 1.5px solid rgba(239, 68, 68, 0.6);
                box-shadow: 0 25px 40px -5px rgba(0, 0, 0, 0.7), 0 0 25px rgba(239, 68, 68, 0.3);
            }

            .sk-toast-progress {
                position: absolute;
                bottom: 0;
                left: 0;
                height: 3px;
                width: 100%;
                background: linear-gradient(90deg, transparent, #38bdf8, #818cf8, transparent);
                background-size: 200% 100%;
                animation: skShimmer 1.5s infinite linear;
            }

            @keyframes skShimmer {
                0% { background-position: 200% 0; }
                100% { background-position: -200% 0; }
            }

            @keyframes skToastPopIn {
                from { opacity: 0; transform: translateY(-24px) scale(0.92); }
                to { opacity: 1; transform: translateY(0) scale(1); }
            }

            @keyframes skSpin {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }

            .sk-spin {
                animation: skSpin 1.1s linear infinite;
            }
        `;
        document.head.appendChild(style);
    }

    getPortal() {
        let portal = document.getElementById(this.portalId);
        if (!portal) {
            portal = document.createElement('div');
            portal.id = this.portalId;
            document.body.appendChild(portal);
        }
        return portal;
    }

    /**
     * Menutup modal konfirmasi putih seketika dari layar
     */
    dismissActiveModal() {
        const modalElements = document.querySelectorAll(
            '.fi-modal, .fi-modal-window-ctn, .fi-modal-window, .fi-modal-close-overlay, [role="dialog"]'
        );
        modalElements.forEach(el => {
            el.style.setProperty('display', 'none', 'important');
            el.style.setProperty('opacity', '0', 'important');
            el.style.setProperty('pointer-events', 'none', 'important');
        });
    }

    showLoading() {
        this.hideLoading();
        this.dismissActiveModal();

        const portal = this.getPortal();
        const toast = document.createElement('div');
        toast.id = this.loadingToastId;
        toast.className = 'sk-toast-card sk-toast-loading';

        toast.innerHTML = `
            <div style="position: relative; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                <svg class="sk-spin" style="width: 2.2rem; height: 2.2rem; color: #38bdf8;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle style="opacity: 0.2;" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path style="opacity: 0.95;" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </div>
            <div style="flex: 1; padding-right: 0.5rem;">
                <div style="font-weight: 700; font-size: 0.95rem; color: #f8fafc; letter-spacing: 0.01em;">
                    Sinkronisasi Sedang Berjalan...
                </div>
                <div style="font-size: 0.8rem; color: #94a3b8; margin-top: 0.25rem; line-height: 1.35;">
                    Membaca spreadsheet UPBU Palu & memperbarui database...
                </div>
            </div>
            <div class="sk-toast-progress"></div>
        `;

        portal.appendChild(toast);
        this.timeoutTimer = setTimeout(() => this.hideLoading(), 180000);
    }

    hideLoading() {
        if (this.timeoutTimer) {
            clearTimeout(this.timeoutTimer);
            this.timeoutTimer = null;
        }

        const toast = document.getElementById(this.loadingToastId);
        if (toast) {
            toast.style.transition = 'opacity 0.25s ease, transform 0.25s ease';
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(-16px) scale(0.92)';
            setTimeout(() => toast.remove(), 250);
        }
    }

    /**
     * Menampilkan Toast Hasil Selesai (dengan proteksi anti-dobel)
     */
    showResult(type, title, message) {
        // Cegah eksekusi berulang dalam rentang 1 detik
        const now = Date.now();
        if (now - this.lastToastTime < 1000 && this.lastToastTitle === title) {
            return;
        }
        this.lastToastTime = now;
        this.lastToastTitle = title;

        this.hideLoading();

        const portal = this.getPortal();

        // Bersihkan toast hasil lama jika masih ada
        portal.querySelectorAll('.sk-toast-success, .sk-toast-danger').forEach(el => el.remove());

        const toast = document.createElement('div');
        const isSuccess = type === 'success';

        toast.className = `sk-toast-card ${isSuccess ? 'sk-toast-success' : 'sk-toast-danger'}`;

        const iconSvg = isSuccess
            ? `<svg style="width: 2.2rem; height: 2.2rem; color: #22c55e; flex-shrink: 0;" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                 <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
               </svg>`
            : `<svg style="width: 2.2rem; height: 2.2rem; color: #ef4444; flex-shrink: 0;" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                 <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
               </svg>`;

        toast.innerHTML = `
            ${iconSvg}
            <div style="flex: 1; padding-right: 0.5rem;">
                <div style="font-weight: 700; font-size: 0.95rem; color: #f8fafc; letter-spacing: 0.01em;">
                    ${title}
                </div>
                <div style="font-size: 0.825rem; color: #cbd5e1; margin-top: 0.25rem; line-height: 1.35;">
                    ${message}
                </div>
            </div>
            <button type="button" onclick="this.closest('.sk-toast-card').remove()" style="color: #94a3b8; background: none; border: none; cursor: pointer; padding: 0.2rem; display: flex;">
                <svg style="width: 1.2rem; height: 1.2rem;" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
        `;

        portal.appendChild(toast);

        setTimeout(() => {
            if (toast && toast.parentElement) {
                toast.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                toast.style.opacity = '0';
                toast.style.transform = 'translateY(-16px) scale(0.92)';
                setTimeout(() => toast.remove(), 300);
            }
        }, 8000);
    }

    init() {
        this.injectStyles();

        // 1. Klik konfirmasi modal -> langsung tutup modal & tampilkan loading toast
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('button');
            if (btn && (btn.textContent.includes('Mulai Sinkronisasi') || btn.closest('[data-sync-submit]'))) {
                this.dismissActiveModal();
                this.showLoading();
            }
        }, true);

        // 2. Satu listener tunggal via window event untuk menerima hasil dari server
        window.addEventListener('sk-sync-result', (event) => {
            let data = event.detail;
            if (Array.isArray(data)) data = data[0];
            if (data && data.title) {
                this.showResult(data.type || 'success', data.title, data.message || '');
            }
        });
    }
}

window.SkyTrackToast = new SkyTrackToastManager();
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => window.SkyTrackToast.init());
} else {
    window.SkyTrackToast.init();
}
