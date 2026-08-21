<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\StockItemResource\Pages;

use App\Filament\Store\Resources\StockItemResource;
use Filament\Resources\Pages\ListRecords;

class ListStockItems extends ListRecords
{
    protected static string $resource = StockItemResource::class;
}
