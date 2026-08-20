# verdict-storefront — Project roadmap

Clone-and-run reference application for [fissible/verdict](https://github.com/fissible/verdict).
**Spec (source of truth):** [fissible/verdict#237](https://github.com/fissible/verdict/issues/237)
and its design comment (replay by default, live by opt-in). This file tracks execution, not design.

**Current version:** 0.1.0 (unreleased). Release process per
[fissible/.github](https://github.com/fissible/.github) — `bash release.sh`.

## Standing constraints

- **Pins tagged Verdict releases only** — never `dev-main`. Each Verdict release bumps this app;
  that is what makes it an upgrade-path fixture.
- **PHP + Composer only** on the clone-and-run path: no Node toolchain, no model key, no database
  server. Server-rendered Blade, SQLite. CI's `clone-and-run` job is this criterion, executable.
- **The app is documentation.** Every Verdict touchpoint carries a comment linking the doc section
  it demonstrates. Code style follows the package (pint enforced in CI).
- **Integrity:** replay is never presented as live (mode banner on every page); replay fixtures
  carry provenance; no forcing techniques anywhere, in either mode; synthetic, reversible seed
  data only.
- `verdict:validate` must stay advisory-free — enforced in CI from wave 0.

## Decisions

| Date | Decision | Rationale |
|---|---|---|
| 2026-08-19 | Repo name: `verdict-storefront` (over `verdict-demo`) | The spec's domain is the name: a storefront support agent, the same domain the attack packs and recorded evaluations use. It is a long-lived reference app and upgrade fixture, not a throwaway demo; "demo"/"reference app" live in the description and keywords for discoverability. |
| 2026-08-19 | Tracked as verdict v0.9.0 work under [verdict#237](https://github.com/fissible/verdict/issues/237); no separate portfolio ROADMAP.md product row | verdict self-tracks in its MILESTONES.md (v0.9.0 table already carries #237); the portfolio ROADMAP does not track verdict, so a row for its reference app alone would be inconsistent. Flagged to PM. |
| 2026-08-19 | `DatabaseEvidenceRecorder` configured from wave 0 | Acceptance requires an advisory-free `verdict:validate`, and every walkthrough ends at an evidence row — the durable recorder is not optional here. |
| 2026-08-19 | Node toolchain removed from the skeleton | "PHP + Composer only" is an acceptance criterion. Blade renders server-side; no build step exists to skip. |
| 2026-08-19 | MIT license | Matches fissible/verdict. |

## Task list (dependency order, leaves → roots)

Effort key: XS (<1h), S (1–2h), M (~half day), L (~1 day), XL (2–3 days).

| # | Task | Effort | Deps | Status |
|---|---|---|---|---|
| — | Wave 0: scaffold, CI, org wiring | M | v0.8.0 tag ✅ | **Done** (2026-08-19) |
| [#1](https://github.com/fissible/verdict-storefront/issues/1) | Wave 1: storefront domain + synthetic seed data | M | none | **Done** (2026-08-19) |
| [#2](https://github.com/fissible/verdict-storefront/issues/2) | Wave 2: context-resolved owned-order lookup capability | M | #1 | **Done** (2026-08-19) |
| [#3](https://github.com/fissible/verdict-storefront/issues/3) | Wave 2: confirmation-gated refund/cancel capability | M | #1 | **Done** (2026-08-19) |
| [#4](https://github.com/fissible/verdict-storefront/issues/4) | Wave 3: agent + ReplayGateway (replay default) | L | #2, #3 | **Done** (2026-08-19) |
| [#5](https://github.com/fissible/verdict-storefront/issues/5) | Wave 3: live mode opt-in (`DEMO_MODE=live`) | S | #4 | **Done** (2026-08-19) |
| [#6](https://github.com/fissible/verdict-storefront/issues/6) | Wave 4: support chat UI + mode banner | M | #4 | **Done** (2026-08-19) |
| [#7](https://github.com/fissible/verdict-storefront/issues/7) | Wave 4: approval screen + exactly-once resume | M | #3, #4 | **Done** (2026-08-19) |
| [#8](https://github.com/fissible/verdict-storefront/issues/8) | Wave 4: evidence browser (read-only) | M | #4 | **Done** (2026-08-19) |
| [#9](https://github.com/fissible/verdict-storefront/issues/9) | Wave 4: "try to break it" page | M | #4, #6 | **Done** (2026-08-19) |
| [#10](https://github.com/fissible/verdict-storefront/issues/10) | Wave 5: README walkthroughs + acceptance pass | M | #2–#9 | **Done** (2026-08-19) |

Within a wave, order by smallest-first; #2 before #3 (the owned-order lookup is the headline
pattern and #4's fixtures want it stable first). Closing #10 closes verdict#237.

## Session handoff notes

**2026-08-19 — #11 complete: fixtures are genuine model recordings.**
- All five replay fixtures re-recorded from `huihui_ai/qwen2.5-abliterate:7b` on local Ollama
  via the new committed `demo:record-replays` command (requires `DEMO_MODE=live`; validates
  each capture against the demo's expected shape and retries — the RAG scenario took 4
  sampled attempts; `--only=<file>` records one fixture). **Run this after every Verdict pin
  bump** — it is the upgrade-fixture tool.
- The RAG-borne scenario's prompt now carries the "retrieved" note inline (single
  instruction), labeled as standing in for a RAG channel — the app has no retrieval tool and
  faking one would misrepresent what is live.
- Recording exposed and fixed a real live-mode bug: `ResumesApprovedConversations` (and the
  command's own resume) must pass `model:` — without it a live resume falls back to the
  provider's default model.
- Brittle phrasing assertions relaxed (a real model's denial wording varies; evidence rows
  are the assertions). Suite: 57 tests / 177 assertions on the recorded fixtures.

**2026-08-19 — #10 complete: milestone shipped.**
- README rewritten around the four walkthroughs, each naming the click, the expected reply,
  and the exact evidence row, with the mode-banner check up front. Scaffold status banner
  removed. Acceptance verified against a REAL fresh clone from GitHub (see verdict#237
  closing comment for the measured timing and the acceptance mapping).
- All ten issues closed. Remaining known debt: delete
  `App\Verdict\PreMigrationTolerantConfigurationStore` when verdict#240 ships.
  (~~Re-record fixtures from a live model~~ — done as #11, 2026-08-19.)
- Next natural work (not scheduled): cut v0.1.0 via `bash release.sh`; hosted replay-mode
  demo idea is parked (owner said hold off — see 2026-08-19 conversation).

**2026-08-19 — #9 complete: the attack page is live.**
- `/attack`: three scenarios from the shipped packs (cross-principal lookup, injected-argument
  redirect, RAG-borne instruction), each with the injection prompt, a what-to-watch note, and a
  one-click send through the ordinary chat path — no special handling, no forcing techniques.
  Two new replay fixtures added (honest authored-captured-proposal provenance; the RAG one says
  explicitly what it represents). In live mode the page carries the refusal-is-a-result note.
- Both new attacks proven denied: no refund row, deny evidence for orders.refund.
- **Next task: #10** — README walkthroughs + acceptance pass; closing it closes verdict#237.

**2026-08-19 — #8 complete: evidence is visible without a database client.**
- `/evidence` (any authenticated demo user): decision evidence (recorded_at, capability,
  stage + approval phase, disposition, reason, target strategy + identity match), approval
  receipts (status/consumed, decided by), and provenance derivations. Read-only by
  construction — no mutating routes exist. Nav links added to the layout.
- **Next task: #9** (try-to-break page), then #10 closes the milestone.

**2026-08-19 — #7 complete: the approval round-trip is clickable.**
- Built from the `verdict:make-approval-flow` skeletons with every TODO answered: reviewer
  authorization is the `review-approvals` gate (new seeded reviewer Sam Reyes,
  `users.is_reviewer`); receipt/conversation ownership is verified against the app's own
  conversation store; the resume runs only after the durable transition succeeds.
- `/approvals` composes Verdict's challenge (capability, reason, expiry — no raw arguments by
  design) with app-owned display context (arguments + customer from the conversation store).
- **The resume runs as the conversation's participant, not the reviewer** —
  `ResumesApprovedConversations` + SupportAgent's context callable using `conversationUser`;
  otherwise the customer-scoped refresh would deny inside the reviewer's empty order scope.
- A second decision submission surfaces as an outcome (`already_resolved` /
  ApprovalMismatchException), never a second refund. Reject resumes with `Decision::reject()`
  so the customer's chat shows the decline.
- **Next task: #8** (evidence browser), then #9 (try-to-break page), #10 (README walkthroughs).

**2026-08-19 — #6 complete: the app has a face.**
- Landing page with one-click demo sign-in (Alice/Bruno — demo-only pattern, commented as such);
  authenticated /chat renders the durable conversation (messages via SQLite `rowid` for
  same-second ordering), shows each assistant turn's proposed tool calls, offers the recorded
  walkthrough prompts as buttons plus free-form input, and a persistent
  "waiting for human approval" notice while a receipt is pending.
- The mode banner is in the layout — every page carries it: replay (with a provenance link)
  vs live (naming model + provider, with the refusal-is-a-result note). Blade only, no Node.
- Smoke-tested through `php artisan serve` + curl on a fresh-clone database.
- **Next task: #7** (approval screen + exactly-once resume). The chat notice already points
  there; the resume mechanics are proven in SupportAgentReplayTest — the screen wraps
  `challengeForToolCall` → `approve`/`reject` → `continueLastConversation` with the per-call
  Decision, and must treat a second submission as an error (ApprovalMismatchException).

**2026-08-19 — #5 complete; live mode VALIDATED against a real model.**
- `DEMO_MODE=live` routes to real providers (the replay gateway binds only in replay mode —
  landed with #4). README documents both live paths (Anthropic key; Ollama with a
  tools-capable model), the gemma3:4b `completion`-only caveat, the refusal-is-a-result note,
  and the no-forcing-techniques guarantee. `config('demo.live_model')` / `DEMO_MODEL` is the
  live model knob (required for Ollama) — #6's controller should pass it to `prompt(model: ...)`.
- **Validated live against local Ollama** (`huihui_ai/qwen2.5-abliterate:7b`): owned lookup →
  real tool call through Verdict, permit evidence, answer composed from the real disclosure;
  cross-principal lookup → Verdict deny evidence row, model relayed the failure. Live mode is
  not CI-testable (no model on runners) — this manual validation is the record.
- **Next task: #6** (chat UI + mode banner).

**2026-08-19 — #4 complete: the replay pipeline runs end-to-end.**
- `SupportAgent` (conversational — required: laravel/ai throws ApprovalNotResumableException
  for approval pauses on non-conversational agents) with both capabilities bound via
  `VerdictManager::bound()` and a callable ActionContext resolving `Auth::user()` fresh per
  invocation. `VerdictApprovalMiddleware` registered on the agent.
- `ReplayGateway` (app-owned `StepTextGateway`) drives the real pipeline from
  `resources/replays/*.json`; installed via `Ai::textProvider(...)->useTextGateway()` only when
  `DEMO_MODE=replay` (the default, config/demo.php). Unrecorded prompts get an honest notice.
  Gateway matches history by message *role* (resume rehydration is not UserMessage instances).
- **Fixture provenance is honest:** no model recording exists yet, so fixtures say "authored
  captured proposal" — re-record from a live model when one is run, then update provenance.
- Proven at agent level: owned lookup → permit evidence; cross-principal → deny evidence, no
  execution; refund → pending receipt, no refund row; approve + resume with per-call Decision →
  exactly-once; replayed decision → laravel/ai ApprovalMismatchException (UI must treat a second
  submit as an error). Executors return JSON strings (bound tools require string results).
- laravel/ai config + conversations migration published; `AI_PROVIDER` defaults to anthropic;
  conversation titles off (a title turn would hit the replay gateway).
- **Next task: #5** (live mode) is now mostly documentation — `DEMO_MODE=live` already routes to
  real providers since the gateway binds only in replay mode. Then #6 (chat UI + mode banner).

**2026-08-19 — #3 complete (wave 2 done).**
- `orders.refund` shipped TDD as generator output filled in: scoped resolver (same #192
  discipline as lookup), `requiresConfirmation` binding customer/order/status/amount/reason,
  `ExecutionTargetPolicy::refresh`, `atMostOnce` claim, executor creates the Refund row and
  marks the order Refunded. `OrderPolicy::refund` allows owner + delivered only.
- The pause → approve → exactly-once round-trip is proven at capability level: the resume must
  run inside `ApprovalManager::withinApprovedToolCalls()` with the human's per-call
  `Decision::approve()` — a receipt alone never executes. #7's approval screen builds on this.
- `AppServiceProvider` registers the approver-route `ReleasePolicy`
  (`ApproverAudience::source() → destination()`, Internal data, any trust) — without it,
  `verdict:validate` advises that approvers see no provenance.
- Test-suite gotcha: `RefreshDatabase`'s wrapping transaction trips Verdict's
  `UnsafeOuterTransaction` guard on receipt mutations — approval tests use
  `DatabaseMigrations`. Filed upstream as a docs/testing.md gap.
- **Next task: #4** (agent + ReplayGateway, replay default) — the wave-3 keystone.

**2026-08-19 — #2 complete; upstream bug verdict#240 found and worked around.**
- `orders.lookup` shipped TDD as `verdict:make-capability` output filled in:
  `App\Capabilities\Orders\LookupCapability` (affirmed) — the proposed `order_number` is a
  filter within the authenticated customer's own orders, never a global key; outside the scope
  it throws `TargetNotResolvable`, which Verdict records as a **deny** evidence row (that is the
  walkthrough's denied cross-principal read). `ExecutionTargetPolicy::refresh` re-loads through
  the same scoped query; `App\Policies\OrderPolicy` is the fail-closed second layer.
- **Upstream bug found (the integration-fixture role working):**
  [verdict#240](https://github.com/fissible/verdict/issues/240) — boot-time configuration
  recording breaks any artisan command (including `migrate` and `key:generate`) on a fresh
  database once a capability is affirmed. Worked around by
  `App\Verdict\PreMigrationTolerantConfigurationStore` (configured in `config/verdict.php`);
  **delete the class and revert `capability_configurations.store` to null when #240 ships.**
- **Next task: #3** (confirmation-gated refund/cancel). Reuse the scoped-resolver shape; add
  `requiresConfirmation()` + execution-target policy per verdict#230/#231; ORD-1001 (delivered,
  Alice's) is the refund walkthrough's order.

**2026-08-19 — Wave 1 (#1) complete.**
- Domain shipped TDD: `Product`, `Order` (+`OrderStatus` enum), `OrderItem`, `Refund`, one
  migration, factories, deterministic idempotent `DatabaseSeeder` (13 tests, 33 assertions).
- Fixed identifiers the walkthroughs and replay fixtures reference: customers
  `alice@example.com` (Alice Storey — the authenticated demo user) and `bruno@example.com`
  (Bruno Marchetti — the cross-principal target); Alice owns ORD-1001 (delivered), ORD-1002
  (shipped), ORD-1003 (paid); Bruno owns ORD-2001 (delivered), ORD-2002 (paid). Refunds are
  never seeded — they exist only as the confirmation-gated capability's outcome.
- **Next task: #2** (context-resolved owned-order lookup capability). Shape it as
  `verdict:make-capability` output; ORD-2001 is the denied cross-principal read's target.

**2026-08-19 — Wave 0 complete.**
- Repo created (public), scaffold pushed: Laravel 12, `fissible/verdict:^0.8.0` (resolved v0.8.0,
  laravel/ai v0.10.3), config + migrations published, `DatabaseEvidenceRecorder` wired,
  `verdict:validate` advisory-free, pint clean, tests green, `composer run setup` is the whole
  README path.
- Issues #1–#10 created (this table). **Next task: #1.**
- `FISSIBLE_PAT` secret is **not set** on this repo — deliberately. All dependencies are public
  (Packagist), so CI needs no auth. If a private dependency ever appears, add the secret manually
  per fissible/.github README §"Wiring up a new fissible repo" step 6 (it is per-repo, never
  copied automatically).
- Verdict-side XS item from the design comment (classify `StepTextGateway` in
  docs/laravel-ai-compatibility.md) filed in fissible/verdict — see verdict#237 thread.
