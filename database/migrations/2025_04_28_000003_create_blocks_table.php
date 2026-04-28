<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gate_id')->constrained('gates');
            $table->unsignedBigInteger('block_number');
            $table->string('block_hash');
            $table->string('parent_hash');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['gate_id', 'block_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blocks');
    }
};
