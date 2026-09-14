<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateBatchActivityLogsCommand extends Command
{
    protected $signature = 'skytrack:generate-batch-logs
                            {--dry-run : Tampilkan saja tanpa mengubah database}
                            {--force : Lewati konfirmasi}';

    protected $description = 'Generate activity log retroaktif dari batch_id yang sudah ada di flight_traffics';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force  = (bool) $this->option('force');

        $this->info('Scanning flight_traffics untuk batch unik...');
        $this->newLine();

        $batches = DB::table('flight_traffics')
            ->selectRaw('
                import_batch_id,
                COUNT(*) as total,
                MIN(created_at) as created_at,
                MAX(created_by) as created_by,
                MIN(schedule_date) as min_date,
                MAX(schedule_date) as max_date
            ')
            ->whereNotNull('import_batch_id')
            ->groupBy('import_batch_id')
            ->orderBy('created_at', 'desc')
            ->get();

        if ($batches->isEmpty()) {
            $this->error('❌ Tidak ada batch_id di flight_traffics.');
            return self::FAILURE;
        }

        $this->info("Ditemukan {$batches->count()} batch:");
        $this->newLine();

        $table = $batches->map(function ($row) {
            $user = $row->created_by ? User::find($row->created_by) : null;
            return [
                substr($row->import_batch_id, 0, 8) . '...',
                $row->total,
                $row->created_at,
                $user?->name ?? '(system)',
                $row->min_date . ' → ' . $row->max_date,
            ];
        })->toArray();

        $this->table(
            ['Batch ID', 'Records', 'Created At', 'User', 'Rentang Tanggal'],
            $table
        );

        // Cek activity log existing
        $existingBatches = DB::table('activity_log')
            ->where('description', 'like', '%Import data penerbangan%')
            ->get()
            ->map(fn ($log) => json_decode($log->properties, true)['batch_id'] ?? null)
            ->filter()
            ->toArray();

        if (! empty($existingBatches)) {
            $this->line('Activity log yang sudah ada: ' . count($existingBatches) . ' batch');
        }

        $needGenerate = $batches->filter(fn ($b) => ! in_array($b->import_batch_id, $existingBatches));
        $this->newLine();
        $this->info("Batch yang perlu di-generate activity log: {$needGenerate->count()}");

        if ($dryRun) {
            $this->warn('⚠ Dry-run mode — tidak ada perubahan di database.');
            return self::SUCCESS;
        }

        if (! $force && ! $this->confirm('Lanjutkan generate activity log?', true)) {
            $this->info('Dibatalkan.');
            return self::SUCCESS;
        }

        $created = 0;

        foreach ($needGenerate as $batch) {
            $user = $batch->created_by ? User::find($batch->created_by) : null;
            $fileName = 'Import_Batch_' . date('Ymd_His', strtotime($batch->created_at)) . '.xlsx';

            DB::table('activity_log')->insert([
                'log_name'     => 'default',
                'description'  => 'Import data penerbangan dari Excel',
                'subject_type' => null,
                'subject_id'   => null,
                'causer_type'  => $user ? get_class($user) : null,
                'causer_id'    => $user?->id,
                'properties'   => json_encode([
                    'success_count'      => $batch->total,
                    'skip_count'         => 0,
                    'auto_created_count' => 0,
                    'file'               => $fileName,
                    'batch_id'           => $batch->import_batch_id,
                    'retroactive'        => true,
                ]),
                'created_at'   => $batch->created_at,
                'updated_at'   => $batch->created_at,
            ]);

            $created++;
            $this->line("  ✓ Log dibuat untuk batch: " . substr($batch->import_batch_id, 0, 8) . "... ({$batch->total} records)");
        }

        $this->newLine();
        $this->info("✅ Selesai. {$created} activity log dibuat.");
        $this->line('Buka /admin/flight-traffic/import — riwayat sekarang muncul.');

        return self::SUCCESS;
    }
}
