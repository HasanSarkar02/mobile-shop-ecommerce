<?php

declare(strict_types=1);

namespace App\Filament\Store\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Restricts an entire Filament Store resource (list, view, create, edit,
 * delete) to owner-role staff. Structural/configuration resources (staff
 * accounts, payment gateway setup, store locations) use this; day-to-day
 * operational resources (orders, products, customers) intentionally do not,
 * since routine staff work is the point of having staff accounts at all.
 */
trait RestrictsToOwner
{
    public static function canViewAny(): bool
    {
        return auth()->user()?->isOwner() ?? false;
    }

    public static function canView(Model $record): bool
    {
        return auth()->user()?->isOwner() ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->isOwner() ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->isOwner() ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->isOwner() ?? false;
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()?->isOwner() ?? false;
    }
}