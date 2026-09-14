@php
    $loginCssPath = public_path('css/filament/auth/login.css');
    $loginCssVersion = file_exists($loginCssPath) ? filemtime($loginCssPath) : time();
@endphp

<div
    class="skytrack-login-wrapper"
    style="--skytrack-bg-image: url('{{ asset('images/bandara.jpeg') }}');"
>
    {{-- CSS link DI DALAM root div, bukan di luar --}}
    <link rel="stylesheet" href="{{ asset('css/filament/auth/login.css') }}?v={{ $loginCssVersion }}">

    {{-- ────────────────────────────────────
         Sidebar Kiri
         ──────────────────────────────────── --}}
    <div class="skytrack-sidebar">
        <div class="skytrack-brand">
            <div class="skytrack-logo-box">
                <img src="{{ asset('images/logo.mutiara.png') }}" alt="Logo Mutiara SkyTrack">
            </div>
            <div class="skytrack-brand-text">
                <h2>Mutiara SkyTrack</h2>
                <p>Bandar Udara PLW</p>
            </div>
        </div>

        <div class="skytrack-content-body">
            <h1>Selamat Datang Kembali</h1>
            <p class="desc">
                Sistem Analitik Pemantau dan Manajemen Data Lalu Lintas Udara Bandar Udara Mutiara SIS Al-Jufrie Palu secara Real-Time, Akurat, dan Terintegrasi.
            </p>

            <ul class="skytrack-features">
                <li>
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                    </svg>
                    <span>Pencatatan data lalu lintas udara real-time</span>
                </li>
                <li>
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                    </svg>
                    <span>Analitik &amp; laporan rekap bulanan / tahunan</span>
                </li>
                <li>
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                    </svg>
                    <span>Import massal dari template Excel LLAU</span>
                </li>
            </ul>
        </div>

        <div class="skytrack-footer">
            © 2024–{{ date('Y') }} UPT Bandara Mutiara — Palu
        </div>
    </div>

    {{-- ────────────────────────────────────
         Panel Kanan (Background + Form Login)
         ──────────────────────────────────── --}}
    <div class="skytrack-main-bg">
        <div class="skytrack-overlay"></div>

        <div class="skytrack-card">
            <div class="skytrack-card-header">
                <h2>Masuk ke Sistem</h2>
                <p>Gunakan alamat email dan kata sandi Anda untuk masuk</p>
            </div>

            <form wire:submit="authenticate">
                {{ $this->form }}
            </form>

            <div class="skytrack-card-footer">
                Hubungi administrator jika Anda memerlukan bantuan akses akun.
            </div>
        </div>
    </div>
</div>
