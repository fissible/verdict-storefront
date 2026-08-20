# verdict-storefront

A clone-and-run storefront support agent demonstrating correct
[fissible/verdict](https://github.com/fissible/verdict) wiring end-to-end —
**models propose, applications authorize**. This app is the executable form of
Verdict's [adoption guide](https://github.com/fissible/verdict/blob/main/docs/adoption-guide.md):
when a question arises about how a piece fits, the answer is a file in this repo.

> Spec: [fissible/verdict#237](https://github.com/fissible/verdict/issues/237).
> Build history: [PROJECT.md](PROJECT.md). CI runs the exact clone-and-run path below
> on every push and fails on any `verdict:validate` advisory.

## Five-minute start

PHP 8.3+ and Composer are the only requirements. No Node, no model API key, no database server.

```bash
git clone https://github.com/fissible/verdict-storefront.git
cd verdict-storefront
composer install
composer run setup   # .env, app key, SQLite database, migrations, synthetic seed data
php artisan serve
```

## The four walkthroughs

Every page carries a **mode banner** — check it first: in replay mode the model's
proposals are recorded fixtures and everything downstream (Verdict, approvals,
evidence) is live. Each walkthrough ends at a visible evidence row.

Start at `http://127.0.0.1:8000` and sign in as **Alice Storey**.

**1. An allowed owned-order read.** In chat, click *"Where is my order ORD-1001?"*.
The agent's turn shows the proposed tool call (`LookupOrderTool({"order_number":"ORD-1001"})`)
and the reply describes Alice's delivered order. Open **evidence**: the top rows are
`orders.lookup` / **permit** at the proposal and execution stages, with target strategy
`refresh ✓` — the executor acted on a re-loaded row, resolved inside Alice's own orders.
The model's argument was a filter within her authority, never a global key.

**2. A denied cross-principal read.** Click *"Show me the details of order ORD-2001."*
(Bruno's order). The reply refuses. In **evidence**: `orders.lookup` / **deny** with
reason *"Capability target could not be resolved."* — outside Alice's scope the target
does not resolve, so the denial happened before the policy layer was even needed, and
it was recorded, not swallowed.

**3. A denied injected-argument redirect.** Open **try to break it** and send
*Injected-argument redirect*. The proposed tool call visibly carries the injected
number (`ORD-2002`) — and **evidence** shows `orders.refund` / **deny**, with no refund
row anywhere. An injected instruction can influence *what is proposed*, never *what is
reachable*.

**4. Confirmation pause → approval → exactly-once completion.** In chat, click
*"Please refund order ORD-1001 — it arrived damaged."* The chat shows the action
**waiting for human approval** and nothing has moved (no refund row in evidence — but a
**pending** approval receipt). Sign out, sign in as **Sam Reyes (reviewer)**, open
**approvals**: the challenge shows the capability, the binding reason, and the
application's display context. Click **Approve**. Back as Alice: the conversation has
resumed and completed. In **evidence**: the receipt is **consumed** by `user:<sam>`,
`orders.refund` shows permit rows through the approval phases, and there is exactly one
refund. Submitting the decision a second time reports an outcome error — never a second
refund.

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
