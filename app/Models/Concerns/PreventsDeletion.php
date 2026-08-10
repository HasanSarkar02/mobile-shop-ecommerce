<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Exceptions\RecordDeletionNotAllowedException;

/**
 * Blocks Model::delete() (and therefore ::destroy(), DeleteAction, bulk
 * deletes issued through Eloquent, etc.) for models that represent immutable
 * financial/audit history. Apply only to models with no legitimate delete
 * pathway in the existing codebase — do not apply this to a model that has
 * an intentional delete flow (e.g. CouponRedemption, which
 * CouponService::releaseForOrder() deletes when an order is cancelled).
 *
 * A DB-level cascade delete (e.g. deleting a Tenant row cascades to its
 * Orders via the tenant_id foreign key) happens entirely inside the database
 * engine and never fires this model's `deleting` event, so whole-tenant
 * offboarding is unaffected by this guard.
 */
trait PreventsDeletion
{
    public static function bootPreventsDeletion(): void
    {
        static::deleting(function ($model): void {
            throw new RecordDeletionNotAllowedException(
                static::class.' records are immutable financial/audit history and cannot be deleted through the application.'
            );
        });
    }
}