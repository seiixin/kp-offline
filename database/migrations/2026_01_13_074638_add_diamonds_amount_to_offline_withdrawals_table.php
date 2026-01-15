<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('offline_withdrawals', function (Blueprint $table) {
            $table->unsignedInteger('diamonds_amount')->default(0)->after('mongo_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('offline_withdrawals', function (Blueprint $table) {
            $table->dropColumn('diamonds_amount');
        });
    }
};
