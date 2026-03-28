<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->string('color', 7)->default('#6B7280')->after('is_preset');
            $table->unsignedSmallInteger('sort_order')->default(0)->after('color');
        });

        // Backfill colors and sort order for existing preset agents
        $presets = [
            'The Advisor'       => ['color' => '#3B82F6', 'sort_order' => 1],
            'Devil\'s Advocate' => ['color' => '#EF4444', 'sort_order' => 2],
            'Strategic Advisor' => ['color' => '#8B5CF6', 'sort_order' => 3],
            'Technical Advisor' => ['color' => '#10B981', 'sort_order' => 4],
            'Coach'             => ['color' => '#F59E0B', 'sort_order' => 5],
        ];

        foreach ($presets as $name => $values) {
            DB::table('agents')
                ->where('name', $name)
                ->where('is_preset', true)
                ->update($values);
        }
    }

    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn(['color', 'sort_order']);
        });
    }
};
