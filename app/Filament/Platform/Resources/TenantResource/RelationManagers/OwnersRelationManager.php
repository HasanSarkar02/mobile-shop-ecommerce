<?php

declare(strict_types=1);

namespace App\Filament\Platform\Resources\TenantResource\RelationManagers;

use App\Models\Tenant;
use App\Models\User;
use App\Notifications\TenantOwnerInvitationNotification;
use App\Services\OwnerLifecycleService;
use App\Services\OwnershipTransferService;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

class OwnersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    protected static ?string $title = 'Owners';

    public function form(Schema $schema): Schema
    {
        return $schema;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Name'),
                TextColumn::make('email')->label('Email'),
                TextColumn::make('role')->label('Role'),
                TextColumn::make('is_active')->label('Status')->formatStateUsing(fn (bool $state): string => $state ? 'Active' : 'Inactive'),
                TextColumn::make('email_verified_at')->label('Email verified')->dateTime()->placeholder('Not verified'),
                TextColumn::make('password_changed_at')->label('Password changed')->dateTime()->placeholder('Not changed'),
            ])
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('role', 'owner')->orderBy('id'))
            ->recordActions([
                Action::make('activateOwner')
                    ->label('Activate')
                    ->visible(fn (User $record): bool => ! $record->is_active)
                    ->action(fn (User $record): User => $this->runLifecycle('activated', $record)),
                Action::make('deactivateOwner')
                    ->label('Deactivate')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (User $record): bool => $record->is_active)
                    ->action(fn (User $record): User => $this->runLifecycle('deactivated', $record)),
                Action::make('resetOwnerPassword')
                    ->label('Reset password')
                    ->requiresConfirmation()
                    ->action(function (User $record): void {
                        try {
                            $actor = auth('platform')->user();
                            abort_unless($actor instanceof User, 403);
                            $issued = app(OwnerLifecycleService::class)->resetPassword($this->tenant(), $record, $actor);
                            $expiresAt = $issued['invitation']->getAttribute('expires_at');
                            abort_unless($expiresAt instanceof CarbonInterface, 500);
                            $record->notify(new TenantOwnerInvitationNotification($this->tenant(), $issued['token'], $expiresAt));
                            Notification::make()->success()->title('Owner password setup link sent.')->send();
                        } catch (Throwable $exception) {
                            Notification::make()->danger()->title('Owner password reset failed.')->body($exception->getMessage())->send();
                        }
                    }),
                Action::make('transferPrimary')
                    ->label('Transfer primary ownership')
                    ->requiresConfirmation()
                    ->visible(fn (User $record): bool => (int) $this->tenant()->getAttribute('primary_owner_id') !== (int) $record->id && $record->is_active)
                    ->action(function (User $record): void {
                        try {
                            $actor = auth('platform')->user();
                            abort_unless($actor instanceof User, 403);
                            $issued = app(OwnershipTransferService::class)->start($this->tenant(), $record, $actor);
                            $expiresAt = $issued['invitation']->getAttribute('expires_at');
                            abort_unless($expiresAt instanceof CarbonInterface, 500);
                            $record->notify(new TenantOwnerInvitationNotification($this->tenant(), $issued['token'], $expiresAt));
                            Notification::make()->success()->title('Ownership transfer invitation sent.')->send();
                        } catch (Throwable $exception) {
                            Notification::make()->danger()->title('Ownership transfer could not be started.')->body($exception->getMessage())->send();
                        }
                    }),
            ]);
    }

    private function runLifecycle(string $event, User $owner): User
    {
        $actor = auth('platform')->user();
        abort_unless($actor instanceof User, 403);

        $updated = $event === 'activated'
            ? app(OwnerLifecycleService::class)->activate($this->tenant(), $owner, $actor)
            : app(OwnerLifecycleService::class)->deactivate($this->tenant(), $owner, $actor);

        Notification::make()->success()->title('Owner '.$event.'.')->send();

        return $updated;
    }

    private function tenant(): Tenant
    {
        $tenant = $this->getOwnerRecord();
        abort_unless($tenant instanceof Tenant, 500);

        return $tenant;
    }
}
