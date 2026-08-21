<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\OrderResource\Pages;

use App\Enums\OrderSource;
use App\Filament\Store\Resources\OrderResource;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use App\Services\OrderService;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('customer_id')
                ->label('Customer (optional — leave empty for guest)')
                ->options(fn (): array => Customer::query()->pluck('name', 'id')->all())
                ->searchable()
                ->live(),

            TextInput::make('guest_name')->label('Guest Name')->visible(fn ($get) => blank($get('customer_id')))->required(fn ($get) => blank($get('customer_id'))),
            TextInput::make('guest_email')->label('Guest Email')->email()->visible(fn ($get) => blank($get('customer_id')))->required(fn ($get) => blank($get('customer_id'))),
            TextInput::make('guest_phone')->label('Guest Phone')->tel()->visible(fn ($get) => blank($get('customer_id')))->required(fn ($get) => blank($get('customer_id'))),

            TextInput::make('shipping_address.recipient_name')->label('Recipient Name')->required(),
            TextInput::make('shipping_address.phone')->label('Delivery Phone')->tel(),
            TextInput::make('shipping_address.address_line_1')->label('Address Line 1')->required(),
            TextInput::make('shipping_address.city')->label('City')->required(),
            TextInput::make('shipping_address.area')->label('Area'),

            Repeater::make('lines')
                ->label('Order Lines')
                ->schema([
                    Select::make('product_variant_id')
                        ->label('Variant')
                        ->options(fn (): array => ProductVariant::query()->with('product')->where('is_active', true)->get()->mapWithKeys(fn (ProductVariant $v): array => [$v->id => $v->sku.' — '.($v->product->name ?? '').' ('.number_format($v->price / 100, 2).')'])->all())
                        ->searchable()
                        ->required(),
                    TextInput::make('quantity')->numeric()->default(1)->minValue(1)->required(),
                ])
                ->minItems(1)
                ->required()
                ->columnSpanFull(),

            Select::make('shipping_method_id')->label('Shipping Method')->options(fn (): array => ShippingMethod::query()->where('is_active', true)->pluck('name', 'id')->all()),
            Select::make('payment_method_id')->label('Payment Method')->options(fn (): array => PaymentMethod::query()->where('is_active', true)->pluck('name', 'id')->all()),

            Textarea::make('customer_note')->label('Customer Note')->rows(2),

            Toggle::make('preorder_ack')->label('Pre-order acknowledgement')->helperText('Check if order contains pre-order items — customer acknowledges ETA is estimate.')->visible(fn ($get): bool => $this->hasPreorderLines($get('lines'))),
        ]);
    }

    private function hasPreorderLines(?array $lines): bool
    {
        if (! $lines) {
            return false;
        }

        $ids = collect($lines)->pluck('product_variant_id')->filter()->all();

        if ($ids === []) {
            return false;
        }

        return ProductVariant::query()->whereIn('id', $ids)->where('fulfillment_strategy', 'preorder')->exists();
    }

    protected function handleRecordCreation(array $data): Model
    {
        $lines = $data['lines'] ?? [];
        $filtered = collect($lines)->map(fn ($l): array => ['product_variant_id' => (int) $l['product_variant_id'], 'quantity' => (int) $l['quantity']])->all();

        $hasPreorder = ProductVariant::query()->whereIn('id', collect($filtered)->pluck('product_variant_id'))->where('fulfillment_strategy', 'preorder')->exists();

        if ($hasPreorder && empty($data['preorder_ack'])) {
            throw ValidationException::withMessages(['preorder_ack' => 'Pre-order acknowledgement is required when order contains pre-order items.']);
        }

        $orderData = [
            'customer_id' => $data['customer_id'] ?? null,
            'guest_name' => $data['guest_name'] ?? null,
            'guest_email' => $data['guest_email'] ?? null,
            'guest_phone' => $data['guest_phone'] ?? null,
            'shipping_address' => $data['shipping_address'] ?? null,
            'payment_method_id' => $data['payment_method_id'] ?? null,
            'shipping_method_id' => $data['shipping_method_id'] ?? null,
            'customer_note' => $data['customer_note'] ?? null,
            'preorder_ack_at' => $hasPreorder && ! empty($data['preorder_ack']) ? now() : null,
            'tenant_id' => tenant()?->id,
        ];

        if (! empty($data['customer_id'])) {
            $customer = Customer::query()->findOrFail($data['customer_id']);
            $orderData['customer_id'] = $customer->id;
            unset($orderData['guest_name'], $orderData['guest_email'], $orderData['guest_phone']);
        }

        return app(OrderService::class)->createFromAdmin($filtered, $orderData, OrderSource::Admin);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
