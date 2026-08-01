<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\Church;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Prepares an otherwise-empty database for real use: ensures the main church and
 * a single Head Pastor account exist, so someone can log in. Idempotent — safe to
 * run on every boot. Reads optional env vars so credentials aren't hard-coded:
 *
 *   CHURCH_NAME     (default "Shepherd Jubillee Church Inc.")
 *   ADMIN_NAME      (default "Head Pastor")
 *   ADMIN_EMAIL     (default "headpastor@sjci.test")
 *   ADMIN_PASSWORD  (default "password" — and then a change is forced on first login)
 */
class BootstrapAdmin extends Command
{
    protected $signature = 'sjci:bootstrap-admin';

    protected $description = 'Ensure a main church and a Head Pastor account exist (idempotent).';

    public function handle(): int
    {
        $main = Church::where('is_main', true)->first();

        if (! $main) {
            $main = Church::create([
                'name' => env('CHURCH_NAME', 'Shepherd Jubillee Church Inc.'),
                'is_main' => true,
            ]);
            $this->info("Created main church: {$main->name}");
        }

        $email = env('ADMIN_EMAIL', 'headpastor@sjci.test');

        if (User::where('email', $email)->exists()) {
            $this->info("Head Pastor already exists: {$email}");

            return self::SUCCESS;
        }

        // If an explicit password was provided, trust it (no forced change). If we
        // fell back to the default, force the pastor to set their own on first login.
        $password = env('ADMIN_PASSWORD');

        $user = User::create([
            'name' => env('ADMIN_NAME', 'Head Pastor'),
            'email' => $email,
            'password' => $password ?? 'password',
            'role' => UserRole::HeadPastor,
            'church_id' => $main->id,
            'must_change_password' => $password === null,
        ]);

        $main->update(['pastor_id' => $user->id]);

        $this->info("Created Head Pastor: {$email}");

        return self::SUCCESS;
    }
}