<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\ProductResource\RelationManagers;

use App\Enums\AttributeDataType;
use App\Enums\BackorderPolicy;
use App\Enums\FulfillmentStrategy;
use App\Enums\InventoryType;
use App\Enums\VariantAvailability;
use App\Models\AttributeDefinition;
use App\Models\Product;
use App\Services\BulkVariantGeneratorService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use LogicException;

class VariantsRelationManager extends RelationManager
{
    protected static string $relationship = 'variants';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('sku')->required()->scopedUnique(ignoreRecord: true),
            TextInput::make('barcode'),
            TextInput::make('color'),
            TextInput::make('storage_gb')->numeric()->suffix('GB'),
            TextInput::make('ram_gb')->numeric()->suffix('GB'),
            Select::make('sim_type')->options([
                'Single SIM' => 'Single SIM',
                'Dual SIM' => 'Dual SIM',
                'eSIM' => 'eSIM',
                'Dual SIM + eSIM' => 'Dual SIM + eSIM',
            ]),
            TextInput::make('region')->helperText('e.g. Global, USA, Japan, Australia — leave blank if not region-specific.'),
            TextInput::make('price')
                ->label('Price (BDT)')
                ->numeric()
                ->required()
                ->formatStateUsing(fn (?int $state): ?float => $state !== null ? $state / 100 : null)
                ->dehydrateStateUsing(fn (?float $state): int => (int) round(($state ?? 0) * 100)),
            TextInput::make('compare_at_price')
                ->label('Original price (BDT) — optional, shows as discount')
                ->numeric()
                ->formatStateUsing(fn (?int $state): ?float => $state !== null ? $state / 100 : null)
                ->dehydrateStateUsing(fn (?float $state): ?int => $state !== null ? (int) round($state * 100) : null)
                ->gt('price')
                ->validationMessages(['gt' => 'The original price must be higher than the selling price.']),
            TextInput::make('cost_price')
                ->label('Cost price (BDT) — internal, not shown to customers')
                ->numeric()
                ->formatStateUsing(fn (?int $state): ?float => $state !== null ? $state / 100 : null)
                ->dehydrateStateUsing(fn (?float $state): ?int => $state !== null ? (int) round($state * 100) : null),
            Select::make('inventory_type')
                ->options(collect(InventoryType::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]))
                ->default(InventoryType::Tracked->value)
                ->required()
                ->live(),
            Select::make('fulfillment_strategy')
                ->options(collect(FulfillmentStrategy::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]))
                ->default(FulfillmentStrategy::Stock->value)
                ->required()
                ->live(),
            Select::make('backorder_policy')
                ->options(collect(BackorderPolicy::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]))
                ->visible(fn (Get $get): bool => $get('fulfillment_strategy') === FulfillmentStrategy::Stock->value),
            TextInput::make('low_stock_threshold')->numeric()->helperText('Overrides the store default for this SKU.'),
            Select::make('availability')
                ->options(collect(VariantAvailability::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]))
                ->default(VariantAvailability::InStock->value)
                ->required(),
            DateTimePicker::make('expected_available_at')
                ->label('Expected availability date')
                ->helperText('Required for pre-orders. Shown to shoppers as “Expected availability M j, Y — estimate, subject to change”.')
                ->visible(fn (Get $get): bool => $get('fulfillment_strategy') === FulfillmentStrategy::Preorder->value)
                ->required(fn (Get $get): bool => $get('fulfillment_strategy') === FulfillmentStrategy::Preorder->value)
                ->after('now')
                ->validationMessages([
                    'required' => 'ETA is required for pre-orders.',
                    'after' => 'ETA must be in the future.',
                ]),
            SpatieMediaLibraryFileUpload::make('images')
                ->collection('images')
                ->image()
                ->multiple()
                ->reorderable()
                ->helperText('Photos specific to this color/variant. Leave empty to use the product\'s general photos.'),
            TextInput::make('weight_grams')->numeric()->suffix('g'),
            Toggle::make('is_active')->default(true),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('sku')
            ->columns([
                TextColumn::make('sku'),
                TextColumn::make('color')->placeholder('—'),
                TextColumn::make('storage_gb')->suffix(' GB')->placeholder('—'),
                TextColumn::make('ram_gb')->suffix(' GB')->placeholder('—'),
                TextColumn::make('price')->formatStateUsing(fn (int $state): string => money((int) $state)),
                TextColumn::make('fulfillment_strategy')->badge(),
                TextColumn::make('inventory_type')->badge(),
                TextColumn::make('availability')->badge(),
                TextColumn::make('is_active')->badge(),
            ])
            ->headerActions([$this->generateVariantsAction(), CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    /**
     * Bulk cartesian generator (PLAN #49): one modal builds every combination
     * of the selected variant-defining attribute options. The engine lives in
     * BulkVariantGeneratorService — this is a thin adapter.
     */
    private function generateVariantsAction(): Action
    {
        return Action::make('generateVariants')
            ->label('Generate Variants')
            ->icon('heroicon-o-squares-plus')
            ->color('gray')
            ->modalHeading('Generate Variants')
            ->modalDescription('Pick options for each variant-defining attribute — every combination becomes its own SKU.')
            ->modalWidth('2xl')
            ->visible(fn (): bool => $this->variantDefiningAttributes()->isNotEmpty())
            ->form(fn (): array => $this->bulkFormComponents())
            ->action(function (array $data): void {
                /** @var array<int|string, mixed> $selections */
                $selections = is_array($data['attributes'] ?? null) ? $data['attributes'] : [];

                $result = app(BulkVariantGeneratorService::class)->generate(
                    $this->ownerProduct(),
                    $selections,
                    (int) $data['base_price'],
                    filled($data['base_sku'] ?? null) ? (string) $data['base_sku'] : null,
                );

                Notification::make()
                    ->title($result['created'].' variant(s) generated'
                        .($result['skipped'] > 0 ? ", {$result['skipped']} skipped (already existed)" : ''))
                    ->success()
                    ->send();
            });
    }

    /**
     * The relation manager is only ever attached to a Product resource; this
     * guard narrows the generic Model contract for the type analyser.
     */
    private function ownerProduct(): Product
    {
        $record = $this->getOwnerRecord();

        if (! $record instanceof Product) {
            throw new LogicException('VariantsRelationManager must be attached to a Product.');
        }

        return $record;
    }

    /**
     * @return Collection<int, AttributeDefinition>
     */
    private function variantDefiningAttributes(): Collection
    {
        return AttributeDefinition::query()
            ->where('is_variant_defining', true)
            ->whereIn('data_type', [AttributeDataType::Select->value, AttributeDataType::MultiSelect->value])
            ->orderBy('sort_order')
            ->with('options')
            ->get();
    }

    /**
     * @return array<int, mixed>
     */
    private function bulkFormComponents(): array
    {
        $product = $this->ownerProduct();

        $components = [
            TextInput::make('base_sku')
                ->label('SKU prefix')
                ->default(fn (): string => is_string($product->model_number) && trim($product->model_number) !== ''
                    ? trim($product->model_number)
                    : 'P-'.$product->id)
                ->helperText('Generated SKUs append the option labels — e.g. TEE-WHITE-M.'),
            TextInput::make('base_price')
                ->label('Base price (BDT)')
                ->numeric()
                ->required()
                ->minValue(1)
                ->default(fn (): float => (float) $product->base_price / 100)
                ->dehydrateStateUsing(fn (mixed $state): int => (int) round(((float) ($state ?? 0)) * 100))
                ->helperText('Applied to every generated variant; adjust individual SKUs afterwards.'),
        ];

        foreach ($this->variantDefiningAttributes() as $definition) {
            $components[] = CheckboxList::make("attributes.{$definition->id}")
                ->label($definition->label)
                ->options($definition->options->pluck('label', 'id')->all())
                ->columns(3)
                ->bulkToggleable()
                ->required();
        }

        return $components;
    }
}
