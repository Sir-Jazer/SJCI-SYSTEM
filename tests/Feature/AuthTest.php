<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Pages\SetPassword;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\AuditLog;
use App\Models\Church;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private Church $main;
    private Church $outreach;
    private User $headPastor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->main = Church::create(['name' => 'Main', 'is_main' => true]);
        $this->headPastor = User::create([
            'name' => 'Head', 'email' => 'head@sjci.test', 'password' => 'password',
            'role' => UserRole::HeadPastor, 'church_id' => $this->main->id,
        ]);

        $this->outreach = Church::create(['name' => 'Outreach A']);
    }

    private function pastor(bool $mustChange = false): User
    {
        return User::create([
            'name' => 'Pastor', 'email' => 'pastor'.uniqid().'@sjci.test', 'password' => 'password',
            'role' => UserRole::OutreachPastor, 'church_id' => $this->outreach->id,
            'must_change_password' => $mustChange,
        ]);
    }

    // --- Invite-only provisioning -------------------------------------------

    public function test_public_registration_is_disabled(): void
    {
        // No ->registration() on the panel, so there is no sign-up route.
        $this->get('/admin/register')->assertNotFound();
    }

    public function test_a_new_account_is_provisioned_with_a_temporary_password(): void
    {
        Livewire::actingAs($this->headPastor)
            ->test(CreateUser::class)
            ->fillForm([
                'name' => 'New Pastor',
                'email' => 'new@sjci.test',
                'role' => UserRole::OutreachPastor->value,
                'church_id' => $this->outreach->id,
                'password' => 'temp-pass-123',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $user = User::where('email', 'new@sjci.test')->firstOrFail();
        $this->assertTrue($user->mustChangePassword());
    }

    public function test_resetting_a_password_via_edit_makes_it_temporary_again(): void
    {
        $target = $this->pastor(mustChange: false);

        Livewire::actingAs($this->headPastor)
            ->test(EditUser::class, ['record' => $target->getKey()])
            ->fillForm(['password' => 'reset-pass-123'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($target->refresh()->mustChangePassword());
    }

    public function test_editing_without_a_password_does_not_flag_a_change(): void
    {
        $target = $this->pastor(mustChange: false);

        Livewire::actingAs($this->headPastor)
            ->test(EditUser::class, ['record' => $target->getKey()])
            ->fillForm(['name' => 'Renamed'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse($target->refresh()->mustChangePassword());
    }

    // --- Forced password change ---------------------------------------------

    public function test_a_pending_change_forces_the_set_password_screen(): void
    {
        $pastor = $this->pastor(mustChange: true);

        $this->actingAs($pastor)
            ->get('/admin')
            ->assertRedirect(SetPassword::getUrl());
    }

    public function test_the_set_password_screen_itself_is_reachable_while_pending(): void
    {
        $pastor = $this->pastor(mustChange: true);

        // Must not redirect-loop onto itself.
        $this->actingAs($pastor)->get(SetPassword::getUrl())->assertOk();
    }

    public function test_a_pastor_with_their_own_password_is_not_forced(): void
    {
        $pastor = $this->pastor(mustChange: false);

        $this->actingAs($pastor)->get('/admin')->assertOk();
    }

    public function test_setting_a_new_password_clears_the_flag_and_logs_it(): void
    {
        $pastor = $this->pastor(mustChange: true);

        Livewire::actingAs($pastor)
            ->test(SetPassword::class)
            ->fillForm([
                'password' => 'my-own-strong-pass',
                'passwordConfirmation' => 'my-own-strong-pass',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $pastor->refresh();
        $this->assertFalse($pastor->mustChangePassword());
        $this->assertTrue(Hash::check('my-own-strong-pass', $pastor->password));
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $pastor->id,
            'action' => 'set_password',
        ]);
    }

    public function test_the_confirmation_must_match(): void
    {
        $pastor = $this->pastor(mustChange: true);

        Livewire::actingAs($pastor)
            ->test(SetPassword::class)
            ->fillForm([
                'password' => 'my-own-strong-pass',
                'passwordConfirmation' => 'does-not-match',
            ])
            ->call('save')
            ->assertHasFormErrors(['password']);

        $this->assertTrue($pastor->refresh()->mustChangePassword()); // unchanged
    }

    // --- Self-service email reset -------------------------------------------

    public function test_forgot_password_reset_clears_a_temporary_flag(): void
    {
        $pastor = $this->pastor(mustChange: true);

        // Choosing a password through the "Forgot password?" email is the pastor
        // setting their own — so the temporary flag is cleared.
        event(new PasswordReset($pastor));

        $this->assertFalse($pastor->refresh()->mustChangePassword());
    }
}