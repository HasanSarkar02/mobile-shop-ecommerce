<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources;

use App\Enums\BannerMediaType;
use App\Enums\BannerPlacement;
use App\Enums\LinkType;
use App\Enums\PopupFrequency;
use App\Enums\Visibility;
use App\Filament\Store\Resources\BannerResource\Pages;
use App\Models\Banner;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class BannerResource extends Resource
{
    protected static ?string $model = Banner::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-photo';

    protected static string|UnitEnum|null $navigationGroup = 'Merchandising';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->required(),
            Select::make('campaign_id')->relationship('campaign', 'name')->searchable()->preload(),
            Select::make('placement')
                ->options(collect(BannerPlacement::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]))
                ->default(BannerPlacement::Hero->value)
                ->required()
                ->live(),
            Select::make('popup_frequency')
                ->options(collect(PopupFrequency::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]))
                ->visible(fn (Get $get): bool => $get('placement') === BannerPlacement::Popup->value),
            Select::make('media_type')
                ->options(collect(BannerMediaType::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]))
                ->default(BannerMediaType::Image->value)
                ->required()
                ->live(),
            SpatieMediaLibraryFileUpload::make('image')
                ->collection('image')
                ->image()
                ->visible(fn (Get $get): bool => $get('media_type') === BannerMediaType::Image->value),
            SpatieMediaLibraryFileUpload::make('video')
                ->collection('video')
                ->acceptedFileTypes(['video/mp4'])
                ->visible(fn (Get $get): bool => $get('media_type') === BannerMediaType::Video->value),
            Select::make('visibility')
                ->options(collect(Visibility::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]))
                ->default(Visibility::All->value)
                ->required(),
            Select::make('link_type')
                ->options(collect(LinkType::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]))
                ->default(LinkType::None->value)
                ->required(),
            TextInput::make('link_value')
                ->helperText('Enter the slug for Product/Category/Brand/Collection/Static Page, or the full URL for External.'),
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
                SpatieMediaLibraryImageColumn::make('image')->collection('image')->conversion('large'),
                TextColumn::make('title')->searchable(),
                TextColumn::make('placement')->badge(),
                TextColumn::make('visibility')->badge(),
                TextColumn::make('is_active')->badge(),
            ])
            ->reorderable('sort_order')
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBanners::route('/'),
            'create' => Pages\CreateBanner::route('/create'),
            'edit' => Pages\EditBanner::route('/{record}/edit'),
        ];
    }
}