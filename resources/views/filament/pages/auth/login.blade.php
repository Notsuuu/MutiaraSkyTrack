<div style="display: flex; min-height: 100vh; width: 100%; font-family: ui-sans-serif, system-ui, sans-serif;">
    <!-- Panel Kiri (Sidebar Informasi) -->
    <div class="hidden lg:flex" style="width: 400px; min-width: 400px; background-color: #0b132b; color: #ffffff; padding: 2.5rem; flex-direction: column; justify-content: space-between; border-right: 1px solid #1e293b; box-sizing: border-box;">
        <!-- Header Logo -->
        <div style="display: flex; align-items: center; gap: 0.75rem;">
            <img src="{{ asset('images/logo.svg') }}" alt="Logo" style="height: 2.5rem; width: 2.5rem;">
            <div>
                <h2 style="font-size: 1rem; font-weight: 700; line-height: 1.2; margin: 0; color: #ffffff;">Mutiara SkyTrack</h2>
                <p style="font-size: 0.65rem; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.1em; font-weight: 600; margin: 0;">BANDAR UDARA PLW</p>
            </div>
        </div>

        <!-- Body Content -->
        <div style="display: flex; flex-direction: column; gap: 1.5rem;">
            <h1 style="font-size: 1.75rem; font-weight: 700; color: #ffffff; margin: 0; line-height: 1.2;">Selamat Datang Kembali</h1>
            <p style="font-size: 0.875rem; color: #cbd5e1; line-height: 1.6; margin: 0;">
                Sistem Analitik Pemantau dan Manajemen Data Lalu Lintas Udara Bandar Udara Mutiara Sis Al-Jufri Palu secara Real-Time, Akurat, dan Terintegrasi.
            </p>

            <ul style="display: flex; flex-direction: column; gap: 0.75rem; padding: 0; margin: 0; list-style: none; font-size: 0.875rem; color: #cbd5e1;">
                <li style="display: flex; align-items: center; gap: 0.625rem;">
                    <svg style="width: 1rem; height: 1rem; color: #60a5fa; flex-shrink: 0;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                    </svg>
                    <span>Pencatatan data lalu lintas udara real-time</span>
                </li>
                <li style="display: flex; align-items: center; gap: 0.625rem;">
                    <svg style="width: 1rem; height: 1rem; color: #60a5fa; flex-shrink: 0;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                    </svg>
                    <span>Analitik & laporan rekap bulanan / tahunan</span>
                </li>
                <li style="display: flex; align-items: center; gap: 0.625rem;">
                    <svg style="width: 1rem; height: 1rem; color: #60a5fa; flex-shrink: 0;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                    </svg>
                    <span>Import massal dari template Excel LLAU</span>
                </li>
            </ul>
        </div>

        <!-- Footer -->
        <div style="font-size: 0.7rem; color: #64748b;">
            © {{ date('Y') }} Mutiara SkyTrack. All rights reserved.
        </div>
    </div>

    <!-- Panel Kanan (Background Image & Form Card) -->
    <div style="flex: 1; position: relative; display: flex; align-items: center; justify-content: center; padding: 1.5rem; background-color: #0f172a; background-image: url('{{ asset('images/bg-bandara.jpg') }}'); background-size: cover; background-position: center;">
        <!-- Overlay Gelap -->
        <div style="position: absolute; inset: 0; background-color: rgba(15, 23, 42, 0.65); backdrop-filter: blur(3px);"></div>

        <!-- Form Login Card -->
        <div style="position: relative; z-index: 10; width: 100%; max-width: 420px; background-color: #ffffff; border-radius: 1rem; padding: 2rem; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3); border: 1px solid #f1f5f9; color: #0f172a;">
            <div style="margin-bottom: 1.5rem; text-align: left;">
                <h2 style="font-size: 1.5rem; font-weight: 700; color: #0f172a; margin: 0;">
                    Masuk ke Sistem
                </h2>
                <p style="margin-top: 0.25rem; font-size: 0.75rem; color: #64748b;">
                    Gunakan alamat email dan kata sandi Anda untuk masuk.
                </p>
            </div>

            <!-- Form Filament -->
            <x-filament-panels::form wire:submit="authenticate">
                {{ $this->form }}

                <x-filament-panels::form.actions
                    :actions="$this->getCachedFormActions()"
                    :full-width="true"
                />
            </x-filament-panels::form>

            <div style="margin-top: 1.5rem; text-align: center; font-size: 0.75rem; color: #64748b; border-top: 1px solid #f1f5f9; padding-top: 1rem;">
                Hubungi administrator jika Anda memerlukan bantuan akses akun.
            </div>
        </div>
    </div>
</div>
