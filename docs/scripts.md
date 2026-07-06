# Developer scripts

Reference for the local tooling in the repo's `scripts/` folder. The scripts
themselves run on **your machine** and are intentionally **not** in git (the
repo's `.gitignore` is a `/*` allowlist, so `scripts/` never enters version
control). This document lives in `docs/` so the tooling stays discoverable
without exposing the scripts.

The scripts contain only a host/user and the *path* to your SSH key — **no
secret**. The actual credential is `~/.ssh/nqa_deploy`, which is never in a
script and never in git.

## The scripts

| Script | Reaches prod? | Writes to prod? | What it does |
| --- | --- | --- | --- |
| `wp` | No | No | WP-CLI against the local Local-by-Flywheel site (Local's PHP + MySQL socket). |
| `wp-prod` | Yes (SSH) | **Only if the command writes** | WP-CLI against the **live** Dreamhost DB. A remote control for production. |
| `db-pull` | Yes (read-only) | **Never** | One-way refresh: dumps prod, overwrites the **local** DB, rewrites URLs. |

### Read vs. write — the rule that matters

`wp-prod` is a thin pass-through, so *what you type* decides whether you touch the
live site:

- **Safe (read-only):** `wp-prod post list …`, `option get …`, `db export …`
- **Live, immediate, no undo:** `wp-prod post update|delete …`, `option update …`,
  `search-replace …`, `db import …`

`db-pull` can't harm production — the only thing it does remotely is a read-only
`wp db export`. Its danger is purely local: it **wipes and replaces your local
database** (that's the point).

## Data-flow model (why these exist)

Production is the single source of truth for the database. Content, ACF field
*values*, taxonomy, and the intake pipeline are managed **on prod** (via wp-admin
or `wp-prod`). Local is a throwaway mirror you refresh with `db-pull`. Code
(themes + mu-plugins + ACF field *group* definitions) flows the other way —
local → prod via `git push main` → GitHub Actions. **Never full-clone
local → prod** (it would clobber live intake submissions).

Full details: [FOR-DEVS.md](FOR-DEVS.md) (environments & data flow) and
[FOR-EDITORS.md](FOR-EDITORS.md) (editorial + intake handbook).

## Usage

```bash
# Local
./scripts/wp post list --post_type=nqa_person

# Production (live) — prefer read-only; back up before bulk writes
./scripts/wp-prod post list --post_type=nqa_submission --post_status=private
./scripts/wp-prod db export ~/nqa-backup-$(date +%F).sql   # server-side backup

# Refresh local from prod (overwrites local DB)
./scripts/db-pull                 # DB only (prompts first)
./scripts/db-pull --with-uploads  # also pull the media library
./scripts/db-pull --yes           # skip the confirmation prompt
```

## Requirements

- **Local by Flywheel** running (both `wp` and `db-pull` need its MySQL socket).
- SSH key at `~/.ssh/nqa_deploy`, authorized on Dreamhost (mirrors the
  `DH_SFTP_KEY` deploy secret).
- System `mysql` / `mysqldump` on `PATH` (used by `db-pull` for the local import).

## Recreating the scripts

Because `scripts/` isn't tracked, a fresh clone won't have them. They're
documented in [FOR-DEVS.md](FOR-DEVS.md) (§ Access & credentials + the workflow
sections); recreate `wp-prod` and `db-pull` from there, or copy them from an
existing working checkout.
