<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_topups', function (Blueprint $table) {
            $table->id();

            $table->foreignId('agent_id')->constrained('agents')->cascadeOnDelete();

            $table->bigInteger('amount_usd_cents')->default(0);
            $table->string('reference', 120)->nullable();

            $table->string('status', 20)->default('draft'); // draft|posted|void
            $table->unsignedBigInteger('created_by')->nullable(); // users.id (admin)
            $table->timestamp('posted_at')->nullable();

            $table->timestamps();

            $table->index(['agent_id', 'status']);
            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_topups');
    }
};
