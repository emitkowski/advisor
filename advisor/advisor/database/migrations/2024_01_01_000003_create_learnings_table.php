<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('advisor_session_id')->nullable()->constrained()->onDelete('set null');
            $table->enum('category', [
                'blind_spot',       // recurring thinking errors
                'pattern',          // how user approaches problems
                'follow_through',   // what they actually complete vs abandon
                'value',            // what they say matters to them
                'reaction',         // how they respond to honesty/pushback
                'domain',           // subject matter expertise or gaps
            ]);
            $table->text('content'); // the learning in plain text
            $table->decimal('confidence', 4, 3)->default(0.7); // 0.0-1.0
            $table->integer('reinforcement_count')->default(1); // times this pattern confirmed
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learnings');
    }
};
