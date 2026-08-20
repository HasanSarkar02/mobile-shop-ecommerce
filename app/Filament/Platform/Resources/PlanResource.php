<?php

declare(strict_types=1);

namespace App\Filament\Platform\Resources;

use App\Filament\Platform\Resources\PlanResource\Pages;
use App\Models\Plan;
use App\Models\PlanChangeRequest;
use App\Models\TenantSubscription;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PlanResource extends Resource
{
    protected static ?string $model = Plan::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required(),
            TextInput::make('slug')->required()->unique(ignoreRecord: true),
            TextInput::make('price')
                ->label('Price (BDT)')
                ->numeric()
                ->required()
                ->formatStateUsing(fn (?int $state) => $state !== null ? $state / 100 : null)
                ->dehydrateStateUsing(fn (?float $state) => (int) round(($state ?? 0) * 100)),
            Select::make('billing_period')->options(['monthly' => 'Monthly', 'yearly' => 'Yearly'])->required(),
            TextInput::make('max_products')->numeric()->helperText('Leave empty for unlimited.'),
            TextInput::make('max_staff')->numeric()->helperText('Leave empty for unlimited.'),
            Toggle::make('custom_domain_allowed'),
            Toggle::make('is_active')->default(true),
            TextInput::make('sort_order')->numeric()->default(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('price')->formatStateUsing(fn (int $state): string => number_format($state / 100, 2)),
                TextColumn::make('max_products')->placeholder('Unlimited'),
                TextColumn::make('max_staff')->placeholder('Unlimited'),
                IconColumn::make('custom_domain_allowed')->boolean(),
                IconColumn::make('is_active')->boolean(),
            ])
            ->reorderable('sort_order')
            ->recordActions([
                EditAction::make(),
                Action::make('delete')
                    ->color('danger')
                    ->action(function (Plan $record): void {
                        if (self::isPlanReferenced($record)) {
                            Notification::make()
                                ->title('Cannot delete plan')
                                ->body('This plan is referenced by existing subscriptions or pending plan-change requests.')
                                ->danger()
                                ->send();

                            return;
                        }

                        $record->delete();
                    }),
            ]);
    }

    public static function isPlanReferenced(Plan $plan): bool
    {
        return TenantSubscription::query()->where('plan_id', $plan->id)->exists()
            || PlanChangeRequest::query()
                ->withoutGlobalScope('tenant')
                ->where('requested_plan_id', $plan->id)
                ->exists();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlans::route('/'),
            'create' => Pages\CreatePlan::route('/create'),
            'edit' => Pages\EditPlan::route('/{record}/edit'),
        ];
    }
}
