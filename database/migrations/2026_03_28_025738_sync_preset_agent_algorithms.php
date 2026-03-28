<?php

use App\Models\Agent;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $presetsByName = collect(Agent::presets())->keyBy('name');

        Agent::query()
            ->where('is_preset', true)
            ->each(function (Agent $agent) use ($presetsByName) {
                $preset = $presetsByName->get($agent->name);

                if ($preset === null) {
                    return;
                }

                $agent->update(['algorithm' => $preset['algorithm']]);
            });
    }

    public function down(): void
    {
        Agent::where('is_preset', true)->update(['algorithm' => null]);
    }
};
