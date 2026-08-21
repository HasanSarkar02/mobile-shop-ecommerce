<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\CouponResource\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CustomerEligibilitiesRelationManager extends RelationManager
{
    protected static string $relationship = 'customerEligibilities';

    protected static ?string $title = 'Eligible Customers';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('customer_id')->relationship('customer', 'name')->searchable()->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([TextColumn::make('customer.name'), TextColumn::make('customer.email')])
            ->headerActions([CreateAction::make()])
            ->recordActions([DeleteAction::make()]);
    }
}
