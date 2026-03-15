<?php

namespace App\Models\Main;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GlobalCustomer extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'phone_hash',
        'country_code',
        'name',
        'email',
        'consent_given',
        'consent_given_at',
        'total_visits',
        'total_spent',
        'last_seen_at',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'consent_given' => 'boolean',
            'consent_given_at' => 'datetime',
            'total_spent' => 'decimal:2',
            'last_seen_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * Hash a phone number for storage.
     */
    public static function hashPhone(string $phone): string
    {
        return hash('sha256', $phone);
    }

    /**
     * Find or create a global customer by phone.
     */
    public static function findOrCreateByPhone(string $phone, string $countryCode = '+91', array $data = []): self
    {
        $phoneHash = self::hashPhone($phone);

        return self::firstOrCreate(
            ['phone_hash' => $phoneHash],
            array_merge([
                'country_code' => $countryCode,
                'total_visits' => 0,
                'total_spent' => 0,
            ], $data)
        );
    }

    /**
     * Update visit statistics.
     */
    public function recordVisit(float $amountSpent = 0): void
    {
        $this->increment('total_visits');
        $this->increment('total_spent', $amountSpent);
        $this->update(['last_seen_at' => now()]);
    }

    /**
     * Give consent for data sharing.
     */
    public function giveConsent(): void
    {
        $this->update([
            'consent_given' => true,
            'consent_given_at' => now(),
        ]);
    }

    /**
     * Revoke consent for data sharing.
     */
    public function revokeConsent(): void
    {
        $this->update([
            'consent_given' => false,
            'consent_given_at' => null,
            'name' => null,
            'email' => null,
        ]);
    }

    /**
     * Scope to get customers with consent.
     */
    public function scopeWithConsent($query)
    {
        return $query->where('consent_given', true);
    }

    /**
     * Scope to get customers by country.
     */
    public function scopeInCountry($query, string $countryCode)
    {
        return $query->where('country_code', $countryCode);
    }

    /**
     * Scope to get active customers (visited recently).
     */
    public function scopeActive($query, int $days = 90)
    {
        return $query->where('last_seen_at', '>=', now()->subDays($days));
    }
}
