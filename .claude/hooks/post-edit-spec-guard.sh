#!/usr/bin/env bash
# PostToolUse Edit/MultiEdit/Write spec-guard hook for InstaParty
# Warns Claude (non-blocking) when newly written code violates locked architectural rules.
# This is "reminder mode" — it doesn't fail the edit, just nudges Claude to fix it next.
set -euo pipefail

INPUT=$(cat)
FILE=$(echo "$INPUT" | jq -r '.tool_input.file_path // .tool_input.path // ""')

# Only PHP files matter for these rules
case "$FILE" in
  *.php) ;;
  *) exit 0 ;;
esac

[ -f "$FILE" ] || exit 0

# Skip generated / vendor files
case "$FILE" in
  */vendor/*|*/node_modules/*|*/storage/*|*/bootstrap/cache/*) exit 0 ;;
esac

WARNINGS=()

# 1) Money: float / decimal cast on *_minor columns or in Money-named code
if grep -nE "(\\\$casts.*'(price|amount|total|deposit|balance)[^']*'\s*=>\s*'(float|decimal:|double)" "$FILE" >/dev/null 2>&1; then
  WARNINGS+=("WARN money: float/decimal cast on a money field. Use Brick\\Money + integer minor units (see CLAUDE.md §6).")
fi
if grep -nE 'protected\s+\$casts.*float|=>\s*\(float\)\s*\$.*minor' "$FILE" >/dev/null 2>&1; then
  WARNINGS+=("WARN money: possible float coercion on a *_minor column.")
fi

# 2) if/elseif chains on product_type strings — should be match($enum)
if grep -nE "if\s*\(\s*\\\$[a-zA-Z_->]+(product_type|productType|type)\s*===?\s*['\"]rental['\"]" "$FILE" >/dev/null 2>&1 \
   || grep -nE "elseif\s*\(\s*\\\$[a-zA-Z_->]+(product_type|productType|type)\s*===?\s*['\"](sale|digital)['\"]" "$FILE" >/dev/null 2>&1; then
  WARNINGS+=("WARN product-type: if/elseif chain on type string detected. Use match(\$productType) on App\\Modules\\Catalog\\Domain\\Enums\\ProductType (see docs/specs/03_Three_Product_Types.md §3.3).")
fi

# 3) Business logic in Models — Models should hold relationships/casts/scopes only
case "$FILE" in
  */Domain/Models/*.php|*/Models/*.php)
    if grep -nE 'public\s+function\s+(create|update|delete|process|handle|execute|calculate|book|cancel|approve|reject|publish|archive|moderate)' "$FILE" >/dev/null 2>&1; then
      WARNINGS+=("WARN architecture: Model contains a likely business-logic method. Move to an Action class in Application/Actions (see CLAUDE.md §1).")
    fi
    ;;
esac

# 4) Soft deletes on append-only tables
if echo "$FILE" | grep -qE '(wallet_ledger|audit_logs|payments|commissions|booking_state_transitions|event_outbox|analytics_events|loyalty_ledger)'; then
  if grep -nE 'softDeletes|SoftDeletes' "$FILE" >/dev/null 2>&1; then
    WARNINGS+=("WARN architecture: append-only table cannot use soft deletes (CLAUDE.md §15).")
  fi
fi

# 5) Migration without utf8mb4 charset
case "$FILE" in
  *Database/Migrations/*.php|*database/migrations/*.php)
    if grep -nE "Schema::create\(" "$FILE" >/dev/null 2>&1; then
      if ! grep -nE "(charset.*utf8mb4|utf8mb4_unicode_ci)" "$FILE" >/dev/null 2>&1; then
        WARNINGS+=("WARN migration: Schema::create without utf8mb4 charset/collation. Set \$table->charset = 'utf8mb4'; \$table->collation = 'utf8mb4_unicode_ci'; (see .claude/rules/migrations.md).")
      fi
    fi
    if grep -nE "Schema::create\(" "$FILE" >/dev/null 2>&1 && ! grep -nE "public_id" "$FILE" >/dev/null 2>&1; then
      # Allow pivot tables / append-only ledger tables to skip public_id
      if ! echo "$FILE" | grep -qE '(_pivot|_table|wallet_ledger|audit_logs|notification_dispatches|booking_state_transitions|event_outbox|analytics_events|booking_locks|service_inventory_reservations)'; then
        WARNINGS+=("INFO migration: no public_id (ULID) column. Most user-facing entities need one — verify whether this table is an exception.")
      fi
    fi
    ;;
esac

# 6) Controllers with too much logic (rough heuristic: > 25 lines per public method)
case "$FILE" in
  */Http/Controllers/*.php)
    LARGE=$(awk '
      /^\s*public\s+function/ { in_method=1; lines=0; name=$0; next }
      in_method { lines++; if (/^\s*}/) { if (lines > 30) print name " — " lines " lines"; in_method=0 } }
    ' "$FILE")
    if [ -n "$LARGE" ]; then
      WARNINGS+=("WARN architecture: large controller method(s) detected. Move to Action class (CLAUDE.md §1):
$LARGE")
    fi
    ;;
esac

# Emit warnings to stderr so Claude sees them. Non-blocking (exit 0).
if [ ${#WARNINGS[@]} -gt 0 ]; then
  echo "=== spec-guard warnings on $FILE ===" >&2
  for w in "${WARNINGS[@]}"; do
    echo "  $w" >&2
  done
fi

exit 0
