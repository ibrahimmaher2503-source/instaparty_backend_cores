#!/usr/bin/env bash
# SessionStart hook for InstaParty
# Injects current git context + spec hierarchy reminder into Claude's session.
# Output JSON on stdout with `additionalContext` field gets prepended to the session.
set -euo pipefail

cd "${CLAUDE_PROJECT_DIR:-$(pwd)}" 2>/dev/null || exit 0

# Gracefully handle non-git directories
if ! git rev-parse --git-dir >/dev/null 2>&1; then
  exit 0
fi

BRANCH=$(git branch --show-current 2>/dev/null || echo "detached")
LAST_COMMIT=$(git log -1 --pretty=format:'%h %s (%ar)' 2>/dev/null || echo "no commits")
DIRTY=$(git status --porcelain 2>/dev/null | wc -l | tr -d ' ')
DIRTY_NOTE=""
if [ "$DIRTY" -gt 0 ]; then
  DIRTY_NOTE=" — $DIRTY uncommitted change(s)"
fi

# Build the context message. jq -Rs builds a properly escaped JSON string.
CONTEXT=$(cat <<EOF
=== InstaParty Session Context ===
Branch: $BRANCH$DIRTY_NOTE
Last commit: $LAST_COMMIT

Source-of-truth hierarchy (top wins):
  1. docs/specs/01_PRD.md
  2. CLAUDE.md
  3. docs/specs/03_Three_Product_Types.md
  4. docs/specs/02_Tech_Decisions.md
  5. docs/specs/05_Software_Description.md
  6. Conversation

Reminders:
  - Three product types are first-class (rental/sale/digital). Every type-aware feature covers all three.
  - Money: Brick\\Money + integer minor units. Never floats.
  - Use match(\$enum), never if/elseif on type strings.
  - Phase 2 features are out of scope (subscription tiers, packages, dispute engine, card templates, page slider, QR catalog).
EOF
)

# Output JSON on stdout — Claude Code reads `additionalContext`
jq -nc --arg ctx "$CONTEXT" '{hookSpecificOutput: {hookEventName: "SessionStart", additionalContext: $ctx}}'
exit 0
