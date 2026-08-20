<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Rules\ValidSubdomain;
use App\Services\TenantRegistrationService;
use App\Support\Tenancy\TenantUrlGenerator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('platform.layout')]
class TenantSignupForm extends Component
{
    public string $business_name = '';

    public string $subdomain = '';

    public string $owner_name = '';

    public string $owner_email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function updatedBusinessName(): void
    {
        if (! $this->subdomain) {
            $this->subdomain = Str::slug($this->business_name);
        }
    }

    public function updatedSubdomain(): void
    {
        $this->subdomain = Str::slug($this->subdomain);
        $this->validateOnly('subdomain');
    }

    protected function rules(): array
    {
        return [
            'business_name' => ['required', 'string', 'max:255'],
            'subdomain' => ['required', 'string', new ValidSubdomain],
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_email' => [
                'required',
                'email',
                Rule::unique('users')->where(fn (Builder $query) => $query->whereNull('tenant_id')),
            ],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function register(TenantRegistrationService $registration, TenantUrlGenerator $urls)
    {
        $this->validate();

        [$tenant, $owner] = $registration->register(
            $this->business_name,
            $this->subdomain,
            $this->owner_name,
            $this->owner_email,
            $this->password,
        );

        $signedPath = URL::temporarySignedRoute(
            'storefront.auto-login',
            now()->addMinutes(5),
            ['user' => $owner->id],
            absolute: false,
        );

        return redirect()->away($urls->canonicalPath($tenant, $signedPath));
    }

    public function render()
    {
        return view('livewire.tenant-signup-form');
    }
}
