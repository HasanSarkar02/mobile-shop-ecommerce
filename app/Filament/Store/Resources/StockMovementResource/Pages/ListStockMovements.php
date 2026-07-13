<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\StockMovementResource\Pages;

use App\Filament\Store\Resources\StockMovementResource;
use Filament\Resources\Pages\ListRecords;

class ListStockMovements extends ListRecords
{
    protected static string $resource = StockMovementResource::class;
}