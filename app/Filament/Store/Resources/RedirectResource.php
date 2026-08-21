<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources;

use App\Filament\Store\Resources\RedirectResource\Pages;
use App\Models\Redirect;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class RedirectResource extends Resource
{
    protected static ?string $model = Redirect::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-turn-down-right';

    protected static string|UnitEnum|null $navigationGroup = 'Merchandising';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('from_path')->required()->helperText('e.g. /product/old-slug'),
            TextInput::make('to_path')->required()->helperText('e.g. /product/new-slug'),
            TextInput::make('status_code')->numeric()->default(301),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('from_path')->searchable(),
                TextColumn::make('to_path')->searchable(),
                TextColumn::make('status_code'),
                TextColumn::make('source_type')->badge()->placeholder('Manual'),
                TextColumn::make('created_at')->dateTime(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRedirects::route('/'),
            'create' => Pages\CreateRedirect::route('/create'),
            'edit' => Pages\EditRedirect::route('/{record}/edit'),
        ];
    }
}
