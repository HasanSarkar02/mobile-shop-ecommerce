<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources;

use App\Enums\CampaignStatus;
use App\Filament\Store\Resources\CampaignResource\Pages;
use App\Models\Campaign;
use App\Models\Product;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class CampaignResource extends Resource
{
    protected static ?string $model = Campaign::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-megaphone';

    protected static string|UnitEnum|null $navigationGroup = 'Storefront';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required(),
            TextInput::make('slug')->required()->scopedUnique(ignoreRecord: true),
            Textarea::make('description')->rows(3),
            TextInput::make('short_tagline')
                ->label('Short tagline')
                ->maxLength(120)
                ->helperText('One-line pitch shown on offer cards and the hero.'),
            ColorPicker::make('accent_color')
                ->helperText('Optional accent for the offer card; a preset tint is used when empty.'),
            FileUpload::make('hero_image')
                ->label('Hero image')
                ->image()
                ->imageEditor()
                ->maxSize(4096)
                ->directory('campaign-heroes')
                ->imagePreviewHeight('250')
                ->columnSpanFull()
                ->helperText('Wide artwork shown on the offer hero. Optional — the campaign banner is used as fallback.'),
            FileUpload::make('card_image')
                ->label('Card image')
                ->image()
                ->maxSize(2048)
                ->directory('campaign-cards')
                ->imagePreviewHeight('180')
                ->columnSpanFull()
                ->helperText('Optional artwork for the offer card on /offers; falls back to the hero image.'),
            Select::make('products')
                ->relationship(name: 'products', titleAttribute: 'id', modifyQueryUsing: fn ($query) => $query->published()->with('translations'))
                ->getOptionLabelFromRecordUsing(fn (Product $record): string => $record->translation('en')->name ?? "Product #{$record->id}")
                ->multiple()
                ->searchable()
                ->preload()
                ->helperText('Only published products are listed.'),
            Select::make('status')
                ->options(collect(CampaignStatus::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]))
                ->default(CampaignStatus::Draft->value)
                ->required(),
            DateTimePicker::make('starts_at'),
            DateTimePicker::make('ends_at'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('starts_at')->dateTime()->placeholder('—'),
                TextColumn::make('ends_at')->dateTime()->placeholder('—'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCampaigns::route('/'),
            'create' => Pages\CreateCampaign::route('/create'),
            'edit' => Pages\EditCampaign::route('/{record}/edit'),
        ];
    }
}
