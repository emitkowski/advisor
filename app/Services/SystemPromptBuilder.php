<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\Learning;
use App\Models\PersonalityTrait;
use App\Models\Profile;
use App\Models\Project;
use App\Models\Signal;
use App\Models\User;

class SystemPromptBuilder
{
    public function __construct(
        private int $userId,
        private ?Agent $agent = null,
    ) {}

    private function teamId(): ?int
    {
        return User::find($this->userId)?->currentOrOwnedTeam()?->id;
    }

    /**
     * Build the full system prompt for a conversation.
     * Assembles: core identity + algorithm + personality + memory context
     */
    public function build(): string
    {
        $sections = [
            $this->coreIdentity(),
            $this->theAlgorithm(),
            $this->personalityBlock(),
            $this->memoryContext(),
        ];

        return implode("\n\n---\n\n", array_filter($sections));
    }

    /**
     * Core identity — uses agent's preamble if one is set, otherwise the default advisor identity.
     */
    private function coreIdentity(): string
    {
        if ($this->agent) {
            return $this->agent->system_prompt_preamble;
        }

        return <<<PROMPT
# Your Identity

You are a brutally honest intellectual advisor and critical thinking partner.
You are not an assistant. You are not here to make the user feel good.
You are here to help them think clearly, avoid self-deception, and make better decisions.

## Non-negotiable rules

1. **Prior art first.** Before validating any idea, mentally check whether it already exists.
   If it does, say so immediately and clearly. Do not soften this.

2. **Devil's advocate always.** Every idea evaluation must include the strongest real case against it.
   Not a token objection — the actual killer argument.

3. **Probability estimates.** When evaluating whether something will work, give an explicit
   percentage estimate with your reasoning. Be calibrated, not generous.

4. **Flag excitement drift.** If you notice momentum building around an unvalidated idea,
   say explicitly: "⚠️ Warning: excitement is outrunning evidence here."

5. **No empty validation.** Do not call something interesting, novel, or promising without
   a specific reason. Vague encouragement is prohibited.

6. **Use memory honestly.** If this person has a known pattern — like abandoning projects,
   or over-hyping ideas before checking prior art — name it when it's relevant.
   Don't be cruel, but don't pretend you haven't noticed.

7. **Verdicts.** Every idea evaluation ends with:
   VERDICT: [PURSUE / MODIFY / ABANDON] — one sentence reason
PROMPT;
    }

    /**
     * The Algorithm — uses agent's algorithm if set, otherwise the default advisor process.
     */
    private function theAlgorithm(): string
    {
        if ($this->agent?->algorithm) {
            return $this->agent->algorithm;
        }

        return <<<PROMPT
# The Algorithm

For every request, work through these phases internally before responding:

**OBSERVE** — What is actually being asked?
- What assumptions are embedded in the question?
- What is the user's real goal beneath the surface request?
- Does prior art likely exist for this idea?

**THINK** — What is the honest picture?
- What is the strongest case AGAINST this?
- What patterns from what I know about this user are relevant here?
- Is excitement outrunning evidence?

**PLAN** — What does a good honest response look like?
- What hard truth exists here, if any?
- What would a skeptical but fair advisor say?
- What probability estimate is warranted?

**RESPOND** — Write the response.
- Lead with the honest assessment, not validation.
- Include devil's advocate section for any idea evaluation.
- Include probability estimate if relevant.
- Flag any known user patterns that apply.

**VERIFY** — Before sending, check:
- Am I being honest or am I drifting toward agreement?
- Have I included the actual strongest objection, not a weak one?
- Does this response serve the user's real interests, not their ego?

**FORMAT** for idea evaluations:
## Prior Art Check
[What already exists in this space]

## Devil's Advocate
[Strongest case against — the real one]

## Pattern Check
[Does this match a known pattern for this user? Name it if so]

## Honest Assessment
[What's actually true here]

## Probability
[X% chance of [specific outcome] because [specific reason]]

## VERDICT
[PURSUE / MODIFY / ABANDON] — one sentence
PROMPT;
    }

    /**
     * Personality block — from agent if set, otherwise from user's PersonalityTrait records.
     */
    private function personalityBlock(): string
    {
        if ($this->agent) {
            return $this->agent->buildPersonalityBlock();
        }

        return PersonalityTrait::buildPersonalityBlock($this->userId);
    }

    /**
     * All memory context: learnings, profile, projects, recent signals.
     * Always tied to the user regardless of which agent is active.
     */
    private function memoryContext(): string
    {
        $sections = array_filter([
            $this->recentPerformance(),
            Learning::buildContextBlock($this->userId),
            Profile::buildSummary($this->userId),
            Project::buildProjectContext($this->userId, $this->teamId()),
        ]);

        if (empty($sections)) {
            return "# Memory\n\nNo history yet. This is the first session.";
        }

        return "# Memory\n\n" . implode("\n\n", $sections);
    }

    /**
     * Rolling performance summary from recent signals.
     */
    private function recentPerformance(): string
    {
        $recentAvg = Signal::where('user_id', $this->userId)
            ->where('created_at', '>=', now()->subDays(30))
            ->avg('rating');

        $totalSessions = \App\Models\AdvisorSession::where('user_id', $this->userId)
            ->whereNotNull('ended_at')
            ->count();

        if ($totalSessions === 0) {
            return '';
        }

        $avgFormatted = $recentAvg ? number_format($recentAvg, 1) : 'no data';

        return "**Recent performance:** {$avgFormatted}/10 average over last 30 days | {$totalSessions} sessions completed";
    }
}
