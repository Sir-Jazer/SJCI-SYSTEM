<?php

namespace Database\Seeders;

use App\Enums\CollectionStatus;
use App\Enums\CollectionType;
use App\Enums\ExpenseCategory;
use App\Enums\RemittanceStatus;
use App\Enums\UserRole;
use App\Models\Church;
use App\Models\Collection;
use App\Models\Expense;
use App\Models\Remittance;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Opt-in sample data for demos and manual testing.
 *   php artisan db:seed --class=DemoSeeder
 *
 * Idempotent: clears existing collections/remittances first, then rebuilds a
 * few months of realistic activity across the main church and its outreaches.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // Ensure the baseline (churches + pastor logins) exists.
        if (Church::count() === 0) {
            $this->call(DatabaseSeeder::class);
        }

        $head = User::where('role', UserRole::HeadPastor)->firstOrFail();
        $main = Church::where('is_main', true)->firstOrFail();
        $outreaches = Church::where('is_main', false)->orderBy('id')->get();

        // Reset any prior demo activity so re-running is clean.
        Collection::query()->delete();
        Expense::query()->delete();
        Remittance::query()->delete();

        // 14 recent Sundays, oldest → newest (spans the last two quarters).
        $latestSunday = Carbon::now()->startOfWeek(Carbon::SUNDAY);
        $weeks = collect(range(13, 0))->map(fn (int $back): Carbon => $latestSunday->copy()->subWeeks($back));
        $lastIndex = $weeks->count() - 1;

        // --- Outreach churches: pastor records, Head Pastor approves ---
        foreach ($outreaches as $church) {
            foreach ($weeks as $index => $sunday) {
                // The most recent week is left pending to populate the approval queue.
                $status = $index === $lastIndex ? CollectionStatus::Pending : CollectionStatus::Locked;

                $this->makeCollection($church, CollectionType::Offering, $sunday, $this->offeringAmount(), $status, $head);
                $this->makeCollection($church, CollectionType::Tithe, $sunday, $this->titheAmount(), $status, $head);
            }
        }

        // One returned report (with a reason) on the first outreach's second-latest week.
        $returned = Collection::query()
            ->where('church_id', $outreaches->first()->id)
            ->where('type', CollectionType::Offering)
            ->orderByDesc('week_of')
            ->skip(1)->first();

        if ($returned) {
            $returned->update([
                'status' => CollectionStatus::Returned,
                'returned_reason' => 'Amount does not match the counting sheet — please recount and resubmit.',
                'approved_by' => null,
                'approved_at' => null,
            ]);
        }

        // A visible correction against an early locked offering (signed −₱150 delta).
        $toCorrect = Collection::query()
            ->where('church_id', $outreaches->first()->id)
            ->where('type', CollectionType::Offering)
            ->where('status', CollectionStatus::Locked)
            ->orderBy('week_of')->first();

        if ($toCorrect) {
            $adjustment = $this->makeCollection(
                $toCorrect->church,
                CollectionType::Offering,
                $toCorrect->week_of,
                -150,
                CollectionStatus::Locked,
                $head,
            );
            $adjustment->update([
                'adjusts_id' => $toCorrect->id,
                'note' => "Correction of #{$toCorrect->id}: over-counted by ₱150.",
            ]);
        }

        // --- Main church: Head Pastor records and self-approves ---
        foreach ($weeks as $sunday) {
            $this->makeCollection($main, CollectionType::Offering, $sunday, $this->offeringAmount(), CollectionStatus::Locked, $head, $head);
            $this->makeCollection($main, CollectionType::Tithe, $sunday, $this->titheAmount(), CollectionStatus::Locked, $head, $head);
        }

        // --- Fund spending: a couple of approved spends + one pending per outreach ---
        foreach ($outreaches as $church) {
            $this->makeExpense($church, ExpenseCategory::Equipment, $latestSunday->copy()->subWeeks(6), 2500, CollectionStatus::Locked, $head);
            $this->makeExpense($church, ExpenseCategory::Ministry, $latestSunday->copy()->subWeeks(3), 1500, CollectionStatus::Locked, $head);
            $this->makeExpense($church, ExpenseCategory::Operations, $latestSunday->copy()->subWeek(), 1200, CollectionStatus::Pending, $head);
        }

        // --- Quarterly Tithes of Tithes ---
        $year = (int) Carbon::now()->year;
        $currentQuarter = Remittance::currentQuarter();
        $previousQuarter = $currentQuarter > 1 ? $currentQuarter - 1 : 1;

        // Previous quarter: computed, approved, and settled.
        Remittance::computeForQuarter($year, $previousQuarter);
        Remittance::where('year', $year)->where('quarter', $previousQuarter)->get()
            ->each(function (Remittance $remittance) use ($head): void {
                $remittance->update([
                    'status' => RemittanceStatus::Remitted,
                    'reviewed_by' => $head->id,
                    'remitted_by' => $head->id,
                    'remitted_at' => Carbon::now()->subDays(20),
                ]);
            });

        // Current quarter: computed and left due for review.
        Remittance::computeForQuarter($year, $currentQuarter);
    }

    private function makeCollection(
        Church $church,
        CollectionType $type,
        Carbon $weekOf,
        float $amount,
        CollectionStatus $status,
        User $head,
        ?User $submitter = null,
    ): Collection {
        return Collection::create([
            'church_id' => $church->id,
            'type' => $type,
            'week_of' => $weekOf->toDateString(),
            'amount' => $amount,
            'status' => $status,
            'submitted_by' => $submitter?->id ?? $church->pastor_id,
            'approved_by' => $status === CollectionStatus::Locked ? $head->id : null,
            'approved_at' => $status === CollectionStatus::Locked ? $weekOf->copy()->addDay() : null,
        ]);
    }

    private function makeExpense(
        Church $church,
        ExpenseCategory $category,
        Carbon $spentOn,
        float $amount,
        CollectionStatus $status,
        User $head,
    ): Expense {
        return Expense::create([
            'church_id' => $church->id,
            'category' => $category,
            'spent_on' => $spentOn->toDateString(),
            'amount' => $amount,
            'purpose' => $category->label().' for '.$church->name,
            'status' => $status,
            'submitted_by' => $church->pastor_id,
            'approved_by' => $status === CollectionStatus::Locked ? $head->id : null,
            'approved_at' => $status === CollectionStatus::Locked ? $spentOn->copy()->addDay() : null,
        ]);
    }

    private function offeringAmount(): float
    {
        return (float) (mt_rand(16, 70) * 50); // ₱800 – ₱3,500, rounded to ₱50
    }

    private function titheAmount(): float
    {
        // Roughly 1 in 8 weeks nothing was received (a ₱0.00 declaration).
        return mt_rand(1, 8) === 1 ? 0.0 : (float) (mt_rand(6, 40) * 50); // up to ₱2,000
    }
}