<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\NewsletterSubscriberResource\Pages;

use App\Filament\Store\Resources\NewsletterSubscriberResource;
use Filament\Resources\Pages\ListRecords;

class ListNewsletterSubscribers extends ListRecords
{
    protected static string $resource = NewsletterSubscriberResource::class;
}
