<?php

declare(strict_types=1);

namespace App\Filament\Platform\Resources\SubscriptionPaymentResource\Pages;

use App\Enums\SubscriptionPaymentIntent;
use App\Filament\Platform\Resources\SubscriptionPaymentResource;
use App\Models\SubscriptionCharge;
use App\Models\User;
use App\Services\SubscriptionPaymentService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateSubscriptionPayment extends CreateRecord
{
    protected static string $resource = SubscriptionPaymentResource::class;

    public function mount(): void
    {
        parent::mount();

        $chargeId = request()->query('charge');

        if ($chargeId === null || $chargeId === '') {
            return;
        }

        $charge = SubscriptionCharge::query()->find((int) $chargeId);

        if ($charge === null) {
            return;
        }

        $rawIntent = $charge->getAttribute('intent');
        $intent = $rawIntent instanceof SubscriptionPaymentIntent ? $rawIntent->value : (string) $rawIntent;

        $this->form->fill([
            'subscription_charge_id' => (string) $charge->id,
            'intent' => $intent,
            'amount' => $charge->outstandingAmount() / 100,
        ]);
    }

    protected function handleRecordCreation(array $data): Model
    {
        $actor = auth('platform')->user();
        abort_unless($actor instanceof User, 403);

        $charge = SubscriptionCharge::query()->find((int) ($data['subscription_charge_id'] ?? 0));
        abort_unless($charge instanceof SubscriptionCharge, 403);

        $rawDays = $data['extension_days'] ?? null;
        $extensionDays = $rawDays === null || $rawDays === '' ? null : (int) $rawDays;

        $rawNote = (string) ($data['note'] ?? '');
        $note = trim($rawNote) !== '' ? $rawNote : null;

        $rawAmount = $data['amount'] ?? null;
        $amount = $rawAmount === null || $rawAmount === '' ? null : (int) round(((float) $rawAmount) * 100);

        return app(SubscriptionPaymentService::class)->record(
            $charge,
            (string) ($data['reference'] ?? ''),
            actor: $actor,
            paymentMethod: (string) ($data['payment_method'] ?? 'other'),
            amount: $amount,
            extensionDays: $extensionDays,
            note: $note,
        );
    }
}
