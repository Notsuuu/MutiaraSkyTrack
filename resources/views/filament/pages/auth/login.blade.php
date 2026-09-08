<div class="skytrack-login-wrapper">
    <style>
        .skytrack-login-wrapper {
            display: flex;
            min-height: 100vh;
            width: 100vw;
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: #0b132b;
            overflow: hidden;
        }

        /* Sidebar Kiri */
        .skytrack-sidebar {
            width: 420px;
            min-width: 420px;
            background-color: #0b132b;
            color: #ffffff;
            padding: 2.5rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            border-right: 1px solid rgba(255, 255, 255, 0.08);
            box-sizing: border-box;
            z-index: 10;
        }

        .skytrack-brand {
            display: flex;
            align-items: center;
            gap: 0.85rem;
        }

        .skytrack-logo-box {
            width: 48px;
            height: 48px;
            background: #ffffff;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 6px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
        }

        .skytrack-logo-box img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .skytrack-brand-text h2 {
            font-size: 1.1rem;
            font-weight: 700;
            margin: 0;
            color: #ffffff;
            line-height: 1.2;
        }

        .skytrack-brand-text p {
            font-size: 0.65rem;
            color: #64748b;
            font-weight: 600;
            letter-spacing: 0.1em;
            margin: 2px 0 0 0;
            text-transform: uppercase;
        }

        .skytrack-content-body {
            margin: auto 0;
            padding: 2rem 0;
        }

        .skytrack-content-body h1 {
            font-size: 1.85rem;
            font-weight: 700;
            color: #ffffff;
            margin: 0 0 1rem 0;
            line-height: 1.25;
        }

        .skytrack-content-body p.desc {
            font-size: 0.875rem;
            color: #94a3b8;
            line-height: 1.6;
            margin: 0 0 1.75rem 0;
        }

        .skytrack-features {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            flex-direction: column;
            gap: 0.85rem;
        }

        .skytrack-features li {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.85rem;
            color: #cbd5e1;
        }

        .skytrack-features svg {
            width: 18px;
            height: 18px;
            color: #3b82f6;
            flex-shrink: 0;
        }

        .skytrack-footer {
            font-size: 0.75rem;
            color: #475569;
        }

        /* Panel Kanan (Background Image dari public/images/bandara.jpeg) */
        .skytrack-main-bg {
            flex: 1;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            background-image: url('{{ asset("images/bandara.jpeg") }}');
            background-size: cover;
            background-position: center;
        }

        .skytrack-overlay {
            position: absolute;
            inset: 0;
            background: rgba(10, 15, 30, 0.65);
            backdrop-filter: blur(2px);
        }

        .skytrack-card {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 440px;
            background: #ffffff;
            border-radius: 16px;
            padding: 2.25rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4);
            box-sizing: border-box;
        }

        .skytrack-card-header {
            margin-bottom: 1.5rem;
        }

        .skytrack-card-header h2 {
            font-size: 1.4rem;
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 0.35rem 0;
        }

        .skytrack-card-header p {
            font-size: 0.8rem;
            color: #64748b;
            margin: 0;
        }

        .skytrack-card-footer {
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid #f1f5f9;
            text-align: center;
            font-size: 0.75rem;
            color: #64748b;
        }

        .skytrack-card label {
            font-size: 0.7rem !important;
            font-weight: 700 !important;
            letter-spacing: 0.05em !important;
            color: #475569 !important;
        }

        .skytrack-card button[type="submit"] {
            background-color: #0284c7 !important;
            color: #ffffff !important;
            font-weight: 600 !important;
            border-radius: 10px !important;
            padding: 0.75rem 1rem !important;
            transition: all 0.2s ease !important;
        }

        .skytrack-card button[type="submit"]:hover {
            background-color: #0369a1 !important;
        }

        @media (max-width: 1024px) {
            .skytrack-sidebar {
                display: none;
            }
        }
    </style>

    <!-- Sidebar Kiri -->
    <div class="skytrack-sidebar">
        <div class="skytrack-brand">
            <div class="skytrack-logo-box">
                <!-- Memanggil public/images/logo.png -->
                <img src="{{ asset('images/logo.mutiara.png') }}" alt="Logo Mutiara SkyTrack">
            </div>
            <div class="skytrack-brand-text">
                <h2>Mutiara SkyTrack</h2>
                <p>BANDAR UDARA PLW</p>
            </div>
        </div>

        <div class="skytrack-content-body">
            <h1>Selamat Datang Kembali</h1>
            <p class="desc">
                Sistem Analitik Pemantau dan Manajemen Data Lalu Lintas Udara Bandar Udara Mutiara Sis Al-Jufri Palu secara Real-Time, Akurat, dan Terintegrasi.
            </p>

            <ul class="skytrack-features">
                <li>
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                    </svg>
                    <span>Pencatatan data lalu lintas udara real-time</span>
                </li>
                <li>
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                    </svg>
                    <span>Analitik & laporan rekap bulanan / tahunan</span>
                </li>
                <li>
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                    </svg>
                    <span>Import massal dari template Excel LLAU</span>
                </li>
            </ul>
        </div>

        <div class="skytrack-footer">
            © {{ date('Y') }} Mutiara SkyTrack. All rights reserved.
        </div>
    </div>

    <!-- Panel Kanan (Background Image + Form Login) -->
    <div class="skytrack-main-bg">
        <div class="skytrack-overlay"></div>

        <div class="skytrack-card">
            <div class="skytrack-card-header">
                <h2>Masuk ke Sistem</h2>
                <p>Gunakan alamat email dan kata sandi Anda untuk masuk.</p>
            </div>

            <x-filament-panels::form wire:submit="authenticate">
                {{ $this->form }}

                <x-filament-panels::form.actions
                    :actions="$this->getCachedFormActions()"
                    :full-width="true"
                />
            </x-filament-panels::form>

            <div class="skytrack-card-footer">
                Hubungi administrator jika Anda memerlukan bantuan akses akun.
            </div>
        </div>
    </div>
</div>
