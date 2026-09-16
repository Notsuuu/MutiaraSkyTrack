/**
 * Mutiara SkyTrack — Sidebar & Page Transitions
 */

// ═══════════════════════════════════════════════════════
// DEBUG FLAG
// ═══════════════════════════════════════════════════════
const DEBUG = false;
const log = (...args) => { if (DEBUG) console.log('[skytrack]', ...args); };
const warn = (...args) => { if (DEBUG) console.warn('[skytrack]', ...args); };

// ═══════════════════════════════════════════════════════
// ICON PRESETS
// ═══════════════════════════════════════════════════════
const ICONS = {
    'Kelola Data': `
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
             stroke-width="1.5" stroke="currentColor"
             class="fi-icon fi-size-md sk-injected-icon">
            <path stroke-linecap="round" stroke-linejoin="round"
                  d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" />
        </svg>`,
    'Master Data': `
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
             stroke-width="1.5" stroke="currentColor"
             class="fi-icon fi-size-md sk-injected-icon">
            <path stroke-linecap="round" stroke-linejoin="round"
                  d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125" />
        </svg>`,
};

// ═══════════════════════════════════════════════════════
// SIDEBAR MANAGER
// ═══════════════════════════════════════════════════════
class SkyTrackSidebar {
    constructor() {
        this.activePoll = null;
        this.internalClick = false;
        this.observerDebounce = null;
        this.groupLocks = new Map();
        this.sidebarObserver = null;
    }

    isLocked(label) { return this.groupLocks.has(label); }

    lockGroup(label, ms = 1200) {
        if (this.groupLocks.has(label)) clearTimeout(this.groupLocks.get(label));
        this.groupLocks.set(label, setTimeout(() => this.groupLocks.delete(label), ms));
    }

    cancelPoll() {
        if (this.activePoll !== null) {
            clearTimeout(this.activePoll);
            this.activePoll = null;
        }
    }

    getGroupLabel(group) {
        const lbl = group.querySelector('.fi-sidebar-group-label');
        return lbl ? lbl.textContent.trim()
            : (group.textContent || '').trim().split('\n')[0].trim().substring(0, 40);
    }

    hasActiveItem(group) {
        if (group.querySelector('[aria-current="page"], [aria-current="true"], .fi-sidebar-item-active, .fi-active')) {
            return true;
        }
        const currentPath = location.pathname.replace(/\/$/, '');
        for (const link of group.querySelectorAll('.fi-sidebar-group-items a[href]')) {
            try {
                const targetPath = new URL(link.href, location.origin).pathname.replace(/\/$/, '');
                if (targetPath === currentPath) return true;
            } catch (e) { /* skip */ }
        }
        return false;
    }

    getGroupToggle(group) {
        return group.querySelector('.fi-sidebar-group-btn')
            || group.querySelector('.fi-sidebar-group-button')
            || group.querySelector('.fi-sidebar-group-trigger')
            || group.querySelector('button')
            || Array.from(group.children).find(el => !el.classList.contains('fi-sidebar-group-items'));
    }

    isGroupExpanded(group) {
        const wrapper = group.querySelector('.fi-sidebar-group-items');
        return wrapper ? wrapper.getBoundingClientRect().height > 0 : false;
    }

    injectGroupIcons() {
        document.querySelectorAll('.fi-sidebar-group').forEach(group => {
            const label = this.getGroupLabel(group);
            if (!ICONS[label]) return;

            const header = group.querySelector('.fi-sidebar-group-btn')
                || group.querySelector('.fi-sidebar-group-button')
                || group.querySelector('.fi-sidebar-group-trigger');
            if (!header) return;
            if (header.querySelector('.sk-injected-icon')) return;

            const firstSvg = header.querySelector(':scope > svg');
            if (firstSvg && !firstSvg.closest('.fi-sidebar-group-collapse-btn')) return;

            header.insertAdjacentHTML('afterbegin', ICONS[label]);
            log('🎨 Icon injected:', label);
        });
    }

    normalizeSpacing() {
        const ul = document.querySelector('.fi-sidebar-nav-groups');
        if (!ul) return;
        ul.style.display = 'flex';
        ul.style.flexDirection = 'column';
        ul.style.gap = '2px';
        Array.from(ul.children).forEach(li => {
            li.style.marginTop = '0';
            li.style.marginBottom = '0';
        });
    }

    reorderLaporan() {
        const ul0 = document.querySelector('.fi-sidebar-nav-groups');
        if (!ul0) return;

        let kelolaLi = null;
        for (const li of ul0.children) {
            if (li.querySelector('.fi-sidebar-group-label')?.textContent.trim() === 'Kelola Data') {
                kelolaLi = li; break;
            }
        }
        if (!kelolaLi) return;

        let laporanLi = null;
        for (const lbl of document.querySelectorAll('.fi-sidebar-item-label')) {
            if (lbl.textContent.trim() === 'Laporan') { laporanLi = lbl.closest('li'); break; }
        }
        if (!laporanLi) return;

        laporanLi.classList.add('sk-standalone-item');
        if (laporanLi.parentElement === ul0 && laporanLi.previousElementSibling === kelolaLi) return;

        ul0.insertBefore(laporanLi, kelolaLi.nextSibling);
        log('📋 Reorder: Laporan moved');
    }

    // ═══════════════════════════════════════════════════════
    // ★ EXPAND grup yang punya item aktif (dipanggil setelah navigate)
    // ═══════════════════════════════════════════════════════
    expandActiveGroup() {
        document.querySelectorAll('.fi-sidebar-group').forEach(group => {
            // Skip kalau grup tidak punya item aktif
            if (!this.hasActiveItem(group)) return;

            // Skip kalau sudah expanded
            if (this.isGroupExpanded(group)) return;

            const toggle = this.getGroupToggle(group);
            if (!toggle) return;

            const label = this.getGroupLabel(group);
            log('🔻 Expand active:', label);

            this.internalClick = true;
            try { toggle.click(); } finally { this.internalClick = false; }
        });
    }

    collapseInactive() {
        document.querySelectorAll('.fi-sidebar-group').forEach(group => {
            if (this.hasActiveItem(group)) return;

            const label = this.getGroupLabel(group);
            if (this.isLocked(label)) return;
            if (!this.isGroupExpanded(group)) return;

            const toggle = this.getGroupToggle(group);
            if (!toggle) return;

            this.lockGroup(label);
            log('🔺 Collapse:', label);

            this.internalClick = true;
            try { toggle.click(); } finally { this.internalClick = false; }
        });
    }

    // ═══════════════════════════════════════════════════════
    // ★ INSTANT NAVIGATE — intercept click sebelum Filament expand
    // ═══════════════════════════════════════════════════════
    handleGroupClick(e) {
        if (this.internalClick) return;
        this.cancelPoll();

        const group = e.target.closest('.fi-sidebar-group');

        if (!group) {
            const sidebarLink = e.target.closest('.fi-sidebar a[href]');
            if (sidebarLink) {
                setTimeout(() => this.collapseInactive(), 0);
            }
            return;
        }

        if (e.target.closest('.fi-sidebar-group-items')) return;
        if (this.hasActiveItem(group)) return;
        if (this.isGroupExpanded(group)) return;

        // ★ Instant navigate — cegah Filament toggle dropdown
        const firstLink = group.querySelector('.fi-sidebar-group-items a[href]');
        if (!firstLink) return;

        const href = firstLink.getAttribute('href');
        if (!href || href === '#') return;

        const targetPath = new URL(href, location.origin).pathname;
        if (location.pathname === targetPath) return;

        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();

        log('⚡ Instant navigate:', targetPath);
        firstLink.click();
    }

    // ═══════════════════════════════════════════════════════
    // Refresh pipeline
    // ═══════════════════════════════════════════════════════
    refresh() {
        this.reorderLaporan();
        this.injectGroupIcons();
        this.normalizeSpacing();
        this.expandActiveGroup();   // ← EXPAND dulu (kalau ada yang aktif)
        this.collapseInactive();    // ← Baru collapse sisanya
    }

    init() {
        // Global listener (capture phase) — once only
        document.addEventListener('click', (e) => this.handleGroupClick(e), true);

        document.addEventListener('livewire:navigating', () => this.cancelPoll());
        document.addEventListener('livewire:navigated', () => {
            this.cancelPoll();
            // Multi-stage refresh — expand grup aktif di setiap stage
            this.refresh();
            setTimeout(() => this.refresh(), 100);
            setTimeout(() => this.refresh(), 250);
            setTimeout(() => this.refresh(), 500);
        });

        const run = () => {
            this.refresh();
            setTimeout(() => this.refresh(), 150);
            setTimeout(() => this.refresh(), 400);
            setTimeout(() => this.refresh(), 800);
        };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => setTimeout(run, 100));
        } else {
            setTimeout(run, 100);
        }

        this.observeSidebar();
        log('Sidebar initialized');
    }

    observeSidebar() {
        const start = () => {
            const sidebar = document.querySelector('.fi-sidebar');
            if (!sidebar) { setTimeout(start, 200); return; }

            if (this.sidebarObserver) this.sidebarObserver.disconnect();

            this.sidebarObserver = new MutationObserver(() => {
                clearTimeout(this.observerDebounce);
                this.observerDebounce = setTimeout(() => {
                    this.refresh();
                }, 200);
            });
            this.sidebarObserver.observe(sidebar, { childList: true, subtree: true });
            log('Observer attached');
        };
        start();
    }
}

// ═══════════════════════════════════════════════════════
// PAGE TRANSITION
// ═══════════════════════════════════════════════════════
class SkyTrackPageTransition {
    constructor() {
        this.className = 'sk-page-enter';
    }

    play() {
        const main = document.querySelector('.fi-main-ctn');
        if (!main) return;

        main.classList.remove(this.className);
        void main.offsetWidth;
        main.classList.add(this.className);
    }

    init() {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.play());
        } else {
            this.play();
        }

        document.addEventListener('livewire:navigated', () => this.play());
    }
}

// ═══════════════════════════════════════════════════════
// BOOT
// ═══════════════════════════════════════════════════════
const skySidebar = new SkyTrackSidebar();
const skyPageTransition = new SkyTrackPageTransition();

skySidebar.init();
skyPageTransition.init();

if (DEBUG) {
    window.__skytrack = { sidebar: skySidebar, pageTransition: skyPageTransition };
}
