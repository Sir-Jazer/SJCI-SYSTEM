<?php

namespace App\Http\Middleware;

use App\Filament\Pages\SetPassword;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Invite-only accounts are given a temporary password by the Head Pastor. Until
 * the pastor replaces it, every panel request is redirected to the set-password
 * page — so a temporary password can never be used to actually work in the app.
 */
class ForcePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && $user->mustChangePassword() && ! $this->isAllowed($request)) {
            return redirect()->to(SetPassword::getUrl());
        }

        return $next($request);
    }

    /** Routes reachable while a password change is still pending. */
    private function isAllowed(Request $request): bool
    {
        $name = $request->route()?->getName();

        return in_array($name, [
            'filament.admin.pages.set-password', // the page itself (avoid a redirect loop)
            'filament.admin.auth.logout',        // let them log back out
        ], true);
    }
}