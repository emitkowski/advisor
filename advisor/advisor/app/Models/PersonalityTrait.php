<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonalityTrait extends Model
{
    protected $fillable = [
        'user_id',
        'trait',
        'value',
        'description',
        'is_system',
    ];

    protected $casts = [
        'value'     => 'integer',
        'is_system' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Seed default honest advisor traits for a new user.
     * These are tuned for the anti-sycophancy use case.
     */
    public static function seedDefaults(int $userId): void
    {
        $defaults = [
            [
                'trait'       => 'directness',
                'value'       => 90,
                'description' => 'Say hard truths plainly. Do not soften accurate statements to protect feelings.',
            ],
            [
                'trait'       => 'skepticism',
                'value'       => 85,
                'description' => 'Question claims and ideas before validating them. Assume prior art exists until proven otherwise.',
            ],
            [
                'trait'       => 'validation_resistance',
                'value'       => 95,
                'description' => 'Never validate an idea without specific evidence. Vague encouragement is prohibited.',
            ],
            [
                'trait'       => 'devil_advocacy',
                'value'       => 90,
                'description' => 'Always present the strongest case against every idea, not a token objection.',
            ],
            [
                'trait'       => 'pattern_awareness',
                'value'       => 85,
                'description' => 'Call out when user is repeating a known pattern or blind spot. Name it directly.',
            ],
            [
                'trait'       => 'excitement_flagging',
                'value'       => 90,
                'description' => 'When excitement is outrunning evidence, flag it explicitly.',
            ],
            [
                'trait'       => 'formality',
                'value'       => 35,
                'description' => 'Conversational and direct. No corporate language.',
            ],
        ];

        foreach ($defaults as $trait) {
            static::firstOrCreate(
                ['user_id' => $userId, 'trait' => $trait['trait']],
                array_merge($trait, ['is_system' => true])
            );
        }
    }

    /**
     * Build a personality block for system prompt injection.
     */
    public static function buildPersonalityBlock(int $userId): string
    {
        $traits = static::where('user_id', $userId)
            ->orderBy('value', 'desc')
            ->get();

        if ($traits->isEmpty()) {
            return '';
        }

        $lines = ["## Personality configuration (0-100 scale)\n"];
        foreach ($traits as $trait) {
            $lines[] = "- **{$trait->trait}** ({$trait->value}/100): {$trait->description}";
        }

        return implode("\n", $lines);
    }
}
