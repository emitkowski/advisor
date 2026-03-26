<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('key'); // e.g. "tends_to_overbuild", "prefers_directness"
            $table->text('value'); // the observation
            $table->decimal('confidence', 4, 3)->default(0.5); // 0.0-1.0
            $table->integer('observation_count')->default(1); // how many times seen
            $table->timestamps();

            $table->unique(['user_id', 'key']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profiles');
    }
};
