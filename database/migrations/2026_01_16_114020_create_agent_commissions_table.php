<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
public function up()
{
    Schema::create('agent_commissions', function (Blueprint $table) {
        $table->id();
        $table->foreignId('agent_id')->constrained()->onDelete('cascade');
        $table->integer('commission_amount_cents');
        $table->float('commission_percentage');
        $table->string('reference')->nullable();
        $table->enum('status', ['pending', 'paid', 'cancelled'])->default('pending');
        $table->foreignId('created_by')->constrained('users');
        $table->timestamps();
    });
}

public function down()
{
    Schema::dropIfExists('agent_commissions');
}

};
