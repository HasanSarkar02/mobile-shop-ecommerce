<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Order;
use App\Models\Tenant;
use App\Services\InvoiceService;
use App\Support\Tenancy\Tenancy;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderConfirmationMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Order $order,
        public ?string $pdfBytes = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Order Confirmed — '.$this->order->getAttribute('order_number'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.orders.confirmation',
            with: [
                'order' => $this->order,
                'storeName' => $this->order->tenant?->getAttribute('name') ?? tenant()?->getAttribute('name') ?? config('app.name'),
            ],
        );
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        $bytes = $this->pdfBytes;

        if ($bytes === null) {
            try {
                $tenant = $this->order->tenant;
                if (! $tenant instanceof Tenant) {
                    $tenant = tenant();
                }
                if ($tenant instanceof Tenant) {
                    app(Tenancy::class)->set($tenant);
                }
                $bytes = app(InvoiceService::class)->pdfBytes($this->order);
            } catch (\Throwable $e) {
                report($e);

                return [];
            }
        }

        $filename = app(InvoiceService::class)->filename($this->order);

        return [
            Attachment::fromData(fn () => $bytes, $filename)
                ->withMime('application/pdf'),
        ];
    }
}
