<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offline_withdrawals', function (Blueprint $table) {
            // already added: diamonds_amount, payout_cents

            if (!Schema::hasColumn('offline_withdrawals', 'currency')) {
                $table->string('currency', 8)->default('PHP')->after('payout_cents');
            }

            if (!Schema::hasColumn('offline_withdrawals', 'payout_method')) {
                $table->string('payout_method', 40)->nullable()->after('currency');
            }

            if (!Schema::hasColumn('offline_withdrawals', 'reference')) {
                $table->string('reference', 120)->nullable()->after('payout_method');
            }

            if (!Schema::hasColumn('offline_withdrawals', 'notes')) {
                $table->text('notes')->nullable()->after('reference');
            }

            if (!Schema::hasColumn('offline_withdrawals', 'status')) {
                $table->string('status', 20)->default('processing')->after('notes');
            }

            if (!Schema::hasColumn('offline_withdrawals', 'idempotency_key')) {
                $table->uuid('idempotency_key')->nullable()->index()->after('status');
            }

            if (!Schema::hasColumn('offline_withdrawals', 'mongo_txn_ref')) {
                $table->string('mongo_txn_ref', 120)->nullable()->after('idempotency_key');
            }

            if (!Schema::hasColumn('offline_withdrawals', 'error_payload')) {
                $table->json('error_payload')->nullable()->after('mongo_txn_ref');
            }

            // If you want to store account details per withdrawal:
            if (!Schema::hasColumn('offline_withdrawals', 'account_name')) {
                $table->string('account_name', 120)->nullable()->after('payout_method');
            }
            if (!Schema::hasColumn('offline_withdrawals', 'account_no')) {
                $table->string('account_no', 60)->nullable()->after('account_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('offline_withdrawals', function (Blueprint $table) {
            foreach ([
                'currency',
                'payout_method',
                'reference',
                'notes',
                'status',
                'idempotency_key',
                'mongo_txn_ref',
                'error_payload',
                'account_name',
                'account_no',
            ] as $col) {
                if (Schema::hasColumn('offline_withdrawals', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
