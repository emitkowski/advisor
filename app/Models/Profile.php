<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Profile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'key',
        'value',
        'confidence',
        'observation_count',
    ];

    protected $casts = [
        'confidence'        => 'decimal:3',
        'observation_count' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Upsert a profile observation.
     * If the key exists, updates value and increases confidence.
     * If new, creates with base confidence.
     */
    public static function record(int $userId, string $key, string $value, float $confidence = 0.5): self
    {
        $existing = static::where('user_id', $userId)->where('key', $key)->first();

        if ($existing) {
            $existing->update([
                'value'             => $value,
                'confidence'        => min(1.0, $existing->confidence + 0.1),
                'observation_count' => $existing->observation_count + 1,
            ]);
            return $existing;
        }

        return static::create([
            'user_id'    => $userId,
            'key'        => $key,
            'value'      => $value,
            'confidence' => $confidence,
        ]);
    }

    /**
     * Build a readable profile summary for system prompt injection.
     */
    public static function buildSummary(int $userId): string
    {
        $entries = static::where('user_id', $userId)
            ->where('confidence', '>=', 0.4)
            ->orderBy('observation_count', 'desc')
            ->limit(20)
            ->get();

        if ($entries->isEmpty()) {
            return '';
        }

        $lines = ["## Profile observations\n"];
        foreach ($entries as $entry) {
            $lines[] = "- **{$entry->key}**: {$entry->value}";
        }

        return implode("\n", $lines);
    }
}
