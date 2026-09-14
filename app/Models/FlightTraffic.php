<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class FlightTraffic extends Model
{
    use HasFactory, LogsActivity;

    /**
     * Nama tabel di database.
     * Didefinisikan secara eksplisit untuk mencegah konversi otomatis
     * (uncountable noun 'traffic' pada Laravel).
     *
     * Sesuaikan ke 'flight_traffics' atau 'flight_traffic' sesuai nama tabel pada Migration Anda.
     */
    protected $table = 'flight_traffics';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'schedule_date'      => 'date',
            'actual_date'        => 'date',
            'schedule_time'      => 'string',
            'actual_time'        => 'string',
            'seat_capacity'      => 'integer',
            'pax_adult'          => 'integer',
            'pax_child'          => 'integer',
            'pax_infant'         => 'integer',
            'transit_pax_adult'  => 'integer',
            'transit_pax_child'  => 'integer',
            'transit_pax_infant' => 'integer',
            'baggage_kg'         => 'decimal:2',
            'cargo_kg'           => 'decimal:2',
            'mail_kg'            => 'decimal:2',
        ];
    }

    // ──────────────────────────────────────
    // Computed / Virtual Attributes
    // ──────────────────────────────────────

    public function getTotalPaxAttribute(): int
    {
        return $this->pax_adult + $this->pax_child + $this->pax_infant;
    }

    public function getTotalTransitPaxAttribute(): int
    {
        return $this->transit_pax_adult + $this->transit_pax_child + $this->transit_pax_infant;
    }

    public function getTotalAllPaxAttribute(): int
    {
        return $this->total_pax + $this->total_transit_pax;
    }

    public function getTotalLogisticsAttribute(): float
    {
        return (float) $this->baggage_kg + (float) $this->cargo_kg + (float) $this->mail_kg;
    }

    public function getMovementLabelAttribute(): string
    {
        return match ($this->movement) {
            'Arrival'   => 'Datang',
            'Departure' => 'Berangkat',
            default     => $this->movement,
        };
    }

    public function getDelayMinutesAttribute(): ?int
    {
        if (! $this->actual_time || ! $this->schedule_time) {
            return null;
        }

        $scheduled = \Carbon\Carbon::parse($this->schedule_time);
        $actual    = \Carbon\Carbon::parse($this->actual_time);

        return (int) $scheduled->diffInMinutes($actual, false);
    }

    /**
     * True jika data ini memiliki catatan penyebab delay
     * (Kategori Delay dan/atau Keterangan Delay terisi).
     */
    public function getHasDelayInfoAttribute(): bool
    {
        return filled($this->delay_category) || filled($this->delay_reason);
    }

    // ──────────────────────────────────────
    // Relations
    // ──────────────────────────────────────

    public function airline(): BelongsTo
    {
        return $this->belongsTo(Airline::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ──────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────

    public function scopeArrivals($query)
    {
        return $query->where('movement', 'Arrival');
    }

    public function scopeDepartures($query)
    {
        return $query->where('movement', 'Departure');
    }

    public function scopeOntime($query)
    {
        return $query->where('flight_status', 'Ontime');
    }

    public function scopeDelayed($query)
    {
        return $query->where('flight_status', 'Delay');
    }

    public function scopeCancelled($query)
    {
        return $query->where('flight_status', 'Cancel');
    }

    public function scopeThisMonth($query)
    {
        return $query->whereMonth('schedule_date', now()->month)
                     ->whereYear('schedule_date', now()->year);
    }

    public function scopeThisYear($query)
    {
        return $query->whereYear('schedule_date', now()->year);
    }

    public function scopeDomestic($query)
    {
        return $query->where('coverage', 'Domestik');
    }

    public function scopeInternational($query)
    {
        return $query->where('coverage', 'Internasional');
    }

    // ──────────────────────────────────────
    // Spatie Activity Log
    // ──────────────────────────────────────

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'flight_number', 'schedule_date', 'actual_date',
                'flight_status', 'movement', 'airline_id',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(
                fn (string $eventName) => "Data penerbangan {$this->flight_number} telah di-{$eventName}"
            );
    }
}
