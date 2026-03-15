<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use HasFactory;

    protected $connection = 'tenant';

    protected $fillable = [
        'order_number',
        'table_id',
        'customer_id',
        'type',
        'status',
        'subtotal',
        'tax_amount',
        'tax_breakdown',
        'discount_amount',
        'delivery_charge',
        'packaging_charge',
        'round_off',
        'total',
        'guest_count',
        'special_instructions',
        'created_by',
        'served_by',
        'kot_printed_at',
        'cooking_started_at',
        'ready_at',
        'served_at',
        'completed_at',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'tax_breakdown' => 'array',
            'discount_amount' => 'decimal:2',
            'delivery_charge' => 'decimal:2',
            'packaging_charge' => 'decimal:2',
            'round_off' => 'decimal:2',
            'total' => 'decimal:2',
            'guest_count' => 'integer',
            'kot_printed_at' => 'datetime',
            'cooking_started_at' => 'datetime',
            'ready_at' => 'datetime',
            'served_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($order) {
            if (!$order->order_number) {
                $order->order_number = static::generateOrderNumber();
            }
        });
    }

    public static function generateOrderNumber(): string
    {
        $date = now()->format('Ymd');
        $count = static::whereDate('created_at', today())->count() + 1;
        return $date . str_pad($count, 4, '0', STR_PAD_LEFT);
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(Table::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function bill(): HasOne
    {
        return $this->hasOne(Bill::class);
    }

    public function calculateTotal(): void
    {
        $itemSubtotal = (float) $this->items()->sum('subtotal');
        $tax = (float) $this->items()->sum('tax_amount');

        $this->update([
            'subtotal' => $itemSubtotal,
            'tax_amount' => $tax,
            'total' => $itemSubtotal + $tax
                + ($this->packaging_charge ?? 0)
                + ($this->delivery_charge ?? 0)
                - ($this->discount_amount ?? 0)
                + ($this->round_off ?? 0),
        ]);
    }

    /**
     * Recalculate totals with aggregated tax breakdown and round-off.
     */
    public function recalculateWithTaxBreakdown(\App\Services\TaxService $taxService): void
    {
        $items = $this->items()->get();
        $itemBreakdowns = $items->pluck('tax_breakdown')->filter()->toArray();
        $aggregated = $taxService->aggregateTaxBreakdown($itemBreakdowns);

        $itemSubtotal = (float) $items->sum('subtotal');
        $tax = (float) $items->sum('tax_amount');

        $preRoundTotal = $itemSubtotal + $tax
            + ($this->packaging_charge ?? 0)
            + ($this->delivery_charge ?? 0)
            - ($this->discount_amount ?? 0);

        $roundOff = $taxService->calculateRoundOff($preRoundTotal);

        $this->update([
            'subtotal' => $itemSubtotal,
            'tax_amount' => $tax,
            'tax_breakdown' => $aggregated,
            'round_off' => $roundOff,
            'total' => $preRoundTotal + $roundOff,
        ]);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function markAsPreparing(): void
    {
        $this->update([
            'status' => 'preparing',
            'cooking_started_at' => now(),
        ]);
    }

    public function markAsReady(): void
    {
        $this->update([
            'status' => 'ready',
            'ready_at' => now(),
        ]);
    }

    public function markAsServed(): void
    {
        $this->update([
            'status' => 'served',
            'served_at' => now(),
        ]);
    }

    public function complete(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    public function cancel(string $reason = null): void
    {
        $this->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ]);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopePreparing($query)
    {
        return $query->where('status', 'preparing');
    }

    public function scopeReady($query)
    {
        return $query->where('status', 'ready');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }
}
