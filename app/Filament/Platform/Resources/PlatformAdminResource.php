<?php

declare(strict_types=1);

namespace App\Filament\Platform\Resources;

use App\Enums\DeploymentMode;
use App\Filament\Platform\Resources\PlatformAdminResource\Pages;
use App\Models\User;
use App\Services\PlatformAdminService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Throwable;

class PlatformAdminResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationLabel = 'Platform Admins';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required(),
            TextInput::make('email')->email()->required()->unique(ignoreRecord: true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('is_active')->label('Status')->formatStateUsing(fn (bool $state): string => $state ? 'Active' : 'Inactive'),
                TextColumn::make('mfa_status')->label('MFA')->state(fn (User $record): string => $record->getAttribute('app_authentication_secret') !== null ? 'Enabled' : 'Not enabled'),
                TextColumn::make('last_login_at')->dateTime()->placeholder('Not recorded'),
            ])
            ->recordActions([
                Action::make('activate')->visible(fn (User $record): bool => ! $record->is_active)->action(fn (User $record): User => self::lifecycle('activate', $record)),
                Action::make('deactivate')->color('danger')->requiresConfirmation()->visible(fn (User $record): bool => $record->is_active)->action(fn (User $record): User => self::lifecycle('deactivate', $record)),
                Action::make('revoke')->color('danger')->requiresConfirmation()->action(fn (User $record): User => self::lifecycle('revoke', $record)),
                Action::make('resetPassword')->requiresConfirmation()->action(function (User $record): void {
                    try {
                        $actor = self::actor();
                        $issued = app(PlatformAdminService::class)->resetPassword($record, $actor);
                        Notification::make()->success()->title('Platform Admin password setup link sent.')->send();
                    } catch (Throwable $exception) {
                        Notification::make()->danger()->title('Password reset failed.')->body($exception->getMessage())->send();
                    }
                }),
                Action::make('resetMfa')->requiresConfirmation()->action(function (User $record): void {
                    try {
                        app(PlatformAdminService::class)->resetMfa($record, self::actor());
                        Notification::make()->success()->title('Platform Admin MFA reset.')->send();
                    } catch (Throwable $exception) {
                        Notification::make()->danger()->title('MFA reset failed.')->body($exception->getMessage())->send();
                    }
                }),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('is_platform_admin', true);
    }

    public static function canViewAny(): bool
    {
        return self::canAccessPlatform();
    }

    public static function canCreate(): bool
    {
        return self::canAccessPlatform();
    }

    public static function canView(Model $record): bool
    {
        return self::canAccessPlatform() && $record instanceof User && $record->is_platform_admin;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlatformAdmins::route('/'),
            'create' => Pages\CreatePlatformAdmin::route('/create'),
        ];
    }

    private static function canAccessPlatform(): bool
    {
        if (config('deployment.mode') !== DeploymentMode::SaaS->value) {
            return false;
        }

        $user = auth('platform')->user();

        return $user instanceof User && $user->is_platform_admin && $user->is_active;
    }

    private static function actor(): User
    {
        $actor = auth('platform')->user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }

    private static function lifecycle(string $action, User $record): User
    {
        try {
            $updated = match ($action) {
                'activate' => app(PlatformAdminService::class)->activate($record, self::actor()),
                'deactivate' => app(PlatformAdminService::class)->deactivate($record, self::actor()),
                default => app(PlatformAdminService::class)->revoke($record, self::actor()),
            };
            Notification::make()->success()->title('Platform Admin '.$action.'d.')->send();

            return $updated;
        } catch (Throwable $exception) {
            Notification::make()->danger()->title('Platform Admin action failed.')->body($exception->getMessage())->send();

            return $record;
        }
    }
}
