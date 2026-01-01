<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('actor_user_id')->nullable(); // users.id
            $table->string('action', 60); // e.g. offline_recharge.created
            $table->string('entity_type', 60); // OfflineRecharge, AgentTopUp, etc
            $table->unsignedBigInteger('entity_id')->nullable();

            $table->json('detail_json')->nullable();

            $table->timestamps();

            $table->index(['actor_user_id', 'created_at']);
            $table->index(['entity_type', 'entity_id']);
            $table->index(['action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
