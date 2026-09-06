<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TenantIndustry;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $name
 * @property StoreSetting|null $settings
 * @property StoreThemeSetting|null $themeSettings
 */
class Tenant extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'name', 'subdomain', 'status', 'plan',
        'currency', 'locales', 'preferred_locale', 'industry', 'contact_email', 'contact_phone',
        'deletion_requested_at', 'deletion_scheduled_at', 'deletion_reason', 'deleted_by_id', 'subdomain_original',
    ];

    protected function casts(): array
    {
        return [
            'locales' => 'array',
            'preferred_locale' => 'string',
            'industry' => TenantIndustry::class,
            'deletion_requested_at' => 'datetime',
            'deletion_scheduled_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Locales the tenant has enabled (always at least ['en']). Additive column
     * added in 2026_08_25_000001 — existing tenants were backfilled to ['en'].
     *
     * @return array<int, string>
     */
    public function enabledLocales(): array
    {
        /** @var mixed $locales */
        $locales = $this->locales;

        if (! is_array($locales) || $locales === []) {
            return ['en'];
        }

        // Normalise to lower-case, unique, and ensure 'en' is always present
        // (EN is the root URL default per Phase A decision).
        $normalised = array_values(array_unique(array_map(
            fn (mixed $v): string => strtolower((string) $v),
            $locales,
        )));

        if (! in_array('en', $normalised, true)) {
            array_unshift($normalised, 'en');
        }

        return $normalised;
    }

    public function supportsLocale(string $locale): bool
    {
        return in_array(strtolower($locale), $this->enabledLocales(), true);
    }

    public function preferredLocale(): string
    {
        $preferred = strtolower((string) ($this->preferred_locale ?? 'en'));

        return $this->supportsLocale($preferred) ? $preferred : 'en';
    }

    public function industryCode(): string
    {
        /** @var mixed $industry */
        $industry = $this->industry;

        if ($industry instanceof TenantIndustry) {
            return $industry->value;
        }

        if (is_string($industry) && $industry !== '') {
            return strtolower($industry);
        }

        return 'general';
    }

    public function domains(): HasMany
    {
        return $this->hasMany(Domain::class);
    }

    public function primaryDomain(): BelongsTo
    {
        return $this->belongsTo(Domain::class, 'primary_domain_id');
    }

    public function themeSettings(): HasOne
    {
        return $this->hasOne(StoreThemeSetting::class);
    }

    public function settings(): HasOne
    {
        return $this->hasOne(StoreSetting::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(TenantSubscription::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(TenantInvitation::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function primaryOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'primary_owner_id');
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by_id');
    }

    public function tombstones(): HasMany
    {
        return $this->hasMany(SubdomainTombstone::class);
    }

    public function isPendingDeletion(): bool
    {
        return $this->status === 'pending_deletion';
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['trial', 'active', 'pending_deletion'], true);
    }
}
