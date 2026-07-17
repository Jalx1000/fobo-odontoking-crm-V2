---
name: feature-development
description: Workflow command scaffold for feature-development in fobo-odontoking-crm-V2.
allowed_tools: ["Bash", "Read", "Write", "Grep", "Glob"]
---

# /feature-development

Use this workflow when working on **feature-development** in `fobo-odontoking-crm-V2`.

## Goal

Standard feature implementation workflow

## Common Files

- `packages/webkul/admin/src/resources/views/components/activities/*`
- `packages/webkul/admin/src/resources/views/components/attributes/edit/*`
- `packages/webkul/admin/src/resources/views/components/layouts/*`
- `**/*.test.*`
- `**/api/**`

## Suggested Sequence

1. Understand the current state and failure mode before editing.
2. Make the smallest coherent change that satisfies the workflow goal.
3. Run the most relevant verification for touched files.
4. Summarize what changed and what still needs review.

## Typical Commit Signals

- Add feature implementation
- Add tests for feature
- Update documentation

## Notes

- Treat this as a scaffold, not a hard-coded script.
- Update the command if the workflow evolves materially.