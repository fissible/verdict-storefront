# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Commit messages follow [Conventional Commits](https://www.conventionalcommits.org/en/v1.0.0/).

## [Unreleased]

### Changed
- Bump `fissible/verdict` to `^0.9.2` (from `^0.8.0`, also pulling `laravel/ai` v0.11) — the first
  upstream bump this app absorbs, and one that breaks it by design: verdict v0.9.1 changed
  `CapabilityConfigurationStore::record()` from `void` to `bool`, making the #240 workaround store
  unrepresentable. Absorbing the bump also did the fixture's job a third time: the fresh-clone
  gauntlet caught verdict#256 (the upstream guard died when the SQLite file itself did not exist
  yet), fixed in verdict v0.9.2, which this constraint targets.

### Removed
- `App\Verdict\PreMigrationTolerantConfigurationStore`, its test, and the config override — back to
  the stock `capability_configurations.store => null` default. The fresh-clone path
  (`composer install` -> `composer run setup` -> `verdict:validate`) is verified clean against
  v0.9.2 with the configuration audit rows recorded on the first post-migration boot. (#12)
## [0.2.0] - 2026-08-20

### Added
- Re-record replay fixtures from a real model (demo:record-replays)
## [0.1.0] - 2026-08-20

### Added
- Scaffold clone-and-run baseline — Laravel 12 + Verdict v0.8.0, advisory-free
- Storefront domain — models, migrations, deterministic synthetic seed data
- Context-resolved owned-order lookup capability (orders.lookup)
- Confirmation-gated refund capability (orders.refund)
- Support agent + ReplayGateway — replay mode runs the real pipeline end-to-end
- Live mode opt-in — validated against a real local model
- Support chat UI with mode banner
- Approval screen and exactly-once resume
- Read-only evidence browser
- 'try to break it' page with attack-pack prompts

### Fixed
- Pin composer platform to php 8.3.0 so the lock installs on every supported PHP

