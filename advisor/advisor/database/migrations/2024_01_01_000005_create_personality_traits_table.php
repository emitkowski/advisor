<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personality_traits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('trait'); // e.g. "directness", "skepticism"
            $table->unsignedTinyInteger('value'); // 0-100
            $table->text('description'); // how this trait manifests in responses
            $table->boolean('is_system')->default(false); // true = default, false = customized
            $table->timestamps();

            $table->unique(['user_id', 'trait']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personality_traits');
    }
};
