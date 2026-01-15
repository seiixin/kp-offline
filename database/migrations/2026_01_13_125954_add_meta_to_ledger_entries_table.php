<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ledger_entries', function (Blueprint $table) {
            // Use json if supported; fallback to longText if you prefer
            $table->json('meta')->nullable()->after('currency');
        });
    }

    public function down(): void
    {
        Schema::table('ledger_entries', function (Blueprint $table) {
            $table->dropColumn('meta');
        });
    }
};
