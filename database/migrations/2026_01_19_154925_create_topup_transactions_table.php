<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTopupTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('topup_transactions', function (Blueprint $table) {
            $table->id(); // auto-incrementing primary key
            $table->foreignId('agent_id') // references the agents table
                ->constrained()
                ->onDelete('cascade'); // cascading delete if agent is deleted
            $table->string('reference')->unique(); // unique reference for the top-up transaction
            $table->decimal('amount', 10, 2); // amount of the top-up
            $table->enum('status', ['completed', 'failed', 'pending']); // status of the transaction
            $table->string('payment_method')->nullable(); // method used for payment (e.g., 'stripe', 'offline')
            $table->timestamps(); // created_at and updated_at timestamps
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('topup_transactions'); // rollback the table if migration is rolled back
    }
}
