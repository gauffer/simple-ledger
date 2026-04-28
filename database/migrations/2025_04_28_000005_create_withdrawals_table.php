<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('withdrawals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gate_id')->constrained('gates');
            $table->string('to_address');
            $table->string('amount');
            $table->string('amount_raw');
            $table->enum('status', ['CREATED', 'BROADCASTED', 'CONFIRMED', 'FAILED'])->default('CREATED');
            $table->text('signed_tx')->nullable();
            $table->string('tx_hash')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawals');
    }
};
