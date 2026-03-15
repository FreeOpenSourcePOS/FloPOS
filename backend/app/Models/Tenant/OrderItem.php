<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    protected $connection = 'tenant';

    protected $fillable = [
        'order_id',
        'product_id',
        'product_name',
        'product_sku',
        'unit_price',
        'quantity',
        'subtotal',
        'tax_amount',
        'tax_breakdown',
        'tax_type',
        'discount_amount',
        'total',
        'variant_selection',
        'modifier_selection',
        'addons',
        'special_instructions',
        'status',
        'prepared_at',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'quantity' => 'integer',
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'tax_breakdown' => 'array',
            'variant_selection' => 'array',
            'modifier_selection' => 'array',
            'addons' => 'array',
            'prepared_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function markAsPreparing(): void
    {
        $this->update(['status' => 'preparing']);
    }

    public function markAsReady(): void
    {
        $this->update([
            'status' => 'ready',
            'prepared_at' => now(),
        ]);
    }

    public function markAsServed(): void
    {
        $this->update(['status' => 'served']);
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

    public function scopeServed($query)
    {
        return $query->where('status', 'served');
    }
}
