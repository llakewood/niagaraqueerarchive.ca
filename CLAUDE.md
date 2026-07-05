# Niagara Queer Archive

**Live site:** <https://niagaraqueerarchive.ca> (Dreamhost)
**Local dev:** Local by Flywheel — `niagaraqueerarchiveca.local`
**WP-CLI wrapper:** `./scripts/wp` (Local's PHP + socket; use for all wp-cli commands)
**Deploy:** push to `main` → GitHub Actions → rsync to Dreamhost (`wp-content/` only)

---

## Audience

LGBTQ+ people in Niagara, ON of all ages. Libraries, museums, cultural institutions. Researchers, writers, media.

## Tone

Historical, factual, archival. Warm but precise. Never invented.

---

## Infrastructure (complete)

- WordPress on Local by Flywheel; Twenty Twenty-Five block theme (no child theme)
- All custom code lives in **mu-plugins** (auto-loaded):
  - `nqa-cpt.php` — registers `nqa_person`, `nqa_org`, `nqa_event`, `nqa_place`; `nqa_entity_post_types()` returns the 4 types
  - `nqa-fields.php` — ACF field groups for all types; `relationship` field on all
  - `nqa-archive-display.php` — renders Archive details panel on single posts
  - `nqa-preservation.php` — Wayback Machine capture, source liveness checks, private full-text storage
  - `nqa-collections.php` — `nqa_collection` taxonomy, Collections page grid, shortcode `[nqa_collections]`
  - `nqa-archives.php` — styled archive/listing pages
  - `nqa-archive-controls.php` — client-side search + tag/category facets + 1/2/3 column grid picker on listing pages
  - `nqa-viewtoggle.php` — grid/list toggle on Collections page only
  - `nqa-archival-note.php` — staff-only `_nqa_archival_note` meta; shown only to logged-in editors on front end
  - **Intake pipeline** (code is now consolidated as `nqa-archive.php` loader + `nqa-archive/` modules, v3.0.0 — the names above are feature-indicative): `stewardship.php` — shared `provenance` + `consent_status` fields on all types with a publish-gate; `submissions.php` — Tell Your Story form #61 capture as private `nqa_submission` records; `importers.php` — submission→draft converter (preserves the contributor's words verbatim) + `wp nqa import-csv` bulk importer
- **CI:** `.github/workflows/deploy.yml` — test (PHP lint) → deploy (rsync themes + mu-plugins); `environment: production` for Maps key
- **Google Maps key:** `NQA_GOOGLE_MAPS_KEY` stored as a GitHub `production` environment **secret** (not a variable); website/referrer-restricted to niagaraqueerarchive.ca

---

## Content Model

### Post types

| Type | Slug | Used for |
| --- | --- | --- |
| Post | `post` | Archival articles (source journalism) |
| Person | `nqa_person` | Individuals |
| Org | `nqa_org` | Organizations and collectives |
| Event | `nqa_event` | Events (annual or one-off) |
| Place | `nqa_place` | Venues, landmarks, buildings |

### Taxonomies

- `category` — item TYPE (article, person, org, event, place)
- `post_tag` — decades, descriptors (e.g. `1980s`, `drag`, `flag-raising`)
- `municipality` — 12 Niagara towns (slugs: `st-catharines`, `niagara-falls`, `welland`, `fort-erie`, `lincoln`, `pelham`, `thorold`, `grimsby`, `west-lincoln`, `niagara-on-the-lake`, `port-colborne`, `wainfleet`); use `niagara` for region-wide
- `nqa_collection` — thematic collections. **Actual slugs:** `pride-roots`, `progress-protest`, `faith-inclusion`, `two-spirit-indigenous`, `trans-niagara`, `drag-performer`, `love-support`, `in-memorium`, `queer-arts-letters` (note: these differ from earlier docs — e.g. `faith-inclusion`, not `faith-and-inclusion`; `two-spirit-indigenous`, not `…-queer-niagara`)

### Key ACF fields

- `relationship` — bidirectional cross-references on all five types. Core `post` uses the code-defined **Cross Post References** group (`fields.php`; key `field_68abc016febec`, `return_format => id`) which supersedes the former DB-only group so it deploys to production. Set both sides manually or via seed script
- `source` / `citation` — primary URL + formatted citation
- `roles` (person), `org_type` (org), `place_type` / `still_exists` (place), `recurrence` / `organizer` (event)
- `location` — Google Map field; **set via admin map picker only, never programmatically**
- `provenance` — how a record entered the archive (`cited-journalism` / `community-submission` / `oral-history-interview` / `institutional-donation` / `staff-research`), plus `provenance_submitter` / `provenance_date`
- `consent_status` — `not-required` / `pending` / `granted` / `restricted`; **`pending` or `restricted` blocks publishing** (enforced by `stewardship.php`; generalizes the per-record consent flags below into schema)

### Protected meta keys (underscore-prefixed, not shown in editor by default)

- `_nqa_seed` — idempotency slug for seed scripts
- `_nqa_archival_note` — staff-only notes; visible only to logged-in editors on front end
- `_nqa_archive_text` — private full preservation text (scraped or pasted); **never shown publicly** unless `_nqa_text_public` is truthy
- `_nqa_wayback_url`, `_nqa_wayback_ts` — Wayback Machine snapshot
- `_nqa_source_ok`, `_nqa_source_checked` — liveness check results

---

## Curation Rules — MUST follow every session

1. **Direct sources only.** Never invent facts, dates, names, or quotes. If a detail isn't in a cited source, flag it in the staff note as needing confirmation.
2. **All new entries as drafts.** A human publishes. Never auto-publish seeded records.
3. **Archival notes to meta, not post_content.** Staff notes go to `_nqa_archival_note` via `update_post_meta()`. Never put them in the body.
4. **Withhold personal contact details.** Names in public roles are fine; private emails, phone numbers, and home addresses are not.
5. **Allies are allies.** Organizations like Rise Against Bullying, PFLAG, NCDSB — include with clear framing as ally/partner, NOT as queer organizations.
6. **National wire/syndicated articles excluded.** Niagara masthead only (Niagara This Week, St. Catharines Standard, etc.).
7. **DB content not in git.** Seed scripts live in the scratchpad. Only code is committed.
8. **`location` ACF field** — Google Map type; set via admin map picker only.
9. **Consent flags:**
   - Colleen McTigue (#317) — consent required before publishing
   - Liam Coward (#303) — family permission required for portrait
   - Son of Monica Davis — not named in sources; do not name without his consent

### Colour palette (pinned — do not swap for theme presets)

`#503AA8` violet · `#FFEE58` yellow · `#F6CFF4` pink · `#FBFAF3` cream · `#111` ink · `#fff` base

---

## Current State (as of July 2026)

### First round of content seeded — ~130 draft records

**24 archival articles** (posts) — all from Niagara This Week (Metroland/Village Media).
Preservation text manually pasted for 22; 2 still thin (paywalled).
Wayback snapshots in progress via `./scripts/wp nqa capture-sources`.

**~41 people · ~26 orgs · ~10 events · ~22 places** — all municipalities represented.

Notable records include:

- Pride Niagara, OUTniagara, Fort Erie Pride, Transgender Niagara, PFLAG Niagara, Safe Space Niagara, Quest CHC, Niagara Falls CHC, Positive Living Niagara (org lineage now seeded: AIDS Committee of Niagara 1987 → AIDS Niagara 1990 → renamed 2014), Fort Erie Native Friendship Centre
- Enzo De Divitiis (#450), Celeste Turner (#470), Monica Davis (#568), Russell Peter Alldread / Michelle DuBarry (#567)
- Pride in the Park (#305), Fort Erie Pride Festival (#306), Niagara UNITY Awards (#452), Family Pride Day (#473)
- Montebello Park (#313), St. Catharines Pride Crosswalk (#476), Envy Lounge (#451), Silver Spire United Church (#525)

**Collections registered:** see the corrected slug list under Content Model → Taxonomies; plus 12 municipality terms.

### Intake pipeline built (July 2026)

The community-contribution workflow is now in code (see Infrastructure): `stewardship.php` consent/provenance layer + publish-gate; `importers.php` submission→draft converter (preserves the contributor's words **verbatim**, sets consent Pending) and the `wp nqa import-csv` bulk importer. This is the path forward now that easy digital source-mining is near-saturated — the remaining depth lives in community memory and institutional holdings.

### Exploratory seeding (July 2026 — drafts unless noted)

Two research passes surfaced/seeded new leads:

- **Niagara Falls same-sex wedding tourism, 2003** (#590, draft) — distinctive Niagara story; rule #6: re-anchor on a Niagara masthead (St. Catharines Standard / Niagara Falls Review microfilm at Brock Archives) before publishing.
- **NCDSB Pride-flag controversy 2023–24** (#591, published) — trustee Natalia Benoit; sourced to Niagara Now / Country 89; cross-linked with NCDSB org #533.
- **Affirming ally congregations** — Westview Christian Fellowship (#592), Unitarian Congregation of Niagara (#593). Documented negative: no MCC / Dignity / Integrity Niagara chapter located.
- **Two-Spirit cluster** (fills a flagged gap) — Fort Erie queer Indigenous Pride drag brunch (#606) + Bella Recinos Athanasas (#604) + Jaylene Tyme (#605); the FENFC↔Pride link pass 1 was missing.
- Cross-refs wired (Fort Erie cluster #453/#454/#455↔#306; UNITY Awards #450/#302↔#452/#270). #453 Cartier enriched with drag/AIDS-fundraising facts; personal history held pending courtesy consent.

**Confirmed dead-ends online → need library/institutional intake:** pre-1980s Ontario-side gay life; pre-Envy gay bars; historical lesbian collectives / women's-music; Shaw Festival queer history; the 1980s–90s AIDS human toll (deaths, quilt panels, individual lives).

### Backlog (pending direct sourcing)

- **Brenda Baldwin** — namesake of UNITY Award; no biographical source found yet
- **Ed Eldred** — namesake of UNITY Award; deceased; no biographical source found yet
- **Johnathon Crawford** — namesake of UNITY Award; "late pioneer"; no biographical source found yet
- **Rise Against Bullying founding year** — article #259 says 2014, staff note says 2012; needs human resolution
- **Pride in the Park founding year** — article #267 says 2012 "inaugural"; event listings imply 2014; flagged in #305 staff note
- **Positive Living Niagara / AIDS Committee of Niagara (1987)** — AIDS crisis era in Niagara is underdocumented; high priority for next intake round

### Consent/permissions pending

- Photo permissions: Justin Preston (#302), Liam Coward (#303, family), book covers (#243/#244), Pride Niagara logo (#270)
- McTigue (#317) consent to be listed
- Michelle DuBarry (#567) — confirm exact death date before publishing; confirm Niagara performance history (The Great Impostors toured rural Ontario broadly but specific Niagara dates not yet sourced)
- Chantal Cartier / Marc Poisson-Leboeuf (#453) — LIVING private individual; consent set to **Pending**; public advocacy facts are in the body, but personal-history details (left home at 16, etc.) are held in the staff note pending a courtesy consent contact. Decision open: keep published, or revert to draft until consent obtained

---

## Next Phase: Story and Presentation

The database has a solid first round of content. The foundation documents are already written (see `data/`). The work now is **implementing** them on the site and building the team and intake process.

### Foundation documents (already written — `data/` folder)

- **`Niagara Queer Archive - Mission & Vision Statements.docx`** — mission, vision (short + expanded), historical inequities statement placeholder, land acknowledgement note, peer archives to credit (The ArQuives, NL Queer Archive, Buffalo-Niagara LGBTQ History Project)
- **`NQA Notes - May 2025.docx`** — brainstorm covering governance, team, intake process, social media, brand kit, funding, outreach list, entries to add
- **`NQA - Archive Outreach Strategy.xlsx`** — outreach contacts and strategy

**Key points from these docs to implement:**

- Historical Inequities Statement (reference The ArQuives 2021 statement; write NQA's own stance)
- Niagara land acknowledgement
- About page: pull from the mission/vision docs — especially the "small towns, suburbs, rural communities" framing and the challenge to major city-centric queer narratives
- OUTniagara micro-grant ($750, Founders Community Fund) — available for print materials, banner, tote bags, buttons; apply when ready
- Social media already exists: @NiagaraQueerArchive on Instagram and Facebook

### Site copy — what needs to be written/built

- **About page** — use the mission/vision docs as source; include historical inequities statement + land acknowledgement + peer archive credits
- **Collections editorial intros** — 2–4 sentences per collection explaining what it documents and why it matters
- **Contribute / submit page** — intake form (web form or Google Form), submission guidelines, consent framework, what happens after submission
- **Resources page** — links to community organizations (already noted in May 2025 brainstorm)

### New archive entries flagged in brainstorm (May 2025)

- Pride in the Park's original Burgoyne Woods location (pre-Montebello Park)
- OUTniagara Community Strengths and Needs Assessment (as a source document)
- Gillian's Place, YWCA Niagara, Niagara Sexual Assault Centre, Birchway Niagara — intersection of gender-based violence services with queer people and families

### Team and governance

Currently a solo project. Questions raised in the brainstorm that need decisions:

- Solo or collaborative? Working group structure?
- Sustainability: pros/cons of becoming a non-profit
- Volunteers: recruitment strategy; is free student labour ethical/aligned with NQA values?
- Possible co-working space at Little Red Coffee

### Source expansion — richest untapped digital sources

- **610 CKTB** (<https://610cktb.com>) — confirmed LGBTQ articles, free, searchable
- **PelhamToday / Village Media Niagara** (<https://pelhamtoday.ca>) — rich confirmed archive
- **CBC Hamilton** (<https://cbc.ca/hamilton>) — NCDSB trustee controversy (2023–24) is a major multi-article thread
- **CHCH-TV** (<https://chch.com>) — annual Pride in the Park coverage, multi-year
- **The Observer** — contact Jen Wilkenson (noted in May 2025 brainstorm)

### Institutional outreach — organizations to contact

- **Fort Erie Museums (FEMS)** ⭐ — warm existing relationship; FEMS has specifically asked about starting an LGBTQ2S+ archive within their system. Live co-stewardship opportunity given Fort Erie's documented queer history (Crystal Beach, Fort Erie Pride, Leboeufs). Prioritize.
- **PFLAG Niagara** — Monica Davis papers, early chapter photos, organizational records; also donated materials to Niagara Falls History Museum
- **Positive Living Niagara** — AIDS Committee of Niagara records (1987+)
- **OUTniagara** — request "OUT IN Niagara Community Strengths and Needs Assessment"; also explore micro-grant
- **St. Catharines Museum** — Sara Nixon (<snixon@st.catharines.ca>); follow up on 2019 community call submissions
- **Niagara Falls History Museum** — holds PFLAG Niagara donated materials
- **Brock University Archives** — <archives@brocku.ca>; search finding aids for Niagara LGBTQ org records
- **The ArQuives (Toronto)** — peer archive; credit in About page; explore deposit/research partnership
- **NL Queer Archive** (<https://nlqueerarchive.com>) — peer archive to credit and connect with
- **Buffalo-Niagara LGBTQ History Project** (<https://bflolgbtqhistoryproject.org>) — peer archive; cross-border ties

### Paywalled press archives — plan library visits

- St. Catharines Standard (back to 1891) via Newspapers.com at St. Catharines Public Library
- Niagara Falls Review via NFPL Newspaper Index (<https://nfpl.historicniagara.ca>) — keyword-searchable online, full text in-person
- Welland Tribune via PressReader (free with Welland Public Library card)

---

## Running the site locally

```bash
# WP-CLI (always use the wrapper)
./scripts/wp [command]

# Common seed workflow
./scripts/wp eval-file /path/to/seed-script.php

# Preservation capture
./scripts/wp nqa capture-sources --all
./scripts/wp nqa check-sources --all
```

Seed scripts go in the session scratchpad — never committed to git.
