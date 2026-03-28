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

                $agent->update([
                    'personality'            => $preset['personality'],
                    'system_prompt_preamble' => $preset['system_prompt_preamble'],
                    'description'            => $preset['description'],
                ]);
            });
    }

    public function down(): void
    {
        // No rollback — personality data is not versioned.
    }
};
