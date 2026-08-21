<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Thrown by App\Models\Concerns\PreventsDeletion when application code
 * attempts to delete a model that represents immutable financial/audit
 * history (Order, OrderPayment, StockMovement). These records must not be
 * casually deletable — through the admin UI, a future bulk action, or direct
 * Eloquent code — because losing them destroys financial/audit integrity.
 *
 * This does not affect whole-tenant offboarding: deleting a Tenant row still
 * cascades at the database level via each table's tenant_id foreign key,
 * which bypasses Eloquent model events entirely and is unaffected by this
 * guard.
 */
class RecordDeletionNotAllowedException extends \RuntimeException {}
