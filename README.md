# verdict-storefront

A clone-and-run storefront support agent demonstrating correct
[fissible/verdict](https://github.com/fissible/verdict) wiring end-to-end —
**models propose, applications authorize**. This app is the executable form of
Verdict's [adoption guide](https://github.com/fissible/verdict/blob/main/docs/adoption-guide.md):
when a question arises about how a piece fits, the answer is a file in this repo.

> **Status: scaffold.** The clone-and-run baseline below works today (Laravel 12 +
> Verdict v0.8.0 installed, migrated, `verdict:validate` advisory-free, CI green).
> The four demo walkthroughs are being built — see [PROJECT.md](PROJECT.md) for the
> dependency-ordered plan and
> [fissible/verdict#237](https://github.com/fissible/verdict/issues/237) for the spec.

## Five-minute start

PHP 8.3+ and Composer are the only requirements. No Node, no model API key, no database server.

```bash
git clone https://github.com/fissible/verdict-storefront.git
cd verdict-storefront
composer install
composer run setup   # .env, app key, SQLite database, migrations, synthetic seed data
php artisan serve
```

## What it demonstrates

Four walkthroughs, each ending at a visible evidence row:

1. **An allowed owned-order read** — a context-resolved capability scoped to the
   authenticated user: the model's argument is a filter, not a key.
2. **A denied cross-principal read** — the same capability refusing another
   customer's order, deterministically, regardless of what the model asks for.
3. **A denied injected-argument redirect** — a prompt-injection attempt from the
   shipped attack packs, denied at the boundary with the evidence to show for it.
4. **A confirmation pause → approval → exactly-once completion** — a refund that
   stops for a human, resumes through the approval screen, and completes exactly once.

## Two modes, one integrity rule

- **Replay (default, zero model dependencies).** An app-owned gateway drives the
  real laravel/ai pipeline from recorded model proposals. Everything downstream of
  the model is live: Verdict evaluation, the confirmation pause, the approval
  round-trip, and real evidence rows in SQLite.
- **Live (opt-in).** `DEMO_MODE=live` plus ordinary laravel/ai provider
  configuration — an Anthropic key, or local Ollama with a tools-capable model.

Replay is never presented as live: every page carries a mode banner, and replay
fixtures carry provenance (what produced the recorded proposal, when, and
under what prompt). This app demonstrates **Verdict's boundary and its evidence**,
not model susceptibility — susceptibility numbers live in Verdict's
[docs/evaluation.md](https://github.com/fissible/verdict/blob/main/docs/evaluation.md)
and are not restated here.

### Running live

```bash
# Anthropic (Laravel AI's default hosted provider)
DEMO_MODE=live AI_PROVIDER=anthropic ANTHROPIC_API_KEY=sk-ant-... php artisan serve

# Local Ollama — the model must report the `tools` capability
DEMO_MODE=live AI_PROVIDER=ollama DEMO_MODEL=qwen2.5:7b php artisan serve
```

The provider wiring is ordinary laravel/ai configuration (`config/ai.php`) — the
replay gateway is simply not installed when `DEMO_MODE=live`. Two caveats, both
inherited from Verdict's evaluation notes:

- **Ollama models must report the `tools` capability.** `gemma3:4b` reports only
  `completion` and will never call a tool; `gpt-oss:20b` reports `tools` and works.
- **An aligned model may refuse the bait on the "try to break it" page.** That
  refusal is a result, not a malfunction: it measures the model's alignment, while
  Verdict's denial is deterministic policy. This app uses no forcing techniques
  (no `tool_choice`, no prefill, no comply-instructions) in either mode.

## Versioning

This app pins **tagged Verdict releases only** — never `dev-main`. Each Verdict
release includes bumping this app, which is what makes it an upgrade-path fixture.
Current pin: `fissible/verdict:^0.8.0`.

## License

MIT — see [LICENSE.md](LICENSE.md).
