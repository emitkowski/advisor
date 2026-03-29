# Advisor — User Manual

## Overview

Advisor is a personal AI thinking partner that gets more useful over time. Unlike a standard chat assistant, it is built to challenge your thinking, not validate it. It remembers what it learns about you across sessions and uses that memory to give increasingly honest, calibrated feedback.

---

## Sessions

### Starting a Session

From the Sessions page, click **New Session**. A picker will open where you choose which agent to use. The default agent (The Advisor) is selected automatically. Pick a different one if you want a specific perspective, then click **Start Session**.

### Chatting

Type your message and press **Enter** (or **Shift+Enter** for a new line). The advisor streams its response in real-time.

Each advisor response has a thumbs up / thumbs down button beneath it. Use these to signal whether a response was useful — this is recorded and fed back into future sessions as part of your memory.

### Editing the Session Title

The title in the header is editable. Click it (a pencil icon appears on hover) to rename it inline. Press **Enter** to save or **Escape** to cancel.

### Ending a Session

When you're done, click **End Session** in the header. This closes the session and triggers background processing — the advisor will extract learnings, update your profile, identify any projects mentioned, and generate a summary. This usually takes 10–30 seconds.

While extraction runs, the session shows a "Processing…" indicator. Once complete, a session summary appears at the bottom.

> **Note:** You cannot send new messages after ending a session. Start a new one if you want to continue.

### Copying the Transcript

Click **Copy** in the header to copy the full conversation as formatted Markdown — title, agent, date, and all messages. Useful for pasting into notes or sharing as text.

---

## Sharing

### Creating a Share Link

Click the **Share** button in the session header to open the share popover.

- If the session hasn't been shared before, click **Create share link**. A read-only public URL is generated immediately.
- Copy the link and send it to anyone — they can read the conversation without logging in.
- The link works even for closed sessions.

### Revoking a Share Link

Open the share popover and click **Revoke link**. The URL immediately stops working for anyone who had it.

### What Recipients See

Shared sessions show the full conversation thread with markdown and code rendering, the session summary (if available), and a Copy as Markdown button. They cannot interact with the session.

The shared page includes link preview metadata (title, summary) for Slack, iMessage, and similar apps.

> **Shared sessions are read-only.** Recipients cannot reply or continue the conversation. If you want someone to pick up where you left off, use **Copy as Markdown** to copy the transcript and paste it as context into a new session.

---

## Agents

Agents define *who* is advising you — their identity, communication style, and how they think. The preset agents cover different perspectives; you can also create your own.

### Preset Agents

| Agent | Personality |
|---|---|
| **The Advisor** | Brutally honest generalist. Challenges assumptions, gives verdicts. |
| **Devil's Advocate** | Maximum skepticism. Assumes every idea is wrong until proven otherwise. |
| **Strategic Advisor** | Business and systems thinker. Focused on positioning and second-order effects. |
| **Technical Advisor** | Engineering focus. Evaluates tradeoffs, complexity, and long-term maintainability. |
| **Coach** | Growth and accountability. Tracks patterns, pushes for specific commitments. |
| **Samuel L. Jackson** | Jules Winnfield energy. Punchy, profane, devastating analogies. |

### Creating a Custom Agent

Go to **Agents → New Agent**.

- **Name & Description** — shown in the agent picker when starting a session
- **Badge Color** — the color dot shown next to the agent name in sessions
- **System Prompt Preamble** — defines the agent's identity and rules. This is the most important field. Write it in the second person ("You are..."). Memory context is always appended automatically after this.
- **Algorithm** — the cognitive process the agent uses before responding (e.g. "Phase 1: check prior art. Phase 2: find the kill shot..."). Optional but powerful for shaping how the agent thinks.
- **Personality Traits** — sliders from 0–100 that inject a traits block into the system prompt. Add trait names like `directness`, `skepticism`, `empathy`. The value and description are included literally in the prompt.

Click **Create Agent** to save. It appears immediately in the agent picker.

### Editing or Deleting an Agent

From the Agents list, click **Edit** to modify or **Delete** to remove. Deleting a preset agent is permanent — it won't be re-seeded automatically.

### Sharing an Agent with Your Team

When editing an agent, check **Share with team** to make it visible to all team members. Shared agents show a blue "shared" badge. Any team member can use a shared agent in their sessions, but only the creator can edit or delete it.

---

## What I Know About You

This page shows everything the advisor has learned about you across all sessions.

### Observed Traits

Stable characteristics inferred from your conversations — things like risk tolerance, how quickly you make decisions, or how often your excitement outpaces evidence. Each trait has a confidence level (Low / Medium / High) and an observation count showing how many sessions contributed to it.

Click the **×** next to any trait to remove it if it's wrong or outdated.

### Learnings

Categorized insights extracted from your sessions:

| Category | What it captures |
|---|---|
| **Blind Spots** | Gaps or weaknesses the advisor has noticed |
| **Patterns** | Recurring behaviours across conversations |
| **Follow-through** | How you act on commitments and plans |
| **Values** | What matters most to you |
| **Reactions** | How you respond to challenge or feedback |
| **Domain Knowledge** | Your technical or contextual expertise |

Click **×** to remove any learning.

### Projects

Every idea or initiative you've mentioned in sessions, automatically tracked with a status:

- **Active** — currently in progress
- **Paused** — mentioned but stalled
- **Completed** — shipped or finished
- **Abandoned** — dropped
- **Unclear** — not enough signal to classify

Projects are extracted automatically when you close a session — there is no manual entry. Mention a project name in conversation and close the session; it will appear here.

---

## Teams

Teams let you and a partner share agents and project context. Both people's team projects are injected into each other's system prompts, so the advisor knows what you're both working on.

### Creating a Team

Go to **Team** in the nav. If you don't have a team, enter a name and click **Create Team**.

### Inviting a Member

Once you have a team, enter your partner's email and click **Send Invite**. They'll receive an email with a link to accept. The invitation expires after 7 days; you can resend to generate a new one.

The invited person must have an account (or create one) and accept while logged in with the email the invitation was sent to.

### Managing Members

As the team owner you can remove members using the **Remove** button next to their name. Removed members lose access to shared agents.

### Disbanding a Team

At the bottom of the Team page, click **Disband Team**. This removes the team, clears all members, and unshares all shared agents and projects. It cannot be undone.

---

## How Memory Works

Every time you send a message, the system assembles a fresh system prompt that includes:

1. The agent's identity and rules
2. The agent's personality traits
3. Your profile observations (stable traits)
4. Your learnings by category
5. Your active and recent projects (including team projects if you're on a team)
6. The agent's algorithm

This means the advisor's responses are always informed by everything it has learned about you. The more sessions you run and close, the more accurate and targeted the feedback becomes.

Sessions that haven't been closed don't contribute to memory yet — the learning extraction only runs on close.
