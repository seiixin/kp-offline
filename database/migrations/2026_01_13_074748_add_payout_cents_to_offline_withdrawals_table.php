<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('offline_withdrawals', function (Blueprint $table) {
            $table->unsignedBigInteger('payout_cents')->default(0)->after('diamonds_amount');
        });
    }

    public function down(): void
    {
        Schema::table('offline_withdrawals', function (Blueprint $table) {
            $table->dropColumn('payout_cents');
        });
    }
};
