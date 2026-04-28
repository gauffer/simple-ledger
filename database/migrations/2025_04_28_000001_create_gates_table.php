<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gates', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('rpc_url')->nullable();
            $table->unsignedBigInteger('chain_id');
            $table->unsignedInteger('confirmations_required')->default(12);
            $table->foreignId('parent_gate_id')->nullable()->constrained('gates')->nullOnDelete();
            $table->enum('asset_type', ['NATIVE', 'ERC20']);
            $table->string('token_contract')->nullable();
            $table->unsignedSmallInteger('token_decimals')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gates');
    }
};
