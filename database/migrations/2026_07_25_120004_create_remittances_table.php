<?php

use App\Enums\RemittanceStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('remittances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('church_id')->constrained('churches')->cascadeOnDelete();

            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('quarter'); // 1..4

            // Computed from approved offerings over the quarter.
            $table->decimal('offerings_total', 12, 2)->default(0);
            $table->decimal('amount_due', 12, 2)->default(0); // 10% of offerings_total

            $table->string('status')->default(RemittanceStatus::Due->value);

            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('remitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('remitted_at')->nullable();

            $table->timestamps();

            $table->unique(['church_id', 'year', 'quarter']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('remittances');
    }
};
