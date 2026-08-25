<?php

declare(strict_types=1);

namespace App\Filament\Store\Pages;

use BackedEnum;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
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
        $data = tenant()->only(['name', 'currency', 'contact_email', 'contact_phone', 'locales', 'preferred_locale']);
        // Ensure at least EN is present for display
        $data['locales'] = tenant()->enabledLocales();
        $data['preferred_locale'] = tenant()->preferredLocale();

        $this->form->fill($data);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required(),
            Select::make('currency')->options(['BDT' => 'BDT', 'USD' => 'USD'])->required(),
            TextInput::make('contact_email')->email(),
            TextInput::make('contact_phone'),

            Section::make('Languages')
                ->description('Enable Bengali to serve /bn/ URLs. English is always enabled.')
                ->schema([
                    CheckboxList::make('locales')
                        ->label('Enabled languages')
                        ->options(['en' => 'English (default)', 'bn' => 'Bengali — বাংলা'])
                        ->required()
                        ->columns(2)
                        ->helperText('English stays at /  ·  Bengali lives at /bn/. Western numerals are kept for both.')
                        ->live()
                        ->afterStateHydrated(function ($component, $state): void {
                            $state = array_values(array_unique(array_merge((array) $state, ['en'])));
                            $component->state($state);
                        })
                        ->dehydrateStateUsing(fn ($state) => array_values(array_unique(array_merge((array) $state, ['en']))))
                        ->rules(['array', 'min:1']),

                    Select::make('preferred_locale')
                        ->label('Default language')
                        ->options(function (callable $get): array {
                            $locales = $get('locales') ?? ['en'];
                            $options = [];
                            if (in_array('en', (array) $locales, true)) {
                                $options['en'] = 'English';
                            }
                            if (in_array('bn', (array) $locales, true)) {
                                $options['bn'] = 'Bengali — বাংলা';
                            }

                            return $options !== [] ? $options : ['en' => 'English'];
                        })
                        ->required()
                        ->live()
                        ->helperText('Used when visitor has no /bn/ prefix and no saved choice.'),
                ]),
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
