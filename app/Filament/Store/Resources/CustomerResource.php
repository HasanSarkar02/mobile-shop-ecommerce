<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources;

use App\Filament\Store\Resources\CustomerResource\Pages;
use App\Models\Customer;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static string|UnitEnum|null $navigationGroup = 'Customers';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required(),
            TextInput::make('email')->email()->required()->unique(ignoreRecord: true),
            TextInput::make('phone'),
            Toggle::make('marketing_opt_in')->label('Subscribed to marketing'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('phone'),
                IconColumn::make('marketing_opt_in')->boolean()->label('Marketing'),
                TextColumn::make('created_at')->date()->label('Joined'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'),
            'edit' => Pages\EditCustomer::route('/{record}/edit'),
        ];
    }

    // Staff can view and edit customer records as part of normal customer
    // service (addresses, notes, etc.); deleting a customer record is a more
    // significant, less-reversible action reserved for owners.
    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->isOwner() ?? false;
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()?->isOwner() ?? false;
    }
}