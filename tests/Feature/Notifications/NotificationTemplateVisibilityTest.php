<?php

declare(strict_types=1);

use App\Filament\Store\Resources\NotificationTemplateResource;
use App\Filament\Store\Resources\NotificationTemplateResource\Pages\CreateNotificationTemplate;
use App\Filament\Store\Resources\NotificationTemplateResource\Pages\ListNotificationTemplates;
use App\Models\NotificationTemplate;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('store');
});

function ntvTenant(): Tenant
{
    return actingAsTenant();
}

function ntvOwner(Tenant $tenant): User
{
    return User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => 'owner',
        'is_active' => true,
    ]);
}

it('marks subscription reminder templates as platform-managed on tenant creation', function (): void {
    ntvTenant();

    $templates = NotificationTemplate::query()->get();

    expect($templates)->toHaveCount(11);

    $platformManaged = $templates->filter(
        fn (NotificationTemplate $template): bool => $template->is_platform_managed,
    );
    $storeEditable = $templates->filter(
        fn (NotificationTemplate $template): bool => ! $template->is_platform_managed,
    );

    expect($platformManaged)->toHaveCount(5)
        ->and($platformManaged->pluck('event_key')->sort()->values()->all())->toBe([
            'subscription.charge.reminder.1d',
            'subscription.charge.reminder.3d',
            'subscription.charge.reminder.7d',
            'subscription.charge.reminder.due',
            'subscription.charge.reminder.overdue',
        ])
        ->and($storeEditable->pluck('event_key')->contains('order.placed'))->toBeTrue()
        ->and($storeEditable->pluck('event_key')->contains('payment.recorded'))->toBeTrue();
});

it('excludes platform-managed templates from the store resource query', function (): void {
    $tenant = ntvTenant();
    Auth::login(ntvOwner($tenant));

    $platformTemplate = NotificationTemplate::query()->firstWhere('event_key', 'subscription.charge.reminder.due');
    $storeTemplate = NotificationTemplate::query()->firstWhere('event_key', 'order.placed');

    $visibleIds = NotificationTemplateResource::getEloquentQuery()->pluck('id');

    expect($visibleIds)->not->toContain($platformTemplate->id)
        ->and($visibleIds)->toContain($storeTemplate->id);

    Auth::logout();
});

it('lists only store-editable templates in the store panel', function (): void {
    $tenant = ntvTenant();
    Auth::login(ntvOwner($tenant));

    $storeTemplate = NotificationTemplate::query()->firstWhere('event_key', 'order.placed');
    $platformTemplate = NotificationTemplate::query()->firstWhere('event_key', 'subscription.charge.reminder.due');

    Livewire::test(ListNotificationTemplates::class)
        ->assertCanSeeTableRecords([$storeTemplate])
        ->assertCanNotSeeTableRecords([$platformTemplate]);

    Auth::logout();
});

it('blocks editing and deleting platform-managed templates', function (): void {
    $tenant = ntvTenant();
    Auth::login(ntvOwner($tenant));

    $platformTemplate = NotificationTemplate::query()->firstWhere('event_key', 'subscription.charge.reminder.due');
    $storeTemplate = NotificationTemplate::query()->firstWhere('event_key', 'order.placed');

    expect(NotificationTemplateResource::canEdit($platformTemplate))->toBeFalse()
        ->and(NotificationTemplateResource::canDelete($platformTemplate))->toBeFalse()
        ->and(NotificationTemplateResource::canEdit($storeTemplate))->toBeTrue()
        ->and(NotificationTemplateResource::canDelete($storeTemplate))->toBeTrue();

    Auth::logout();
});

it('rejects creating a template with a platform-managed event key', function (): void {
    $tenant = ntvTenant();
    Auth::login(ntvOwner($tenant));

    Livewire::test(CreateNotificationTemplate::class)
        ->fillForm([
            'event_key' => 'subscription.charge.reminder.due',
            'channel' => 'email',
            'subject' => 'Overridden by tenant',
            'body' => 'Tenant-controlled reminder body.',
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasFormErrors(['event_key']);

    expect(NotificationTemplate::query()
        ->where('event_key', 'subscription.charge.reminder.due')
        ->where('subject', 'Overridden by tenant')
        ->doesntExist())->toBeTrue();

    Auth::logout();
});

it('allows creating a store template with a normal event key', function (): void {
    $tenant = ntvTenant();
    Auth::login(ntvOwner($tenant));

    Livewire::test(CreateNotificationTemplate::class)
        ->fillForm([
            'event_key' => 'order.status_changed.custom',
            'channel' => 'sms',
            'subject' => null,
            'body' => 'Custom store notification body.',
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(NotificationTemplate::query()
        ->where('event_key', 'order.status_changed.custom')
        ->exists())->toBeTrue();

    Auth::logout();
});
