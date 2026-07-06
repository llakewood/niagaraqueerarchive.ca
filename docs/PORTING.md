# Environments & data flow

> **Status: the port is complete.** The site now lives on production
> (`niagaraqueerarchive.ca`, Dreamhost). It will **not** be ported again.
> Going forward the production database is the single source of truth: it is
> written to **remotely** — through the WordPress admin (CMS) and WP-CLI over
> SSH — and mirrored *down* to Local only for development. The one-time
> "pull the live site into Local" procedure is kept at the bottom for the record.
>
> Editors: for how to *use* the archive (intake, consent, cross-referencing),
> see **[FOR-EDITORS.md](FOR-EDITORS.md)**. This document is the technical /
> environments reference.

## The model

```text
   CODE  ───────────────────────────────────────────────►  PRODUCTION
   (this git repo: themes + mu-plugins)   git push main      Dreamhost
                                          → GitHub Actions
                                          → rsync wp-content

   DATABASE / CONTENT / UPLOADS  ◄────────────────────────  PRODUCTION
   (posts, ACF field *values*, taxonomy,   ./scripts/db-pull  (authoritative)
    options, intake submissions)           (one-way, prod→local)
```

Two separate pipelines, two separate directions:

| Layer | Source of truth | How it moves | Direction |
| --- | --- | --- | --- |
| **Code** — themes, mu-plugins, ACF field **group definitions** | this git repo | push to `main` → CI → rsync (`.github/workflows/deploy.yml`) | local → prod |
| **Data** — posts, ACF field **values**, taxonomy, options, uploads, intake submissions | production DB | edit on prod (CMS or `./scripts/wp-prod`); `./scripts/db-pull` to refresh local | prod → local (read-only mirror) |

The golden rule that follows: **never full-clone local → prod again.** Intake
submissions now arrive on the live site; a push from local would clobber them.

### ACF: field groups vs. field values

This trips people up, so it's worth stating plainly:

- **Field *group* definitions** (which fields exist, their keys/types) are
  **code** — defined in `mu-plugins/nqa-archive/*.php` and shipped via git → CI.
  **Never build or edit a field group in the prod admin UI**; it won't be in git,
  and the next deploy won't know about it. Edit the PHP and push to `main`.
- **Field *values*** (the data entered into those fields on a given record) are
  **content** — they live in the DB and are edited on prod like any other content.

## Everyday workflows

### Manage content / ACF values / intake on production

Use the admin at `https://www.niagaraqueerarchive.ca/wp-admin`, or drive WP-CLI
against the live DB with the wrapper:

```bash
./scripts/wp-prod <any wp-cli command>          # runs ON the server, over SSH

# examples
./scripts/wp-prod post list --post_type=nqa_submission --post_status=private
./scripts/wp-prod post list --post_type=nqa_person --post_status=draft
./scripts/wp-prod db export ~/nqa-backup-$(date +%F).sql   # backup, server-side
```

`wp-prod` touches the **live** site — there is no undo. Prefer read-only
commands, and take a server-side `db export` before any bulk write.

### Develop code against real data

```bash
./scripts/db-pull                 # refresh local DB from prod (one-way)
./scripts/db-pull --with-uploads  # also pull the media library down
```

Then develop the theme / mu-plugins locally with `./scripts/wp` as usual. When
the code is ready, `git push` to `main` and CI deploys it. Your local DB changes
are throwaway — the next `db-pull` overwrites them, and that's intended.

### Deploy code

```bash
git push origin main    # → GitHub Actions → rsync themes + mu-plugins to Dreamhost
```

Nothing else deploys. The database, `wp-config.php`, and `uploads/` are owned by
production and are never pushed from here.

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
| `NQA_GOOGLE_MAPS_KEY` | Google Maps key; CI injects it into `mu-plugins/0-nqa-runtime-config.php` on the server (never committed). Stored as a **`production` environment** secret. |

---

## Appendix — how the initial port was done (historical)

Kept for the record. This is a **one-time** procedure; it should not be repeated
now that production is authoritative.

The live site was pulled into Local by Flywheel and this repo wired to it:

1. **Capture the live site** — export via All-in-One WP Migration (or Duplicator),
   or a full `wp db export` + `uploads/` rsync.
2. **Create the Local site** — Local → **+ Add Local Site** → `niagaraqueerarchive`
   → Preferred environment (PHP 8.x, MySQL). Import the archive.
3. **Wire the repo** — this repo holds only custom code (themes + mu-plugins);
   symlink/copy those into Local's `app/public/wp-content/`. Core, uploads, and
   the DB never enter git.
4. **Verify** — site loads, login works, media displays, permalinks resolve
   (Settings → Permalinks → Save once to flush).

The subsequent local → prod **full DB clone** (done 2026-07-06) is likewise
one-time: local prefix `wp_`, prod was `wp_x232e8_`; imported the local dump,
ran `wp config set table_prefix wp_` on prod, search-replaced
`http://niagaraqueerarchiveca.local` → `https://www.niagaraqueerarchive.ca`, and
rsynced uploads. Full clone preserves post IDs (so ACF ID-based `relationship`
fields survive — a WXR export/import would break them). **That direction is now
closed:** prod is the source of truth; use `./scripts/db-pull` to go the other way.
