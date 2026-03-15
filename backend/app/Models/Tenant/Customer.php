<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory;

    protected $connection = 'tenant';

    protected $fillable = [
        'phone',
        'country_code',
        'name',
        'email',
        'address',
        'city',
        'state',
        'postal_code',
        'date_of_birth',
        'anniversary',
        'visits_count',
        'total_spent',
        'average_bill',
        'last_visit_at',
        'preferences',
        'notes',
        'gstin',
        'customer_state_code',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'anniversary' => 'date',
            'visits_count' => 'integer',
            'total_spent' => 'decimal:2',
            'average_bill' => 'decimal:2',
            'last_visit_at' => 'datetime',
            'preferences' => 'array',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }

    public function loyaltyLedger(): HasMany
    {
        return $this->hasMany(LoyaltyLedger::class);
    }

    public function getWalletBalanceAttribute(): float
    {
        return LoyaltyLedger::getBalance($this->id);
    }

    public function getFullPhoneAttribute(): string
    {
        return $this->country_code . $this->phone;
    }

    public function recordVisit(float $amount): void
    {
        $this->increment('visits_count');
        $this->increment('total_spent', $amount);
        $this->update([
            'last_visit_at' => now(),
            'average_bill' => $this->total_spent / $this->visits_count,
        ]);
    }

    public function isVip(): bool
    {
        return $this->visits_count >= 10 || $this->total_spent >= 10000;
    }

    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('phone', 'like', "%{$search}%")
              ->orWhere('email', 'like', "%{$search}%");
        });
    }

    public function scopeVip($query)
    {
        return $query->where('visits_count', '>=', 10)
            ->orWhere('total_spent', '>=', 10000);
    }

    public function scopeActive($query, int $days = 90)
    {
        return $query->where('last_visit_at', '>=', now()->subDays($days));
    }
}
