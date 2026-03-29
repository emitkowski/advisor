<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'team_id',
        'name',
        'description',
        'status',
        'notes',
        'mentions',
        'first_seen_at',
        'last_seen_at',
    ];

    protected $casts = [
        'mentions'     => 'array',
        'first_seen_at' => 'datetime',
        'last_seen_at'  => 'datetime',
    ];

    const STATUS_ACTIVE    = 'active';
    const STATUS_ABANDONED = 'abandoned';
    const STATUS_COMPLETED = 'completed';
    const STATUS_PAUSED    = 'paused';
    const STATUS_UNCLEAR   = 'unclear';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeAbandoned(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ABANDONED);
    }

    /**
     * Record that this project was mentioned in a session.
     */
    public function recordMention(int $sessionId): void
    {
        $mentions   = $this->mentions ?? [];
        $mentions[] = $sessionId;

        $this->update([
            'mentions'     => array_unique($mentions),
            'last_seen_at' => now(),
        ]);
    }

    /**
     * Build a project summary for system prompt injection.
     * Particularly highlights abandoned projects for follow-through tracking.
     */
    public static function buildProjectContext(int $userId, ?int $teamId = null): string
    {
        $projects = static::where(function ($q) use ($userId, $teamId) {
                $q->where('user_id', $userId);
                if ($teamId) {
                    $q->orWhere('team_id', $teamId);
                }
            })
            ->orderBy('last_seen_at', 'desc')
            ->limit(20)
            ->get()
            ->unique('name')
            ->groupBy('status');

        if ($projects->isEmpty()) {
            return '';
        }

        $lines = ["## Project history\n"];

        if ($projects->has('active')) {
            $lines[] = '**Active projects:**';
            foreach ($projects['active'] as $p) {
                $lines[] = "- {$p->name}" . ($p->description ? ": {$p->description}" : '');
            }
            $lines[] = '';
        }

        if ($projects->has('abandoned')) {
            $lines[] = '**Abandoned projects (use for follow-through assessment):**';
            foreach ($projects['abandoned'] as $p) {
                $lines[] = "- {$p->name}" . ($p->notes ? " — {$p->notes}" : '');
            }
            $lines[] = '';
        }

        if ($projects->has('completed')) {
            $lines[] = '**Completed projects:**';
            foreach ($projects['completed'] as $p) {
                $lines[] = "- {$p->name}";
            }
            $lines[] = '';
        }

        return implode("\n", $lines);
    }
}
