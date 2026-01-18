<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // MongoDB ObjectId is 24-char hex string
            $table
                ->string('mongo_user_id', 24)
                ->nullable()
                ->after('id');

            $table->unique('mongo_user_id', 'users_mongo_user_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_mongo_user_id_unique');
            $table->dropColumn('mongo_user_id');
        });
    }
};
