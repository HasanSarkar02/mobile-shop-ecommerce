<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class StoreSetting extends Model
{
    use BelongsToTenant;

    protected $table = 'store_settings';

    protected $fillable = ['tenant_id','meta_title_template', 'meta_description_default', 'order_confirmation_note', 'robots_txt_extra'];
}