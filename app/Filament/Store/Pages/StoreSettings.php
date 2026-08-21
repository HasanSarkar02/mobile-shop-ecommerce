<?php

declare(strict_types=1);

namespace App\Filament\Store\Pages;

use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use UnitEnum;

class StoreSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-office';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Store Profile';

    protected string $view = 'filament.store.pages.store-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(tenant()->only(['name', 'currency', 'contact_email', 'contact_phone']));
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required(),
            Select::make('currency')->options(['BDT' => 'BDT', 'USD' => 'USD'])->required(),
            TextInput::make('contact_email')->email(),
            TextInput::make('contact_phone'),
        ])->statePath('data');
    }

    public static function canAccess(array $parameters = []): bool
    {
        return auth()->user()?->isOwner() ?? false;
    }

    public function save(): void
    {
        tenant()->update($this->form->getState());

        Notification::make()->title('Settings saved')->success()->send();
    }
}
