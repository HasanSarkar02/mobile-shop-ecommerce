<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Order Confirmed</title>
</head>
<body style="margin:0; padding:0; background:#f4f6f8; font-family: 'Instrument Sans','Hind Siliguri', Arial, sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f8; padding: 24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px; width:100%; background:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #e5e7eb;">
                    <tr>
                        <td style="background:#16a34a; padding: 20px 24px; text-align:center;">
                            <h1 style="margin:0; color:#ffffff; font-size:20px; font-weight:700; letter-spacing:0.3px;">Order Confirmed</h1>
                            <p style="margin:6px 0 0; color:#dcfce7; font-size:13px;">Thank you for shopping with {{ $storeName }}</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 24px;">
                            <p style="margin:0 0 8px; font-size:14px; color:#17202b;">Hi {{ $order->customerDisplayName() }},</p>
                            <p style="margin:0 0 16px; font-size:13px; color:#475569; line-height:1.6;">
                                Your order <strong style="color:#17202b;">{{ $order->getAttribute('order_number') }}</strong>
                                @if ($order->getAttribute('invoice_number'))
                                    (Invoice {{ $order->getAttribute('invoice_number') }})
                                @endif
                                has been confirmed. We are preparing it for shipment.
                            </p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; margin-bottom:16px;">
                                <tr>
                                    <td style="padding:12px 16px; font-size:13px; color:#475569;">
                                        <strong style="color:#17202b;">Order:</strong> {{ $order->getAttribute('order_number') }}<br>
                                        <strong style="color:#17202b;">Date:</strong> {{ $order->getAttribute('placed_at')?->format('F j, Y g:i A') ?? '—' }}<br>
                                        <strong style="color:#17202b;">Total:</strong> {{ money((int) $order->getAttribute('grand_total'), (string) $order->getAttribute('currency_code')) }}<br>
                                        <strong style="color:#17202b;">Status:</strong> {{ $order->getAttribute('status')?->label() ?? $order->getAttribute('status') }}
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0 0 16px; font-size:12px; color:#64748b; line-height:1.6;">
                                Your invoice is attached as PDF to this email. You can also download it anytime from your account order history.
                            </p>

                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin: 0 0 16px;">
                                <tr>
                                    <td style="background:#17202b; border-radius:6px; text-align:center;">
                                        <a href="{{ tenant() ? 'https://'.tenant()->subdomain.'.'.config('tenancy.central_domain').'/account/orders/'.$order->getAttribute('order_number') : url('/account/orders/'.$order->getAttribute('order_number')) }}" style="display:inline-block; padding:10px 18px; color:#ffffff; text-decoration:none; font-size:13px; font-weight:600;">View Order</a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0; font-size:12px; color:#94a3b8; line-height:1.5;">
                                If you have any questions, reply to this email or contact {{ $storeName }} support.<br>
                                This is an automated message — please do not reply directly to this address if it is no-reply.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background:#f8fafc; padding:12px 24px; text-align:center; border-top:1px solid #e2e8f0;">
                            <p style="margin:0; font-size:11px; color:#94a3b8;">{{ $storeName }} · {{ tenant()?->getAttribute('contact_email') ?? config('mail.from.address') }}</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
