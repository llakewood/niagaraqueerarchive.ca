# Niagara Queer Archive

Community-led digital archive preserving and sharing the histories of 2SLGBTQIA+
people in the Niagara region (Ontario). Built on WordPress, hosted on Dreamhost
at [niagaraqueerarchive.ca](https://niagaraqueerarchive.ca).

> Queer people have always existed, and we will continue to exist and thrive.

## What this repository tracks

This repo version-controls the **custom parts** of the site — the theme(s), any
custom plugins, and the deploy automation — so changes can be developed locally
and pushed live safely. WordPress core, uploads, the database, and secrets are
**not** tracked (see `.gitignore`). Only `wp-content/themes/` is tracked today;
custom plugins are added via explicit `.gitignore` exceptions when written.

```text
.
├── .github/workflows/   # CI + deploy-to-Dreamhost pipeline
├── docs/                # Porting & operations guides
├── wp-content/
│   ├── themes/          # custom theme(s)   ← tracked
│   └── plugins/         # custom plugin(s)  ← tracked
└── data/                # research/source files (LOCAL ONLY — gitignored)
```

## Development workflow

1. **Local** runs the site via [Local by Flywheel](https://localwp.com/).
2. Work happens on a branch; commits are pushed to GitHub.
3. On merge to `main`, GitHub Actions runs tests and deploys the changed
   theme/plugin files to Dreamhost over SFTP/SSH.

See [`docs/PORTING.md`](docs/PORTING.md) to bring the live site into Local.

## Privacy note

The `data/` directory holds research spreadsheets and notes that contain
**personal contact information** of community members. It is gitignored and must
never be committed to this (public) repository.
