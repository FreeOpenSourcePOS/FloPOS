<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Table extends Model
{
    use HasFactory;

    protected $connection = 'tenant';

    protected $fillable = [
        'name',
        'capacity',
        'status',
        'kitchen_station_id',
        'floor',
        'section',
        'position_x',
        'position_y',
        'qr_code',
        'is_active',
        'reservation_customer_id',
        'reservation_customer_name',
        'reservation_customer_phone',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'position_x' => 'integer',
            'position_y' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function kitchenStation(): BelongsTo
    {
        return $this->belongsTo(KitchenStation::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function currentOrder()
    {
        return $this->hasOne(Order::class)
            ->whereIn('status', ['pending', 'preparing', 'ready'])
            ->latest();
    }

    public function isAvailable(): bool
    {
        return $this->status === 'available';
    }

    public function isOccupied(): bool
    {
        return $this->status === 'occupied';
    }

    public function markAsOccupied(): void
    {
        $this->update(['status' => 'occupied']);
    }

    public function markAsAvailable(): void
    {
        $this->update(['status' => 'available']);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
    }

    public function scopeOccupied($query)
    {
        return $query->where('status', 'occupied');
    }

    public function scopeByFloor($query, string $floor)
    {
        return $query->where('floor', $floor);
    }

    public function scopeBySection($query, string $section)
    {
        return $query->where('section', $section);
    }
}
