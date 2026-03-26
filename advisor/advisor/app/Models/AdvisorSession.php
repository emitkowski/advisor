<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;

class AdvisorSession extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'thread',
        'meta',
        'isc',
        'message_count',
        'avg_rating',
        'started_at',
        'ended_at',
    ];

    protected $casts = [
        'thread'     => 'array',
        'meta'       => 'array',
        'isc'        => 'array',
        'started_at' => 'datetime',
        'ended_at'   => 'datetime',
        'avg_rating' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function signals(): HasMany
    {
        return $this->hasMany(Signal::class);
    }

    public function learnings(): HasMany
    {
        return $this->hasMany(Learning::class);
    }

    /**
     * Add a message to the thread and increment message count.
     */
    public function addMessage(string $role, string $content): void
    {
        $thread   = $this->thread ?? [];
        $thread[] = [
            'role'       => $role,
            'content'    => $content,
            'timestamp'  => now()->toISOString(),
        ];

        $this->update([
            'thread'        => $thread,
            'message_count' => $this->message_count + 1,
        ]);
    }

    /**
     * Get thread in Anthropic API message format (role + content only).
     */
    public function getApiMessages(): array
    {
        return collect($this->thread ?? [])
            ->map(fn($msg) => [
                'role'    => $msg['role'],
                'content' => $msg['content'],
            ])
            ->toArray();
    }

    /**
     * Mark the session as ended and compute final average rating.
     */
    public function close(): void
    {
        $avg = $this->signals()->avg('rating');

        $this->update([
            'ended_at'   => now(),
            'avg_rating' => $avg,
        ]);
    }

    public function isActive(): bool
    {
        return is_null($this->ended_at);
    }
}
