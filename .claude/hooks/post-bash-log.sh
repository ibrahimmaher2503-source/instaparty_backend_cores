#!/usr/bin/env bash
# PostToolUse Bash logging hook for InstaParty
# Appends timestamp + command + exit code to .claude/bash-commands.log for traceability.
set -euo pipefail

INPUT=$(cat)
CMD=$(echo "$INPUT" | jq -r '.tool_input.command // ""')
EXIT_CODE=$(echo "$INPUT" | jq -r '.tool_response.exit_code // .tool_response.returncode // "?"')

[ -z "$CMD" ] && exit 0

LOG="${CLAUDE_PROJECT_DIR}/.claude/bash-commands.log"
mkdir -p "$(dirname "$LOG")"

# ISO timestamp for sortability
TS=$(date -u +"%Y-%m-%dT%H:%M:%SZ")

# Truncate very long commands so the log stays readable
SHORT_CMD=$(echo "$CMD" | head -c 500)

printf '%s [exit=%s] %s\n' "$TS" "$EXIT_CODE" "$SHORT_CMD" >> "$LOG"

# Trim log to last 1000 lines once it gets big
LINES=$(wc -l < "$LOG" 2>/dev/null || echo 0)
if [ "$LINES" -gt 2000 ]; then
  tail -n 1000 "$LOG" > "$LOG.tmp" && mv "$LOG.tmp" "$LOG"
fi

exit 0
