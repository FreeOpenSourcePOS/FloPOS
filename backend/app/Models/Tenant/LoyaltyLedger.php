<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class LoyaltyLedger extends Model
{
    protected $connection = 'tenant';
    protected $table = 'loyalty_ledger';

    protected $fillable = [
        'customer_id',
        'bill_id',
        'type',
        'amount',
        'description',
        'expires_at',
    ];

    protected $casts = [
        'amount'     => 'float',
        'expires_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function bill()
    {
        return $this->belongsTo(Bill::class);
    }

    protected static function booted(): void
    {
        static::created(function (LoyaltyLedger $entry) {
            // Cloud wallet sync — no-op when service is not registered (self-hosted)
            try {
                if (app()->bound('wallet-sync')) {
                    $tenantId = request()->attributes->get('tenant')?->id;
                    if ($tenantId) {
                        app('wallet-sync')->sync($entry, $tenantId);
                    }
                }
            } catch (\Throwable) {
                // Non-fatal — local ledger entry was created successfully
            }
        });
    }

    /** Scope: only non-expired entries */
    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
        });
    }

    /** Get active wallet balance for a customer */
    public static function getBalance(int $customerId): float
    {
        $credits = self::where('customer_id', $customerId)
            ->where('type', 'credit')
            ->active()
            ->sum('amount');

        $debits = self::where('customer_id', $customerId)
            ->where('type', 'debit')
            ->sum('amount');

        return max(0, round((float) $credits - (float) $debits, 2));
    }
}
