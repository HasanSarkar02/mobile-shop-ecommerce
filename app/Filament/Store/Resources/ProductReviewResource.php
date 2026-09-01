<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources;

use App\Enums\ReviewStatus;
use App\Filament\Store\Resources\ProductReviewResource\Pages;
use App\Models\ProductReview;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class ProductReviewResource extends Resource
{
    protected static ?string $model = ProductReview::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-star';

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Reviews';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Textarea::make('reply_text')
                    ->label('Store Reply')
                    ->rows(3)
                    ->placeholder('Write a reply visible to the customer...')
                    ->maxLength(2000)
                    ->visible(fn (): bool => auth()->check() && auth()->user()?->tenant_id !== null && ! (auth()->user()->is_platform_admin ?? false))
                    ->disabled(fn (): bool => ! (auth()->check() && auth()->user()?->tenant_id !== null && ! (auth()->user()->is_platform_admin ?? false)))
                    ->helperText('Only the store owner can reply. Superadmins cannot reply on behalf of merchants.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product.name')->limit(30),
                TextColumn::make('customer.name'),
                TextColumn::make('rating')->formatStateUsing(fn (int $state): string => str_repeat('★', $state)),
                TextColumn::make('body')->limit(40),
                TextColumn::make('reply_text')->limit(40)->label('Reply')->toggleable(),
                IconColumn::make('is_verified_purchase')->boolean()->label('Verified'),
                TextColumn::make('status')->badge(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                EditAction::make()
                    ->visible(fn (): bool => auth()->check() && auth()->user()?->tenant_id !== null && ! (auth()->user()->is_platform_admin ?? false)),
                Action::make('approve')
                    ->icon('heroicon-o-check')
                    ->visible(fn (ProductReview $record): bool => $record->status !== ReviewStatus::Approved)
                    ->action(fn (ProductReview $record) => $record->update(['status' => ReviewStatus::Approved])),
                Action::make('reject')
                    ->icon('heroicon-o-x-mark')
                    ->visible(fn (ProductReview $record): bool => $record->status !== ReviewStatus::Rejected)
                    ->action(fn (ProductReview $record) => $record->update(['status' => ReviewStatus::Rejected])),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListProductReviews::route('/')];
    }
}
