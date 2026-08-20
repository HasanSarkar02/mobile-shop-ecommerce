<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources;

use App\Enums\BlogPostStatus;
use App\Filament\Store\Resources\BlogPostResource\Pages;
use App\Models\BlogPost;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class BlogPostResource extends Resource
{
    protected static ?string $model = BlogPost::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-newspaper';

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->required(),
            TextInput::make('slug')->required()->scopedUnique(ignoreRecord: true),
            Textarea::make('excerpt')->rows(2),
            RichEditor::make('content')->required(),
            SpatieMediaLibraryFileUpload::make('cover')->collection('cover')->image(),
            Select::make('status')
                ->options(collect(BlogPostStatus::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]))
                ->default(BlogPostStatus::Draft->value)
                ->required(),
            DateTimePicker::make('published_at'),
            TextInput::make('meta_title'),
            Textarea::make('meta_description')->rows(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('published_at')->dateTime()->placeholder('—'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBlogPosts::route('/'),
            'create' => Pages\CreateBlogPost::route('/create'),
            'edit' => Pages\EditBlogPost::route('/{record}/edit'),
        ];
    }
}
