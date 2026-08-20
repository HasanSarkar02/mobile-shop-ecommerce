<?php

declare(strict_types=1);

namespace App\Filament\Platform\Resources\PlatformAdminResource\Pages;

use App\Filament\Platform\Resources\PlatformAdminResource;
use App\Models\User;
use App\Services\PlatformAdminService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePlatformAdmin extends CreateRecord
{
    protected static string $resource = PlatformAdminResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $actor = auth('platform')->user();
        abort_unless($actor instanceof User, 403);

        return app(PlatformAdminService::class)->invite($data['name'], $data['email'], $actor)['user'];
    }
}
