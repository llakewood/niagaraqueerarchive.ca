# Niagara Queer Archive — Developer Handbook

The technical companion to the archive. For *editorial* work (intake, consent, cross-referencing, page copy) see **[FOR-EDITORS.md](FOR-EDITORS.md)**. This document is everything a developer needs: local setup, how code and data move between environments, deploy, and the maintenance commands.

> **The port is complete and will not be repeated.** The site lives on production (`niagaraqueerarchive.ca`, Dreamhost). Production's database is the single source of truth: code flows *up* from this git repo via CI, and data is mirrored *down* to Local only for development. The one-time "pull the live site into Local" procedure is preserved in the [Appendix](#appendix--the-initial-port-historical).

---

## Local setup (Local by Flywheel)

The site runs locally under **Local by Flywheel** — Local bundles its own PHP, MySQL, and nginx. You do **not** use a system PHP or a global WP-CLI (see the wrapper note below — it matters).

- **Local site / URL:** `niagaraqueerarchiveca.local`
- **Stack:** the site's Local environment (PHP 8.x, MySQL); block theme **Twenty Twenty-Five**, no child theme.
- **What's in git:** only custom code — the theme `wp-content/themes/nqa` and `wp-content/mu-plugins`, which live inside Local's `app/public/wp-content/`. WordPress core, `uploads/`, `wp-config.php`, and the database are **never** committed.

Getting running from scratch:

1. Install Local by Flywheel and start the `niagaraqueerarchive` site. **It must be running** — the wrappers need Local's MySQL socket to exist.
2. This repo's `themes/nqa` and `mu-plugins` are the tracked code, inside Local's `app/public/wp-content/`.
3. Pull live data down: `./scripts/db-pull` (see [below](#develop-code-against-real-data)).
4. Visit `https://niagaraqueerarchiveca.local`.

### Always use the wrappers — never bare `wp` or system `php`

This is the single most common footgun. **The system/Homebrew `php` on this machine is broken** (`php@8.1` references a missing `libicuio.74.dylib`), so anything that shells out to the global `wp` or `php` dies before it starts:

```
dyld: Library not loaded: /usr/local/opt/icu4c/lib/libicuio.74.dylib
```

Use Local's bundled PHP instead, via the project wrappers:

| Command | What it does |
| --- | --- |
| `./scripts/wp <cmd>` | WP-CLI against the **local** Local-by-Flywheel DB (Local's PHP + MySQL socket) |
| `./scripts/wp-prod <cmd>` | WP-CLI against the **live** Dreamhost DB, over SSH — **no undo** |
| `./scripts/db-pull [--with-uploads]` | Refresh the local DB (and optionally uploads) from prod, one-way |

So `wp nqa geocode` fails with the dyld error, but `./scripts/wp nqa geocode` works.

**Linting PHP** hits the same wall. Resolve Local's real PHP binary and lint with it — note the binary is nested one level deeper than you'd expect (`.../bin/darwin/bin/php`; the shallower `.../bin/darwin/php` is not executable):

```bash
LPHP="$(ls /Applications/Local.app/Contents/Resources/extraResources/lightning-services/php-*/bin/darwin*/bin/php | sort -V | tail -1)"
"$LPHP" -l path/to/file.php
```

That is the same PHP `./scripts/wp` resolves internally.

---

## Architecture

- **Block theme** `wp-content/themes/nqa`. Templates in `templates/`, parts in `parts/`. The homepage is `front-page.html`, assembled entirely from shortcodes; the assigned front page's own body is never rendered (see FOR-EDITORS §9 for the editor-facing consequence).
- **All custom PHP lives in mu-plugins**, auto-loaded. Since v3.0.0 it's consolidated behind one loader:
  - **`nqa-archive.php`** — the loader. Defines `NQA_VERSION` and `require`s each module listed in `$nqa_modules`, in order. **Add a module** by dropping a file in `nqa-archive/` and adding it to that array.
  - **`nqa-archive/` modules** (grouped as the loader groups them):
    - *Foundations:* `support/palette.php` (the pinned colour palette → CSS vars), `support/helpers.php`, `support/assets.php` (enqueues `nqa.css`)
    - *Data layer:* `content-model.php` (registers the 4 CPTs + `municipality` / `nqa_collection` taxonomies), `fields.php` + `page-fields.php` (ACF field groups: record fields, page content, and the **Site Copy** options page), `stewardship.php` (shared `provenance` + `consent_status` fields with the publish-gate)
    - *Admin:* `access.php`, `archival-note.php` (staff-only `_nqa_archival_note`), `preservation.php` (Wayback capture, source liveness, private full-text), `geocode.php` (`wp nqa geocode`)
    - *Front end:* `item-details.php`, `collections.php` (Collections grid + `[nqa_collections]`), `listing.php` + `listing-controls.php`, `view-toggle.php`, `map.php`, `submissions.php` (form #61 → `nqa_submission`), `newsletter.php`, `importers.php` (submission→draft + `wp nqa import-csv`), `shortcodes.php` (homepage sections), `search.php`, `tell.php`, `contact.php`, `privacy.php`, `forms.php`
  - **`0-nqa-runtime-config.php`** — **not in git**; written on the server by CI to inject `NQA_GOOGLE_MAPS_KEY`. The `0-` prefix loads it before `nqa-archive.php`.

### ACF: field *groups* are code, field *values* are content

This trips people up, so state it plainly:

- **Field group definitions** (which fields exist, their keys/types) are **code** — defined in `nqa-archive/*.php` and shipped via git → CI. **Never build or edit a field group in the prod admin UI** — it won't be in git and the next deploy won't know about it. Edit the PHP and push.
- **Field values** (the data entered on a record) are **content** — they live in the DB and are edited on prod like any other content.

### Assets & cache-busting

`support/assets.php` (CSS) and `search.php` (JS) enqueue their assets with the file's `filemtime()` as the version string, so any deploy that changes `nqa.css` / `nqa-search.js` auto-busts the browser cache. `NQA_VERSION` is only the fallback if the file is missing. If stale CSS/JS persists on prod after a deploy: hard-reload first; if it survives, look for a proxy/CDN cache that strips query strings.

### Content-side copy that lives in code vs. the CMS

- **Collection & municipality card intros** are **CMS-owned** — read from each taxonomy term's Description field via `nqa_term_intro()` in `collections.php`. The two CPT-backed doorways (Community Organizing, Gathering Places) keep an inline `desc` fallback since they have no term.
- **Collection titles** and which post-type/term each doorway points at are still defined in the `collections.php` registry (code).
- **Homepage text + global labels** are the **Site Copy** ACF options page (`page-fields.php`); the homepage stat counts / featured card / recent list are query-driven from the DB.

---

## Environments & data flow

```text
   CODE  ───────────────────────────────────────────────►  PRODUCTION
   (this git repo: themes + mu-plugins)   git push main      Dreamhost
                                          → GitHub Actions
                                          → rsync wp-content

   DATABASE / CONTENT / UPLOADS  ◄────────────────────────  PRODUCTION
   (posts, ACF field *values*, taxonomy,   ./scripts/db-pull  (authoritative)
    options, intake submissions)           (one-way, prod→local)
```

Two pipelines, two directions:

| Layer | Source of truth | How it moves | Direction |
| --- | --- | --- | --- |
| **Code** — themes, mu-plugins, ACF field **group definitions** | this git repo | push `main` → CI → rsync (`.github/workflows/deploy.yml`) | local → prod |
| **Data** — posts, ACF field **values**, taxonomy, options, uploads, intake submissions | production DB | edit on prod (CMS or `./scripts/wp-prod`); `./scripts/db-pull` to refresh local | prod → local (read-only mirror) |

**The golden rule:** never full-clone local → prod again. Intake submissions arrive on the live site; a push from local would clobber them.

---

## Everyday workflows

### Manage content / ACF values / intake on production

Use the admin at `https://www.niagaraqueerarchive.ca/wp-admin`, or drive WP-CLI against the live DB:

```bash
./scripts/wp-prod <any wp-cli command>          # runs ON the server, over SSH

# examples
./scripts/wp-prod post list --post_type=nqa_submission --post_status=private
./scripts/wp-prod db export ~/nqa-backup-$(date +%F).sql   # server-side backup
```

`wp-prod` touches the **live** site — there is no undo. Prefer read-only commands, and take a server-side `db export` before any bulk write.

### Develop code against real data

```bash
./scripts/db-pull                 # refresh local DB from prod (one-way)
./scripts/db-pull --with-uploads  # also pull the media library down
```

Then develop the theme / mu-plugins locally with `./scripts/wp`. Local DB changes are throwaway — the next `db-pull` overwrites them, and that's intended.

### Deploy code

```bash
git push origin main    # → GitHub Actions → rsync themes + mu-plugins to Dreamhost
```

Nothing else deploys. The database, `wp-config.php`, and `uploads/` are owned by production and are never pushed from here. CI runs a PHP-lint test job first, then the deploy job (`environment: production`, which is where the Maps key secret is scoped).

---

## Maintenance commands

All via `./scripts/wp` (local) or `./scripts/wp-prod` (live):

```bash
# Preservation — Wayback capture + source liveness
./scripts/wp nqa capture-sources --all
./scripts/wp nqa check-sources --all

# Geocoding — bulk-fill EMPTY Google Map `location` pins from address / title.
# Sanctioned exception to "map picker only": only fills records with no pin yet.
./scripts/wp nqa geocode --dry-run          # preview queries, writes nothing, no API key needed
./scripts/wp nqa geocode                     # local real run  (needs NQA_GOOGLE_MAPS_KEY)
./scripts/wp nqa geocode --type=nqa_place    # limit to one type
./scripts/wp-prod nqa geocode                # production (the Maps key lives here)
# Review every new pin in the admin before publishing.

# Bulk import — CSV → draft records (see FOR-EDITORS §7 for columns)
./scripts/wp nqa import-csv file.csv --type=nqa_place --dry-run

# Seed scripts (local, drafts only) — kept in the scratchpad, never committed
./scripts/wp eval-file /path/to/seed-script.php
```

> `wp nqa geocode` needs `NQA_GOOGLE_MAPS_KEY` for a real run (it's injected into `0-nqa-runtime-config.php`). Locally that file may not have the key — run `--dry-run` locally to validate, and do the real geocode on prod where the key exists.

---

## Access & credentials

Local WP-CLI wrapper: `./scripts/wp` (Local's PHP + MySQL socket).
Prod WP-CLI wrapper: `./scripts/wp-prod` (SSH to Dreamhost).

| Thing | Value |
| --- | --- |
| Prod host | `pdx1-shared-a2-02.dreamhost.com` |
| Prod user | `dh_enz88s` |
| SSH key | `~/.ssh/nqa_deploy` (mirrors the `DH_SFTP_KEY` deploy secret) |
| Prod WP root | `/home/dh_enz88s/niagaraqueerarchive.ca` (wp-config here; `wp-content/` is the rsync target) |
| Prod `wp` | `/usr/bin/wp` (PHP 8.2) · table prefix `wp_` |
| Canonical URL | `https://www.niagaraqueerarchive.ca` (bare domain 301s → www) |

CI deploy secrets (GitHub → Settings → Secrets and variables → Actions):

| Secret | Value |
| --- | --- |
| `DH_SFTP_HOST` | Dreamhost server hostname |
| `DH_SFTP_USER` | SFTP/shell username |
| `DH_SFTP_KEY`  | Private SSH key (passwordless) authorized on Dreamhost |
| `DH_REMOTE_PATH` | Absolute path to the live `wp-content` |
| `NQA_GOOGLE_MAPS_KEY` | Google Maps key; CI injects it into `mu-plugins/0-nqa-runtime-config.php` on the server (never committed). Stored as a **`production` environment** secret (not a variable), website/referrer-restricted to niagaraqueerarchive.ca. |

---

## Appendix — the initial port (historical)

Kept for the record. This is a **one-time** procedure; do not repeat it now that production is authoritative.

The live site was pulled into Local by Flywheel and this repo wired to it:

1. **Capture the live site** — export via All-in-One WP Migration (or Duplicator), or a full `wp db export` + `uploads/` rsync.
2. **Create the Local site** — Local → **+ Add Local Site** → `niagaraqueerarchive` → preferred environment (PHP 8.x, MySQL). Import the archive.
3. **Wire the repo** — this repo holds only custom code (themes + mu-plugins); symlink/copy those into Local's `app/public/wp-content/`. Core, uploads, and the DB never enter git.
4. **Verify** — site loads, login works, media displays, permalinks resolve (Settings → Permalinks → Save once to flush).

The subsequent local → prod **full DB clone** (done 2026-07-06) is likewise one-time: local prefix `wp_`, prod was `wp_x232e8_`; imported the local dump, ran `wp config set table_prefix wp_` on prod, search-replaced `http://niagaraqueerarchiveca.local` → `https://www.niagaraqueerarchive.ca`, and rsynced uploads. A full clone preserves post IDs (so ACF ID-based `relationship` fields survive — a WXR export/import would break them). **That direction is now closed:** prod is the source of truth; use `./scripts/db-pull` to go the other way.
