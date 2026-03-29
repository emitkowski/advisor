<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use App\Models\Agent;

class AdvisorSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'agent_id',
        'title',
        'thread',
        'meta',
        'isc',
        'message_count',
        'input_tokens',
        'output_tokens',
        'avg_rating',
        'started_at',
        'ended_at',
        'learnings_extracted_at',
        'summary',
        'share_token',
    ];

    protected $casts = [
        'thread'                  => 'array',
        'meta'                    => 'array',
        'isc'                     => 'array',
        'input_tokens'            => 'integer',
        'output_tokens'           => 'integer',
        'started_at'              => 'datetime',
        'ended_at'                => 'datetime',
        'learnings_extracted_at'  => 'datetime',
        'avg_rating'              => 'decimal:2',
    ];

    protected $appends = ['cost_usd'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
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
     * Uses a transaction + row lock to safely read-modify-write the thread JSON.
     */
    public function addMessage(string $role, string $content): void
    {
        DB::transaction(function () use ($role, $content) {
            $fresh    = static::query()->lockForUpdate()->findOrFail($this->id);
            $thread   = $fresh->thread ?? [];
            $thread[] = [
                'role'      => $role,
                'content'   => $content,
                'timestamp' => now()->toISOString(),
            ];

            $this->update([
                'thread'        => $thread,
                'message_count' => DB::raw('message_count + 1'),
            ]);
        });
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

    /**
     * Atomically add token counts for a completed exchange.
     */
    public function accumulateTokens(int $input, int $output): void
    {
        if ($input > 0) {
            $this->increment('input_tokens', $input);
        }

        if ($output > 0) {
            $this->increment('output_tokens', $output);
        }
    }

    /**
     * Estimated cost in USD based on current model pricing.
     */
    public function getCostUsdAttribute(): float
    {
        $pricing = config('advisor.pricing.' . config('advisor.model'));

        if (!$pricing) {
            return 0.0;
        }

        return round(
            ($this->input_tokens / 1_000_000) * $pricing['input_per_million'] +
            ($this->output_tokens / 1_000_000) * $pricing['output_per_million'],
            4
        );
    }
}
