<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources;

use App\Enums\CouponCustomerEligibility;
use App\Enums\CouponEligibilityScope;
use App\Enums\CouponScopeMode;
use App\Enums\CouponType;
use App\Filament\Store\Resources\CouponResource\Pages;
use App\Filament\Store\Resources\CouponResource\RelationManagers\CustomerEligibilitiesRelationManager;
use App\Filament\Store\Resources\CouponResource\RelationManagers\ScopesRelationManager;
use App\Models\Coupon;
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
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class CouponResource extends Resource
{
    protected static ?string $model = Coupon::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-ticket';

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required(),
            TextInput::make('code')->helperText('Leave blank for an automatic discount (no code required).'),
            Textarea::make('description')->rows(2)->helperText('Shown to customers.'),
            Select::make('campaign_id')->relationship('campaign', 'name')->searchable()->preload(),

            Select::make('type')
                ->options(collect(CouponType::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]))
                ->required()
                ->live(),
            TextInput::make('value')
                ->numeric()
                ->visible(fn (Get $get): bool => $get('type') !== CouponType::FreeShipping->value)
                ->helperText(fn (Get $get): string => $get('type') === CouponType::Percentage->value ? 'Enter 0-100' : 'Enter amount in BDT')
                ->formatStateUsing(fn (Get $get, $state) => $get('type') === CouponType::FixedAmount->value && $state !== null ? $state / 100 : $state)
                ->dehydrateStateUsing(fn (Get $get, $state) => $get('type') === CouponType::FixedAmount->value && $state !== null ? (int) round($state * 100) : $state),
            TextInput::make('max_discount_amount')
                ->label('Max discount cap (BDT, optional)')
                ->numeric()
                ->formatStateUsing(fn (?int $state) => $state !== null ? $state / 100 : null)
                ->dehydrateStateUsing(fn (?float $state) => $state !== null ? (int) round($state * 100) : null),

            TextInput::make('min_order_amount')
                ->label('Minimum order amount (BDT, optional)')
                ->numeric()
                ->formatStateUsing(fn (?int $state) => $state !== null ? $state / 100 : null)
                ->dehydrateStateUsing(fn (?float $state) => $state !== null ? (int) round($state * 100) : null),
            TextInput::make('min_quantity')->numeric(),

            Select::make('eligibility_scope')
                ->options(collect(CouponEligibilityScope::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]))
                ->default(CouponEligibilityScope::All->value)
                ->required()
                ->live(),
            Select::make('scope_mode')
                ->options(collect(CouponScopeMode::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]))
                ->default(CouponScopeMode::Include->value)
                ->visible(fn (Get $get): bool => $get('eligibility_scope') === CouponEligibilityScope::Specific->value)
                ->helperText('Manage the specific products/categories/brands/collections in the Scope tab after saving.'),

            Select::make('customer_eligibility')
                ->options(collect(CouponCustomerEligibility::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]))
                ->default(CouponCustomerEligibility::All->value)
                ->required()
                ->live()
                ->helperText(fn (Get $get) => $get('customer_eligibility') === CouponCustomerEligibility::SpecificCustomers->value
                    ? 'Manage eligible customers in the Eligible Customers tab after saving.' : null),

            TextInput::make('usage_limit_total')->label('Total usage limit (optional)')->numeric(),
            TextInput::make('usage_limit_per_customer')->label('Per-customer usage limit (optional)')->numeric(),

            DateTimePicker::make('starts_at'),
            DateTimePicker::make('ends_at'),
            Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('code')->placeholder('Automatic')->searchable(),
                TextColumn::make('type')->badge(),
                TextColumn::make('redemptions_count')->counts('redemptions')->label('Used'),
                IconColumn::make('is_active')->boolean(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getRelations(): array
    {
        return [ScopesRelationManager::class, CustomerEligibilitiesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCoupons::route('/'),
            'create' => Pages\CreateCoupon::route('/create'),
            'edit' => Pages\EditCoupon::route('/{record}/edit'),
        ];
    }

    // Coupons directly control discounting/revenue, so creating, editing, and
    // deleting them is owner-only. Staff can still view active coupons
    // (canViewAny/canView keep Filament's default) to help customers.
    public static function canCreate(): bool
    {
        return auth()->user()?->isOwner() ?? false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->isOwner() ?? false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->isOwner() ?? false;
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()?->isOwner() ?? false;
    }
}