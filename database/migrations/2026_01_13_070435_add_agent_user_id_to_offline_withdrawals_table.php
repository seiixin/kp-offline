<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('offline_withdrawals', function (Blueprint $table) {
            $table->unsignedBigInteger('agent_user_id')->index()->after('id');
        });
    }

    public function down(): void {
        Schema::table('offline_withdrawals', function (Blueprint $table) {
            $table->dropColumn('agent_user_id');
        });
    }
};
