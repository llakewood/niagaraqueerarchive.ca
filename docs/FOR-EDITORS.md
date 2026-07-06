# Niagara Queer Archive — Editor's Handbook

A practical guide for anyone adding to, reviewing, or curating the archive. Before your dig in, read **Rules & consent** and **Historic vs. living** — they are the ones that protect people.

For the technical side (environments, deploys, database), see [FOR-DEVS.md](FOR-DEVS.md). This document is about the *editorial* work.

---

## 1. Purpose

The Niagara Queer Archive documents LGBTQ2S+ life in the Niagara region of Ontario — its people, organizations, events, and places — and makes that history accessible to the community, to researchers, and to the institutions that hold our region's memory.

A guiding commitment: **queer history is not only a big-city story.** Most archives centre Toronto, Montréal, Vancouver. We deliberately document the small towns, suburbs, and rural communities of Niagara — Fort Erie, Welland, Pelham, Port Colborne, and the rest — because that history is real, it is here, and it is disappearing faster than the urban record.

**Who we serve:** LGBTQ+ people in Niagara of all ages; libraries, museums, and cultural institutions; researchers, writers, and media.

Everything below serves that purpose. When a judgement call is hard, ask: *does this preserve Niagara's queer history accurately, and does it treat the people in it with care?*

---

## 2. Tone & style

The archive's voice is **historical, factual, archival — warm but precise, and never invented.**

- **Direct sources only.** Never invent facts, dates, names, or quotes. If a detail isn't in a cited source, it does not go in the record as fact. Flag it in the staff note as needing confirmation instead.
- **Warm, not casual.** We are documenting real lives, often hard-won. Respectful and plain beats clever or breezy.
- **Precise.** Prefer the specific ("Pride in the Park, Montebello Park, 2014") over the vague ("a Pride event in the 2010s").
- **Preserve contributors' words.** When a record comes from a community submission, the contributor's own account is copied **verbatim** into the body and is *never rewritten*. You may add sourced context around it, correct nothing inside it. (The automation enforces this — see §7.)
- **Niagara mastheads only for journalism.** Cite Niagara This Week, the St. Catharines Standard, Niagara Falls Review, Welland Tribune, 610 CKTB, Village Media Niagara, etc. National wire / syndicated articles are excluded.

**Colour palette** (for any editorial graphics; do not substitute theme presets):
`#503AA8` violet · `#FFEE58` yellow · `#F6CFF4` pink · `#FBFAF3` cream · `#111`
ink · `#fff` base.

---

## 3. The intake tool & workflow

Contributions enter the archive through the **Tell Your Story** page (`/tell/`)
and its form. Nothing a contributor submits ever goes public automatically — it lands in a private review queue for an editor to handle.

### What the contributor sees

- A guided form (name, email, phone, submission type, story/description, time period, location, credit preference, optional file upload).
- A **safety prompt** under the description box reminding them to consider whether people they name are publicly out.
- On submit, a **thank-you panel** explaining the three steps that follow — *Review → Follow-up → Archiving* — and an invitation to register for updates.

### What you see (the review queue)

In **wp-admin → Submissions** you get every contribution as a private **Submission** record:

- **Submitted Information** panel — all the contributor's answers, read-only, plus their story and any attached file (moved into the media library as a *private* attachment).
- A **Status** dropdown: `Pending → In Review → Accepted → Declined`.
- An **Archivist notes** box (the post body) — record your review, questions, and next steps here. These notes are staff-only.
- The list view has **Submitter / Type / Status** columns and a **Pending** quick-filter tab with a live count, so you always know what's waiting.

Submissions are never public: the `nqa_submission` type has no front-end URL and is excluded from search.

---

## 4. The intake process (step by step)

1. **A submission arrives.** It shows up under **Submissions** with status *Pending* (you'll see it in the Pending tab).
2. **Review it.** Open the record. Read the story and check the attached file. Decide: is this in scope (Niagara, LGBTQ2S+ or a clearly-framed ally), and is it real? Set status to **In Review** while you work.
3. **Do the sourcing.** Confirm names, dates, and places against a citable source where you can. Anything you can't confirm goes in **Archivist notes**, not into the record as fact.
4. **Handle consent.** Decide the consent status (see §5). If the submission names living private people who may not be out, or the contributor hasn't cleared thers they've named, keep it **Pending** and note what you're waiting on.
5. **Create the archive record.** In the **Create archive record** box, pick the type (Person / Organization / Event / Place / Article) and tick *Create draft record on Update*, then Update. This produces a **draft** archive record (see §7 for exactly what the automation does). The submission's status advances to *Accepted* automatically.
6. **Enrich the draft.** Add taxonomy (municipality, tags, collection), a source and citation, cross-references (§8), and the map location if relevant (via the admin map picker only — never programmatically).
7. **Record consent, then publish.** The record **cannot be published while consent is Pending or Restricted** — that's enforced, not a guideline. Once consent is *Granted* or *Not required*, a human publishes it. Never auto-publish.

> **Two-person principle:** the person who enters a record is ideally not the only person who publishes it. At minimum, sleep on anything involving a living individual before making it public.

---

## 5. Rules & consent

These are the non-negotiables. They come first, ahead of completeness or speed.

1. **Direct sources only.** No invented facts, dates, names, or quotes.
2. **All new entries start as drafts.** A human publishes. Nothing is auto-published — not seeds, not conversions, not imports.
3. **Staff notes go to the archival-note field, never the body.** Use the *Archivist notes* / `_nqa_archival_note` meta. It's shown only to logged-in editors, never to the public.
4. **Withhold personal contact details.** Names in *public* roles are fine.
   Private emails, phone numbers, and home addresses are not — ever.
5. **Allies are allies.** Organizations like PFLAG, Rise Against Bullying, or a school board are included with clear framing as **ally/partner**, not as queer organizations. Be precise about the relationship.
6. **Niagara mastheads only** for journalistic sources (see §2).
7. **Google Map `location` field is set via the admin map picker only** — never from a CSV, and not by hand-editing coordinates. *One sanctioned exception:* a server-side geocoder (`wp nqa geocode`) can bulk-fill records that have **no** pin yet, from their Address / title. It never overwrites a pin you placed by hand, and **every pin it sets must still be reviewed in the admin before publishing** (closed venues and region-wide orgs are easily mislocated). It's a maintenance command run by the server admin (`./scripts/wp-prod nqa geocode`), not an everyday editor action.

### The consent field

Every record carries a **consent status**:

| Status | Meaning | Can publish? |
| --- | --- | --- |
| `not-required` | Public facts about public roles, orgs, events, deceased public figures with cited sources | ✅ Yes |
| `pending` | Consent needed but not yet obtained | ⛔ **No — blocked** |
| `granted` | The person (or their family/estate, where appropriate) has agreed | ✅ Yes |
| `restricted` | Consent limited or withheld; hold | ⛔ **No — blocked** |

`pending` and `restricted` **block publishing at the system level.** This is
deliberate: it makes "publish first, ask later" impossible for records that need
care. Community submissions are set to `pending` on conversion for exactly this
reason.

### Standing consent flags (check before publishing these)

- **Colleen McTigue** — consent required before being listed.
- **Liam Coward** — family permission required for portrait.
- **Son of Monica Davis** — not named in sources; do not name without his consent.
- **Chantal Cartier / Marc Poisson-Leboeuf** — living private individual; public advocacy facts are in the body, personal history is held in the staff note pending a courtesy-consent contact.
- **Michelle DuBarry (Russell Peter Alldread)** — confirm death date and Niagara performance history before publishing.

Photo/logo permissions are still pending for several records (Justin Preston, Liam Coward, book covers, the Pride Niagara logo). When in doubt, don't publish the image.

---

## 6. Historic vs. living

The single most important distinction in this archive. It decides how much care a record needs and whether it can be published at all.

**Deceased people, defunct organizations, past events, historic places** — with cited sources, these are the core of the archive and are generally publishable (consent `not-required`). Treat estates and grieving families with care; for recently deceased individuals, confirm dates and, where a portrait or personal detail is involved, seek family permission.

**Living people** need a consent judgement, and it turns on one question: *are they public in this capacity?*

- **Living, and public in the relevant role** (an out advocate quoted by name in the press, an org's public spokesperson) — their *public* activity can be documented with a citation. Consent for those public facts is typically `not-required`, but still withhold private contact details (rule 4).
- **Living, and private** (named in a submission, not a public figure, or not publicly out) — **hold as `pending` and obtain consent.** Do not out anyone. This is why the intake safety prompt asks contributors to flag living people, and why conversions land on `pending`.

When a submission mixes both — a public figure's advocacy *plus* private personal history — publish the public part and hold the private part in the staff note until you have consent. (Cartier, above, is the worked example.)

> Rule of thumb: **being dead is not consent, and being alive is not a veto.** The test is dignity and public-ness. Source the public record; protect the private person.

---

## 7. The automation (what the tool does for you)

Understanding this keeps you from fighting the system or duplicating its work.

### On submission (fully automatic)

When the Tell Your Story form (`/tell/`, form **#61**) is submitted, code hooks the send and, behind the scenes:

- Creates a **private `nqa_submission`** record titled `Name — YYYY-MM-DD`.
- Stores every field as private `_nqa_sub_*` meta (name, email, phone, type, story, time period, location, credit preference).
- Moves any uploaded file into the **media library as a private attachment** and sets it as the submission's featured image.
- Sets the submission status to **Pending**.

No email-only handling, no lost attachments — every submission becomes a tracked,
reviewable record.

### On conversion (you click; the tool does the rest)

**Where you do this:** open the submission (**wp-admin → Submissions →** the record). In the **Create archive record** box in the right-hand sidebar, choose the **Record type** (Person / Organization / Event / Place / Article), tick **Create draft record on Update**, and click **Update**. That's the whole trigger — one dropdown and one checkbox. Once a record has been made, the box replaces itself with a link to the new draft (and won't create a second — see *idempotent* below), so you can click straight through to enrich it.

When you do that, `nqa_create_record_from_submission()`:

- Creates a **draft** of your chosen type.
- Copies the contributor's story **verbatim** into the body — *never rewritten*.
- Sets **provenance = `community-submission`**, records the submitter's name and
  the submission date.
- Sets **consent = `pending`** (so the publish gate holds it as a draft).
- Writes an auto **consent note** capturing the submission number, the
  contributor's credit preference, and their contact — so you know who to reach.
- Best-effort matches the free-text location to a **municipality** term.
- Carries the uploaded file over as the **featured image**.
- Links the two records **both ways** and drops an **archival note** reminding you
  the body is the contributor's own words.
- Advances the submission to **Accepted**.
- **Idempotent** — converting the same submission twice will not create a second
  record; it returns the one already made.

### Bulk import (CLI, for staff research / spreadsheets)

For batches (e.g. a list of places or people compiled from sources), use the
WP-CLI importer. Every row becomes a **draft**:

```bash
./scripts/wp     nqa import-csv places.csv --type=nqa_place   # local
./scripts/wp-prod nqa import-csv places.csv --type=nqa_place  # production
./scripts/wp     nqa import-csv mixed.csv --dry-run           # validate only
```

Recognized columns: `title` (required), `content`, `source`, `citation`, `link`,
`municipality`, `tags`, `collection`, `provenance`, `consent_status`,
`consent_notes`, `seed`, plus type-specific ones (e.g. `born`/`died`/`roles` for
people; `org_type`/`founded` for orgs; `place_type`/`address` for places;
`start_date`/`recurrence` for events). A `type` column sets each row's type unless
`--type` forces it. The map `location` is **never** set from CSV (rule 7) — use
the `address` text column instead. Re-running with the same `seed` **updates**
rather than duplicates.

### Provenance values (how a record entered the archive)

`cited-journalism` · `community-submission` · `oral-history-interview` ·
`institutional-donation` · `staff-research`. Set this honestly — it's part of the
archive's integrity, and researchers rely on it.

---

## 8. Cross-referencing records

Cross-references are what turn a pile of entries into an *archive*. A person links
to the org they founded, which links to the event it ran, which links to the place
it happened. Wire them as you go.

### The tools

- **Relationship field** — the bidirectional cross-reference available on all five types (Person, Org, Event, Place, and core Article posts). It links records to one another. **Set both sides.** Linking A→B does not automatically create B→A; add the reciprocal link on the other record so the connection shows from both.
- **Source / citation** — the primary URL and a formatted citation. Every fact-bearing record should carry its source.
- **Municipality** (taxonomy) — which of the 12 Niagara towns (or `niagara` for region-wide). Slugs like `st-catharines`, `niagara-falls`, `fort-erie`, `port-colborne`…
- **Tags** — decades and descriptors (`1980s`, `drag`, `flag-raising`). Use these for the cross-cutting threads that aren't a single place or collection.
- **Collections** (`nqa_collection` taxonomy) — thematic groupings with editorial intros: `pride-roots`, `progress-protest` `faith-inclusion`, `two-spirit-indigenous`, `trans-niagara`, `drag-performer`, `love-support`,`in-memorium`, `queer-arts-letters`.

### How to think about it

When you finish a record, ask: *what else in the archive touches this?*

- A **person** → their orgs, the events they ran or spoke at, the places tied to them, and any article that covers them.
- An **organization** → its people, its events, its venue(s), its lineage (predecessor/successor orgs), and its ally/partner relationships (framed per rule 5).
- An **event** → its organizer(s), its venue, the org behind it, and prior/later instances of a recurring event.
- A **place** → what happened there and who was connected to it.
- An **article** → every person, org, event, and place it documents.

Good cross-referencing is also a research prompt: a link you *want* to make but can't find a record for is a gap worth seeding (as a draft, with sources).

---

## 9. Editing page & site copy

Records are only half the site. The other half is the **standing pages** — About, Contact, the homepage, and so on. Their text lives in **three different places** depending on the page, and the trap is looking for content in the block editor when it actually lives in a field box or under Settings. This section is the map.

### The three editing surfaces

**1. The block editor (normal pages).** Open **wp-admin → Pages**, click the page, edit the body in the block editor, **Update**. These pages render whatever you type into the body.

- About · Resources · Monthly Storytelling Series · Visibility, Safety & Access

**2. Field boxes on the page's edit screen (templated pages).** These pages have a fixed layout, so their text is broken into **ACF field boxes** that appear *on the page's own edit screen* — scroll below (or beside) the block editor. **The block-editor body is empty and ignored** on these pages; typing there does nothing. Edit the fields, **Update**.

| Page | Open in Pages → | Content is in |
| --- | --- | --- |
| Privacy Policy | *Privacy Policy* | "Privacy Policy content" field boxes (§1–§7) |
| Contact Us | *Contact Us* | "Contact page content" field boxes |
| Submit Your Story | *Submit Your Story…* | "Tell Your Story content" field boxes; the form itself is Contact Form 7 **#61** |
| Collections | *Collections* | "Collections page content" — hero heading + lede only (the grid below is automatic) |

**3. Settings → Site Copy (the homepage + global labels).** The homepage is **not** an editable page. Even though a page called "Niagara Queer Archive" is set as the front page, the theme overrides it and builds the homepage from code — **that page's own content field is never shown.** All the homepage *text* lives in an ACF options page at **wp-admin → Settings → Site Copy**, organized into tabs:

- **Homepage — Hero** (location tag, headline, lede, the three call-to-action buttons)
- **Homepage — CTAs** (button labels + URLs)
- **Homepage — Stats panel** (panel heading + the four stat subtitles)
- **Homepage — Principles** (the Catalogue / Curate / Preserve trio)
- **Homepage — Submit CTA** and **Homepage — Newsletter** (headings + body)
- **Global labels** ("Featured Collection", "View all collections →", "Recently Added", etc. — these appear in more than one place, so they live here)

Edit the fields, **Update**. Changes are live immediately (no publish step — this is a settings page, not a post).

### Collection & municipality intros

The short intro under each collection (and municipality) card — and the intro on that collection's own archive page — is the **term's Description field.** Edit it under **wp-admin → the Collections taxonomy** (or **Municipalities**) → open the term → **Description** → Update. Municipalities ship with no intro; add one whenever you want. (Setting a collection's homepage **Featured** card is separate — that's the *Featured* checkbox on the same term.)

### What is *not* editable in the CMS

Some things look like page copy but are generated, and must be changed in code (ask the developer):

- **The homepage stat counts, Featured Collection card, and Recently Added list** — pulled live from the database, not typed anywhere.
- **A collection's title** (and which post types/terms a doorway points at) — defined in `nqa-archive/collections.php`. The *intro* is CMS-editable (above); the *title* is not.

> Rule of thumb for standing pages: **if you can't find the text in the block editor body, it's in a field box lower on the same edit screen — and if the page is the homepage, it's under Settings → Site Copy.**

---

## Quick reference

| I want to… | Where |
| --- | --- |
| See what's waiting for review | wp-admin → **Submissions** → *Pending* tab |
| Edit a normal page (About, Resources…) | wp-admin → **Pages** → the page → block editor |
| Edit Privacy / Contact / Submit pages | **Pages** → the page → **field boxes** on that edit screen (not the body) |
| Edit homepage text or global labels | wp-admin → **Settings → Site Copy** |
| Feature a collection on the homepage | Collections taxonomy → the collection → tick **Featured** |
| Edit a collection or municipality intro | The taxonomy term → **Description** field |
| Change a collection's title | Code only (`collections.php`) — ask the developer |
| Turn a submission into a record | Submission → **Create archive record** box |
| Stop a record going public until consent | Set **consent = Pending / Restricted** |
| Add a staff-only note | **Archivist notes** on the record (never the body) |
| Bulk-add from a spreadsheet | `./scripts/wp-prod nqa import-csv file.csv` |
| Set a map pin | Admin **map picker** only (never CSV/code) |
| Link two records | **Relationship** field — on **both** records |
| Manage live content from the terminal | `./scripts/wp-prod …` (see FOR-DEVS.md) |
