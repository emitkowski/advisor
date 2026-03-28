<?php

use App\Models\Agent;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $preset = collect(Agent::presets())->firstWhere('name', 'Samuel L. Jackson');

        User::query()->each(function (User $user) use ($preset) {
            Agent::firstOrCreate(
                ['user_id' => $user->id, 'name' => $preset['name'], 'is_preset' => true],
                array_merge($preset, ['user_id' => $user->id])
            );
        });
    }

    public function down(): void
    {
        Agent::where('name', 'Samuel L. Jackson')->where('is_preset', true)->delete();
    }
};
