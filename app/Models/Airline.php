<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Airline extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'flight_frequency'  => 'integer',
            'is_auto_generated' => 'boolean',
        ];
    }

    // ──────────────────────────────────────
    // Accessors
    // ──────────────────────────────────────

    public function getFullCodeAttribute(): string
    {
        $iata = $this->iata_code ? "/{$this->iata_code}" : '';
        $icao = $this->icao_code ?? '—';
        return "{$icao}{$iata}";
    }

    public function getCoverageLabelAttribute(): string
    {
        return match ($this->coverage) {
            'domestik'       => 'Domestik',
            'internasional'  => 'Internasional',
            default          => ucfirst($this->coverage),
        };
    }

    public function getOperationalStatusLabelAttribute(): string
    {
        return match ($this->operational_status) {
            'beroperasi' => 'Beroperasi',
            'tidak'      => 'Tidak Beroperasi',
            default      => ucfirst($this->operational_status),
        };
    }

    // ──────────────────────────────────────
    // Relations
    // ──────────────────────────────────────

    public function flightTraffics(): HasMany
    {
        return $this->hasMany(FlightTraffic::class);
    }

    // ──────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────

    /**
     * Maskapai yang dibuat otomatis dari proses import Excel
     * dan belum dilengkapi kode ICAO/IATA-nya oleh admin.
     */
    public function scopeNeedsVerification($query)
    {
        return $query->where('is_auto_generated', true);
    }

    // ──────────────────────────────────────
    // Spatie Activity Log
    // ──────────────────────────────────────

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['icao_code', 'iata_code', 'brand_name', 'operator_name', 'coverage', 'operational_status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "Maskapai {$this->brand_name} telah di-{$eventName}");
    }
}
