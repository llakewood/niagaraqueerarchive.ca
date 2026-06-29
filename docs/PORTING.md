# Porting the live site into Local by Flywheel

Goal: get an exact copy of the production WordPress site
(`niagaraqueerarchive.ca`, hosted on Dreamhost) running locally in Local by
Flywheel, then wire this git repository to it for development.

## Recommended method — "Pull" with a migration plugin

This is the lowest-risk path and survives Dreamhost's server config without
manual SQL surgery.

### 1. Capture the live site
On the **production** site (`wp-admin`):
1. Install **All-in-One WP Migration** (or **Duplicator**). AIO is simplest.
2. Export → download the `.wpress` archive. This bundles the database, uploads,
   themes, and plugins into one file.
3. *(Safety)* Also take a Dreamhost panel backup / snapshot before any changes.

### 2. Create the Local site
1. In Local: **+ Add Local Site** → name it `niagaraqueerarchive` → "Preferred"
   environment (matches typical Dreamhost: PHP 8.x, MySQL, nginx/Apache).
2. Start the site, open its `wp-admin`.
3. Install the **same** migration plugin and **Import** the `.wpress` file.
4. Local's import bumps the upload size limit automatically if needed.

### 3. Point this repo at the Local site
Local stores the site at:
`~/Local Sites/niagaraqueerarchive/app/public/`

You have two clean options:

- **A. Repo holds only custom code (recommended).** Keep this repo where it is.
  Symlink or copy the custom theme/plugin folders between here and Local's
  `app/public/wp-content/`. CI deploys just those folders. Core/uploads/db never
  enter git. This keeps the repo small and the public repo free of site dumps.

- **B. Repo lives inside `app/public/`.** `git init` inside Local's public
  folder and use the same `.gitignore`. Simpler symlinking, but you must be
  strict about the ignore rules so core/uploads/db stay out.

For a public archive repo, **Option A** is preferred.

### 4. Verify
- Local site loads, login works, media displays, permalinks resolve
  (Settings → Permalinks → Save once to flush rewrite rules).
- `wp-content/themes` + `plugins` match production.

## Deploy back to production
The `.github/workflows/deploy.yml` scaffold deploys tracked theme/plugin files
to Dreamhost over SFTP on push to `main`. Add these repo **secrets**
(Settings → Secrets and variables → Actions):

| Secret | Value |
| --- | --- |
| `DH_SFTP_HOST` | Dreamhost server hostname |
| `DH_SFTP_USER` | SFTP/shell username |
| `DH_SFTP_KEY`  | Private SSH key (passwordless) authorized on Dreamhost |
| `DH_REMOTE_PATH` | Absolute path to the live `wp-content` |
| `NQA_GOOGLE_MAPS_KEY` | Google Maps API key; CI injects it into `mu-plugins/0-nqa-runtime-config.php` on the server (never committed) |

Never deploy `wp-config.php`, the database, or `uploads/` — production owns those.

## Database / content changes
Database changes (new posts, taxonomy, settings) are **content**, not code — they
are made directly in production's `wp-admin` (or staged in Local and migrated
deliberately). Git tracks code; it does not sync the database.
