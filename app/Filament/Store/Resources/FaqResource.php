<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources;

use App\Filament\Store\Resources\FaqResource\Pages;
use App\Models\Faq;
use App\Models\Product;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class FaqResource extends Resource
{
    protected static ?string $model = Faq::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-question-mark-circle';

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('product_id')
                ->label('Product (leave empty for general site FAQ)')
                ->options(fn () => Product::query()->with('translations')->get()->mapWithKeys(fn (Product $p) => [$p->id => $p->name ?? "#{$p->id}"]))
                ->searchable(),
            TextInput::make('question')->required(),
            Textarea::make('answer')->required()->rows(4),
            TextInput::make('sort_order')->numeric()->default(0),
            Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('question')->limit(50)->searchable(),
                TextColumn::make('product.name')->placeholder('General FAQ'),
                TextColumn::make('is_active')->badge(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFaqs::route('/'),
            'create' => Pages\CreateFaq::route('/create'),
            'edit' => Pages\EditFaq::route('/{record}/edit'),
        ];
    }
}