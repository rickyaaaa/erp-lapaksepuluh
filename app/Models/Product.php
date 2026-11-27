<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Product extends Model
{
    protected $fillable = ['sku_code', 'partner_id', 'name', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'branch_products')->withTimestamps()->withPivot('is_active');
    }
}
