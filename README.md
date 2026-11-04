# Stage Tracker

Application web multiutilisateur de suivi de stages, de traçabilité professionnelle et de valorisation des compétences.

**Gouvernance : AIDF 0.1.0** — voir `.aidf/AIDF.md`, `.aidf/docs/constitution.md` et `AGENTS.md`.

## Statut

Phase AIDF : **SPECIFICATION** (voir `.aidf/docs/`). Aucun code applicatif n'est encore implémenté.

## Socle cible

| Élément | Choix | Preuve |
|---|---|---|
| PHP | 8.5.12 (NTS) | `php -v` |
| Framework | Laravel 13.40.0 | `composer create-project` réel (2026-11-04) |
| Base de données | PostgreSQL 16 (Docker `postgres:16-alpine`) | image locale présente |
| Frontend | Blade + Livewire + JavaScript ciblé | TRD |
| Tests | PHPUnit (Feature/Unit) + Playwright | PLAN |
| Architecture | Monolithe modulaire par domaine | ADR-001 |

## Démarrage local (après entrée en IMPLEMENTATION)

```bash
# 1. Base de données
cp .env.example .env        # renseigner DB_PASSWORD
docker compose up -d
docker compose ps           # healthcheck "healthy"

# 2. Application (quand le squelette Laravel sera créé en phase IMPLEMENTATION)
composer install
php artisan key:generate
php artisan migrate --seed   # seeders dev/test uniquement
npm install && npm run dev
php artisan serve
```

## Règles non négociables

1. Aucune fonctionnalité déclarée terminée sans preuve d'exécution (tests réels).
2. Isolation multi-tenant vérifiée côté serveur **et** en base.
3. Aucune donnée COAB hors de son workspace dédié.
4. Aucun secret dans Git (`.env` ignoré, jetons GitHub côté serveur uniquement).
5. Les statuts de preuve sont exclusivement : PASS, BLOCKED, FAIL, NOT_PROVEN.

## Documents

- Spécification : `.aidf/docs/` (11 documents AIDF)
- Annexes techniques : `docs/` (MCD, MLD, dictionnaire, contrats API, invariants, tests)
- Rapports de phase : `.aidf/reports/`
