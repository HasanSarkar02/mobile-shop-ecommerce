<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources;

use App\Enums\StaticPageStatus;
use App\Filament\Store\Resources\StaticPageResource\Pages;
use App\Models\StaticPage;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class StaticPageResource extends Resource
{
    protected static ?string $model = StaticPage::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static string|UnitEnum|null $navigationGroup = 'Merchandising';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->required(),
            TextInput::make('slug')->required()->unique(ignoreRecord: true),
            RichEditor::make('content'),
            Select::make('status')
                ->options(collect(StaticPageStatus::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]))
                ->default(StaticPageStatus::Draft->value)
                ->required(),
            Toggle::make('show_in_footer'),
            TextInput::make('footer_group')->helperText('e.g. "Company", "Support", "Legal".'),
            TextInput::make('meta_title'),
            Textarea::make('meta_description')->rows(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('show_in_footer')->badge(),
                TextColumn::make('footer_group')->placeholder('—'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStaticPages::route('/'),
            'create' => Pages\CreateStaticPage::route('/create'),
            'edit' => Pages\EditStaticPage::route('/{record}/edit'),
        ];
    }
}