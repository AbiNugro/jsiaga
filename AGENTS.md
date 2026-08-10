## ui-evidence
<!-- ui-evidence:agents:begin -->
Use ui-evidence for UI before/after review in this repo.

- If setup is missing, read `ui-evidence/installation.md` and complete the installation.
- Prefer the local skill bundle in `.agents/skills/ui-evidence/` when the user asks for UI comparison, before/after screenshots, or review artifacts.
- Use `npx ui-evidence` as the deterministic engine.
- Persist config in `ui-evidence/config.yaml` and only create hooks when deterministic state requires them.
- Default human-facing output is `review/index.html`.
<!-- ui-evidence:agents:end -->
