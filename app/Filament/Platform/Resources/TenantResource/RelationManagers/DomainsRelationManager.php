<?php

declare(strict_types=1);

namespace App\Filament\Platform\Resources\TenantResource\RelationManagers;

use App\Filament\Platform\Resources\DomainResource;
use App\Models\Domain;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DomainsRelationManager extends RelationManager
{
    protected static string $relationship = 'domains';

    protected static ?string $title = 'Custom Domains';

    public function form(Schema $schema): Schema
    {
        return $schema;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('domain')->copyable(),
                TextColumn::make('status')->badge(),
                IconColumn::make('is_primary')
                    ->label('Primary')
                    ->boolean()
                    ->state(fn (Domain $record): bool => (int) $this->getOwnerRecord()->getAttribute('primary_domain_id') === (int) $record->id),
                TextColumn::make('verified_at')->dateTime()->placeholder('Not verified'),
            ])
            ->recordActions([
                Action::make('manage')
                    ->label('Manage')
                    ->url(fn (Domain $record): string => DomainResource::getUrl('view', ['record' => $record])),
            ]);
    }
}
