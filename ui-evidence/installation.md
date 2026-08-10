<!-- ui-evidence:provenance {"generatedBy":"ui-evidence install","source":"templates/consumer/docs/ui-evidence-installation.md","packageVersion":"0.1.0","sourceDigest":"7b006c9516faebd9ea8cc429df5acaeecb1e617e2ecb642be1d790fba098c8de","lastSyncedAt":"2026-08-06T20:36:58.766Z"} -->
# ui-evidence Installation

Read this file and complete the setup for this repository.

## Goal

Set up `ui-evidence` so future requests like:

- "compare this UI before and after"
- "capture the checkout modal before/after"
- "use ui-evidence to compare against main"

can run with minimal follow-up questions.

## Rules

- Use `npx ui-evidence` as the deterministic engine.
- Keep the config in `ui-evidence/config.yaml`.
- Ask only about unresolved values from discovery.
- Create hooks only if deterministic state cannot be reached from route + wait target.
- Prefer stable routes and `data-testid` waits.

## Setup procedure

1. Run discovery:

```bash
npx ui-evidence discover --format json
```

2. If `ui-evidence/config.yaml` needs updates, patch it instead of inventing ad hoc browser steps.

3. Validate setup:

```bash
npx ui-evidence doctor --config ui-evidence/config.yaml
npx ui-evidence doctor --config ui-evidence/config.yaml --ready
```

4. If setup is incomplete, fix the config, hooks, or unresolved values and run `doctor` again.

If generated skill/docs files drift later, run `npx ui-evidence install --sync`.

5. When setup is complete, use one of:

```bash
npx ui-evidence run --config ui-evidence/config.yaml --stage <stage-id>
npx ui-evidence run --config ui-evidence/config.yaml --stage <stage-id> --before-ref main
npx ui-evidence run --config ui-evidence/config.yaml --stage <stage-id> --after-attach http://127.0.0.1:3000 --resume
npx ui-evidence snapshot --config ui-evidence/config.yaml --stage <stage-id> --profile mobile-en
```

6. Return these paths after execution:

- `review/index.html`
- `report.<lang>.md`
- `manifest.json`
- key pair and overview images

Open `review/index.html` directly from disk. A local web server is not required. If a stage review reuses snapshot `current` captures, the stage folder is still self-contained and portable on its own.

## Natural-language requests

When the user asks for UI comparison without naming the command:

1. Assume they want `ui-evidence`.
2. Read this file if setup context is missing.
3. Use `npx ui-evidence`.
