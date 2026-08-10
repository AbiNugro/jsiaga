<!-- ui-evidence:provenance {"generatedBy":"ui-evidence install","source":"templates/consumer/.claude/commands/ui-evidence.md","packageVersion":"0.1.0","sourceDigest":"300311a731ad918005920ace977e3586a8d3f41b6d1167f231f6e4a460520d99","lastSyncedAt":"2026-08-06T20:36:58.768Z"} -->
---
description: Configure or run ui-evidence for before/after UI review in this repository
---

If setup looks incomplete, read `ui-evidence/installation.md` and finish the setup first.

Then:

1. Run `npx ui-evidence discover --format json`.
2. Ask only about unresolved values.
3. Persist config in `ui-evidence/config.yaml`.
4. Run `npx ui-evidence doctor --config ui-evidence/config.yaml`.
5. Run `npx ui-evidence run --config ui-evidence/config.yaml ...` or the manual capture/compare/report/review sequence.
6. Return:
   - `review/index.html`
   - `report.<lang>.md`
   - `manifest.json`
   - pair and overview image paths
