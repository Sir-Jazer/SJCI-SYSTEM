<?php

namespace Tests\Feature;

use App\Enums\CollectionStatus;
use App\Enums\CollectionType;
use App\Enums\UserRole;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\Church;
use App\Models\Collection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class AdminResourcesTest extends TestCase
{
    use RefreshDatabase;

    private User $headPastor;
    private Church $main;
    private Church $outreachA;
    private User $pastorA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->main = Church::create(['name' => 'Main', 'is_main' => true]);
        $this->headPastor = User::create([
            'name' => 'Head', 'email' => 'head@sjci.test', 'password' => 'password',
            'role' => UserRole::HeadPastor, 'church_id' => $this->main->id,
        ]);

        $this->outreachA = Church::create(['name' => 'Outreach A']);
        $this->pastorA = User::create([
            'name' => 'Pastor A', 'email' => 'a@sjci.test', 'password' => 'password',
            'role' => UserRole::OutreachPastor, 'church_id' => $this->outreachA->id,
        ]);
    }

    public function test_admin_resources_are_head_pastor_only(): void
    {
        $this->assertTrue($this->headPastor->can('viewAny', User::class));
        $this->assertTrue($this->headPastor->can('viewAny', Church::class));

        $this->assertFalse($this->pastorA->can('viewAny', User::class));
        $this->assertFalse($this->pastorA->can('viewAny', Church::class));
    }

    public function test_creating_a_user_hashes_the_password(): void
    {
        Livewire::actingAs($this->headPastor)
            ->test(CreateUser::class)
            ->fillForm([
                'name' => 'New Pastor',
                'email' => 'new@sjci.test',
                'role' => UserRole::OutreachPastor->value,
                'church_id' => $this->outreachA->id,
                'password' => 'secret-pass',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $user = User::where('email', 'new@sjci.test')->first();
        $this->assertNotNull($user);
        $this->assertNotSame('secret-pass', $user->password); // stored hashed
        $this->assertTrue(Hash::check('secret-pass', $user->password));
    }

    public function test_editing_a_user_without_a_password_keeps_the_current_one(): void
    {
        $target = User::create([
            'name' => 'Keep Me', 'email' => 'keep@sjci.test', 'password' => 'original-pass',
            'role' => UserRole::OutreachPastor, 'church_id' => $this->outreachA->id,
        ]);

        Livewire::actingAs($this->headPastor)
            ->test(EditUser::class, ['record' => $target->getKey()])
            ->fillForm(['name' => 'Renamed'])
            ->call('save')
            ->assertHasNoFormErrors();

        $target->refresh();
        $this->assertSame('Renamed', $target->name);
        $this->assertTrue(Hash::check('original-pass', $target->password)); // not re-hashed/blanked
    }

    public function test_church_delete_is_guarded(): void
    {
        // Main church can never be deleted.
        $this->assertFalse($this->headPastor->can('delete', $this->main));

        // An outreach holding financial records can't be deleted.
        Collection::create([
            'church_id' => $this->outreachA->id, 'type' => CollectionType::Offering,
            'week_of' => now(), 'amount' => 100, 'status' => CollectionStatus::Locked,
        ]);
        $this->assertFalse($this->headPastor->can('delete', $this->outreachA->fresh()));

        // An empty outreach can.
        $empty = Church::create(['name' => 'Empty Outreach']);
        $this->assertTrue($this->headPastor->can('delete', $empty));
    }

    public function test_head_pastor_cannot_delete_their_own_account(): void
    {
        $this->assertFalse($this->headPastor->can('delete', $this->headPastor));
        $this->assertTrue($this->headPastor->can('delete', $this->pastorA));
    }
}