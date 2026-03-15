<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The connection name for the model.
     *
     * @var string
     */
    protected $connection = 'tenant';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'category_id',
        'name',
        'sku',
        'description',
        'price',
        'cost_price',
        'cb_percent',
        'tax_type',
        'tax_rate',
        'hsn_code',
        'track_inventory',
        'stock_quantity',
        'low_stock_threshold',
        'is_active',
        'available_online',
        'image_url',
        'tags',
        'variants',
        'modifiers',
        'sort_order',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'track_inventory' => 'boolean',
            'stock_quantity' => 'integer',
            'is_active' => 'boolean',
            'available_online' => 'boolean',
            'tags' => 'array',
            'variants' => 'array',
            'modifiers' => 'array',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Get the category that owns the product.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the addon groups for this product.
     */
    public function addonGroups(): BelongsToMany
    {
        return $this->belongsToMany(AddonGroup::class, 'addon_group_product')
            ->withTimestamps();
    }

    /**
     * Calculate final price with tax.
     */
    public function getFinalPriceAttribute(): float
    {
        if ($this->tax_type === 'inclusive') {
            return (float) $this->price;
        }

        if ($this->tax_type === 'exclusive') {
            return (float) ($this->price + ($this->price * $this->tax_rate / 100));
        }

        return (float) $this->price;
    }

    /**
     * Calculate tax amount.
     */
    public function getTaxAmountAttribute(): float
    {
        if ($this->tax_type === 'none') {
            return 0;
        }

        if ($this->tax_type === 'inclusive') {
            // Calculate tax embedded in price
            return (float) ($this->price - ($this->price / (1 + $this->tax_rate / 100)));
        }

        // Exclusive tax
        return (float) ($this->price * $this->tax_rate / 100);
    }

    /**
     * Calculate profit margin.
     */
    public function getProfitMarginAttribute(): ?float
    {
        if (!$this->cost_price || $this->cost_price == 0) {
            return null;
        }

        return (float) ((($this->price - $this->cost_price) / $this->cost_price) * 100);
    }

    /**
     * Check if product is in stock.
     */
    public function inStock(): bool
    {
        if (!$this->track_inventory) {
            return true;
        }

        return $this->stock_quantity > 0;
    }

    /**
     * Check if stock is low.
     */
    public function isLowStock(): bool
    {
        if (!$this->track_inventory || !$this->low_stock_threshold) {
            return false;
        }

        return $this->stock_quantity <= $this->low_stock_threshold;
    }

    /**
     * Decrease stock quantity.
     */
    public function decreaseStock(int $quantity): bool
    {
        if (!$this->track_inventory) {
            return true;
        }

        if ($this->stock_quantity < $quantity) {
            return false;
        }

        $this->decrement('stock_quantity', $quantity);
        return true;
    }

    /**
     * Increase stock quantity.
     */
    public function increaseStock(int $quantity): void
    {
        if ($this->track_inventory) {
            $this->increment('stock_quantity', $quantity);
        }
    }

    /**
     * Scope to get only active products.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get products available online.
     */
    public function scopeAvailableOnline($query)
    {
        return $query->where('available_online', true);
    }

    /**
     * Scope to get in-stock products.
     */
    public function scopeInStock($query)
    {
        return $query->where(function ($q) {
            $q->where('track_inventory', false)
              ->orWhere('stock_quantity', '>', 0);
        });
    }

    /**
     * Scope to get low stock products.
     */
    public function scopeLowStock($query)
    {
        return $query->where('track_inventory', true)
            ->whereNotNull('low_stock_threshold')
            ->whereColumn('stock_quantity', '<=', 'low_stock_threshold');
    }

    /**
     * Scope to search products by name or SKU.
     */
    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('sku', 'like', "%{$search}%");
        });
    }
}
