<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Models\AuditLog;
use App\Models\Church;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AuditTrailTest extends TestCase
{
    use RefreshDatabase;

    private User $headPastor;
    private User $pastorA;

    protected function setUp(): void
    {
        parent::setUp();

        $main = Church::create(['name' => 'Main', 'is_main' => true]);
        $this->headPastor = User::create([
            'name' => 'Head', 'email' => 'head@sjci.test', 'password' => 'password',
            'role' => UserRole::HeadPastor, 'church_id' => $main->id,
        ]);

        $outreach = Church::create(['name' => 'Outreach A']);
        $this->pastorA = User::create([
            'name' => 'Pastor A', 'email' => 'a@sjci.test', 'password' => 'password',
            'role' => UserRole::OutreachPastor, 'church_id' => $outreach->id,
        ]);
    }

    public function test_login_and_logout_are_recorded(): void
    {
        auth()->login($this->pastorA);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'login',
            'user_id' => $this->pastorA->id,
        ]);

        auth()->logout();
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'logout',
            'user_id' => $this->pastorA->id,
        ]);
    }

    public function test_audit_trail_is_head_pastor_only_and_read_only(): void
    {
        $this->assertTrue($this->headPastor->can('viewAny', AuditLog::class));
        $this->assertFalse($this->pastorA->can('viewAny', AuditLog::class));

        $log = AuditLog::create(['user_id' => $this->headPastor->id, 'action' => 'approve']);
        $this->assertFalse($this->headPastor->can('create', AuditLog::class));
        $this->assertFalse($this->headPastor->can('update', $log));
        $this->assertFalse($this->headPastor->can('delete', $log));
    }

    public function test_trail_renders_for_head_pastor(): void
    {
        $log = AuditLog::create([
            'user_id' => $this->headPastor->id,
            'action' => 'approve',
            'auditable_type' => \App\Models\Collection::class,
            'auditable_id' => 1,
            'details' => ['amount' => '1000.00'],
        ]);

        Livewire::actingAs($this->headPastor)
            ->test(ListAuditLogs::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$log]);
    }
}