<?php

namespace Tests\Feature;

use App\Enums\CollectionStatus;
use App\Enums\CollectionType;
use App\Enums\ExpenseCategory;
use App\Enums\UserRole;
use App\Filament\Resources\Expenses\Pages\CreateExpense;
use App\Filament\Resources\Expenses\Pages\ListExpenses;
use App\Models\Church;
use App\Models\Collection;
use App\Models\Expense;
use App\Models\User;
use App\Support\DashboardStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ExpenseWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $headPastor;
    private Church $outreachA;
    private Church $outreachB;
    private User $pastorA;
    private User $pastorB;

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
        $this->outreachA->update(['pastor_id' => $this->pastorA->id]);

        $this->outreachB = Church::create(['name' => 'Outreach B']);
        $this->pastorB = User::create([
            'name' => 'Pastor B', 'email' => 'b@sjci.test', 'password' => 'password',
            'role' => UserRole::OutreachPastor, 'church_id' => $this->outreachB->id,
        ]);
        $this->outreachB->update(['pastor_id' => $this->pastorB->id]);

        // Give Outreach A a fund: two ₱1,000 approved offerings → ₱1,800 (90%) on hand.
        $this->fundOffering($this->outreachA, 1000);
        $this->fundOffering($this->outreachA, 1000);
    }

    private function fundOffering(Church $church, float $amount): Collection
    {
        return Collection::create([
            'church_id' => $church->id,
            'type' => CollectionType::Offering,
            'week_of' => now()->subWeek(),
            'amount' => $amount,
            'status' => CollectionStatus::Locked,
        ]);
    }

    private function expense(Church $church, float $amount, CollectionStatus $status, ?User $submitter = null): Expense
    {
        return Expense::create([
            'church_id' => $church->id,
            'category' => ExpenseCategory::Operations,
            'spent_on' => now()->subDay(),
            'amount' => $amount,
            'purpose' => 'Test spend',
            'status' => $status,
            'submitted_by' => $submitter?->id ?? $church->pastor_id,
        ]);
    }

    public function test_infrastructure_fund_is_net_of_approved_expenses(): void
    {
        $this->assertEquals(1800.00, DashboardStats::infrastructureFund($this->outreachA->id));

        $this->expense($this->outreachA, 500, CollectionStatus::Locked);
        $this->assertEquals(1300.00, DashboardStats::infrastructureFund($this->outreachA->id));

        // A pending spend does not reduce the on-hand fund, but reserves availability.
        $this->expense($this->outreachA, 200, CollectionStatus::Pending);
        $this->assertEquals(1300.00, DashboardStats::infrastructureFund($this->outreachA->id));
        $this->assertEquals(1100.00, DashboardStats::availableToSpend($this->outreachA->id));
    }

    public function test_overspending_is_blocked_when_recording(): void
    {
        Storage::fake('public');

        Livewire::actingAs($this->pastorA)
            ->test(CreateExpense::class)
            ->fillForm([
                'category' => ExpenseCategory::Equipment->value,
                'spent_on' => now()->toDateString(),
                'amount' => 2000, // fund only holds 1,800
                'purpose' => 'A very expensive thing',
                'attachments' => [UploadedFile::fake()->image('receipt.jpg')],
            ])
            ->call('create')
            ->assertHasFormErrors(['amount']);

        $this->assertSame(0, Expense::count());
    }

    public function test_a_within_fund_spend_can_be_recorded_then_approved(): void
    {
        Storage::fake('public');

        Livewire::actingAs($this->pastorA)
            ->test(CreateExpense::class)
            ->fillForm([
                'category' => ExpenseCategory::Ministry->value,
                'spent_on' => now()->toDateString(),
                'amount' => 500,
                'purpose' => 'Ministry materials',
                'attachments' => [UploadedFile::fake()->image('receipt.jpg')],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $expense = Expense::firstOrFail();
        $this->assertSame(CollectionStatus::Pending, $expense->status);
        $this->assertSame($this->pastorA->id, $expense->submitted_by);
        $this->assertEquals(1800.00, DashboardStats::infrastructureFund($this->outreachA->id)); // not yet deducted

        Livewire::actingAs($this->headPastor)
            ->test(ListExpenses::class)
            ->callTableAction('approve', $expense);

        $expense->refresh();
        $this->assertSame(CollectionStatus::Locked, $expense->status);
        $this->assertEquals(1300.00, DashboardStats::infrastructureFund($this->outreachA->id));
    }

    public function test_approval_is_blocked_if_it_would_exceed_the_fund(): void
    {
        // Two spends recorded directly (bypassing the create-time guard).
        $first = $this->expense($this->outreachA, 1000, CollectionStatus::Pending);
        $second = $this->expense($this->outreachA, 1000, CollectionStatus::Pending);

        $component = Livewire::actingAs($this->headPastor)->test(ListExpenses::class);

        $component->callTableAction('approve', $first);
        $this->assertSame(CollectionStatus::Locked, $first->refresh()->status);
        $this->assertEquals(800.00, DashboardStats::infrastructureFund($this->outreachA->id));

        // Only ₱800 remains — approving another ₱1,000 must be refused.
        $component->callTableAction('approve', $second);
        $this->assertSame(CollectionStatus::Pending, $second->refresh()->status);
    }

    public function test_outreach_pastor_sees_only_their_own_and_cannot_approve(): void
    {
        $this->fundOffering($this->outreachB, 1000);
        $mine = $this->expense($this->outreachA, 300, CollectionStatus::Pending);
        $theirs = $this->expense($this->outreachB, 300, CollectionStatus::Pending);

        Livewire::actingAs($this->pastorA)
            ->test(ListExpenses::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$theirs])
            ->assertTableActionHidden('approve', $mine);
    }

    public function test_spend_requires_a_receipt(): void
    {
        Storage::fake('public');

        Livewire::actingAs($this->pastorA)
            ->test(CreateExpense::class)
            ->fillForm([
                'category' => ExpenseCategory::Operations->value,
                'spent_on' => now()->toDateString(),
                'amount' => 300,
                'purpose' => 'Utilities',
            ])
            ->call('create')
            ->assertHasFormErrors(['attachments']); // proof of spending is required

        $this->assertSame(0, Expense::count());
    }

    public function test_uploaded_receipt_is_stored_with_the_spend(): void
    {
        Storage::fake('public');

        Livewire::actingAs($this->pastorA)
            ->test(CreateExpense::class)
            ->fillForm([
                'category' => ExpenseCategory::Equipment->value,
                'spent_on' => now()->toDateString(),
                'amount' => 300,
                'purpose' => 'New chairs',
                'attachments' => [UploadedFile::fake()->image('receipt.jpg')],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $expense = Expense::firstOrFail();
        $this->assertNotEmpty($expense->attachments);
        Storage::disk('public')->assertExists($expense->attachments[0]);
    }
}