<?php

declare(strict_types=1);

namespace App\Filament\Platform\Resources\SubscriptionChargeResource\Pages;

use App\Enums\SubscriptionDiscountType;
use App\Enums\SubscriptionPaymentIntent;
use App\Filament\Platform\Resources\SubscriptionChargeResource;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SubscriptionChargeService;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateSubscriptionCharge extends CreateRecord
{
    protected static string $resource = SubscriptionChargeResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $actor = auth('platform')->user();
        abort_unless($actor instanceof User, 403);

        $tenant = Tenant::query()->find((int) ($data['tenant_id'] ?? 0));
        abort_unless($tenant instanceof Tenant, 403);

        $plan = Plan::query()->find((int) ($data['plan_id'] ?? 0));
        abort_unless($plan instanceof Plan, 403);

        $intent = SubscriptionPaymentIntent::tryFrom((string) ($data['intent'] ?? ''));
        abort_unless($intent instanceof SubscriptionPaymentIntent, 403);

        $rawBase = $data['base_amount'] ?? null;
        $baseAmount = $rawBase === null || $rawBase === '' ? null : (int) round(((float) $rawBase) * 100);

        $rawType = $data['discount_type'] ?? null;
        $discountType = is_string($rawType) && $rawType !== ''
            ? SubscriptionDiscountType::tryFrom($rawType)
            : null;

        $rawValue = $data['discount_value'] ?? null;
        $discountValue = match ($discountType) {
            SubscriptionDiscountType::Percentage => $rawValue === null || $rawValue === '' ? null : (int) $rawValue,
            SubscriptionDiscountType::Fixed => $rawValue === null || $rawValue === '' ? null : (int) round(((float) $rawValue) * 100),
            null => null,
        };

        $rawNote = (string) ($data['note'] ?? '');
        $note = trim($rawNote) !== '' ? $rawNote : null;

        return app(SubscriptionChargeService::class)->createCharge(
            $tenant,
            $plan,
            $intent,
            $actor,
            periodStartsAt: $this->date($data['period_starts_at'] ?? null),
            periodEndsAt: $this->date($data['period_ends_at'] ?? null),
            baseAmount: $baseAmount,
            discountType: $discountType,
            discountValue: $discountValue,
            note: $note,
        );
    }

    private function date(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse((string) $value);
    }
}
