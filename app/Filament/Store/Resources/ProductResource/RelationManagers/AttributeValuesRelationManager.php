<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\ProductResource\RelationManagers;

use App\Enums\AttributeDataType;
use App\Models\AttributeDefinition;
use App\Models\AttributeOption;
use App\Models\ProductAttributeValue;
use App\Services\ProductSpecificationBulkService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Placeholder;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttributeValuesRelationManager extends RelationManager
{
    protected static string $relationship = 'attributeValues';

    protected static ?string $title = 'Specifications';

    /** @var array<int, Collection<int, AttributeDefinition>> */
    private static array $definitionCache = [];

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
                ->createOptionForm(fn (): array => $this->fullAttributeDefinitionForm())
                ->createOptionUsing(function (array $data): int {
                    $definition = AttributeDefinition::query()->create([
                        'code' => $data['code'],
                        'label' => $data['label'],
                        'data_type' => $data['data_type'],
                        'unit' => $data['unit'] ?? null,
                        'group' => $data['group'] ?? null,
                        'group_sort_order' => (int) ($data['group_sort_order'] ?? 0),
                        'sort_order' => (int) ($data['sort_order'] ?? 0),
                        'is_variant_defining' => (bool) ($data['is_variant_defining'] ?? false),
                        'is_filterable' => (bool) ($data['is_filterable'] ?? true),
                    ]);
                    if (! empty($data['categories'])) {
                        $definition->categories()->sync($data['categories']);
                    }
                    // Sync options if provided (Select/MultiSelect)
                    if (! empty($data['options']) && in_array($data['data_type'], [AttributeDataType::Select->value, AttributeDataType::MultiSelect->value], true)) {
                        foreach ($data['options'] as $opt) {
                            $definition->options()->create([
                                'value' => $opt['value'],
                                'label' => $opt['label'],
                                'sort_order' => (int) ($opt['sort_order'] ?? 0),
                            ]);
                        }
                    }
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
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['variant', 'attributeDefinition', 'attributeOption'])->whereNull('product_variant_id'))
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('attributeDefinition.group')->label('Group')->placeholder('General')->badge()->color('gray'),
                TextColumn::make('attributeDefinition.label')->label('Attribute')->sortable(query: fn (Builder $q, string $dir) => $q->orderBy(
                    AttributeDefinition::select('sort_order')->whereColumn('attribute_definitions.id', 'product_attribute_values.attribute_definition_id'), $dir
                )),
                TextColumn::make('display_value')
                    ->label('Value')
                    ->getStateUsing(fn ($record): string => (string) ($record->displayValue() ?? '—')),
                TextColumn::make('variant.sku')->label('Variant')->placeholder('Product')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                Action::make('manageSpecifications')
                    ->label('Manage Specifications')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->color('primary')
                    ->modalWidth('7xl')
                    ->modalHeading(fn (): string => 'Manage Specifications — '.$this->getOwnerRecord()?->translations()->where('locale', 'en')->value('name') ?? 'Product')
                    ->form(fn (): array => $this->bulkSpecificationForm())
                    ->action(function (array $data): void {
                        $product = $this->getOwnerRecord();
                        if (! $product) {
                            return;
                        }

                        // Handle inline new attribute creation first
                        $newAttribute = $data['new_attribute'] ?? null;
                        $newAttributeId = null;
                        if (is_array($newAttribute) && ! empty($newAttribute['label']) && ! empty($newAttribute['code'])) {
                            $existing = AttributeDefinition::where('tenant_id', tenant()?->id)->where('code', $newAttribute['code'])->first();
                            if (! $existing) {
                                $def = AttributeDefinition::create([
                                    'code' => $newAttribute['code'],
                                    'label' => $newAttribute['label'],
                                    'data_type' => $newAttribute['data_type'] ?? AttributeDataType::Text->value,
                                    'unit' => $newAttribute['unit'] ?? null,
                                    'group' => $newAttribute['group'] ?? null,
                                    'group_sort_order' => (int) ($newAttribute['group_sort_order'] ?? 0),
                                    'sort_order' => (int) ($newAttribute['sort_order'] ?? 0),
                                    'is_filterable' => (bool) ($newAttribute['is_filterable'] ?? true),
                                    'is_variant_defining' => (bool) ($newAttribute['is_variant_defining'] ?? false),
                                ]);
                                if (! empty($newAttribute['categories'])) {
                                    $def->categories()->sync($newAttribute['categories']);
                                }
                                if (! empty($newAttribute['options']) && in_array($newAttribute['data_type'], [AttributeDataType::Select->value, AttributeDataType::MultiSelect->value], true)) {
                                    foreach ($newAttribute['options'] as $opt) {
                                        if (empty($opt['value']) || empty($opt['label'])) {
                                            continue;
                                        }
                                        $def->options()->create([
                                            'value' => $opt['value'],
                                            'label' => $opt['label'],
                                            'sort_order' => (int) ($opt['sort_order'] ?? 0),
                                        ]);
                                    }
                                }
                                $newAttributeId = $def->getKey();
                                self::clearDefinitionCache();
                            }
                        }

                        $bulkService = app(ProductSpecificationBulkService::class);
                        $showAll = (bool) ($data['show_all_attributes'] ?? false);
                        $specs = $data['specs'] ?? [];

                        // If a new attribute was just created, ensure its (empty) spec is not required to be in $specs yet
                        // The newly created attribute will be available on next open; for current save, merge if value provided
                        if ($newAttributeId && isset($data['new_attribute_value'])) {
                            $specs[$newAttributeId] = $this->normalizeSpecPayloadForNewAttribute($newAttribute, $data['new_attribute_value']);
                        }

                        $bulkService->sync($product, $specs, $showAll);

                        Notification::make()
                            ->title('Specifications saved')
                            ->success()
                            ->send();
                    })
                    ->slideOver(false),
                CreateAction::make()->label('Add Single')->icon('heroicon-o-plus')->color('gray')->size('sm'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->emptyStateHeading('No specifications yet')
            ->emptyStateDescription('Use Manage Specifications to fill multiple specs at once, or Add Single for one-off.')
            ->emptyStateActions([
                Action::make('manageSpecificationsEmpty')
                    ->label('Manage Specifications')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->color('primary')
                    ->modalWidth('7xl')
                    ->form(fn (): array => $this->bulkSpecificationForm())
                    ->action(function (array $data): void {
                        $product = $this->getOwnerRecord();
                        if (! $product) {
                            return;
                        }
                        app(ProductSpecificationBulkService::class)->sync($product, $data['specs'] ?? [], (bool) ($data['show_all_attributes'] ?? false));
                        Notification::make()->title('Specifications saved')->success()->send();
                    }),
            ]);
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
        $tenantId = tenant()?->id ?? 0;
        if (isset(self::$definitionCache[$tenantId])) {
            return self::$definitionCache[$tenantId];
        }

        return self::$definitionCache[$tenantId] = AttributeDefinition::query()
            ->with('options')
            ->orderBy('group_sort_order')
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get();
    }

    public static function clearDefinitionCache(): void
    {
        self::$definitionCache = [];
    }

    /**
     * @return array<int, mixed>
     */
    private function fullAttributeDefinitionForm(): array
    {
        return [
            TextInput::make('code')->required()->alphaDash()->helperText('Unique code, e.g. color, storage_gb')->scopedUnique(ignoreRecord: false),
            TextInput::make('label')->required(),
            Select::make('data_type')->options(collect(AttributeDataType::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()]))->required()->default(AttributeDataType::Text->value),
            TextInput::make('unit')->helperText('e.g. GB, inch, mAh'),
            TextInput::make('group')->placeholder('e.g. Display, Battery, Dimensions')->helperText('Specification section heading. Empty → General.'),
            TextInput::make('group_sort_order')->numeric()->default(0)->helperText('Orders this spec group (lower first).'),
            TextInput::make('sort_order')->numeric()->default(0)->helperText('Orders this attribute within its group.'),
            Toggle::make('is_filterable')->default(true),
            Toggle::make('is_variant_defining')->helperText('Enable if this defines variant combinations.'),
            Select::make('categories')->relationship('categories', 'name')->multiple()->preload()->helperText('Limit to categories. Empty = all products.'),
            Repeater::make('options')->label('Options (for Select/MultiSelect)')->schema([
                TextInput::make('value')->required()->helperText('Stored value'),
                TextInput::make('label')->required()->helperText('Display label'),
                TextInput::make('sort_order')->numeric()->default(0),
            ])->columns(3)->collapsible()->default([])->visible(fn (Get $get): bool => in_array($get('data_type'), [AttributeDataType::Select->value, AttributeDataType::MultiSelect->value], true)),
        ];
    }

    /**
     * Build the grouped bulk specification form.
     *
     * @return array<int, mixed>
     */
    private function bulkSpecificationForm(): array
    {
        $product = $this->getOwnerRecord();
        if (! $product) {
            return [];
        }

        $bulkService = app(ProductSpecificationBulkService::class);
        // Default: show applicable only; user can toggle Show all
        // We need to handle show_all live, but Filament Action form is static at open. We will render both paths and use visible() on fields.
        // Simpler: always load applicable + all, but hidden when not applicable.
        $allDefinitions = $bulkService->applicableDefinitions($product, true);
        $applicableDefinitions = $bulkService->applicableDefinitions($product, false);
        $applicableIds = $applicableDefinitions->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $isFallback = count($applicableIds) === 0 || count($applicableIds) === $allDefinitions->count();

        $existingMap = $bulkService->existingValues($product);
        $groupedAll = $bulkService->groupedDefinitions($allDefinitions);

        $components = [];

        // Header: completion + controls
        $totalApplicable = $applicableDefinitions->count();
        $completedApplicable = $applicableDefinitions->filter(fn (AttributeDefinition $d) => $existingMap->has($d->id) && ! $this->isEmptyValueForDisplay($d, $existingMap->get($d->id)))->count();

        $components[] = Placeholder::make('__completion')
            ->content(fn (): string => "{$completedApplicable} / {$totalApplicable} specifications completed".($isFallback ? ' (showing all — no category mapping)' : ''))
            ->columnSpanFull();

        $components[] = Toggle::make('show_all_attributes')
            ->label('Show all attributes')
            ->helperText($isFallback ? 'No category mapping for this product — showing all.' : 'When off, only attributes mapped to this product\'s category are shown.')
            ->default(false)
            ->live()
            ->columnSpanFull()
            ->visible(fn (): bool => ! $isFallback);

        $components[] = TextInput::make('spec_search')
            ->label('Search specifications')
            ->placeholder('Filter by label or code…')
            ->live(debounce: 300)
            ->columnSpanFull();

        $components[] = Toggle::make('show_missing_only')
            ->label('Show missing only')
            ->helperText('Hide already filled specifications')
            ->live()
            ->columnSpanFull();

        // Grouped sections
        foreach ($groupedAll as $groupName => $definitions) {
            $isGeneralFallbackGroup = $groupName === 'General' && $isFallback;
            $fields = [];

            foreach ($definitions as $definition) {
                $isApplicable = in_array((int) $definition->id, $applicableIds, true);
                $existing = $existingMap->get($definition->id);
                $isRequired = $this->isRequiredForProduct($definition, $product);
                $label = $definition->label.($definition->unit ? " ({$definition->unit})" : '').($isRequired ? ' *' : '');

                $field = match ($definition->data_type) {
                    AttributeDataType::Text => TextInput::make("specs.{$definition->id}.value_string")->label($label)->default($existing?->value_string)->placeholder($definition->unit ?? ''),
                    AttributeDataType::Number => TextInput::make("specs.{$definition->id}.value_integer")->label($label)->numeric()->default($existing?->value_integer),
                    AttributeDataType::Decimal => TextInput::make("specs.{$definition->id}.value_decimal")->label($label)->numeric()->step(0.01)->default($existing?->value_decimal),
                    AttributeDataType::Boolean => Toggle::make("specs.{$definition->id}.value_boolean")->label($label)->default((bool) ($existing?->value_boolean ?? false)),
                    AttributeDataType::Select, AttributeDataType::MultiSelect => Select::make("specs.{$definition->id}.attribute_option_id")
                        ->label($label)
                        ->options($definition->options->pluck('label', 'id')->all())
                        ->searchable()
                        ->preload()
                        ->placeholder('Select '.$definition->label)
                        ->default($existing?->attribute_option_id)
                        ->createOptionForm([
                            TextInput::make('value')->required(),
                            TextInput::make('label')->required(),
                            TextInput::make('sort_order')->numeric()->default(0),
                        ])
                        ->createOptionUsing(function (array $data) use ($definition): int {
                            $opt = AttributeOption::create([
                                'attribute_definition_id' => $definition->id,
                                'value' => $data['value'],
                                'label' => $data['label'],
                                'sort_order' => (int) ($data['sort_order'] ?? 0),
                            ]);
                            self::clearDefinitionCache();

                            return $opt->getKey();
                        }),
                };

                // Tenant-safe visible logic: filtered by applicability + search + missing only
                $fields[] = $field
                    ->visible(function (Get $get) use ($definition, $isApplicable, $existing): bool {
                        $showAll = (bool) $get('show_all_attributes');
                        if (! $isApplicable && ! $showAll) {
                            return false;
                        }
                        $search = trim((string) ($get('spec_search') ?? ''));
                        if ($search !== '' && ! str_contains(strtolower($definition->label.' '.$definition->code), strtolower($search))) {
                            return false;
                        }
                        $missingOnly = (bool) $get('show_missing_only');
                        if ($missingOnly && $existing && ! $this->isEmptyValueForDisplay($definition, $existing)) {
                            return false;
                        }

                        return true;
                    })
                    ->helperText($isRequired ? 'Required for this category' : null);
            }

            if ($fields === []) {
                continue;
            }

            $components[] = Section::make($groupName)
                ->description(count($definitions).' attributes')
                ->collapsible()
                ->collapsed(fn (Get $get): bool => $this->shouldCollapseGroup($groupName, $definitions, $get, $existingMap))
                ->columns(2)
                ->schema($fields);
        }

        // Inline Add Attribute (full form)
        $components[] = Section::make('Add New Attribute')
            ->description('Create a new attribute definition. It will be available immediately in the correct group without closing this manager.')
            ->collapsible()
            ->collapsed()
            ->columns(2)
            ->schema([
                TextInput::make('new_attribute.code')->label('Code')->alphaDash()->placeholder('e.g. battery_capacity'),
                TextInput::make('new_attribute.label')->label('Label')->placeholder('e.g. Battery Capacity'),
                Select::make('new_attribute.data_type')->label('Data type')->options(collect(AttributeDataType::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()]))->default(AttributeDataType::Text->value)->live(),
                TextInput::make('new_attribute.unit')->label('Unit')->placeholder('e.g. mAh, inch'),
                TextInput::make('new_attribute.group')->label('Group')->placeholder('e.g. Battery')->helperText('Pre-filled from current group if Add is clicked inside a group. You can change it.'),
                TextInput::make('new_attribute.group_sort_order')->label('Group order')->numeric()->default(0),
                TextInput::make('new_attribute.sort_order')->label('Attribute order')->numeric()->default(0),
                Select::make('new_attribute.categories')->label('Categories')->relationship('categories', 'name', modifyQueryUsing: fn (Builder $q) => $q->where('tenant_id', tenant()?->id ?? 0))->multiple()->preload()->helperText('Empty = all products. Map to limit.'),
                Toggle::make('new_attribute.is_filterable')->label('Filterable')->default(true),
                Toggle::make('new_attribute.is_variant_defining')->label('Variant-defining')->default(false),
                Repeater::make('new_attribute.options')->label('Options (for Select)')->schema([
                    TextInput::make('value')->required(),
                    TextInput::make('label')->required(),
                    TextInput::make('sort_order')->numeric()->default(0),
                ])->columns(3)->visible(fn (Get $get): bool => in_array($get('new_attribute.data_type'), [AttributeDataType::Select->value, AttributeDataType::MultiSelect->value], true))->columnSpanFull(),
                Select::make('new_attribute_value_select')->label('Initial value (for new Select)')->options(function (Get $get): array {
                    $opts = $get('new_attribute.options') ?? [];
                    $map = [];
                    foreach ($opts as $idx => $opt) {
                        if (! empty($opt['label'])) {
                            $map[$idx] = $opt['label'];
                        }
                    }

                    return $map;
                })->visible(fn (Get $get): bool => in_array($get('new_attribute.data_type'), [AttributeDataType::Select->value], true))->helperText('Optional: set initial value for this product after creation'),
                TextInput::make('new_attribute_value')->label('Initial value')->visible(fn (Get $get): bool => ! in_array($get('new_attribute.data_type'), [AttributeDataType::Select->value, AttributeDataType::MultiSelect->value, AttributeDataType::Boolean->value], true))->helperText('Optional initial value for this product'),
                Toggle::make('new_attribute_value_boolean')->label('Initial value')->visible(fn (Get $get): bool => $get('new_attribute.data_type') === AttributeDataType::Boolean->value),
            ]);

        return $components;
    }

    private function shouldCollapseGroup(string $groupName, Collection $definitions, Get $get, Collection $existingMap): bool
    {
        $search = trim((string) ($get('spec_search') ?? ''));
        $missingOnly = (bool) $get('show_missing_only');
        if ($search !== '' || $missingOnly) {
            return false;
        }
        // Collapse groups where all are already filled (no missing) unless General with many
        $missing = $definitions->filter(fn (AttributeDefinition $d) => ! $existingMap->has($d->id) || $this->isEmptyValueForDisplay($d, $existingMap->get($d->id)))->count();

        return $missing === 0;
    }

    private function isEmptyValueForDisplay(AttributeDefinition $definition, ?ProductAttributeValue $value): bool
    {
        if (! $value) {
            return true;
        }

        return match ($definition->data_type) {
            AttributeDataType::Text => trim((string) $value->value_string) === '',
            AttributeDataType::Number => $value->value_integer === null,
            AttributeDataType::Decimal => $value->value_decimal === null,
            AttributeDataType::Boolean => false,
            AttributeDataType::Select, AttributeDataType::MultiSelect => $value->attribute_option_id === null,
        };
    }

    private function isRequiredForProduct(AttributeDefinition $definition, $product): bool
    {
        if (! $product->category_id) {
            return false;
        }
        $pivot = DB::table('category_attribute_definition')
            ->where('category_id', $product->category_id)
            ->where('attribute_definition_id', $definition->id)
            ->first();

        return $pivot ? (bool) $pivot->is_required : false;
    }

    private function normalizeSpecPayloadForNewAttribute(array $newAttribute, mixed $value): array
    {
        $type = $newAttribute['data_type'] ?? AttributeDataType::Text->value;

        return match ($type) {
            AttributeDataType::Text->value => ['value_string' => (string) $value],
            AttributeDataType::Number->value => ['value_integer' => (int) $value],
            AttributeDataType::Decimal->value => ['value_decimal' => (string) $value],
            AttributeDataType::Boolean->value => ['value_boolean' => (bool) $value],
            default => ['attribute_option_id' => is_numeric($value) ? (int) $value : null],
        };
    }
}
