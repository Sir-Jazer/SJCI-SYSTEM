<?php

namespace Tests\Feature;

use App\Enums\CollectionStatus;
use App\Enums\CollectionType;
use App\Enums\RemittanceStatus;
use App\Enums\UserRole;
use App\Filament\Resources\Remittances\Pages\ListRemittances;
use App\Models\Church;
use App\Models\Collection;
use App\Models\Remittance;
use App\Models\User;
use App\Support\DashboardStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RemittanceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private const YEAR = 2026;
    private const QUARTER = 3; // Jul–Sep

    private User $headPastor;
    private Church $main;
    private Church $outreachA;
    private Church $outreachB;
    private User $pastorA;
    private User $pastorB;

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

        $this->outreachB = Church::create(['name' => 'Outreach B']);
        $this->pastorB = User::create([
            'name' => 'Pastor B', 'email' => 'b@sjci.test', 'password' => 'password',
            'role' => UserRole::OutreachPastor, 'church_id' => $this->outreachB->id,
        ]);
    }

    private function offering(Church $church, float $amount, CollectionStatus $status, string $weekOf = '2026-08-10', ?int $adjustsId = null): Collection
    {
        return Collection::create([
            'church_id' => $church->id,
            'type' => CollectionType::Offering,
            'week_of' => $weekOf,
            'amount' => $amount,
            'status' => $status,
            'submitted_by' => $church->pastor_id,
            'adjusts_id' => $adjustsId,
        ]);
    }

    public function test_compute_sums_only_approved_offerings_in_the_quarter(): void
    {
        // Outreach A: 1000 + 500 approved, plus a −100 approved correction = 1400.
        $this->offering($this->outreachA, 1000, CollectionStatus::Locked, '2026-07-06');
        $base = $this->offering($this->outreachA, 500, CollectionStatus::Locked, '2026-08-10');
        $this->offering($this->outreachA, -100, CollectionStatus::Locked, '2026-08-10', $base->id);

        // Excluded: pending offering, a tithe, and an offering outside the quarter.
        $this->offering($this->outreachA, 999, CollectionStatus::Pending, '2026-08-11');
        Collection::create([
            'church_id' => $this->outreachA->id, 'type' => CollectionType::Tithe,
            'week_of' => '2026-08-10', 'amount' => 350, 'status' => CollectionStatus::Locked,
        ]);
        $this->offering($this->outreachA, 5000, CollectionStatus::Locked, '2026-10-05'); // Q4

        // Outreach B: 2000 approved.
        $this->offering($this->outreachB, 2000, CollectionStatus::Locked, '2026-09-01');

        // Main church offering must never get a remittance.
        $this->offering($this->main, 3000, CollectionStatus::Locked, '2026-08-10');

        $count = Remittance::computeForQuarter(self::YEAR, self::QUARTER);

        $this->assertSame(2, $count); // only the two outreaches
        $this->assertDatabaseMissing('remittances', ['church_id' => $this->main->id]);

        $a = Remittance::where('church_id', $this->outreachA->id)->first();
        $this->assertEquals('1400.00', $a->offerings_total);
        $this->assertEquals('140.00', $a->amount_due);

        $b = Remittance::where('church_id', $this->outreachB->id)->first();
        $this->assertEquals('2000.00', $b->offerings_total);
        $this->assertEquals('200.00', $b->amount_due);
    }

    public function test_recompute_does_not_touch_approved_rows(): void
    {
        $this->offering($this->outreachA, 1000, CollectionStatus::Locked);
        Remittance::computeForQuarter(self::YEAR, self::QUARTER);

        $a = Remittance::where('church_id', $this->outreachA->id)->first();
        $a->update(['status' => RemittanceStatus::Approved]); // freeze it

        // More approved offerings arrive, then we recompute.
        $this->offering($this->outreachA, 500, CollectionStatus::Locked);
        Remittance::computeForQuarter(self::YEAR, self::QUARTER);

        $a->refresh();
        $this->assertEquals('100.00', $a->amount_due); // unchanged — frozen at approval
    }

    public function test_head_pastor_approves_then_marks_remitted(): void
    {
        $this->offering($this->outreachA, 1000, CollectionStatus::Locked);
        Remittance::computeForQuarter(self::YEAR, self::QUARTER);
        $rem = Remittance::where('church_id', $this->outreachA->id)->first();

        $component = Livewire::actingAs($this->headPastor)->test(ListRemittances::class);

        $component->callTableAction('approve', $rem);
        $rem->refresh();
        $this->assertSame(RemittanceStatus::Approved, $rem->status);
        $this->assertSame($this->headPastor->id, $rem->reviewed_by);

        $component->callTableAction('remit', $rem, ['remitted_on' => now()->toDateString()]);
        $rem->refresh();
        $this->assertSame(RemittanceStatus::Remitted, $rem->status);
        $this->assertNotNull($rem->remitted_at);
        $this->assertSame($this->headPastor->id, $rem->remitted_by);

        // Once remitted, it drops out of "outstanding" but shows in the received total.
        $this->assertEquals(0.00, DashboardStats::remittancesOutstanding($this->outreachA->id)['amount']);
        $this->assertEquals(100.00, DashboardStats::remittedTotal($this->outreachA->id));
    }

    public function test_outreach_pastor_sees_only_own_and_cannot_approve(): void
    {
        $this->offering($this->outreachA, 1000, CollectionStatus::Locked);
        $this->offering($this->outreachB, 2000, CollectionStatus::Locked);
        Remittance::computeForQuarter(self::YEAR, self::QUARTER);

        $mine = Remittance::where('church_id', $this->outreachA->id)->first();
        $theirs = Remittance::where('church_id', $this->outreachB->id)->first();

        Livewire::actingAs($this->pastorA)
            ->test(ListRemittances::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$theirs])
            ->assertTableActionHidden('approve', $mine);
    }
}