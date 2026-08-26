<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UomType;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class UnitOfMeasure extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'code', 'name', 'type', 'base_conversion_factor'];

    protected function casts(): array
    {
        return [
            'type' => UomType::class,
            'base_conversion_factor' => 'decimal:6',
        ];
    }

    /**
     * Discrete UOMs (pcs/pack/dozen) keep the whole-number fast path; weight/
     * volume/length UOMs mean stock is measured and must be handled with the
     * bcmath decimal path (InventoryService).
     */
    public function isDiscrete(): bool
    {
        // Read the raw attribute so this works whether the enum cast has been
        // applied or the raw string came straight from the DB row.
        /** @var mixed $raw */
        $raw = $this->getAttribute('type');
        $value = $raw instanceof UomType ? $raw->value : (is_string($raw) ? $raw : null);

        return $value === UomType::Discrete->value;
    }
}
