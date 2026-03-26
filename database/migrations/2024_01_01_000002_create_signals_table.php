<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('advisor_session_id')->constrained()->onDelete('cascade');
            $table->decimal('rating', 4, 2); // 1.0 - 10.0
            $table->enum('type', ['explicit', 'implicit']); // user typed it vs inferred
            $table->decimal('sentiment', 4, 3)->nullable(); // 0.000 - 1.000 raw score
            $table->text('context')->nullable(); // what was happening when rated
            $table->text('message_snippet')->nullable(); // the message that triggered this
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signals');
    }
};
