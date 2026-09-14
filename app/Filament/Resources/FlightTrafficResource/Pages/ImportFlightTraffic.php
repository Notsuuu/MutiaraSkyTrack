<?php

namespace App\Filament\Resources\FlightTrafficResource\Pages;

use App\Filament\Resources\FlightTrafficResource;
use App\Imports\FlightTrafficImport;
use App\Models\FlightTraffic;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Activitylog\Models\Activity;

class ImportFlightTraffic extends Page implements HasForms, HasActions
{
    use InteractsWithForms;
    use InteractsWithActions;

    protected static string $resource = FlightTrafficResource::class;

    protected string $view = 'filament.resources.flight-traffic-resource.pages.import-flight-traffic';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('attachment')
                    ->label('Unggah Laporan Lalu Lintas Udara')
                    ->placeholder('Seret & letakkan file Excel di sini atau klik untuk memilih file')
                    ->acceptedFileTypes([
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/vnd.ms-excel',
                        'text/csv',
                    ])
                    ->disk('local')
                    ->directory('imports/flight-traffic')
                    ->required()
                    ->maxSize(10 * 1024)
                    ->helperText('Format: .xlsx, .xls, atau .csv — maksimal 10MB'),

                Placeholder::make('info')
                    ->label('')
                    ->content('File rekap operasional yang sudah dipakai petugas bisa langsung diupload tanpa perlu diubah strukturnya. Maskapai yang belum terdaftar akan otomatis dibuat dan ditandai untuk diverifikasi.'),
            ])
            ->statePath('data');
    }

    public function getImportHistory(): Collection
    {
        return Activity::query()
            ->where('description', 'Import data penerbangan dari Excel')
            ->with('causer')
            ->latest()
            ->limit(20)
            ->get();
    }

    /**
     * ⚡ ACTION dengan modal konfirmasi custom Filament.
     * Menggantikan wire:confirm native browser.
     */
    public function deleteBatchAction(): Action
    {
        return Action::make('deleteBatchAction')
            ->label('Hapus Batch')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->requiresConfirmation()
            ->modalIcon('heroicon-o-exclamation-triangle')
            ->modalIconColor('danger')
            ->modalHeading('Hapus Batch Import?')
            ->modalDescription('Semua data penerbangan dari batch ini akan dihapus PERMANEN dari database. Tindakan ini tidak dapat dibatalkan.')
            ->modalSubmitActionLabel('Ya, Hapus Permanen')
            ->modalCancelActionLabel('Batal')
            ->modalWidth('md')
            ->action(function (array $arguments) {
                $activityId = (int) ($arguments['activityId'] ?? 0);
                $this->deleteBatch($activityId);
            });
    }

    public function import(): void
    {
        $data = $this->form->getState();

        if (empty($data['attachment'])) {
            Notification::make()
                ->title('Tidak ada file')
                ->body('Silakan unggah file Excel terlebih dahulu.')
                ->warning()
                ->send();
            return;
        }

        $filePath = Storage::disk('local')->path($data['attachment']);

        if (! file_exists($filePath)) {
            $filePath = Storage::disk('public')->path($data['attachment']);
        }

        try {
            $import = new FlightTrafficImport();
            Excel::import($import, $filePath);

            $batchId          = $import->getBatchId();
            $successCount     = $import->getSuccessCount();
            $skipCount        = $import->getSkipCount();
            $failureCount     = count($import->failures());
            $errorCount       = count($import->errors());
            $autoCreatedCount = $import->getAutoCreatedAirlineCount();
            $totalIssues      = $failureCount + $errorCount + $skipCount;

            $message = "Berhasil: {$successCount} baris | Gagal/dilewati: {$totalIssues} baris"
                . ($autoCreatedCount > 0 ? " | {$autoCreatedCount} maskapai baru otomatis dibuat, perlu diverifikasi." : '');

            Notification::make()
                ->title($totalIssues > 0 ? 'Import selesai dengan peringatan' : 'Import berhasil!')
                ->body($message)
                ->{$totalIssues > 0 ? 'warning' : 'success'}()
                ->persistent()
                ->send();

            activity()
                ->causedBy(Auth::user())
                ->withProperties([
                    'success_count'      => $successCount,
                    'skip_count'         => $totalIssues,
                    'auto_created_count' => $autoCreatedCount,
                    'file'               => $data['attachment'],
                    'batch_id'           => $batchId,
                ])
                ->log('Import data penerbangan dari Excel');

        } catch (\Throwable $e) {
            Notification::make()
                ->title('Import gagal!')
                ->body('Terjadi kesalahan: ' . $e->getMessage())
                ->danger()
                ->persistent()
                ->send();
        } finally {
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
        }

        $this->form->fill();
    }

    /**
     * Hapus PERMANEN semua data penerbangan dari batch import tertentu.
     * Model FlightTraffic tidak pakai SoftDeletes, jadi ->delete() = hard delete.
     */
    public function deleteBatch(int $activityId): void
    {
        $activity = Activity::find($activityId);

        if (! $activity || $activity->description !== 'Import data penerbangan dari Excel') {
            Notification::make()
                ->title('Data tidak ditemukan')
                ->body('Batch import yang Anda pilih tidak valid.')
                ->danger()
                ->send();
            return;
        }

        $batchId = $activity->properties['batch_id'] ?? null;

        if (! $batchId) {
            Notification::make()
                ->title('Batch ID tidak ditemukan')
                ->body('Import ini tidak memiliki batch ID.')
                ->warning()
                ->persistent()
                ->send();
            return;
        }

        $deletedCount = FlightTraffic::where('import_batch_id', $batchId)->delete();

        Notification::make()
            ->title('Batch berhasil dihapus')
            ->body("{$deletedCount} data penerbangan dari batch ini telah dihapus permanen.")
            ->success()
            ->send();

        $activity->delete();

        activity()
            ->causedBy(Auth::user())
            ->withProperties([
                'batch_id'      => $batchId,
                'deleted_count' => $deletedCount,
            ])
            ->log('Hapus batch import Excel dari riwayat');
    }

    public function getTitle(): string
    {
        return 'Import Excel';
    }
}
