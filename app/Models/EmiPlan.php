<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class EmiPlan extends Model
{
    use BelongsToTenant;

    protected $fillable = ['bank_name', 'tenure_months', 'interest_rate', 'active'];

    protected function casts(): array
    {
        return [
            'interest_rate' => 'decimal:2',
            'active' => 'boolean',
        ];
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_emi_plan');
    }
}
