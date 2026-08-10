<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class SearchQuery extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $fillable = ['term', 'results_count', 'searched_at'];

    protected function casts(): array
    {
        return ['searched_at' => 'datetime'];
    }
}