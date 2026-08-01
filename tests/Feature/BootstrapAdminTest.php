<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Church;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BootstrapAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_main_church_and_head_pastor_on_an_empty_database(): void
    {
        $this->artisan('sjci:bootstrap-admin')->assertSuccessful();

        $main = Church::where('is_main', true)->firstOrFail();
        $head = User::where('role', UserRole::HeadPastor)->firstOrFail();

        $this->assertSame($main->id, $head->church_id);
        $this->assertSame($head->id, $main->fresh()->pastor_id);
        // No data seeded beyond the bootstrap account.
        $this->assertSame(1, Church::count());
        $this->assertSame(1, User::count());
    }

    public function test_it_is_idempotent(): void
    {
        $this->artisan('sjci:bootstrap-admin')->assertSuccessful();
        $this->artisan('sjci:bootstrap-admin')->assertSuccessful();

        $this->assertSame(1, Church::count());
        $this->assertSame(1, User::count());
    }

    public function test_a_provided_password_is_not_forced_to_change(): void
    {
        putenv('ADMIN_EMAIL=real@church.org');
        putenv('ADMIN_PASSWORD=chosen-secret');

        try {
            $this->artisan('sjci:bootstrap-admin')->assertSuccessful();
        } finally {
            putenv('ADMIN_EMAIL');
            putenv('ADMIN_PASSWORD');
        }

        $user = User::where('email', 'real@church.org')->firstOrFail();
        $this->assertFalse($user->mustChangePassword());
    }
}