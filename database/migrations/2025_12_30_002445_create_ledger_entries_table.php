<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();

            $table->foreignId('wallet_id')->constrained('wallets')->cascadeOnDelete();

            // direction: + or -
            $table->string('direction', 1);

            // always cents for v1
            $table->bigInteger('amount_cents');

            // event type + id to link back
            $table->string('event_type', 40); // topup|offline_recharge|offline_withdrawal|adjustment
            $table->unsignedBigInteger('event_id')->nullable();

            $table->json('meta_json')->nullable();

            $table->timestamps();

            $table->index(['wallet_id', 'created_at']);
            $table->index(['event_type', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
    }
};
