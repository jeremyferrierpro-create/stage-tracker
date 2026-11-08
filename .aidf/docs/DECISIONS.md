---
doc: DECISIONS
status: VALIDATED
version: 1.0.0
source: stage-tracker-specification
updated: 2026-11-08
validated_by: Jeremy Ferrier
---

# DECISIONS — Journal des décisions d'architecture (ADR)

Format : identifiant, statut, date, décision, justification, conséquences.

## DEC-ST-001 — Socle Laravel + PostgreSQL + Blade/Livewire
- Statut : ACCEPTED (2026-11-04, approbation humaine D-ST-04)
- Décision : monolithe modulaire Laravel 13.40.0, PostgreSQL 16, Blade +
  Livewire + JavaScript ciblé, API REST versionnée.
- Justification : authentification, autorisations, migrations, validation et
  sécurité sont centrales ; les réimplémenter serait coûteux et risqué.
- Conséquences : dépendance à l'écosystème Laravel ; pas de microservices.

## DEC-ST-002 — Isolation multi-tenant par workspace
- Statut : ACCEPTED (2026-11-04, prompt maître + D-ST-01)
- Décision : toute donnée métier est rattachée à un workspace ; contrôle
  serveur + contraintes SQL composites ; rôles contextualisés.
- Justification : un utilisateur peut être stagiaire dans un espace et maître
  de stage dans un autre ; l'isolation ne peut pas reposer sur l'interface.
- Conséquences : complexité accrue des requêtes et des tests ; MT-01..MT-03 bloquants.

## DEC-ST-003 — Référentiels génériques et versionnés
- Statut : ACCEPTED (2026-11-04)
- Décision : hiérarchie Référentiel → Version → Bloc → Compétence → Critère ;
  version publiée immuable ; aucun référentiel codé en dur.
- Justification : réutilisabilité multi-formations ; le DWWM n'est qu'un pilote.
- Conséquences : modélisation plus abstraite ; tests de non-régression sur versions.

## DEC-ST-004 — Trois niveaux de compétence distincts
- Statut : ACCEPTED (2026-11-04)
- Décision : `mobilized`, `documented`, `evaluated` sont des états distincts ;
  aucune transition automatique vers `evaluated`.
- Justification : éviter les bilans artificiellement positifs (INV-09).
- Conséquences : workflows d'évaluation explicites ; UI dédiée.

## DEC-ST-005 — Visibilité à trois niveaux
- Statut : ACCEPTED (2026-11-04)
- Décision : `private`, `stage`, `shared` ; vérification serveur à chaque accès,
  téléchargement et export.
- Justification : le stagiaire conserve la maîtrise de ses notes ; le maître de
  stage n'accède qu'au partagé.
- Conséquences : filtrage systématique des exports ; tests MT-07/MT-08.

## DEC-ST-006 — Connecteur Git derrière une interface
- Statut : ACCEPTED (2026-11-04)
- Décision : `GitProviderInterface` → `GitHubConnector` en V1 ; import manuel,
  idempotent, lecture seule, jeton chiffré côté serveur.
- Justification : éviter la dépendance directe à l'API GitHub ; extensibilité.
- Conséquences : `sync_runs`, unicité `(repository_id, sha)`, MT-06.

## DEC-ST-007 — Stockage des preuves abstrait
- Statut : ACCEPTED (2026-11-04)
- Décision : `EvidenceStorageInterface` avec implémentation locale en V1,
  évolution distante possible sans changer le domaine.
- Justification : éviter le couplage au système de fichiers local.
- Conséquences : métadonnées `storage_key` en base ; accès contrôlé.

## DEC-ST-008 — PHP 8.5.12 + Laravel 13.40.0
- Statut : ACCEPTED (2026-11-04, preuve de compatibilité enregistrée)
- Décision : conserver le PHP installé (8.5.12 NTS) et Laravel 13.40.0.
- Justification : `composer create-project` réussi, `artisan --version` OK,
  contrainte `php: ^8.3` satisfaite.
- Conséquences : surveiller les dépendances tierces lors des mises à jour ;
  toute montée de version exige une nouvelle preuve.

## DEC-ST-009 — PostgreSQL via Docker Compose
- Statut : ACCEPTED (2026-11-04, approbation humaine D-ST-02)
- Décision : `postgres:16-alpine` via `docker-compose.yml`, volume persistant.
- Justification : aucune installation native ; image déjà présente ; reproductible.
- Conséquences : Docker requis en développement ; port 5432 à libérer.

## DEC-ST-010 — Emplacement du projet et install AIDF dédiée
- Statut : ACCEPTED (2026-11-04, approbation humaine D-ST-01)
- Décision : `projets/stage-tracker/` avec son propre `.aidf/` et ses 11 documents.
- Justification : précédent `taskflow` ; séparation nette des projets gouvernés.
- Conséquences : les gates se valident dans le projet, pas à la racine workspace.

## DEC-ST-011 — Rapports configurables, trames non figées
- Statut : ACCEPTED (2026-11-04)
- Décision : modèles de rapports versionnés en base ; aucune trame ADRAR codée.
- Justification : trames officielles indisponibles ; éviter le figeage.
- Conséquences : versionnement des modèles ; exports dépendants des modèles.

## DEC-ST-012 — Journal d'audit append-only
- Statut : ACCEPTED (2026-11-04)
- Décision : `audit_logs` en append-only pour les actions sensibles.
- Justification : traçabilité et non-répudiation (SECURITY §2).
- Conséquences : aucune mise à jour/suppression applicative des entrées.

## DEC-ST-013 — README comme manuel utilisateur vivant
- Statut : ACCEPTED (2026-11-04, exigence propriétaire)
- Décision : le `README.md` du projet est un **mode d'emploi de l'outil**,
  construit partie par partie. À la fin de **chaque tâche d'implémentation**,
  la ou les sections concernées sont mises à jour (ou créées) et la mise à jour
  est incluse dans la Definition of Done de la tâche.
- Objectif : permettre à un camarade de formation de cloner le dépôt et
  d'utiliser l'outil pour son propre stage, sans connaissance préalable du code.
- Règles :
  1. Aucune section ne documente une fonctionnalité non implémentée et non
     testée (statut explicite « à venir » avec la tâche qui la livrera).
  2. Chaque section indique le rôle concerné et le parcours utilisateur réel.
  3. Les commandes affichées sont celles réellement exécutées et vérifiées.
  4. Les captures ne remplacent jamais une procédure textuelle.
  5. Le README est mis à jour dans le même commit que la tâche (ou le commit
     suivant immédiat, avec preuve).
- Conséquences : la revue de tâche vérifie le README ; une tâche sans mise à
  jour README est incomplète (sauf tâche purement technique sans impact
  utilisateur, justification obligatoire).

## DEC-ST-014 — Version Livewire (baseline TRD 3.x vs recommandation 4.x)
- Statut : ACCEPTED (2026-11-08, décision propriétaire)
- Contexte (2026-11-08, T-011) : la TRD imposait Livewire 3.x. Les versions 3.x
  publiées sont refusées à l'installation par Packagist à cause des avis de
  sécurité PKSA-bgw4-5zmg-2njg et PKSA-g5js-3ypd-k2kr (CVE contre Livewire
  3.6.0) ; aucune version 3.x installable ne reste disponible.
- Décision : installation de `livewire/livewire` **v4.4.8** par le propriétaire
  du projet le 2026-11-08 (requête `composer require livewire/livewire`
  exécutée manuellement), résoluant l'arbitrage en faveur de 4.x.
- Vérifications : `composer validate` OK ; `composer audit` 0 vulnérabilité ;
  `php artisan test` 76 tests PASS / 358 assertions après installation ;
  `package:discover` enregistre le paquet sans configuration requise.
- Conséquences : le TRD §Livewire « 3.x » doit être relu comme « Livewire 3.x
  à la rédaction, remplacée par 4.4.8 après arbitrage propriétaire » ; la
  première révision du TRD corrigera la baseline. Aucun composant Livewire
  n'est encore utilisé (interfaces en Blade pur jusqu'à T-020+) ; le point
  d'entrée `resources/js/app.js` sera branché quand le premier composant
  interactif sera livré.

