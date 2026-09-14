<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mutiara SkyTrack — Bandar Udara Mutiara SIS Al-Jufrie Palu</title>

    <link rel="icon" href="{{ asset('images/favicon.ico') }}">
    <meta name="description" content="Sistem Analitik Pemantau dan Manajemen Data Lalu Lintas Udara Bandar Udara Mutiara SIS Al-Jufrie Palu — Real-Time, Akurat, Terintegrasi.">

    @php
        $cssPath = public_path('css/skytrack-landing.css');
        $cssVersion = file_exists($cssPath) ? filemtime($cssPath) : time();
    @endphp
    <link rel="stylesheet" href="{{ asset('css/skytrack-landing.css') }}?v={{ $cssVersion }}">
</head>
<body>
    <div class="sk-landing">
        {{-- ═══════════════════════════════════════════════
             NAVBAR
             ═══════════════════════════════════════════════ --}}
        <nav class="sk-nav">
            <div class="sk-nav-inner">
                <a href="/" class="sk-nav-brand">
                    <div class="sk-nav-logo">
                        <img src="{{ asset('images/logo.mutiara.png') }}" alt="Mutiara SkyTrack">
                    </div>
                    <div class="sk-nav-brand-text">
                        <div class="sk-nav-brand-name">Mutiara SkyTrack</div>
                        <div class="sk-nav-brand-subtitle">Bandar Udara PLW</div>
                    </div>
                </a>

                <div class="sk-nav-links">
                    <a href="#fitur">Fitur</a>
                    <a href="#tentang">Tentang</a>
                    @auth
                        <a href="/admin" class="sk-btn sk-btn-primary">
                            <x-heroicon-m-squares-2x2 class="sk-btn-icon" />
                            Buka Panel Admin
                        </a>
                    @else
                        <a href="/admin/login" class="sk-btn sk-btn-primary">
                            <x-heroicon-m-arrow-right-end-on-rectangle class="sk-btn-icon" />
                            Masuk ke Sistem
                        </a>
                    @endauth
                </div>
            </div>
        </nav>

        {{-- ═══════════════════════════════════════════════
             HERO
             ═══════════════════════════════════════════════ --}}
        <section class="sk-hero">
            <div class="sk-hero-overlay"></div>
            <div class="sk-hero-content">
                <div class="sk-hero-badge">
                    <span class="sk-hero-badge-dot"></span>
                    Sistem Aktif — Real-Time Monitoring
                </div>

                <h1 class="sk-hero-title">
                    Pantau Lalu Lintas Udara<br>
                    <span class="sk-hero-title-highlight">Bandar Udara Mutiara</span><br>
                    Secara Real-Time
                </h1>

                <p class="sk-hero-desc">
                    Sistem analitik pemantau dan manajemen data lalu lintas udara
                    Bandar Udara Mutiara SIS Al-Jufrie Palu — akurat, terintegrasi,
                    dan mudah digunakan untuk seluruh personel operasional.
                </p>

                <div class="sk-hero-actions">
                    @auth
                        <a href="/admin" class="sk-btn sk-btn-primary sk-btn-lg">
                            <x-heroicon-m-squares-2x2 class="sk-btn-icon" />
                            Buka Panel Admin
                        </a>
                    @else
                        <a href="/admin/login" class="sk-btn sk-btn-primary sk-btn-lg">
                            <x-heroicon-m-arrow-right-end-on-rectangle class="sk-btn-icon" />
                            Masuk ke Sistem
                        </a>
                        <a href="#fitur" class="sk-btn sk-btn-ghost sk-btn-lg">
                            Pelajari Fitur
                            <x-heroicon-m-arrow-down class="sk-btn-icon" />
                        </a>
                    @endauth
                </div>

                <div class="sk-hero-stats">
                    <div class="sk-hero-stat">
                        <div class="sk-hero-stat-value">Real-Time</div>
                        <div class="sk-hero-stat-label">Data Penerbangan</div>
                    </div>
                    <div class="sk-hero-stat-divider"></div>
                    <div class="sk-hero-stat">
                        <div class="sk-hero-stat-value">Excel</div>
                        <div class="sk-hero-stat-label">Import Massal LLAU</div>
                    </div>
                    <div class="sk-hero-stat-divider"></div>
                    <div class="sk-hero-stat">
                        <div class="sk-hero-stat-value">Analitik</div>
                        <div class="sk-hero-stat-label">Visualisasi Dashboard</div>
                    </div>
                </div>
            </div>
        </section>

        {{-- ═══════════════════════════════════════════════
             FITUR
             ═══════════════════════════════════════════════ --}}
        <section class="sk-section" id="fitur">
            <div class="sk-section-inner">
                <div class="sk-section-header">
                    <div class="sk-section-eyebrow">Fitur Unggulan</div>
                    <h2 class="sk-section-title">Semua yang Anda Butuhkan untuk Operasional LLAU</h2>
                    <p class="sk-section-desc">
                        Dirancang khusus untuk kebutuhan pemantauan dan pelaporan
                        lalu lintas udara di Bandar Udara Mutiara.
                    </p>
                </div>

                <div class="sk-features">
                    <div class="sk-feature-card">
                        <div class="sk-feature-icon sk-feature-icon-indigo">
                            <x-heroicon-o-paper-airplane class="sk-feature-icon-svg" />
                        </div>
                        <h3 class="sk-feature-title">Pencatatan Real-Time</h3>
                        <p class="sk-feature-desc">
                            Catat dan pantau setiap pergerakan penerbangan — Arrival
                            maupun Departure — dengan data yang langsung tersinkron.
                        </p>
                    </div>

                    <div class="sk-feature-card">
                        <div class="sk-feature-icon sk-feature-icon-blue">
                            <x-heroicon-o-arrow-up-tray class="sk-feature-icon-svg" />
                        </div>
                        <h3 class="sk-feature-title">Import Massal Excel</h3>
                        <p class="sk-feature-desc">
                            Unggah file rekap operasional harian dalam format .xlsx
                            dan sistem akan memprosesnya secara otomatis.
                        </p>
                    </div>

                    <div class="sk-feature-card">
                        <div class="sk-feature-icon sk-feature-icon-emerald">
                            <x-heroicon-o-chart-bar-square class="sk-feature-icon-svg" />
                        </div>
                        <h3 class="sk-feature-title">Dashboard Analitik</h3>
                        <p class="sk-feature-desc">
                            Visualisasi tren pergerakan, volume penumpang, kargo,
                            dan statistik maskapai dalam satu tampilan.
                        </p>
                    </div>

                    <div class="sk-feature-card">
                        <div class="sk-feature-icon sk-feature-icon-amber">
                            <x-heroicon-o-clock class="sk-feature-icon-svg" />
                        </div>
                        <h3 class="sk-feature-title">Rekap Bulanan & Tahunan</h3>
                        <p class="sk-feature-desc">
                            Laporan periodik siap ekspor untuk kebutuhan pelaporan
                            internal maupun eksternal instansi.
                        </p>
                    </div>

                    <div class="sk-feature-card">
                        <div class="sk-feature-icon sk-feature-icon-rose">
                            <x-heroicon-o-shield-check class="sk-feature-icon-svg" />
                        </div>
                        <h3 class="sk-feature-title">Manajemen Akses</h3>
                        <p class="sk-feature-desc">
                            Kelola peran Admin dan Staff dengan hak akses terpisah
                            serta audit log aktivitas pengguna.
                        </p>
                    </div>

                    <div class="sk-feature-card">
                        <div class="sk-feature-icon sk-feature-icon-slate">
                            <x-heroicon-o-building-office-2 class="sk-feature-icon-svg" />
                        </div>
                        <h3 class="sk-feature-title">Master Data Lengkap</h3>
                        <p class="sk-feature-desc">
                            Database maskapai, kode ICAO/IATA, dan referensi bandara
                            terintegrasi dalam satu sistem terpusat.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        {{-- ═══════════════════════════════════════════════
             TENTANG
             ═══════════════════════════════════════════════ --}}
        <section class="sk-section sk-section-alt" id="tentang">
            <div class="sk-section-inner">
                <div class="sk-about">
                    <div class="sk-about-text">
                        <div class="sk-section-eyebrow">Tentang Sistem</div>
                        <h2 class="sk-section-title sk-section-title-left">
                            Dibangun untuk Efisiensi Operasional LLAU
                        </h2>
                        <p class="sk-about-desc">
                            Mutiara SkyTrack dikembangkan sebagai solusi digital
                            untuk menggantikan pencatatan manual data lalu lintas
                            udara. Sistem ini memungkinkan personel operasional
                            untuk fokus pada pengambilan keputusan strategis,
                            bukan pada administrasi data.
                        </p>

                        <ul class="sk-about-list">
                            <li>
                                <x-heroicon-s-check-circle class="sk-about-check" />
                                <span>Pencatatan data lalu lintas udara real-time</span>
                            </li>
                            <li>
                                <x-heroicon-s-check-circle class="sk-about-check" />
                                <span>Analitik &amp; laporan rekap bulanan / tahunan</span>
                            </li>
                            <li>
                                <x-heroicon-s-check-circle class="sk-about-check" />
                                <span>Import massal dari template Excel LLAU</span>
                            </li>
                            <li>
                                <x-heroicon-s-check-circle class="sk-about-check" />
                                <span>Audit log &amp; manajemen akses berbasis peran</span>
                            </li>
                        </ul>
                    </div>

                    <div class="sk-about-card">
                        <div class="sk-about-card-header">
                            <div class="sk-about-card-logo">
                                <img src="{{ asset('images/logo.mutiara.png') }}" alt="Mutiara">
                            </div>
                            <div>
                                <div class="sk-about-card-name">Mutiara SkyTrack</div>
                                <div class="sk-about-card-role">v1.0 — Production Ready</div>
                            </div>
                        </div>
                        <div class="sk-about-card-rows">
                            <div class="sk-about-card-row">
                                <span>Instansi</span>
                                <strong>UPT Bandar Udara Mutiara</strong>
                            </div>
                            <div class="sk-about-card-row">
                                <span>Kode Bandara</span>
                                <strong>PLW — Palu</strong>
                            </div>
                            <div class="sk-about-card-row">
                                <span>Framework</span>
                                <strong>Laravel 13 + Filament v5</strong>
                            </div>
                            <div class="sk-about-card-row">
                                <span>Database</span>
                                <strong>MySQL 8 + Redis</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- ═══════════════════════════════════════════════
             CTA
             ═══════════════════════════════════════════════ --}}
        <section class="sk-cta-section">
            <div class="sk-cta-inner">
                <h2 class="sk-cta-title">Siap Memulai?</h2>
                <p class="sk-cta-desc">
                    Masuk ke panel admin untuk mulai mencatat, memantau, dan
                    menganalisis data lalu lintas udara Bandar Udara Mutiara.
                </p>
                @auth
                    <a href="/admin" class="sk-btn sk-btn-primary sk-btn-lg">
                        <x-heroicon-m-squares-2x2 class="sk-btn-icon" />
                        Buka Panel Admin
                    </a>
                @else
                    <a href="/admin/login" class="sk-btn sk-btn-primary sk-btn-lg">
                        <x-heroicon-m-arrow-right-end-on-rectangle class="sk-btn-icon" />
                        Masuk ke Sistem
                    </a>
                @endauth
            </div>
        </section>

        {{-- ═══════════════════════════════════════════════
             FOOTER
             ═══════════════════════════════════════════════ --}}
        <footer class="sk-footer">
            <div class="sk-footer-inner">
                <div class="sk-footer-left">
                    <div class="sk-footer-brand">Mutiara SkyTrack</div>
                    <div class="sk-footer-copy">
                        © 2024–{{ date('Y') }} UPT Bandara Mutiara — Palu. All rights reserved.
                    </div>
                </div>
                <div class="sk-footer-right">
                    <span>Sistem Analitik Lalu Lintas Udara</span>
                </div>
            </div>
        </footer>
    </div>
</body>
</html>
