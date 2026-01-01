<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();

            // owner_type: agent|pool
            $table->string('owner_type', 20);
            $table->unsignedBigInteger('owner_id');

            // asset: USD_CENTS (v1)
            $table->string('asset', 20)->default('USD_CENTS');

            $table->bigInteger('available_cents')->default(0);
            $table->bigInteger('reserved_cents')->default(0);

            $table->timestamps();

            $table->index(['owner_type', 'owner_id']);
            $table->unique(['owner_type', 'owner_id', 'asset'], 'wallets_owner_asset_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
