# Web Development Curriculum Baseline (MODULES index)

Purpose: seed rules derived from the owner's complete web-developer curriculum
(`resources/MODULES/`, ~400 course documents indexed; code trees and secrets
excluded). Index-only: this file lists domains and the module folders that
carry authority; it does not copy course content.

Domains and authoritative sources (relative to the project root, folder
`resources/MODULES/`):

| Domain | MODULES folder (now resources/MODULES) | Rule for AIDF projects |
|---|---|---|
| Algorithmics | `algo/` | Design before code; pseudocode/uml for non-trivial logic |
| HTML/CSS/Sass/Bootstrap | `html_css/` | Semantic HTML, responsive layouts, SASS conventions |
| JavaScript/AJAX/API | `JAVASCRIPT/` | ES conventions, DOM, fetch/API usage, algos in JS |
| PHP / POO / MVC | `PHP/` | MVC separation, POO design, form/session handling |
| SQL | `SQL/` | Pareto 80/20 querying, schema correctness, injections awareness |
| Data modelling (Merise, MCD/MLD) | `CONCEPTION/` | Entity/association modelling before coding |
| UX/UI, mockups, SEO, graphisme | `CONCEPTION/` | Mockups precede implementation; SEO taken into account |
| Cahier des charges | `CAHIER DES CHARGES/` | Requirements + personas precede development (PRD source material) |
| CNIL / RGPD | `CNIL_RGPD/` | Privacy-by-design; data minimisation; consent; retention (owner is CNIL-certified) |
| Project management | `GESTION DE PROJET/` | Phases, planning, deliverables orientation |
| Git | `GIT/` | Commit discipline, branching, PR hygiene |
| Docker | `DOCKER/` | Container parity for dev/staging (course + labs) |
| Linux | `LINUX/` | Users/permissions, shell basics for deploying |
| Networks & hardware | `infra_reseaux_hard_soft/` | TCP/IP, hosting constraints |
| Hosting | `hebergement/` | Deployment targets and constraints |
| English | `ANGLAIS/` | Docs/comments readability in English |
| AI usage | `IA/` | AI-assisted work under governance (AIDF) |
| Career positioning | `POSITIONNEMENT/`, `PRO/` | Portfolio and professional context |

Exclusions honoured by the AIDF resource registry (`sources.yaml`:
`jeremy-web-modules`): `STAGE/**` (real company project), node_modules, vendor,
`.env*`, `*.pem`, `*.crt`, `*.key`, `db/**`. Secrets observed in lab projects
are never read or copied; `aidf scan` reports filenames only.

Usage: when a task matches a domain above, the corresponding MODULES documents
are the owner's reference material. AIDF documents (PRD/SECURITY/CONVENTIONS…)
must stay consistent with these rules.
