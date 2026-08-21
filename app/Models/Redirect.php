<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Redirect extends Model
{
    use BelongsToTenant;

    protected $fillable = ['from_path', 'to_path', 'status_code', 'source_type', 'source_id'];

    protected function casts(): array
    {
        return ['status_code' => 'integer'];
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
