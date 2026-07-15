<?php

declare(strict_types=1);

namespace App\Filament\Store\Pages;

use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use UnitEnum;

class GlobalStoreSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-magnifying-glass';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'SEO & Defaults';

    protected string $view = 'filament.store.pages.global-store-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(tenant()->settings->only([
            'meta_title_template', 'meta_description_default', 'order_confirmation_note', 'robots_txt_extra',
        ]));
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('meta_title_template')
                ->helperText('e.g. "{product_name} - {store_name}". Default pattern for new product/category pages.'),
            Textarea::make('meta_description_default')->rows(2),
            Textarea::make('order_confirmation_note')->rows(3)->helperText('Shown to customers after checkout.'),
            Textarea::make('robots_txt_extra')->rows(3)->helperText('Extra directives appended to the generated robots.txt.'),
        ])->statePath('data');
    }

    public static function canAccess(array $parameters = []): bool
    {
        return auth()->user()?->isOwner() ?? false;
    }

    public function save(): void
    {
        tenant()->settings->update($this->form->getState());

        Notification::make()->title('Settings saved')->success()->send();
    }
}