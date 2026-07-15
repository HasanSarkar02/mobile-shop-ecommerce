<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use Illuminate\Support\Facades\Mail;

class EmailChannelDriver implements NotificationChannelDriver
{
    public function send(string $address, ?string $subject, string $body): void
    {
        Mail::raw($body, function ($message) use ($address, $subject): void {
            $message->to($address)->subject($subject ?? config('app.name'));
        });
    }
}