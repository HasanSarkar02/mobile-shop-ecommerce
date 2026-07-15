<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class SequenceCounter extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id','key', 'value'];
}