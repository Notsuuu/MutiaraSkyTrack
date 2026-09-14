<?php

namespace App\Console\Commands;

use App\Models\Airline;
use Illuminate\Console\Command;

class CleanupOrphanAirlinesCommand extends Command
{
    protected $signature = 'skytrack:cleanup-orphan-airlines
                            {--force : Lewati konfirmasi}
                            {--all : Hapus semua maskapai auto-generated tanpa relasi flight}';

    protected $description = 'Hapus maskapai yang dibuat otomatis (is_auto_generated=true) dan tidak dipakai oleh flight_traffics apapun';

    public function handle(): int
    {
        $force = (bool) $this->option('force');

        $this->info('Mencari maskapai auto-generated yang tidak dipakai...');
        $this->newLine();

        $orphans = Airline::query()
            ->where('is_auto_generated', true)
            ->whereDoesntHave('flightTraffics')
            ->orderBy('brand_name')
            ->get();

        if ($orphans->isEmpty()) {
            $this->info('✅ Tidak ada maskapai orphan. Semua maskapai auto-generated sudah dipakai.');
            return self::SUCCESS;
        }

        $this->warn("Ditemukan {$orphans->count()} maskapai orphan:");
        $this->newLine();

        $this->table(
            ['ID', 'Kode', 'Nama Maskapai', 'Dibuat'],
            $orphans->map(fn ($a) => [
                $a->id,
                $a->icao_code ?? '—',
                $a->brand_name,
                $a->created_at?->format('d/m/Y H:i'),
            ])->toArray()
        );

        if (! $force && ! $this->confirm('Hapus semua maskapai orphan di atas?', false)) {
            $this->info('Dibatalkan.');
            return self::SUCCESS;
        }

        $deleted = 0;
        foreach ($orphans as $airline) {
            $airline->forceDelete(); // hapus permanen, tidak soft delete
            $deleted++;
        }

        $this->newLine();
        $this->info("✅ Berhasil menghapus {$deleted} maskapai orphan.");

        return self::SUCCESS;
    }
}
