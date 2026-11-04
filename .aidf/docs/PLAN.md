---
doc: PLAN
status: VALIDATED
version: 1.0.0
source: stage-tracker-specification
updated: 2026-11-04
validated_by: Jeremy Ferrier
---

# PLAN — Feuille de route

> Ce document définit la **stratégie** de livraison. La décomposition atomique
> des tâches (identifiants, entrées/sorties, dépendances, preuves attendues)
> appartient à la phase AIDF **PLANNING**, non démarrée à ce stade.

## 1. Correspondance AIDF ↔ produit

| Phase AIDF | Objet produit |
|---|---|
| intake | cadrage, périmètre, acteurs, contraintes |
| discovery | inspection environnement, faisabilité, preuve de compatibilité |
| specification | 11 documents, MCD/MLD, contrats, invariants, tests |
| planning | découpage atomique, ordonnancement, risques par tâche |
| implementation | socle → domaines, une unité fonctionnelle à la fois |
| testing | PHPUnit, Feature, API, permissions, multi-tenant, Playwright |
| security | revue indépendante, vulnérabilités, blocage release |
| release | rapport de release, preuves, approbations |

## 2. Phases produit

### Phase 0 — Architecture et conception (phase AIDF : specification)
Livrables : ADR-001, ADR-002, MCD, MLD, dictionnaire, contrats API, invariants,
critères d'acceptation, spécifications de tests.
Critère de sortie : gate `specification-exit` PASS + validation humaine.

### Phase 1 — Socle multiutilisateur (phase AIDF : planning → implementation)
Périmètre : squelette Laravel, authentification, workspaces, membres, rôles,
organisations, isolation, PostgreSQL, migrations, audit de base.
Livrable : plusieurs comptes utilisent des espaces indépendants.
Tests bloquants : MT-01, MT-02, MT-03, MT-10.

### Phase 2 — Gestion des stages (implementation)
Périmètre : formations, inscriptions, stages, participants, projets, activités,
tâches, journal quotidien.
Livrable : un stage complet est suivi sans intégration externe.
Tests bloquants : MT-04, MT-05, MT-09.

### Phase 3 — Traçabilité (implementation)
Périmètre : preuves, pièces jointes, connecteur GitHub, import idempotent,
rattachement commits ↔ activités, historique des synchronisations.
Livrable : une activité est documentée par des preuves réelles.
Tests bloquants : MT-06, MT-07, MT-08.

### Phase 4 — Collaboration (implementation)
Périmètre : invitations, droits par stage, commentaires, revues, appréciations,
dossiers de partage.
Livrable : maître de stage et stagiaire collaborent dans les limites définies.

### Phase 5 — Pédagogie et documents (implementation)
Périmètre : référentiels versionnés, associations activités ↔ compétences,
évaluations, modèles de documents, exports mémoire/DP/oral.
Livrable : exploitation pédagogique des données.
Tests bloquants : MT-11, MT-12.

## 3. Ordre d'implémentation imposé

1. Socle technique (Laravel, PostgreSQL, Docker, CI locale).
2. Identité et authentification.
3. Workspaces et isolation.
4. Organisations.
5. Formations et référentiels.
6. Stages et participants.
7. Projets, activités, tâches.
8. Journal quotidien.
9. Preuves.
10. GitHub.
11. Collaboration.
12. Évaluations.
13. Exports.

Aucune tâche ne démarre si ses dépendances ne sont pas validées.

## 4. Definition of Done (par unité fonctionnelle)

1. Code conforme aux conventions, revu.
2. Tests automatisés écrits et **exécutés** (sortie enregistrée).
3. Autorisations et isolation vérifiées.
4. Validation serveur en place.
5. Audit des actions sensibles.
6. Documentation mise à jour.
7. Preuve AIDF enregistrée (commande + résultat).
8. Aucun secret, aucune donnée fictive en production.

## 5. Jalons et preuves attendues

| Jalon | Preuve de sortie |
|---|---|
| J1 — Socle opérationnel | `docker compose up` healthy + `php artisan migrate` réel |
| J2 — Isolation prouvée | MT-01/MT-02/MT-03 exécutés et PASS |
| J3 — Stage pilote COAB | stage réel saisi, journal + Kanban + activité |
| J4 — Import GitHub | double import sans doublon (MT-06) |
| J5 — Collaboration | preuve privée/partagée vérifiée (MT-07/MT-08) |
| J6 — Exports | export sans donnée privée (MT-11) |
| J7 — Restauration | sauvegarde/restauration testée (MT-12) |

## 6. Risques de planification

- Compatibilité des dépendances avec PHP 8.5 (surveillance Composer).
- Disponibilité des navigateurs Playwright sous Windows.
- Charge de la phase 5 (référentiels/exports) : découpage strict.
- Tentation de livrer une interface sans persistance : bloquée par la DoD.
