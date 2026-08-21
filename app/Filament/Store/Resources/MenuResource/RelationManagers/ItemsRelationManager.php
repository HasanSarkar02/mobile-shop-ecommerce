<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\MenuResource\RelationManagers;

use App\Enums\LinkType;
use App\Enums\Visibility;
use App\Models\MenuItem;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('label')->required(),
            Select::make('parent_id')
                ->label('Parent item (leave empty for top-level)')
                ->options(function (?MenuItem $record): array {
                    $query = MenuItem::query()
                        ->where('menu_id', $this->getOwnerRecord()->id);

                    if ($record?->getKey() !== null) {
                        $query->whereKeyNot($record->getKey());
                    }

                    return $query
                        ->pluck('label', 'id')
                        ->all();
                }),
            Select::make('link_type')
                ->options(collect(LinkType::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]))
                ->default(LinkType::None->value)
                ->required(),
            TextInput::make('link_value'),
            TextInput::make('icon')->helperText('Optional icon name (heroicon-o-*).'),
            TextInput::make('badge_text')->helperText('e.g. "New", "Sale".'),
            Select::make('visibility')
                ->options(collect(Visibility::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]))
                ->default(Visibility::All->value)
                ->required(),
            TextInput::make('sort_order')->numeric()->default(0),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('label')
            ->columns([
                TextColumn::make('label'),
                TextColumn::make('parent.label')->label('Parent')->placeholder('— top level —'),
                TextColumn::make('badge_text')->placeholder('—'),
                TextColumn::make('visibility')->badge(),
                TextColumn::make('sort_order'),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
