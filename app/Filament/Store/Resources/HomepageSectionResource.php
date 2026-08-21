<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources;

use App\Enums\BannerPlacement;
use App\Enums\HomepageSectionType;
use App\Enums\LinkType;
use App\Enums\ProductGridDataSource;
use App\Enums\Visibility;
use App\Filament\Store\Resources\HomepageSectionResource\Pages;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Collection as CollectionModel;
use App\Models\HomepageSection;
use App\Models\Tag;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class HomepageSectionResource extends Resource
{
    protected static ?string $model = HomepageSection::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';

    protected static string|UnitEnum|null $navigationGroup = 'Merchandising';

    protected static ?string $navigationLabel = 'Homepage Sections';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('type')
                ->options(collect(HomepageSectionType::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]))
                ->required()
                ->live(),
            TextInput::make('title'),
            Select::make('campaign_id')->relationship('campaign', 'name')->searchable()->preload(),

            Select::make('config_placement')
                ->label('Which banner placement to show')
                ->options(collect(BannerPlacement::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]))
                ->visible(fn (Get $get): bool => $get('type') === HomepageSectionType::BannerCarousel->value),
            Select::make('config_layout')
                ->label('Layout')
                ->options(['carousel' => 'Rotating carousel', 'grid' => 'Static grid'])
                ->default('carousel')
                ->helperText('Grid shows every active banner for this placement side by side, instead of rotating through them.')
                ->visible(fn (Get $get): bool => $get('type') === HomepageSectionType::BannerCarousel->value),

            Select::make('config_data_source')
                ->label('Product source')
                ->options(collect(ProductGridDataSource::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]))
                ->visible(fn (Get $get): bool => $get('type') === HomepageSectionType::ProductGrid->value)
                ->live(),
            TextInput::make('config_limit')
                ->label('Number of products')
                ->numeric()
                ->default(8)
                ->visible(fn (Get $get): bool => $get('type') === HomepageSectionType::ProductGrid->value),
            Select::make('config_category_id')
                ->label('Category')
                ->options(fn () => Category::query()->pluck('name', 'id'))
                ->searchable()
                ->visible(fn (Get $get): bool => $get('type') === HomepageSectionType::ProductGrid->value
                    && $get('config_data_source') === ProductGridDataSource::Category->value),
            Select::make('config_collection_id')
                ->label('Collection')
                ->options(fn () => CollectionModel::query()->pluck('name', 'id'))
                ->searchable()
                ->visible(fn (Get $get): bool => $get('type') === HomepageSectionType::ProductGrid->value
                    && $get('config_data_source') === ProductGridDataSource::Collection->value),
            Select::make('config_tag_id')
                ->label('Tag')
                ->options(fn () => Tag::query()->pluck('name', 'id'))
                ->searchable()
                ->visible(fn (Get $get): bool => $get('type') === HomepageSectionType::ProductGrid->value
                    && $get('config_data_source') === ProductGridDataSource::Tag->value),

            Select::make('config_source')
                ->label('Show')
                ->options(['category' => 'Categories', 'brand' => 'Brands'])
                ->default('category')
                ->live()
                ->visible(fn (Get $get): bool => $get('type') === HomepageSectionType::CategoryGrid->value),
            Select::make('config_category_ids')
                ->label('Categories to show')
                ->options(fn () => Category::query()->pluck('name', 'id'))
                ->multiple()
                ->searchable()
                ->helperText('Leave empty to automatically show the top-level categories with the most products.')
                ->visible(fn (Get $get): bool => $get('type') === HomepageSectionType::CategoryGrid->value
                    && ($get('config_source') ?? 'category') === 'category'),
            Select::make('config_brand_ids')
                ->label('Brands to show')
                ->options(fn () => Brand::query()->pluck('name', 'id'))
                ->multiple()
                ->searchable()
                ->helperText('Leave empty to automatically show the brands with the most products.')
                ->visible(fn (Get $get): bool => $get('type') === HomepageSectionType::CategoryGrid->value
                    && $get('config_source') === 'brand'),

            Textarea::make('config_html')
                ->label('HTML content')
                ->rows(6)
                ->helperText('Sanitized before rendering on the storefront.')
                ->visible(fn (Get $get): bool => $get('type') === HomepageSectionType::CustomHtml->value),

            Select::make('visibility')
                ->options(collect(Visibility::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]))
                ->default(Visibility::All->value)
                ->required(),
            Select::make('link_type')
                ->options(collect(LinkType::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]))
                ->default(LinkType::None->value)
                ->required(),
            TextInput::make('link_value')->label('"View all" link value'),
            DateTimePicker::make('starts_at'),
            DateTimePicker::make('ends_at'),
            Toggle::make('is_active')->default(true),
            TextInput::make('sort_order')->numeric()->default(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->placeholder('—'),
                TextColumn::make('type')->badge(),
                TextColumn::make('visibility')->badge(),
                TextColumn::make('is_active')->badge(),
                TextColumn::make('sort_order'),
            ])
            ->reorderable('sort_order')
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListHomepageSections::route('/'),
            'create' => Pages\CreateHomepageSection::route('/create'),
            'edit' => Pages\EditHomepageSection::route('/{record}/edit'),
        ];
    }
}
