<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\StockItemResource\Pages;

use App\Filament\Store\Imports\StockItemImporter;
use App\Filament\Store\Resources\StockItemResource;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;

class ListStockItems extends ListRecords
{
    protected static string $resource = StockItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ImportAction::make()
                ->importer(StockItemImporter::class)
                ->label('Import Stock CSV'),
        ];
    }
}
