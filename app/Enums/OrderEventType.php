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
    case ItemAdded = 'item_added';
    case ItemUpdated = 'item_updated';
    case ItemRemoved = 'item_removed';
    case PriceAdjusted = 'price_adjusted';
    case DiscountAdjusted = 'discount_adjusted';
    case ShippingUpdated = 'shipping_updated';
    case FinancialAdjustmentRequired = 'financial_adjustment_required';
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
            self::ItemAdded => 'Item Added',
            self::ItemUpdated => 'Item Updated',
            self::ItemRemoved => 'Item Removed',
            self::PriceAdjusted => 'Unit Price Adjusted',
            self::DiscountAdjusted => 'Discount Adjusted',
            self::ShippingUpdated => 'Shipping Updated',
            self::FinancialAdjustmentRequired => 'Refund Required',
            self::Custom => 'Event',
        };
    }
}
