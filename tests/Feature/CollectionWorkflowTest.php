<?php

namespace Tests\Feature;

use App\Enums\CollectionStatus;
use App\Enums\CollectionType;
use App\Enums\UserRole;
use App\Filament\Resources\Collections\Pages\CreateCollection;
use App\Filament\Resources\Collections\Pages\ListCollections;
use App\Models\Church;
use App\Models\Collection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class CollectionWorkflowTest extends TestCase
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

        $this->outreachB = Church::create(['name' => 'Outreach B']);
        $this->pastorB = User::create([
            'name' => 'Pastor B', 'email' => 'b@sjci.test', 'password' => 'password',
            'role' => UserRole::OutreachPastor, 'church_id' => $this->outreachB->id,
        ]);
    }

    private function makeOffering(Church $church, User $submitter, float $amount, CollectionStatus $status): Collection
    {
        return Collection::create([
            'church_id' => $church->id,
            'type' => CollectionType::Offering,
            'week_of' => now()->subDay(),
            'amount' => $amount,
            'status' => $status,
            'submitted_by' => $submitter->id,
        ]);
    }

    public function test_offering_is_split_ten_ninety(): void
    {
        $c = $this->makeOffering($this->outreachA, $this->pastorA, 1000, CollectionStatus::Pending);

        $this->assertEquals('100.00', $c->main_share);
        $this->assertEquals('900.00', $c->outreach_share);
    }

    public function test_main_church_offering_is_not_split(): void
    {
        // The head pastor's church is the main church.
        $main = $this->makeOffering(
            \App\Models\Church::whereKey($this->headPastor->church_id)->first(),
            $this->headPastor,
            1000,
            CollectionStatus::Locked,
        );

        $this->assertEquals('0.00', $main->main_share);
        $this->assertEquals('1000.00', $main->outreach_share); // keeps 100%
    }

    public function test_tithe_is_never_split(): void
    {
        $t = Collection::create([
            'church_id' => $this->outreachA->id,
            'type' => CollectionType::Tithe,
            'week_of' => now()->subDay(),
            'amount' => 350,
            'submitted_by' => $this->pastorA->id,
        ]);

        $this->assertEquals('0.00', $t->main_share);
        $this->assertEquals('0.00', $t->outreach_share);
    }

    public function test_outreach_pastor_sees_only_their_own_church(): void
    {
        $mine = $this->makeOffering($this->outreachA, $this->pastorA, 500, CollectionStatus::Locked);
        $theirs = $this->makeOffering($this->outreachB, $this->pastorB, 700, CollectionStatus::Locked);

        Livewire::actingAs($this->pastorA)
            ->test(ListCollections::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$theirs]);
    }

    public function test_head_pastor_can_approve_and_lock_a_pending_report(): void
    {
        $c = $this->makeOffering($this->outreachA, $this->pastorA, 1000, CollectionStatus::Pending);

        Livewire::actingAs($this->headPastor)
            ->test(ListCollections::class)
            ->callTableAction('approve', $c);

        $c->refresh();
        $this->assertSame(CollectionStatus::Locked, $c->status);
        $this->assertSame($this->headPastor->id, $c->approved_by);
        $this->assertNotNull($c->approved_at);
    }

    public function test_return_action_records_a_reason(): void
    {
        $c = $this->makeOffering($this->outreachA, $this->pastorA, 1000, CollectionStatus::Pending);

        Livewire::actingAs($this->headPastor)
            ->test(ListCollections::class)
            ->callTableAction('return', $c, ['returned_reason' => 'Amount looks wrong']);

        $c->refresh();
        $this->assertSame(CollectionStatus::Returned, $c->status);
        $this->assertSame('Amount looks wrong', $c->returned_reason);
    }

    public function test_outreach_pastor_cannot_approve(): void
    {
        $c = $this->makeOffering($this->outreachA, $this->pastorA, 1000, CollectionStatus::Pending);

        Livewire::actingAs($this->pastorA)
            ->test(ListCollections::class)
            ->assertTableActionHidden('approve', $c);
    }

    public function test_correction_posts_a_linked_pending_adjustment(): void
    {
        $original = $this->makeOffering($this->outreachA, $this->pastorA, 1000, CollectionStatus::Locked);

        Livewire::actingAs($this->pastorA)
            ->test(ListCollections::class)
            ->callTableAction('correct', $original, [
                'corrected_amount' => 900,
                'reason' => 'Miscounted by ₱100',
            ]);

        $adjustment = Collection::where('adjusts_id', $original->id)->first();

        $this->assertNotNull($adjustment);
        $this->assertEquals('-100.00', $adjustment->amount);        // signed delta
        $this->assertEquals('-10.00', $adjustment->main_share);      // 10% of the delta
        $this->assertEquals('-90.00', $adjustment->outreach_share);  // 90% of the delta
        $this->assertSame(CollectionStatus::Pending, $adjustment->status);
        $this->assertSame($this->pastorA->id, $adjustment->submitted_by);

        // The original locked record is never touched.
        $original->refresh();
        $this->assertEquals('1000.00', $original->amount);
        $this->assertSame(CollectionStatus::Locked, $original->status);
    }

    public function test_correction_is_only_offered_on_own_locked_originals(): void
    {
        $lockedOwn = $this->makeOffering($this->outreachA, $this->pastorA, 1000, CollectionStatus::Locked);
        $pendingOwn = $this->makeOffering($this->outreachA, $this->pastorA, 500, CollectionStatus::Pending);
        $lockedOther = $this->makeOffering($this->outreachB, $this->pastorB, 700, CollectionStatus::Locked);

        $component = Livewire::actingAs($this->pastorA)->test(ListCollections::class);

        $component->assertTableActionVisible('correct', $lockedOwn);   // locked + own church
        $component->assertTableActionHidden('correct', $pendingOwn);   // not locked yet

        // A pastor cannot even see another church's records, let alone correct them.
        $component->assertCanNotSeeTableRecords([$lockedOther]);

        // The Head Pastor may not correct an outreach's records.
        Livewire::actingAs($this->headPastor)
            ->test(ListCollections::class)
            ->assertTableActionHidden('correct', $lockedOwn);
    }

    public function test_offering_requires_proof_when_money_was_received(): void
    {
        Storage::fake('public');

        Livewire::actingAs($this->pastorA)
            ->test(CreateCollection::class)
            ->fillForm([
                'type' => CollectionType::Offering->value,
                'week_of' => now()->toDateString(),
                'amount' => 1000,
            ])
            ->call('create')
            ->assertHasFormErrors(['attachments']); // proof required for a non-zero amount

        $this->assertSame(0, Collection::count());
    }

    public function test_zero_declaration_needs_no_proof(): void
    {
        Storage::fake('public');

        Livewire::actingAs($this->pastorA)
            ->test(CreateCollection::class)
            ->fillForm([
                'type' => CollectionType::Tithe->value,
                'week_of' => now()->toDateString(),
                'amount' => 0,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(1, Collection::count());
    }

    public function test_uploaded_proof_is_stored_with_the_report(): void
    {
        Storage::fake('public');

        Livewire::actingAs($this->pastorA)
            ->test(CreateCollection::class)
            ->fillForm([
                'type' => CollectionType::Offering->value,
                'week_of' => now()->toDateString(),
                'amount' => 1000,
                'attachments' => [UploadedFile::fake()->image('counting-sheet.jpg')],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $report = Collection::firstOrFail();
        $this->assertNotEmpty($report->attachments);
        Storage::disk('public')->assertExists($report->attachments[0]);
    }

    public function test_locked_records_are_immutable(): void
    {
        $locked = $this->makeOffering($this->outreachA, $this->pastorA, 1000, CollectionStatus::Locked);

        // Even the original submitter cannot edit or delete a locked record.
        $this->assertFalse($this->pastorA->can('update', $locked));
        $this->assertFalse($this->pastorA->can('delete', $locked));

        // The Head Pastor can never edit an outreach's records.
        $pending = $this->makeOffering($this->outreachA, $this->pastorA, 500, CollectionStatus::Pending);
        $this->assertFalse($this->headPastor->can('update', $pending));
    }
}
