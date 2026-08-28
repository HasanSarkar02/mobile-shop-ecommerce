<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources;

use App\Filament\Store\Resources\TenantShippingRateResource\Pages;
use App\Models\BdDistrict;
use App\Models\BdDivision;
use App\Models\BdUpazila;
use App\Models\TenantShippingRate;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class TenantShippingRateResource extends Resource
{
    protected static ?string $model = TenantShippingRate::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-map-pin';

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    protected static ?string $navigationLabel = 'Shipping Rates';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Rate Name')
                ->placeholder('Inside Dhaka')
                ->required()
                ->maxLength(255),

            Grid::make(3)->schema([
                Select::make('bd_division_id')
                    ->label('Division')
                    ->options(fn () => BdDivision::query()->orderBy('name_en')->pluck('name_en', 'id'))
                    ->searchable()
                    ->preload()
                    ->nullable()
                    ->live()
                    ->afterStateUpdated(function (callable $set): void {
                        $set('bd_district_id', null);
                        $set('bd_upazila_id', null);
                    })
                    ->hint('Leave empty for whole country'),

                Select::make('bd_district_id')
                    ->label('District')
                    ->options(function (callable $get): array {
                        $divisionId = $get('bd_division_id');
                        $query = BdDistrict::query()->orderBy('name_en');
                        if ($divisionId) {
                            $query->where('division_id', $divisionId);
                        }

                        return $query->pluck('name_en', 'id')->all();
                    })
                    ->searchable()
                    ->preload()
                    ->nullable()
                    ->live()
                    ->afterStateUpdated(fn (callable $set) => $set('bd_upazila_id', null))
                    ->hint('Leave empty to apply to whole division/country'),

                Select::make('bd_upazila_id')
                    ->label('Upazila')
                    ->options(function (callable $get): array {
                        $districtId = $get('bd_district_id');
                        if (! $districtId) {
                            return [];
                        }

                        return BdUpazila::query()->where('district_id', $districtId)->orderBy('name_en')->pluck('name_en', 'id')->all();
                    })
                    ->searchable()
                    ->preload()
                    ->nullable()
                    ->hint('Leave empty for whole district'),
            ]),

            TextInput::make('charge')
                ->label('Charge (BDT)')
                ->numeric()
                ->required()
                ->prefix('৳')
                ->helperText('Enter amount in BDT, e.g. 80 for 80 BDT')
                ->formatStateUsing(fn (?int $state): ?float => $state !== null ? $state / 100 : null)
                ->dehydrateStateUsing(fn (?float $state): int => (int) round(($state ?? 0) * 100)),

            TextInput::make('free_threshold')
                ->label('Free Delivery Threshold (BDT)')
                ->numeric()
                ->nullable()
                ->prefix('৳')
                ->helperText('Evaluates post-discount cart total to protect margins. Overrides standard charge if met. Leave empty to never auto-free. E.g., 1000 means free shipping over 1000 TK.')
                ->formatStateUsing(fn (?int $state): ?float => $state !== null ? $state / 100 : null)
                ->dehydrateStateUsing(fn (?float $state): ?int => $state !== null ? (int) round($state * 100) : null),

            Toggle::make('is_active')->label('Active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('geo_target')
                    ->label('Geo Target')
                    ->getStateUsing(function (TenantShippingRate $record): string {
                        if ($record->bd_upazila_id) {
                            $upazilaName = $record->upazila ? $record->upazila->name_en : '-';
                            $districtName = $record->district ? $record->district->name_en : '-';

                            return $upazilaName.' / '.$districtName;
                        }
                        if ($record->bd_district_id) {
                            return $record->district ? $record->district->name_en : '-';
                        }
                        if ($record->bd_division_id) {
                            return $record->division ? $record->division->name_en : '-';
                        }

                        return 'Outside / Fallback';
                    })
                    ->description(function (TenantShippingRate $record): string {
                        if ($record->bd_division_id && $record->division) {
                            return $record->division->name_en;
                        }

                        return '';
                    }),
                TextColumn::make('charge')->label('Charge')->formatStateUsing(fn (int $state): string => money($state))->sortable(),
                TextColumn::make('free_threshold')->label('Free over')->formatStateUsing(fn (?int $state): string => $state !== null ? money($state) : '—')->placeholder('—')->sortable(),
                IconColumn::make('is_active')->boolean()->label('Active'),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->orderBy('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTenantShippingRates::route('/'),
            'create' => Pages\CreateTenantShippingRate::route('/create'),
            'edit' => Pages\EditTenantShippingRate::route('/{record}/edit'),
        ];
    }
}
