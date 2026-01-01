<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agents', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('agent_code', 64)->unique(); // e.g. AGENT-001
            $table->string('name', 160);
            $table->string('phone', 64)->nullable();
            $table->string('country', 2)->nullable(); // ISO2, e.g. PH
            $table->string('status', 20)->default('active'); // active|inactive

            $table->timestamps();

            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agents');
    }
};
