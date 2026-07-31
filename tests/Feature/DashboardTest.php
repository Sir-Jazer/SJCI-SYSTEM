<?php

namespace Tests\Feature;

use App\Enums\CollectionStatus;
use App\Enums\CollectionType;
use App\Enums\RemittanceStatus;
use App\Enums\UserRole;
use App\Filament\Widgets\FinanceOverview;
use App\Filament\Widgets\PendingApprovals;
use App\Models\Church;
use App\Models\Collection;
use App\Models\Remittance;
use App\Models\User;
use App\Support\DashboardStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $headPastor;
    private Church $outreachA;
    private Church $outreachB;
    private User $pastorA;

    protected function setUp(): void
    {
        parent::setUp();

        $main = Church::create(['name' => 'Main', 'is_main' => true]);
        $this->headPastor = User::create([
            'name' => 'Head', 'email' => 'head@sjci.test', 'password' => 'password',
            'role' => UserRole::HeadPastor, 'church_id' => $main->id,
        ]);

        $this->outreachA = Church::create(['name' => 'Outreach A']);
        $this->pastorA = User::create([
            'name' => 'Pastor A', 'email' => 'a@sjci.test', 'password' => 'password',
            'role' => UserRole::OutreachPastor, 'church_id' => $this->outreachA->id,
        ]);

        $this->outreachB = Church::create(['name' => 'Outreach B']);
    }

    private function offering(Church $church, float $amount, CollectionStatus $status, string $weekOf = '2026-08-10'): Collection
    {
        return Collection::create([
            'church_id' => $church->id,
            'type' => CollectionType::Offering,
            'week_of' => $weekOf,
            'amount' => $amount,
            'status' => $status,
        ]);
    }

    public function test_infrastructure_fund_is_ninety_percent_of_approved_offerings(): void
    {
        $this->offering($this->outreachA, 1000, CollectionStatus::Locked); // 900 to fund
        $this->offering($this->outreachA, 500, CollectionStatus::Locked);  // 450 to fund
        $this->offering($this->outreachA, 999, CollectionStatus::Pending); // excluded
        $this->offering($this->outreachB, 2000, CollectionStatus::Locked); // 1800, other church

        $this->assertEquals(1350.00, DashboardStats::infrastructureFund($this->outreachA->id));
        $this->assertEquals(3150.00, DashboardStats::infrastructureFund()); // all churches
    }

    public function test_quarter_totals_and_pending_counts(): void
    {
        $this->offering($this->outreachA, 1000, CollectionStatus::Locked, '2026-08-10'); // Q3
        $this->offering($this->outreachA, 5000, CollectionStatus::Locked, '2026-02-10'); // Q1, excluded
        $this->offering($this->outreachA, 200, CollectionStatus::Pending, '2026-08-12');
        Collection::create([
            'church_id' => $this->outreachA->id, 'type' => CollectionType::Tithe,
            'week_of' => '2026-08-10', 'amount' => 350, 'status' => CollectionStatus::Locked,
        ]);

        $this->assertEquals(1000.00, DashboardStats::quarterTotal(CollectionType::Offering, 2026, 3, $this->outreachA->id));
        $this->assertEquals(350.00, DashboardStats::quarterTotal(CollectionType::Tithe, 2026, 3, $this->outreachA->id));
        $this->assertSame(1, DashboardStats::pendingApprovals($this->outreachA->id));
    }

    public function test_outstanding_remittances_sum_due_and_approved(): void
    {
        Remittance::create(['church_id' => $this->outreachA->id, 'year' => 2026, 'quarter' => 1, 'offerings_total' => 1000, 'amount_due' => 100, 'status' => RemittanceStatus::Due]);
        Remittance::create(['church_id' => $this->outreachA->id, 'year' => 2026, 'quarter' => 2, 'offerings_total' => 2000, 'amount_due' => 200, 'status' => RemittanceStatus::Approved]);
        Remittance::create(['church_id' => $this->outreachA->id, 'year' => 2026, 'quarter' => 3, 'offerings_total' => 3000, 'amount_due' => 300, 'status' => RemittanceStatus::Remitted]); // settled

        $out = DashboardStats::remittancesOutstanding($this->outreachA->id);
        $this->assertSame(2, $out['count']);
        $this->assertEquals(300.00, $out['amount']);
    }

    public function test_finance_overview_renders_for_both_roles(): void
    {
        $this->offering($this->outreachA, 1000, CollectionStatus::Locked);

        Livewire::actingAs($this->headPastor)->test(FinanceOverview::class)->assertOk();
        Livewire::actingAs($this->pastorA)->test(FinanceOverview::class)->assertOk();
    }

    public function test_church_breakdown_is_head_pastor_only_and_lists_each_church(): void
    {
        $this->offering($this->outreachA, 1000, CollectionStatus::Locked);
        $this->offering($this->outreachB, 2000, CollectionStatus::Locked);

        // Head Pastor sees a row per church; an outreach pastor cannot see it at all.
        Livewire::actingAs($this->headPastor);
        $this->assertTrue(\App\Filament\Widgets\ChurchBreakdown::canView());

        Livewire::actingAs($this->headPastor)
            ->test(\App\Filament\Widgets\ChurchBreakdown::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$this->outreachA, $this->outreachB]);

        Livewire::actingAs($this->pastorA);
        $this->assertFalse(\App\Filament\Widgets\ChurchBreakdown::canView());
    }

    public function test_network_overview_is_outreach_only_and_renders_aggregate(): void
    {
        $this->offering($this->outreachA, 1000, CollectionStatus::Locked);
        $this->offering($this->outreachB, 2000, CollectionStatus::Locked);

        // Outreach pastors get the aggregate network view...
        Livewire::actingAs($this->pastorA);
        $this->assertTrue(\App\Filament\Widgets\NetworkOverview::canView());

        Livewire::actingAs($this->pastorA)
            ->test(\App\Filament\Widgets\NetworkOverview::class)
            ->assertOk();

        // ...the Head Pastor does not (they already see the network elsewhere).
        Livewire::actingAs($this->headPastor);
        $this->assertFalse(\App\Filament\Widgets\NetworkOverview::canView());
    }

    public function test_pending_queue_is_head_pastor_only(): void
    {
        Livewire::actingAs($this->headPastor);
        $this->assertTrue(PendingApprovals::canView());

        Livewire::actingAs($this->pastorA);
        $this->assertFalse(PendingApprovals::canView());
    }
}