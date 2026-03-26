<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class Learning extends Model
{
    protected $fillable = [
        'user_id',
        'advisor_session_id',
        'category',
        'content',
        'confidence',
        'reinforcement_count',
        'last_seen_at',
    ];

    protected $casts = [
        'confidence'          => 'decimal:3',
        'last_seen_at'        => 'datetime',
        'reinforcement_count' => 'integer',
    ];

    // Category constants
    const BLIND_SPOT    = 'blind_spot';
    const PATTERN       = 'pattern';
    const FOLLOW_THROUGH = 'follow_through';
    const VALUE         = 'value';
    const REACTION      = 'reaction';
    const DOMAIN        = 'domain';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AdvisorSession::class, 'advisor_session_id');
    }

    public function scopeByCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    public function scopeHighConfidence(Builder $query, float $threshold = 0.7): Builder
    {
        return $query->where('confidence', '>=', $threshold);
    }

    /**
     * Reinforce an existing learning — increases confidence and count.
     */
    public function reinforce(): void
    {
        $newConfidence = min(1.0, $this->confidence + 0.05);

        $this->update([
            'reinforcement_count' => $this->reinforcement_count + 1,
            'confidence'          => $newConfidence,
            'last_seen_at'        => now(),
        ]);
    }

    /**
     * Format all learnings for a user into a readable context block
     * suitable for injection into a system prompt.
     */
    public static function buildContextBlock(int $userId): string
    {
        $learnings = static::where('user_id', $userId)
            ->where('confidence', '>=', 0.5)
            ->orderBy('reinforcement_count', 'desc')
            ->get()
            ->groupBy('category');

        if ($learnings->isEmpty()) {
            return '';
        }

        $lines = ["## What I know about you so far\n"];

        $labels = [
            'blind_spot'     => 'Known blind spots',
            'pattern'        => 'Thinking patterns',
            'follow_through' => 'Follow-through history',
            'value'          => 'What you say matters to you',
            'reaction'       => 'How you respond to feedback',
            'domain'         => 'Domain knowledge',
        ];

        foreach ($labels as $category => $label) {
            if ($learnings->has($category)) {
                $lines[] = "**{$label}:**";
                foreach ($learnings[$category] as $learning) {
                    $lines[] = "- {$learning->content}";
                }
                $lines[] = '';
            }
        }

        return implode("\n", $lines);
    }
}
