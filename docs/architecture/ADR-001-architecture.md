# ADR-001 — Architecture applicative

- Statut : ACCEPTED (2026-11-04)
- Décideurs : Jeremy Ferrier (propriétaire), agent de développement
- Référence : DEC-ST-001, DEC-ST-008, DEC-ST-009

## Contexte

Stage Tracker doit être une application web multiutilisateur, réutilisable pour
plusieurs stagiaires, organisations et référentiels. L'environnement local
dispose de PHP 8.5.12, Composer 2.10.3, Node 24.21.0, Docker 29.8.2 et Git
2.56.0. PostgreSQL n'est pas installé nativement ; l'image `postgres:16-alpine`
est disponible localement.

## Décision

1. **Monolithe modulaire** Laravel 13.40.0 organisé par domaine métier
   (`app/Domain/*`), sans microservices.
2. **PostgreSQL 16** via Docker Compose, volume persistant.
3. **Blade + Livewire + JavaScript ciblé** pour l'interface ; Vite pour les assets.
4. **API REST versionnée** `/api/v1` pour les intégrations.
5. **Tests** : PHPUnit (Unit/Feature) + Playwright (navigateur).
6. **Stockage** des preuves derrière une interface (local en V1, distant futur).

## Justification

- Laravel fournit authentification, autorisations, migrations, validation,
  files d'attente et protection CSRF/XSS de façon éprouvée.
- Le monolithe modulaire limite la complexité opérationnelle tout en gardant
  des frontières de responsabilité claires.
- PostgreSQL apporte les contraintes relationnelles et l'isolation nécessaires
  au multi-tenant ; SQLite ne permet pas les contraintes composites requises.
- La compatibilité PHP 8.5.12 ↔ Laravel 13.40.0 a été **prouvée** par un
  `composer create-project` réel et `php artisan --version`.

## Conséquences

- Positives : socle standard, maintenable, testable ; déploiement unique.
- Négatives : dépendance à l'écosystème Laravel ; PHP 8.5 très récent impose
  une vigilance sur les dépendances tierces.
- Contraintes : Docker requis en développement ; migrations versionnées
  obligatoires ; aucune migration destructive sans autorisation.

## Alternatives écartées

| Alternative | Motif du rejet |
|---|---|
| PHP natif sans framework | réimplémentation risquée de l'authentification/sécurité |
| SQLite | isolation multi-tenant et concurrence insuffisantes |
| SPA React/Vue complète | complexité inutile pour le périmètre V1 |
| Microservices | complexité distribuée sans bénéfice métier mesurable |
| MySQL | contraintes et fonctionnalités PostgreSQL préférées |
