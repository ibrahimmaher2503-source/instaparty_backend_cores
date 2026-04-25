#!/usr/bin/env bash
# PreToolUse Bash firewall for InstaParty
# Blocks dangerous commands at the hook layer (defense in depth — settings.json deny list is the first line).
# Reads JSON {"tool_name": "Bash", "tool_input": {"command": "..."}} from stdin.
# Exit 2 + stderr message blocks the command and tells Claude why.
set -euo pipefail

INPUT=$(cat)
CMD=$(echo "$INPUT" | jq -r '.tool_input.command // ""')

# Empty command — let it through, the tool itself will complain
if [ -z "$CMD" ]; then
  exit 0
fi

block() {
  echo "BLOCKED by pre-bash-firewall: $1" >&2
  echo "Command: $CMD" >&2
  exit 2
}

# Catastrophic filesystem operations
if echo "$CMD" | grep -qE '\brm\s+(-[rRf]+\s+|.*\s+-[rRf]+)'; then
  case "$CMD" in
    *"rm -rf /"*|*"rm -rf /*"*|*"rm -rf ~"*|*"rm -rf \$HOME"*|*"rm -rf /root"*)
      block "destructive rm targeting root or home directory"
      ;;
  esac
  if echo "$CMD" | grep -qE 'rm\s+-[rRf]+\s+\.\.?\s*(\$|;|&&|\|\|)'; then
    block "rm -rf on . or .."
  fi
fi

# Git destructive operations
if echo "$CMD" | grep -qE 'git\s+push\s+(--force|-f\b|--force-with-lease)'; then
  block "force push — push manually after review"
fi
if echo "$CMD" | grep -qE 'git\s+reset\s+--hard'; then
  block "git reset --hard — use git stash or branch checkout instead"
fi
if echo "$CMD" | grep -qE 'git\s+clean\s+-[fdx]'; then
  block "git clean -fdx — would delete untracked files"
fi

# Secrets — never read .env via shell
if echo "$CMD" | grep -qE '\b(cat|grep|less|head|tail|bat|cp|mv)\s+.*\.env'; then
  block "do not read .env files — use config() helper or describe via plain text"
fi
if echo "$CMD" | grep -qE 'echo\s+.*>>?\s*\.env'; then
  block "do not write to .env — ask Ibrahim to add the variable manually"
fi

# Database destructive
if echo "$CMD" | grep -qiE '\b(DROP\s+TABLE|DROP\s+DATABASE|TRUNCATE\s+TABLE)\b'; then
  block "destructive SQL — use Eloquent / migrations instead"
fi
if echo "$CMD" | grep -qE 'php\s+artisan\s+(migrate:fresh|migrate:reset|db:wipe)'; then
  block "destructive artisan command — use migrate:rollback on a single migration if needed, or ask first"
fi

# Network egress
if echo "$CMD" | grep -qE '^\s*(curl|wget|http|httpie)\s'; then
  block "network egress disabled — Ibrahim runs network commands manually"
fi

# Composer destructive
if echo "$CMD" | grep -qE 'composer\s+remove\b'; then
  block "composer remove — confirm with Ibrahim before removing dependencies"
fi

# Tinker - too unpredictable to allow without supervision
if echo "$CMD" | grep -qE 'php\s+artisan\s+tinker'; then
  block "php artisan tinker is interactive — write a one-off script in /tmp instead, or ask Ibrahim to run it"
fi

# All clear
exit 0
