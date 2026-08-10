<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\ProductResource\Pages;

use App\Filament\Store\Resources\ProductResource;
use Filament\Resources\Pages\CreateRecord;
use App\Services\SubscriptionService;
use Filament\Notifications\Notification;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (! app(SubscriptionService::class)->canCreateProduct(tenant())) {
            Notification::make()->title('Product limit reached for your plan. Please upgrade to add more products.')->danger()->send();
            $this->halt();
        }
        $data['created_by'] = auth()->id();

        return $data;
    }
}