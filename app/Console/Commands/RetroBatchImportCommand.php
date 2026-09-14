<?php

namespace App\Console\Commands;

use App\Models\FlightTraffic;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RetroBatchImportCommand extends Command
{
    protected $signature = 'skytrack:retro-batch-import
                            {--min-rows=5 : Minimum jumlah record dalam 1 menit untuk dianggap 1 batch import}
                            {--dry-run : Tampilkan saja tanpa mengubah database}';

    protected $description = 'Tag data lama yang belum punya import_batch_id berdasarkan created_at + created_by';

    public function handle(): int
    {
        $minRows = (int) $this->option('min-rows');
        $dryRun  = (bool) $this->option('dry-run');

        $this->info("Mencari data lama tanpa batch_id (minimal {$minRows} record per menit)...");

        // Group by user + menit dari created_at
        $candidates = FlightTraffic::withTrashed()
            ->whereNull('import_batch_id')
            ->whereNotNull('created_by')
            ->selectRaw('created_by, DATE_FORMAT(created_at, "%Y-%m-%d %H:%i") as minute_bucket, COUNT(*) as total')
            ->groupBy('created_by', 'minute_bucket')
            ->having('total', '>=', $minRows)
            ->orderBy('minute_bucket')
            ->get();

        if ($candidates->isEmpty()) {
            $this->info('Tidak ada data yang perlu di-retro-batch.');
            $this->line('Semua data lama sudah punya batch_id, atau tidak ada grup yang mencapai minimal ' . $minRows . ' record.');
            return self::SUCCESS;
        }

        $this->newLine();
        $this->info("Ditemukan {$candidates->count()} potensi batch:");

        $table = $candidates->map(function ($row) {
            $user = User::find($row->created_by);
            return [
                $row->minute_bucket,
                $user?->name ?? 'User#' . $row->created_by,
                $row->total,
            ];
        })->toArray();

        $this->table(
            ['Waktu (menit)', 'Dibuat Oleh', 'Jumlah Record'],
            $table
        );

        if ($dryRun) {
            $this->warn('Dry-run mode — tidak ada perubahan di database.');
            return self::SUCCESS;
        }

        if (! $this->confirm('Lanjutkan retro-batch?', true)) {
            $this->info('Dibatalkan.');
            return self::SUCCESS;
        }

        $totalUpdated = 0;

        foreach ($candidates as $batch) {
            $batchId = (string) Str::uuid();

            $updated = FlightTraffic::withTrashed()
                ->whereNull('import_batch_id')
                ->where('created_by', $batch->created_by)
                ->whereRaw('DATE_FORMAT(created_at, "%Y-%m-%d %H:%i") = ?', [$batch->minute_bucket])
                ->update(['import_batch_id' => $batchId]);

            $totalUpdated += $updated;

            // Buat activity log retroaktif agar muncul di riwayat
            activity()
                ->causedBy(User::find($batch->created_by))
                ->withProperties([
                    'success_count'      => $updated,
                    'skip_count'         => 0,
                    'auto_created_count' => 0,
                    'file'               => "Retro-batch_{$batch->minute_bucket}.xlsx",
                    'batch_id'           => $batchId,
                    'retroactive'        => true,
                ])
                ->createdAt(Carbon::parse($batch->minute_bucket))
                ->log('Import data penerbangan dari Excel');

            $this->line("  → Batch {$batchId} : {$updated} records ({$batch->minute_bucket})");
        }

        $this->newLine();
        $this->info("Selesai. Total {$totalUpdated} records di-tag dengan batch_id.");
        $this->line('Buka halaman /admin/flight-traffic/import — riwayat sekarang muncul dengan tombol Hapus.');

        return self::SUCCESS;
    }
}
