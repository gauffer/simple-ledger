<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gate_id')->constrained('gates');
            $table->unsignedInteger('account');
            $table->unsignedInteger('change');
            $table->unsignedInteger('address_index');
            $table->string('address');
            $table->timestamps();

            $table->unique(['gate_id', 'account', 'change', 'address_index']);
            $table->index('address');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
