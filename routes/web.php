<?php

use App\Http\Controllers\Platform\PlatformAdminInvitationController;
use App\Http\Middleware\ResolveSupportSession;
use App\Livewire\TenantSignupForm;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::domain(config('tenancy.central_domain'))
    ->middleware(['central', 'throttle:20,1'])
    ->group(function (): void {
        Route::get('/', fn () => view('platform.home'))->name('platform.home');
        Route::get('/signup', TenantSignupForm::class)->name('platform.signup');
        Route::get('/signup/pending', fn () => view('platform.signup-pending'))->name('platform.signup.pending');
        Route::get('/platform-admin-invitation/{token}', [PlatformAdminInvitationController::class, 'show'])->name('platform.admin-invitation.show');
        Route::post('/platform-admin-invitation/{token}', [PlatformAdminInvitationController::class, 'accept'])
            ->middleware('throttle:5,1')
            ->name('platform.admin-invitation.accept');
    });

Route::domain('www.'.config('tenancy.central_domain'))
    ->middleware(['central', 'throttle:20,1'])
    ->group(function (): void {
        Route::get('/', fn () => view('platform.home'))->name('platform.home');
        Route::get('/signup', TenantSignupForm::class)->name('platform.signup');
        Route::get('/signup/pending', fn () => view('platform.signup-pending'))->name('platform.signup.pending');
        Route::get('/platform-admin-invitation/{token}', [PlatformAdminInvitationController::class, 'show'])->name('platform.admin-invitation.show');
        Route::post('/platform-admin-invitation/{token}', [PlatformAdminInvitationController::class, 'accept'])
            ->middleware('throttle:5,1')
            ->name('platform.admin-invitation.accept');
    });

Route::domain(config('tenancy.central_domain'))
    ->middleware(['throttle:20,1'])
    ->group(function (): void {
        Route::post('/support/exit', function (): RedirectResponse {
            $payload = session(ResolveSupportSession::SESSION_KEY);

            if (is_array($payload)) {
                $tenant = Tenant::query()->find((int) ($payload['tenant_id'] ?? 0));

                if ($tenant instanceof Tenant) {
                    $actor = auth('platform')->user();

                    activity('support')
                        ->performedOn($tenant)
                        ->causedBy($actor instanceof User ? $actor : null)
                        ->event('support.mode_ended')
                        ->withProperties([
                            'support_session_id' => (string) ($payload['id'] ?? ''),
                            'tenant_id' => (int) ($payload['tenant_id'] ?? 0),
                            'entered_by_user_id' => (int) ($payload['entered_by_user_id'] ?? 0),
                            'exit_type' => 'manual',
                        ])
                        ->log('support.mode_ended');
                }
            }

            session()->forget(ResolveSupportSession::SESSION_KEY);

            return redirect('/platform');
        })->name('support.exit');

        Route::get('/support/{tenant}/admin', function (Request $request): RedirectResponse {
            abort_unless((int) session('support_mode.tenant_id') === (int) $request->route('tenant'), 403);

            return redirect('/admin');
        })->name('support.admin');

        Route::get('/support/{tenant}/admin/{path?}', function (Request $request, string $path = ''): RedirectResponse {
            abort_unless((int) session('support_mode.tenant_id') === (int) $request->route('tenant'), 403);
            $query = $request->getQueryString();

            return redirect('/admin'.($path !== '' ? '/'.$path : '').($query !== null ? '?'.$query : ''));
        })->where('path', '.*')->name('support.admin.path');
    });

Route::middleware('tenant')->group(function (): void {
    require __DIR__.'/tenant.php';
});
