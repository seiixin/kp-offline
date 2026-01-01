<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offline_withdrawals', function (Blueprint $table) {
            $table->id();

            $table->foreignId('agent_id')->constrained('agents')->cascadeOnDelete();

            $table->char('mongo_user_id', 24);

            // normalized from mobile backend
            $table->bigInteger('amount_usd_cents')->default(0);
            $table->bigInteger('diamonds_debited')->default(0);

            $table->string('channel', 40)->nullable(); // bank/gcash/etc
            $table->string('payout_account_ref', 160)->nullable();
            $table->string('country', 2)->nullable();
            $table->string('reference', 120)->nullable();

            $table->string('status', 20)->default('processing'); // processing|successful|failed

            $table->string('mobile_txn_ref', 120)->nullable()->unique();
            $table->uuid('idempotency_key')->unique();

            $table->string('proof_url', 2048)->nullable();
            $table->json('meta_json')->nullable();

            $table->timestamps();

            $table->index(['agent_id', 'status']);
            $table->index(['mongo_user_id']);
            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offline_withdrawals');
    }
};
