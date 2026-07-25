<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_id')->constrained('competition_setups')->cascadeOnDelete();
            $table->enum('type', ['REGULAR_SEASON', 'PLAYOFFS']);
            $table->enum('format', ['ROUND_ROBIN', 'DOUBLE_ELIMINATION']);
            $table->json('seeding_rule')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stages');
    }
};
