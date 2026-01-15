<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ledger_entries', function (Blueprint $table) {
            $table->string('direction', 16)->change();
        });
    }

    public function down(): void
    {
        Schema::table('ledger_entries', function (Blueprint $table) {
            // restore only if you know the old length; common old is 1
            $table->string('direction', 1)->change();
        });
    }
};
