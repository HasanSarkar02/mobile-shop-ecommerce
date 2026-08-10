<?php

// app/Http/Controllers/Storefront/NewsletterController.php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NewsletterController extends Controller
{
    public function subscribe(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        NewsletterSubscriber::query()->firstOrCreate(
            ['email' => $data['email']],
            ['subscribed_at' => now()],
        );

        return back()->with('status', "You're subscribed! We'll keep you posted on new arrivals and deals.");
    }
}