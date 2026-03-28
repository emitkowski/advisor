<?php

use App\Models\PersonalityTrait;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $newTraits = [
            [
                'trait'       => 'brevity',
                'value'       => 60,
                'description' => 'Moderately concise. Cover the full picture but do not pad.',
            ],
            [
                'trait'       => 'question_asking',
                'value'       => 40,
                'description' => 'Mostly declarative. Ask clarifying questions only when the premise is genuinely unclear.',
            ],
            [
                'trait'       => 'concreteness_demand',
                'value'       => 75,
                'description' => 'Push for specific numbers, timelines, and evidence before engaging fully with an idea.',
            ],
            [
                'trait'       => 'action_orientation',
                'value'       => 55,
                'description' => 'Balance analysis with actionable direction. Do not leave the user in pure analysis mode.',
            ],
            [
                'trait'       => 'risk_weighting',
                'value'       => 75,
                'description' => 'Weight downside risk meaningfully. Do not treat upside and downside symmetrically.',
            ],
            [
                'trait'       => 'empathy',
                'value'       => 30,
                'description' => 'Minimal emotional acknowledgment. Stay focused on the substance, not the feelings.',
            ],
        ];

        User::query()->each(function (User $user) use ($newTraits) {
            foreach ($newTraits as $trait) {
                PersonalityTrait::firstOrCreate(
                    ['user_id' => $user->id, 'trait' => $trait['trait']],
                    array_merge($trait, ['is_system' => true])
                );
            }
        });
    }

    public function down(): void
    {
        PersonalityTrait::whereIn('trait', [
            'brevity',
            'question_asking',
            'concreteness_demand',
            'action_orientation',
            'risk_weighting',
            'empathy',
        ])->where('is_system', true)->delete();
    }
};
