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
| [#1](https://github.com/fissible/verdict-storefront/issues/1) | Wave 1: storefront domain + synthetic seed data | M | none | open |
| [#2](https://github.com/fissible/verdict-storefront/issues/2) | Wave 2: context-resolved owned-order lookup capability | M | #1 | open |
| [#3](https://github.com/fissible/verdict-storefront/issues/3) | Wave 2: confirmation-gated refund/cancel capability | M | #1 | open |
| [#4](https://github.com/fissible/verdict-storefront/issues/4) | Wave 3: agent + ReplayGateway (replay default) | L | #2, #3 | open |
| [#5](https://github.com/fissible/verdict-storefront/issues/5) | Wave 3: live mode opt-in (`DEMO_MODE=live`) | S | #4 | open |
| [#6](https://github.com/fissible/verdict-storefront/issues/6) | Wave 4: support chat UI + mode banner | M | #4 | open |
| [#7](https://github.com/fissible/verdict-storefront/issues/7) | Wave 4: approval screen + exactly-once resume | M | #3, #4 | open |
| [#8](https://github.com/fissible/verdict-storefront/issues/8) | Wave 4: evidence browser (read-only) | M | #4 | open |
| [#9](https://github.com/fissible/verdict-storefront/issues/9) | Wave 4: "try to break it" page | M | #4, #6 | open |
| [#10](https://github.com/fissible/verdict-storefront/issues/10) | Wave 5: README walkthroughs + acceptance pass | M | #2–#9 | open |

Within a wave, order by smallest-first; #2 before #3 (the owned-order lookup is the headline
pattern and #4's fixtures want it stable first). Closing #10 closes verdict#237.

## Session handoff notes

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
