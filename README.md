# InstaParty — Claude Code Setup Bundle

This bundle gives your InstaParty Laravel project a complete Claude Code working environment: project memory (`CLAUDE.md`), 8 spec docs in `docs/specs/`, 7 hooks for guardrails and automation, 5 modular rule files that load on demand, and 3 slash commands for repeating workflows.

It's also pre-configured to play nicely with **GitHub Spec Kit** if you choose to use it.

---

## What's in this bundle

```
instaparty-claude-setup/
├── README.md                                    ← this file
├── CLAUDE.md                                    ← project constitution (always loaded)
├── docs/specs/
│   ├── 01_PRD.md
│   ├── 02_Tech_Decisions.md
│   ├── 03_Three_Product_Types.md
│   ├── 04_Bilingual_Spec.md
│   ├── 05_Software_Description.md               ← AR + EN bilingual
│   ├── 06_Customer_Journey.md                   ← AR + EN + Mermaid
│   ├── 07_Vendor_Journey.md                     ← AR + EN + Mermaid
│   └── 08_Admin_Journey.md                      ← AR + EN + Mermaid
└── .claude/
    ├── settings.json                            ← permissions + hook wiring
    ├── hooks/                                   ← 7 executable shell scripts
    │   ├── session-start-context.sh             ← inject git + spec hierarchy at start
    │   ├── pre-bash-firewall.sh                 ← block destructive shell commands
    │   ├── pre-edit-protect-paths.sh            ← block edits to .env, vendor/, etc.
    │   ├── post-edit-pint.sh                    ← auto-format PHP with Laravel Pint
    │   ├── post-edit-spec-guard.sh              ← warn on architectural anti-patterns
    │   ├── post-bash-log.sh                     ← audit log of every shell command
    │   └── stop-summary.sh                      ← session summary with git status
    ├── rules/                                   ← context-specific guidance (auto-loaded)
    │   ├── migrations.md
    │   ├── actions.md
    │   ├── filament.md
    │   ├── modules.md
    │   └── product-types.md
    └── commands/                                ← slash commands (you trigger them)
        ├── migrate-module.md                    ← /migrate-module Identity
        ├── scaffold-type-resources.md           ← /scaffold-type-resources Service
        └── scope-audit.md                       ← /scope-audit subscription tiers
```

---

## Installation

### Step 1: Drop the bundle into your Laravel project root

```bash
cd ~/projects/instaparty
# Copy CLAUDE.md to project root
cp /path/to/bundle/CLAUDE.md .
# Copy docs and .claude folders
cp -r /path/to/bundle/docs ./
cp -r /path/to/bundle/.claude ./
```

### Step 2: Make the hook scripts executable

This is **required** — Claude Code won't run hooks otherwise:

```bash
chmod +x .claude/hooks/*.sh
```

### Step 3: Install Claude Code

If you don't have it yet:

```bash
npm install -g @anthropic-ai/claude-code
claude --version  # Should be 2.1.59 or later for auto-memory
```

Optional but useful tools the hooks may need:

```bash
# Ubuntu/Debian
sudo apt install jq ripgrep fd-find
# macOS
brew install jq ripgrep fd
```

### Step 4: Gitignore local-only files

Add these lines to your `.gitignore`:

```gitignore
# Claude Code local state
.claude/settings.local.json
.claude/bash-commands.log
.claude/session-log.md
```

### Step 5: First run

```bash
cd ~/projects/instaparty
claude
```

When Claude Code starts, the SessionStart hook runs and injects current git context. Verify it worked by asking:

> "Read CLAUDE.md and docs/specs/03_Three_Product_Types.md. Confirm you understand the polymorphic base + 3 detail tables pattern for services."

If Claude correctly distinguishes the three types and references the locked decisions, your setup is healthy.

---

## How the hooks help you

| Hook | What it does | When you'll feel it |
|---|---|---|
| **session-start-context.sh** | Injects current git branch + last commit + the spec hierarchy reminder into every session | Every `claude` invocation |
| **pre-bash-firewall.sh** | Blocks destructive commands (rm -rf /, force push, .env reads, DROP TABLE, migrate:fresh, curl) | Whenever Claude tries something risky |
| **pre-edit-protect-paths.sh** | Blocks edits to .env, vendor/, node_modules/, storage/logs/, lockfiles | Whenever Claude tries to touch files it shouldn't |
| **post-edit-pint.sh** | Runs `./vendor/bin/pint --quiet` on every PHP file Claude edits | Every PHP edit — your code stays formatted |
| **post-edit-spec-guard.sh** | Warns (non-blocking) when generated code violates locked rules: float for money, `if/elseif` on type strings, business logic in Models, soft deletes on append-only tables, missing `utf8mb4` on migrations, large controllers | Right after Claude writes problematic code — it sees the warning and self-corrects |
| **post-bash-log.sh** | Appends every shell command + exit code to `.claude/bash-commands.log` | Audit trail; nothing visible in the chat |
| **stop-summary.sh** | At session end, writes git branch + dirty state + last commit to `.claude/session-log.md` | When you want to review what happened |

The two **most valuable** hooks for you specifically are:

1. **post-edit-spec-guard.sh** — catches the exact anti-patterns we agreed are forbidden (floats for money, if/elseif on product type, etc.) and tells Claude about them in the same turn so it self-corrects without you having to.
2. **pre-bash-firewall.sh** — defense-in-depth on top of `settings.json` deny rules. It refuses to run dangerous commands even if Claude tries hard.

---

## Using GitHub Spec Kit alongside this bundle

Spec Kit (https://github.com/github/spec-kit) is a workflow framework that splits feature work into four ordered phases: **Constitution → Specify → Plan → Tasks → Implement**. Each phase produces a markdown artifact you review before moving on.

It works very well for InstaParty because each module (Identity, Catalog, Booking, …) is a natural "feature" in spec-kit terms.

### Install Spec Kit

```bash
# Install uv if you don't have it
curl -LsSf https://astral.sh/uv/install.sh | sh

# Install specify-cli
uv tool install specify-cli --from git+https://github.com/github/spec-kit.git

# Initialize in your existing project
cd ~/projects/instaparty
specify init --here --ai claude
```

This creates a `.specify/` folder with templates and slash commands, and adds `/speckit.constitution`, `/speckit.specify`, `/speckit.plan`, `/speckit.tasks`, `/speckit.implement` to Claude Code.

### How spec-kit fits with the files in this bundle

| Spec Kit concept | InstaParty equivalent | What you do |
|---|---|---|
| `/speckit.constitution` → `.specify/memory/constitution.md` | This bundle's `CLAUDE.md` + `docs/specs/01_PRD.md` + `docs/specs/02_Tech_Decisions.md` + `docs/specs/03_Three_Product_Types.md` | **Skip the slash command** — your constitution is already richer than what Spec Kit would generate. Tell Claude: *"Treat CLAUDE.md and docs/specs/ as the constitution. Don't run /speckit.constitution."* |
| `/speckit.specify` → `specs/NNN-feature/spec.md` | Per-module spec: "what should the Identity module do" | Run for each module. Spec Kit asks user-story questions, you answer based on the PRD + journey docs. |
| `/speckit.plan` → `specs/NNN-feature/plan.md` | Per-module technical plan referencing Tech Decisions | Spec Kit produces a plan; verify it cites Tech Decisions §1, §4, and (if applicable) the Three Product Types doc. Reject and re-prompt if it doesn't. |
| `/speckit.tasks` → `specs/NNN-feature/tasks.md` | Concrete ordered task list (migrations → models → actions → controllers → tests) | Output is a checklist Claude works through. |
| `/speckit.implement` | Generates code task by task | Hook system kicks in: Pint formats, spec-guard warns on anti-patterns, firewall blocks danger. |

### Recommended workflow per module

```bash
# Inside Claude Code
/speckit.specify
> The Identity module covers user accounts (customer, vendor, admin), 
> vendor profiles with per-product-type approval, vendor documents, 
> vendor coverage areas, vendor business hours, customer profiles, 
> customer addresses, user devices, and 2FA secrets. 
> Reference docs/specs/02_Tech_Decisions.md §3 and the schema list 
> we already locked in chat.

# Review the generated spec, refine if needed

/speckit.plan
> Use Laravel 12, Sanctum, spatie/laravel-permission, and the modular 
> monolith pattern in app/Modules/Identity/. Follow .claude/rules/migrations.md
> and .claude/rules/modules.md. Per-product-type vendor permissions are mandatory.

# Review the plan; verify it references the locked decisions

/speckit.tasks
# Get the ordered task list

/speckit.implement
# Claude works through the tasks; your hooks enforce style, safety, and architecture
```

### When NOT to use Spec Kit

For one-off questions, a single migration, debugging a specific bug, or any task smaller than "build a whole module" — just talk to Claude Code normally. Spec Kit's overhead pays off for module-sized work and above.

---

## Slash commands in this bundle

You trigger these by typing `/command-name [arguments]` inside Claude Code:

### `/migrate-module Identity`

Generates the migrations for one of your modules. Forces Claude to plan first (table list, dependency order, indexes) and only writes files after you approve.

### `/scaffold-type-resources Service`

Generates the three per-type Filament Resources (Rental + Sale + Digital) for an entity, with the right navigation group, translatable plugin wiring, and Shield permissions.

### `/scope-audit subscription tiers`

Forces Claude to check whether something is Phase 1 or Phase 2 against the PRD. Use this when you're tempted to add scope and want a sanity check.

---

## Daily workflow

```bash
# Morning
cd ~/projects/instaparty
git pull
claude
```

Inside the session:

1. **Use Plan Mode (Shift+Tab) for new modules.** Forces the model to propose before writing.
2. **One module per session.** Run `/clear` between modules to keep context tight.
3. **Commit after each working module.** Easy rollback.
4. **Trust the hooks.** When `post-edit-spec-guard` warns, Claude usually self-corrects in the next turn. If it doesn't, you'll see the warning in the transcript and can intervene.

---

## What changes if Spec Kit isn't installed

Nothing breaks. The bundle stands alone. The slash commands in `.claude/commands/` work without Spec Kit. CLAUDE.md and the rules/hooks all function the same way. Spec Kit is purely additive.

---

## Troubleshooting

**Hooks aren't running.**
- Check `chmod +x .claude/hooks/*.sh` — most common cause.
- Run `/hooks` inside Claude Code to see configured hooks and their status.
- Test a hook manually: `echo '{"tool_name":"Bash","tool_input":{"command":"ls"}}' | ./.claude/hooks/pre-bash-firewall.sh; echo $?`

**Pint isn't running on edits.**
- Make sure `composer install` has been run so `vendor/bin/pint` exists.
- The hook silently skips when Pint isn't installed (so it doesn't break in fresh clones).

**SessionStart context isn't appearing.**
- Run `claude --version` — needs 2.1.59+ for full hook support.
- Check `.claude/session-log.md` after a few sessions to confirm the Stop hook is firing too.

**Spec-guard is too noisy.**
- Edit `.claude/hooks/post-edit-spec-guard.sh` and comment out specific checks.
- The hook is intentionally non-blocking so noise doesn't stop work.

**Settings.json says hooks need approval on first use.**
- That's expected. Approve them once. They'll run silently afterward.

---

## Maintenance

- **CLAUDE.md** is the single source of truth for "what Claude should always remember." Update it when you make a binding decision in chat.
- **`.claude/rules/*.md`** loads on file glob match — use them for deep guidance that isn't always relevant.
- **Hooks** should be small, fast, and idempotent. Don't put long-running commands in them — they block tool execution.
- **Spec docs in `docs/specs/`** are reference material. Update them when locked decisions change. Reference them by path (e.g., "see `docs/specs/02_Tech_Decisions.md` §11") rather than copy-pasting content.

---

## Next step

Once installed, kick off Module 2 (Identity) in Claude Code:

```
/migrate-module Identity
```

Or, if using Spec Kit:

```
/speckit.specify
> Module: Identity. Covers users, vendor_profiles (with per-product-type approval),
> vendor_documents, vendor_approved_product_types, vendor_business_hours,
> vendor_coverage_areas, customer_profiles, customer_addresses, user_devices,
> two_factor_secrets. Reference docs/specs/02_Tech_Decisions.md §3.
```

Both routes will produce the same migrations — pick whichever workflow you find more comfortable.
