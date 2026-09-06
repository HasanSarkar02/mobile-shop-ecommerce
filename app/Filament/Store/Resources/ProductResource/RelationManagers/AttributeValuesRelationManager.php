<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\ProductResource\RelationManagers;

use App\Enums\AttributeDataType;
use App\Models\AttributeDefinition;
use App\Models\AttributeOption;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class AttributeValuesRelationManager extends RelationManager
{
    protected static string $relationship = 'attributeValues';

    protected static ?string $title = 'Specifications';

    /** @var Collection<int, AttributeDefinition>|null */
    private static ?Collection $definitionCache = null;

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('product_variant_id')
                ->label('Variant')
                ->options(fn () => $this->getOwnerRecord()?->variants()->pluck('sku', 'id')->all() ?? [])
                ->searchable()
                ->placeholder('Product-level attribute')
                ->helperText('Assign to a variant when this value describes a specific variation (e.g. Size, Color, Weight). Leave empty for product-level specifications.'),
            Select::make('attribute_definition_id')
                ->label('Attribute')
                ->options(fn (): array => $this->cachedDefinitions()->pluck('label', 'id')->all())
                ->searchable()
                ->required()
                ->live()
                ->createOptionForm([
                    TextInput::make('code')
                        ->required()
                        ->alphaDash()
                        ->helperText('Unique code, e.g. color, storage_gb'),
                    TextInput::make('label')
                        ->required(),
                    Select::make('data_type')
                        ->options(collect(AttributeDataType::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()]))
                        ->required()
                        ->default(AttributeDataType::Select->value),
                    Toggle::make('is_variant_defining')
                        ->label('Variant-defining')
                        ->default(true)
                        ->helperText('Enable if this attribute defines variant combinations (e.g. Color, Storage).'),
                    Toggle::make('is_filterable')
                        ->default(true),
                ])
                ->createOptionUsing(function (array $data): int {
                    $definition = AttributeDefinition::query()->create([
                        'code' => $data['code'],
                        'label' => $data['label'],
                        'data_type' => $data['data_type'],
                        'is_variant_defining' => (bool) ($data['is_variant_defining'] ?? false),
                        'is_filterable' => (bool) ($data['is_filterable'] ?? true),
                    ]);
                    self::clearDefinitionCache();

                    return $definition->getKey();
                }),
            TextInput::make('value_string')
                ->label('Value')
                ->visible(fn (Get $get): bool => $this->dataTypeOf($get('attribute_definition_id')) === AttributeDataType::Text),
            TextInput::make('value_integer')
                ->label('Value')
                ->numeric()
                ->visible(fn (Get $get): bool => $this->dataTypeOf($get('attribute_definition_id')) === AttributeDataType::Number),
            TextInput::make('value_decimal')
                ->label('Value')
                ->numeric()
                ->visible(fn (Get $get): bool => $this->dataTypeOf($get('attribute_definition_id')) === AttributeDataType::Decimal),
            Toggle::make('value_boolean')
                ->label('Value')
                ->visible(fn (Get $get): bool => $this->dataTypeOf($get('attribute_definition_id')) === AttributeDataType::Boolean),
            Select::make('attribute_option_id')
                ->label('Value')
                ->options(function (Get $get): array {
                    $id = $get('attribute_definition_id');
                    if (! $id) {
                        return [];
                    }
                    $definition = $this->cachedDefinitions()->firstWhere('id', (int) $id);
                    if ($definition === null) {
                        return [];
                    }

                    return $definition->options->pluck('label', 'id')->all();
                })
                ->visible(fn (Get $get): bool => in_array($this->dataTypeOf($get('attribute_definition_id')), [AttributeDataType::Select, AttributeDataType::MultiSelect], true))
                ->createOptionForm(fn (Get $get): array => [
                    TextInput::make('value')
                        ->required()
                        ->helperText('Stored value, e.g. black_256gb or plain label if same.'),
                    TextInput::make('label')
                        ->required()
                        ->helperText('Display label, e.g. Black 256GB'),
                    TextInput::make('sort_order')
                        ->numeric()
                        ->default(0),
                    Hidden::make('attribute_definition_id')
                        ->default($get('attribute_definition_id')),
                ])
                ->createOptionUsing(function (array $data, Get $get): int {
                    $definitionId = $data['attribute_definition_id'] ?? $get('attribute_definition_id');
                    if (! $definitionId) {
                        throw new ValidationException(
                            validator([], []),
                            response(['message' => 'Select an attribute first.'])
                        );
                    }
                    $definition = $this->cachedDefinitions()->firstWhere('id', (int) $definitionId);
                    if ($definition === null) {
                        throw ValidationException::withMessages([
                            'attribute_definition_id' => 'Invalid attribute.',
                        ]);
                    }
                    // Explicit tenant check (hardened)
                    if (tenant() !== null && (int) $definition->tenant_id !== (int) tenant()->id) {
                        throw ValidationException::withMessages([
                            'attribute_definition_id' => 'Attribute does not belong to this store.',
                        ]);
                    }
                    $option = AttributeOption::query()->create([
                        'attribute_definition_id' => $definition->id,
                        'value' => $data['value'],
                        'label' => $data['label'],
                        'sort_order' => (int) ($data['sort_order'] ?? 0),
                    ]);
                    self::clearDefinitionCache();

                    return $option->getKey();
                }),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['variant', 'attributeDefinition', 'attributeOption']))
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('variant.sku')->label('Variant')->placeholder('Product'),
                TextColumn::make('attributeDefinition.label')->label('Attribute'),
                TextColumn::make('display_value')
                    ->label('Value')
                    ->getStateUsing(fn ($record): string => (string) ($record->displayValue() ?? $record->value_string ?? '—')),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    private function dataTypeOf(?int $attributeDefinitionId): ?AttributeDataType
    {
        if (! $attributeDefinitionId) {
            return null;
        }

        return $this->cachedDefinitions()->firstWhere('id', (int) $attributeDefinitionId)?->data_type;
    }

    /**
     * @return Collection<int, AttributeDefinition>
     */
    private function cachedDefinitions(): Collection
    {
        if (self::$definitionCache !== null) {
            return self::$definitionCache;
        }

        return self::$definitionCache = AttributeDefinition::query()
            ->with('options')
            ->orderBy('sort_order')
            ->get();
    }

    public static function clearDefinitionCache(): void
    {
        self::$definitionCache = null;
    }
}
