---
doc: CHANGELOG
status: VALIDATED
version: 1.0.0
source: stage-tracker-specification
updated: 2026-11-04
validated_by: Jeremy Ferrier
---

# CHANGELOG

Format : [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/) ;
versionnement [SemVer](https://semver.org/lang/fr/).

## [Unreleased]

### À venir (PLANNING puis IMPLEMENTATION)
- Découpage atomique des tâches (phase PLANNING).
- Squelette Laravel + PostgreSQL + Docker.
- Modules Identity, Workspace, Organization.

## [0.1.0] — 2026-11-04 — Spécification

### Ajouté
- Installation AIDF dédiée au projet (`projets/stage-tracker/.aidf`).
- Dépôt Git initialisé (`main`).
- `docker-compose.yml` PostgreSQL 16 + `.env.example` + `.gitignore` + README.
- 11 documents AIDF : constitution, PRD, TRD, APPFLOW, UI-UX, BACKEND, PLAN,
  SECURITY, CONVENTIONS, DECISIONS, CHANGELOG.
- Annexes : ADR-001, ADR-002, parcours utilisateurs, matrice rôles/permissions,
  MCD, MLD, dictionnaire de données, contrats API, invariants INV-01..INV-14,
  critères d'acceptation, spécifications de tests MT-01..MT-12.
- Preuve de compatibilité PHP 8.5.12 ↔ Laravel 13.40.0.
- Preuve d'approbation humaine des décisions D-ST-01 à D-ST-04.

### Décisions
- DEC-ST-001 à DEC-ST-012 (voir DECISIONS.md).

### Limites connues
- Aucun code applicatif (phase IMPLEMENTATION non démarrée).
- Conformité RGPD : NOT_PROVEN (analyse juridique requise).
- Trames de rapports officielles indisponibles.
- Playwright non vérifié sous Windows.
