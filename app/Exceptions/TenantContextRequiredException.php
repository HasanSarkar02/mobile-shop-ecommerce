<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Thrown by App\Models\Concerns\BelongsToTenant when a tenant-scoped model is
 * queried without a resolved tenant() context. This is a fail-closed guard:
 * previously, an unresolved tenant silently produced an UNSCOPED query across
 * every tenant's data. That is never correct for an ordinary request, job, or
 * command, so we throw instead of guessing.
 *
 * Legitimate cross-tenant reads (e.g. the Platform panel listing every
 * tenant's PlanChangeRequest) must opt in explicitly per-query with
 * `Model::query()->withoutGlobalScope('tenant')` rather than relying on this
 * exception being suppressed.
 */
class TenantContextRequiredException extends \RuntimeException {}
