<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KitchenStation extends Model
{
    use HasFactory;

    protected $connection = 'tenant';

    protected $fillable = [
        'name',
        'description',
        'category_ids',
        'is_active',
        'printer_ip',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'category_ids' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function tables(): HasMany
    {
        return $this->hasMany(Table::class);
    }

    public function hasCategory(int $categoryId): bool
    {
        return in_array($categoryId, $this->category_ids ?? []);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
