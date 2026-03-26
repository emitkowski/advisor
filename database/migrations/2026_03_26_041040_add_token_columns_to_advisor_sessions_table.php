<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('advisor_sessions', function (Blueprint $table) {
            $table->unsignedInteger('input_tokens')->default(0)->after('message_count');
            $table->unsignedInteger('output_tokens')->default(0)->after('input_tokens');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('advisor_sessions', function (Blueprint $table) {
            $table->dropColumn(['input_tokens', 'output_tokens']);
        });
    }
};
