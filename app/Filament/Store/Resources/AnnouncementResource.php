<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources;

use App\Enums\LinkType;
use App\Filament\Store\Resources\AnnouncementResource\Pages;
use App\Models\Announcement;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class AnnouncementResource extends Resource
{
    protected static ?string $model = Announcement::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-speaker-wave';

    protected static string|UnitEnum|null $navigationGroup = 'Merchandising';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('message')->required(),
            Select::make('link_type')
                ->options(collect(LinkType::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]))
                ->default(LinkType::None->value)
                ->required(),
            TextInput::make('link_value'),
            DateTimePicker::make('starts_at'),
            DateTimePicker::make('ends_at'),
            Toggle::make('is_active')->default(true),
            Toggle::make('is_dismissible')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('message')->limit(50),
                TextColumn::make('is_active')->badge(),
                TextColumn::make('starts_at')->dateTime()->placeholder('—'),
                TextColumn::make('ends_at')->dateTime()->placeholder('—'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAnnouncements::route('/'),
            'create' => Pages\CreateAnnouncement::route('/create'),
            'edit' => Pages\EditAnnouncement::route('/{record}/edit'),
        ];
    }
}
