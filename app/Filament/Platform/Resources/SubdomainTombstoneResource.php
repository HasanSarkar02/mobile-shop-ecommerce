<?php

declare(strict_types=1);

namespace App\Filament\Platform\Resources;

use App\Enums\DeploymentMode;
use App\Filament\Platform\Resources\SubdomainTombstoneResource\Pages\ListSubdomainTombstones;
use App\Models\SubdomainTombstone;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class SubdomainTombstoneResource extends Resource
{
    protected static ?string $model = SubdomainTombstone::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-no-symbol';

    protected static ?string $navigationLabel = 'Subdomain Tombstones';

    protected static string|UnitEnum|null $navigationGroup = 'Tenancy';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('subdomain')->searchable()->copyable(),
                TextColumn::make('reason')->badge()->formatStateUsing(fn ($state) => $state instanceof BackedEnum ? $state->value : (string) $state),
                TextColumn::make('quarantine_until')->dateTime()->placeholder('Permanent'),
                TextColumn::make('released_at')->dateTime(),
                TextColumn::make('tenant_id')->label('Tenant ID')->placeholder('—'),
                TextColumn::make('created_at')->dateTime(),
            ])
            ->filters([])
            ->recordActions([
                DeleteAction::make()->label('Release Now')->requiresConfirmation()->visible(fn (SubdomainTombstone $record): bool => ! $record->isPermanent()),
            ])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSubdomainTombstones::route('/'),
        ];
    }

    public static function canViewAny(): bool
    {
        return config('deployment.mode') === DeploymentMode::SaaS->value && auth('platform')->user()?->is_platform_admin === true;
    }

    public static function canView(Model $record): bool
    {
        return self::canViewAny() && $record instanceof SubdomainTombstone;
    }
}
