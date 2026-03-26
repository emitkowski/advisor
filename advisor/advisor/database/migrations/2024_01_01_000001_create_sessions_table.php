<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advisor_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('title')->nullable(); // auto-generated summary title
            $table->longText('thread')->nullable(); // full conversation as JSON
            $table->json('meta')->nullable(); // status, context, timestamps
            $table->json('isc')->nullable(); // Ideal State Criteria
            $table->integer('message_count')->default(0);
            $table->decimal('avg_rating', 4, 2)->nullable(); // rolling average
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advisor_sessions');
    }
};
