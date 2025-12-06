<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\TwoFactorAuthenticationRequest;
// Not using the Controllers\HasMiddleware contract here because Laravel's
// base Controller already defines middleware() as an instance method with a
// different signature. We'll register conditional middleware in the
// controller constructor instead.
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

class TwoFactorAuthenticationController extends Controller
{
    /**
     * Register conditional middleware in the controller's constructor so we
     * avoid conflicting with the base Controller::middleware signature.
     */
    public function __construct()
    {
        if (Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword')) {
            // Apply the `password.confirm` middleware only to the `show` method.
            $this->middleware('password.confirm')->only('show');
        }
    }

    /**
     * Show the user's two-factor authentication settings page.
     */
    public function show(TwoFactorAuthenticationRequest $request): Response
    {
        $request->ensureStateIsValid();

        return Inertia::render('settings/TwoFactor', [
            'twoFactorEnabled' => $request->user()->hasEnabledTwoFactorAuthentication(),
            'requiresConfirmation' => Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm'),
        ]);
    }
}
