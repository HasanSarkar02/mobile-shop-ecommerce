<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Filament\Store\Resources\ProductResource\Pages;
use App\Filament\Store\Resources\ProductResource\RelationManagers\AttributeValuesRelationManager;
use App\Filament\Store\Resources\ProductResource\RelationManagers\ProductRelationsRelationManager;
use App\Filament\Store\Resources\ProductResource\RelationManagers\VariantsRelationManager;
use App\Models\Product;
use App\Models\ProductTranslation;
use BackedEnum;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cube';

    protected static string|UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Repeater::make('translations')
                ->relationship('translations')
                ->schema([
                    Select::make('locale')->options(['en' => 'English', 'bn' => 'Bangla'])->required(),
                    TextInput::make('name')->required(),
                    TextInput::make('slug')->required(),
                    RichEditor::make('description')
                        ->toolbarButtons([
                            ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'],
                            ['bold', 'italic', 'underline'],
                            ['link'],
                            ['bulletList', 'orderedList', 'blockquote'],
                            ['alignStart', 'alignCenter', 'alignEnd'],
                            ['table', 'attachFiles'],
                            ['horizontalRule', 'undo', 'redo', 'clearFormatting'],
                        ])
                        ->preventFileAttachmentPathTampering(
                            allowFilePathUsing: function (string $file, ?ProductTranslation $record): bool {
                                return $record?->media()
                                    ->where('collection_name', 'description_images')
                                    ->where('uuid', $file)
                                    ->exists() ?? false;
                            },
                        ),
                    Textarea::make('warranty_info')->rows(3)->helperText('Warranty terms shown in the Warranty tab.'),
                    TextInput::make('meta_title'),
                    Textarea::make('meta_description')->rows(2),
                ])
                ->defaultItems(1)
                ->addActionLabel('Add language')
                ->columns(1),
            Select::make('brand_id')->relationship('brand', 'name')->searchable()->preload(),
            Select::make('category_id')->relationship('category', 'name')->searchable()->preload(),
            TextInput::make('model_number'),
            Select::make('type')
                ->options(collect(ProductType::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]))
                ->default(ProductType::Simple->value)
                ->required(),
            Select::make('status')->options(['draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived'])->required()->default('draft'),
            Select::make('uom_id')
                ->relationship('uom', 'name')
                ->searchable()
                ->preload()
                ->placeholder('Whole units (no UOM)')
                ->helperText('Sell unit for measured goods (e.g. kg for rice, l for oil). Leave empty for whole-unit products.'),
            TextInput::make('sell_by_unit')
                ->label('Sales increment')
                ->numeric()
                ->step('0.001')
                ->minValue(0)
                ->helperText('Standard sales step in the selected unit — e.g. 0.500 on a kg product sells by the half kilo. Requires a unit above.'),
            Toggle::make('is_featured'),
            Toggle::make('is_serialized')->helperText('Enable for products requiring IMEI/serial tracking.'),
            Select::make('tags')->relationship('tags', 'name')->multiple()->preload(),
            Select::make('emiPlans')->relationship('emiPlans', 'bank_name')->multiple()->preload(),
            SpatieMediaLibraryFileUpload::make('images')->collection('images')->image()->multiple()->reorderable(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['translations', 'brand', 'category', 'variants']))
            ->columns([
                SpatieMediaLibraryImageColumn::make('images')->collection('images')->conversion('thumb'),
                TextColumn::make('name')
                    ->label('Name')
                    ->limit(40)
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $q) use ($search): void {
                            $q->whereHas('translations', fn (Builder $t): Builder => $t->where('name', 'like', "%{$search}%"))
                                ->orWhere('model_number', 'like', "%{$search}%")
                                ->orWhereHas('variants', fn (Builder $v): Builder => $v->where('sku', 'like', "%{$search}%")->orWhere('barcode', 'like', "%{$search}%"))
                                ->orWhereHas('brand', fn (Builder $b): Builder => $b->where('name', 'like', "%{$search}%"))
                                ->orWhereHas('category', fn (Builder $c): Builder => $c->where('name', 'like', "%{$search}%"))
                                ->orWhereHas('tags', fn (Builder $tg): Builder => $tg->where('name', 'like', "%{$search}%"));
                        });
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        $locale = app()->getLocale();

                        return $query->orderBy(
                            ProductTranslation::select('name')
                                ->whereColumn('product_translations.product_id', 'products.id')
                                ->where('product_translations.locale', $locale)
                                ->limit(1),
                            $direction
                        );
                    }),
                TextColumn::make('model_number')->label('Model')->searchable()->toggleable()->sortable(),
                TextColumn::make('brand.name')->searchable(false)->sortable()->toggleable(),
                TextColumn::make('category.name')->searchable(false)->sortable()->toggleable(),
                TextColumn::make('base_price')->label('Price')->formatStateUsing(fn (int $state): string => money((int) $state))->sortable(),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('variants_count')->counts('variants')->label('Variants'),
            ])
            ->searchPlaceholder('Search by name, model, SKU, barcode, brand, category, tags...')
            ->filters([
                SelectFilter::make('status')
                    ->options(['draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived'])
                    ->multiple()
                    ->preload(),
                SelectFilter::make('brand_id')
                    ->label('Brand')
                    ->relationship('brand', 'name')
                    ->multiple()
                    ->preload()
                    ->searchable(),
                SelectFilter::make('category_id')
                    ->label('Category')
                    ->relationship('category', 'name')
                    ->multiple()
                    ->preload()
                    ->searchable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    BulkAction::make('publish')
                        ->label('Publish')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            foreach ($records as $record) {
                                $record->update(['status' => ProductStatus::Published]);
                            }
                            Notification::make()->title($records->count().' product(s) published')->success()->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('draft')
                        ->label('Move to Draft')
                        ->icon('heroicon-o-pencil-square')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            foreach ($records as $record) {
                                $record->update(['status' => ProductStatus::Draft]);
                            }
                            Notification::make()->title($records->count().' product(s) moved to draft')->success()->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('archive')
                        ->label('Archive')
                        ->icon('heroicon-o-archive-box')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            foreach ($records as $record) {
                                $record->update(['status' => ProductStatus::Archived]);
                            }
                            Notification::make()->title($records->count().' product(s) archived')->success()->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('toggleFeatured')
                        ->label('Toggle Featured')
                        ->icon('heroicon-o-star')
                        ->color('warning')
                        ->action(function (Collection $records): void {
                            foreach ($records as $record) {
                                if ($record instanceof Product) {
                                    $record->update(['is_featured' => ! $record->is_featured]);
                                }
                            }
                            Notification::make()->title($records->count().' product(s) toggled featured')->success()->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [VariantsRelationManager::class, AttributeValuesRelationManager::class, ProductRelationsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'model_number',
            'translations.name',
            'brand.name',
            'category.name',
            'tags.name',
            'variants.sku',
            'variants.barcode',
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['translations', 'brand', 'category', 'tags', 'variants']);
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        /** @var Product $record */
        return $record->translation()?->name ?? $record->translation('en')?->name ?? $record->model_number ?? 'Product #'.$record->getKey();
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var Product $record */
        return array_filter([
            'Brand' => $record->brand?->name,
            'Category' => $record->category?->name,
            'Model' => $record->model_number,
            'Price' => money((int) $record->base_price),
        ]);
    }

    public static function getGlobalSearchResultUrl(Model $record): string
    {
        return static::getUrl('edit', ['record' => $record]);
    }
}
