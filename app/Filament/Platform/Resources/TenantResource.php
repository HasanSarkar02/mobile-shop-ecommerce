<?php

declare(strict_types=1);

namespace App\Filament\Platform\Resources;

use App\Filament\Platform\Resources\TenantResource\Pages;
use App\Models\Tenant;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required(),
            TextInput::make('subdomain')->required()->unique(ignoreRecord: true)->alpha_dash(),
            Select::make('status')->options([
                'trial' => 'Trial',
                'active' => 'Active',
                'suspended' => 'Suspended',
            ])->required(),
            Select::make('plan')->options([
                'free' => 'Free',
                'paid' => 'Paid',
            ]),
            TextInput::make('contact_email')->email(),
            TextInput::make('contact_phone'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('subdomain')->searchable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('plan'),
                TextColumn::make('created_at')->date(),
            ])
            ->filters([])
            ->recordActions([EditAction::make()])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTenants::route('/'),
            'create' => Pages\CreateTenant::route('/create'),
            'edit' => Pages\EditTenant::route('/{record}/edit'),
        ];
    }
}