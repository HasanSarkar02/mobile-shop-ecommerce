<?php

declare(strict_types=1);

namespace App\Enums;

enum OrderEventType: string
{
    case StatusChanged = 'status_changed';
    case PaymentRecorded = 'payment_recorded';
    case FulfillmentUpdated = 'fulfillment_updated';
    case NoteAdded = 'note_added';
    case ContactUpdated = 'contact_updated';
    case AddressUpdated = 'address_updated';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::StatusChanged => 'Status Changed',
            self::PaymentRecorded => 'Payment Recorded',
            self::FulfillmentUpdated => 'Fulfillment Updated',
            self::NoteAdded => 'Note Added',
            self::ContactUpdated => 'Contact Corrected',
            self::AddressUpdated => 'Address Corrected',
            self::Custom => 'Event',
        };
    }
}
