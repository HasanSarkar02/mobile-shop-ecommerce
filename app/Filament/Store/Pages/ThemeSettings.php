<?php

declare(strict_types=1);

namespace App\Filament\Store\Pages;

use BackedEnum;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use UnitEnum;

class ThemeSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-paint-brush';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Theme & Branding';

    protected string $view = 'filament.store.pages.theme-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(tenant()->themeSettings->only([
            'logo_path', 'favicon_path', 'primary_color', 'secondary_color', 'font_family', 'social_links', 'footer_text',
        ]));
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            FileUpload::make('logo_path')->image()->directory('tenant-logos'),
            FileUpload::make('favicon_path')->image()->directory('tenant-favicons'),
            ColorPicker::make('primary_color')->required(),
            ColorPicker::make('secondary_color'),
            Select::make('font_family')->options(['inter' => 'Inter', 'poppins' => 'Poppins', 'roboto' => 'Roboto']),
            TextInput::make('social_links.facebook')->label('Facebook URL'),
            TextInput::make('social_links.instagram')->label('Instagram URL'),
            TextInput::make('social_links.whatsapp')->label('WhatsApp Number/Link'),
            TextInput::make('social_links.youtube')->label('YouTube URL'),
            TextInput::make('social_links.tiktok')->label('TikTok URL'),
            Textarea::make('footer_text')->rows(2),
        ])->statePath('data');
    }

    public static function canAccess(array $parameters = []): bool
    {
        return auth()->user()?->isOwner() ?? false;
    }

    public function save(): void
    {
        tenant()->themeSettings->update($this->form->getState());

        Notification::make()->title('Theme settings saved')->success()->send();
    }
}