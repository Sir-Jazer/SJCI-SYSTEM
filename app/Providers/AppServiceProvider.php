<?php

namespace App\Providers;

use App\Models\AuditLog;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Keep the audit trail complete for sign-in / sign-out.
        Event::listen(Login::class, function (Login $event): void {
            AuditLog::create([
                'user_id' => $event->user->getAuthIdentifier(),
                'action' => 'login',
            ]);
        });

        Event::listen(Logout::class, function (Logout $event): void {
            if ($event->user === null) {
                return;
            }

            AuditLog::create([
                'user_id' => $event->user->getAuthIdentifier(),
                'action' => 'logout',
            ]);
        });
    }
}