<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Signal extends Model
{
    protected $fillable = [
        'user_id',
        'advisor_session_id',
        'rating',
        'type',
        'sentiment',
        'context',
        'message_snippet',
    ];

    protected $casts = [
        'rating'    => 'decimal:2',
        'sentiment' => 'decimal:3',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AdvisorSession::class, 'advisor_session_id');
    }

    /**
     * Detect an explicit rating in a user message.
     * Looks for patterns like "3/10", "7 out of 10", "rating: 8", or standalone "4"
     */
    public static function detectExplicitRating(string $message): ?float
    {
        // "7/10" or "7 out of 10"
        if (preg_match('/\b(\d+)\s*(?:\/\s*10|out\s+of\s+10)\b/i', $message, $m)) {
            $val = (float) $m[1];
            return ($val >= 1 && $val <= 10) ? $val : null;
        }

        // "rating: 8" or "score: 6"
        if (preg_match('/\b(?:rating|score)\s*:?\s*(\d+(?:\.\d+)?)\b/i', $message, $m)) {
            $val = (float) $m[1];
            return ($val >= 1 && $val <= 10) ? $val : null;
        }

        // Standalone single/double digit at start of message: "3 - that was wrong"
        if (preg_match('/^(\d+(?:\.\d+)?)\s*[-–—]/', $message, $m)) {
            $val = (float) $m[1];
            return ($val >= 1 && $val <= 10) ? $val : null;
        }

        return null;
    }

    /**
     * Convert a sentiment score (0.0–1.0) to a 1–10 rating.
     */
    public static function sentimentToRating(float $sentiment): float
    {
        return round(1 + ($sentiment * 9), 1);
    }
}
