<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bill extends Model
{
    use HasFactory;

    protected $connection = 'tenant';

    protected $fillable = [
        'bill_number',
        'order_id',
        'customer_id',
        'subtotal',
        'tax_amount',
        'tax_breakdown',
        'discount_amount',
        'discount_type',
        'discount_value',
        'discount_reason',
        'service_charge',
        'delivery_charge',
        'packaging_charge',
        'round_off',
        'total',
        'paid_amount',
        'balance',
        'payment_status',
        'payment_details',
        'cashier_id',
        'paid_at',
        'printed_at',
        'sent_via_whatsapp',
        'whatsapp_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'tax_breakdown' => 'array',
            'discount_amount' => 'decimal:2',
            'discount_value' => 'decimal:2',
            'service_charge' => 'decimal:2',
            'delivery_charge' => 'decimal:2',
            'packaging_charge' => 'decimal:2',
            'round_off' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'balance' => 'decimal:2',
            'payment_details' => 'array',
            'paid_at' => 'datetime',
            'printed_at' => 'datetime',
            'sent_via_whatsapp' => 'boolean',
            'whatsapp_sent_at' => 'datetime',
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($bill) {
            if (!$bill->bill_number) {
                $bill->bill_number = static::generateBillNumber();
            }
        });
    }

    public static function generateBillNumber(): string
    {
        $date = now()->format('Ymd');
        $count = static::whereDate('created_at', today())->count() + 1;
        return 'INV-' . $date . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    public function isPartiallyPaid(): bool
    {
        return $this->payment_status === 'partial';
    }

    public function isUnpaid(): bool
    {
        return $this->payment_status === 'unpaid';
    }

    public function recordPayment(float $amount, string $method = 'cash'): void
    {
        $this->paid_amount += $amount;
        $this->balance = $this->total - $this->paid_amount;

        $payments = $this->payment_details ?? [];
        $payments[] = [
            'method' => $method,
            'amount' => $amount,
            'timestamp' => now()->toIso8601String(),
        ];

        $status = $this->balance <= 0 ? 'paid' : ($this->paid_amount > 0 ? 'partial' : 'unpaid');

        $this->update([
            'paid_amount' => $this->paid_amount,
            'balance' => $this->balance,
            'payment_status' => $status,
            'payment_details' => $payments,
            'paid_at' => $status === 'paid' ? now() : $this->paid_at,
        ]);
    }

    public function applyDiscount(float $value, string $type = 'fixed', string $reason = null): void
    {
        $discountAmount = $type === 'percentage'
            ? ($this->subtotal * $value / 100)
            : $value;

        $this->update([
            'discount_type' => $type,
            'discount_value' => $value,
            'discount_amount' => $discountAmount,
            'discount_reason' => $reason,
            'total' => $this->subtotal + $this->tax_amount + $this->service_charge
                + $this->delivery_charge + ($this->packaging_charge ?? 0)
                + ($this->round_off ?? 0) - $discountAmount,
            'balance' => $this->total - $this->paid_amount,
        ]);
    }

    public function markAsPrinted(): void
    {
        $this->update(['printed_at' => now()]);
    }

    public function markAsWhatsAppSent(): void
    {
        $this->update([
            'sent_via_whatsapp' => true,
            'whatsapp_sent_at' => now(),
        ]);
    }

    public function scopePaid($query)
    {
        return $query->where('payment_status', 'paid');
    }

    public function scopeUnpaid($query)
    {
        return $query->where('payment_status', 'unpaid');
    }

    public function scopePartial($query)
    {
        return $query->where('payment_status', 'partial');
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }
}
