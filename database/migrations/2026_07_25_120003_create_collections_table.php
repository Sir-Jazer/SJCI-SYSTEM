<?php

use App\Enums\CollectionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('church_id')->constrained('churches')->cascadeOnDelete();

            // 'offering' or 'tithe'.
            $table->string('type');
            // The Sunday/week the collection belongs to.
            $table->date('week_of');
            $table->decimal('amount', 12, 2)->default(0);

            // Offering split, carved out at collection. Zero for tithes.
            $table->decimal('main_share', 12, 2)->default(0);      // 10% -> Main Church
            $table->decimal('outreach_share', 12, 2)->default(0);  // 90% -> Outreach Infrastructure Fund

            $table->string('status')->default(CollectionStatus::Pending->value);
            $table->text('note')->nullable();
            $table->text('returned_reason')->nullable();

            // Who submitted, who approved, and when it was locked.
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            // Correcting entries reference the locked record they adjust.
            $table->foreignId('adjusts_id')->nullable()->constrained('collections')->nullOnDelete();

            $table->timestamps();

            $table->index(['church_id', 'week_of']);
            $table->index(['type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collections');
    }
};
