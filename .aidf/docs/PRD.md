---
doc: PRD
status: VALIDATED
version: 1.0.0
source: stage-tracker-specification
updated: 2026-11-04
validated_by: Jeremy Ferrier
---

# PRD — Stage Tracker

## 1. Vision

Stage Tracker est une application de suivi des stages et de traçabilité des
activités professionnelles. Elle permet aux stagiaires de documenter leurs
travaux, de relier leurs réalisations à des preuves techniques, de suivre
leurs compétences et de produire des synthèses adaptées à leur formation. Les
maîtres de stage consultent les activités partagées, commentent les
réalisations et suivent l'avancement.

Chaîne de valeur : **Travail réalisé → Activité → Tâche → Commit → Fichier →
Preuve → Compétence → Évaluation → Synthèse.**

## 2. Problème

Les stagiaires produisent un travail technique réel (commits, correctifs,
conceptions) mais :
- la traçabilité est dispersée (Git, notes, captures, documents bureautiques) ;
- les preuves ne sont pas reliées aux exigences de formation ;
- les maîtres de stage n'ont pas de vue fiable et limitée sur les travaux ;
- les bilans sont reconstruits après coup, avec un risque d'invention.

## 3. Utilisateurs et rôles (contextualisés par workspace)

| Rôle | Responsabilité | Périmètre |
|---|---|---|
| Administrateur | Configure la plateforme, les référentiels, les utilisateurs | Administration |
| Stagiaire | Documente stages, activités, preuves, compétences | Espace personnel / stage |
| Maître de stage | Suit et commente les travaux partagés | Stages attribués |
| Tuteur pédagogique | Suit le parcours et les compétences | Stagiaires autorisés |
| Gestionnaire d'organisme | Gère formations et référentiels | V2 |

Un même utilisateur peut être stagiaire dans un workspace et maître de stage
dans un autre. Les rôles ne sont jamais globaux au compte.

## 4. Périmètre V1

### 4.1 Modules inclus

**M1 — Identité et authentification**
- FR-1.1 : inscription/invitation, connexion, déconnexion, sessions sécurisées.
- FR-1.2 : rôles attribués dans le contexte d'un workspace.
- FR-1.3 : désactivation d'un compte sans destruction de l'historique.
- FR-1.4 : permissions vérifiées côté serveur sur chaque ressource.

**M2 — Workspaces multi-tenant**
- FR-2.1 : création de workspaces (personnel, associatif, professionnel, pédagogique).
- FR-2.2 : appartenance multi-workspace avec rôle distinct par workspace.
- FR-2.3 : isolation serveur et base de données ; refus de toute relation inter-workspace non autorisée.
- FR-2.4 : toute donnée métier rattachée à un workspace.

**M3 — Organisations**
- FR-3.1 : organisations d'accueil et organismes de formation distincts.
- FR-3.2 : une organisation rattachée à plusieurs stages.
- FR-3.3 : membres d'organisation avec rôles.

**M4 — Formations et référentiels**
- FR-4.1 : programmes de formation, inscriptions, certifications.
- FR-4.2 : référentiels versionnés, blocs, compétences, critères facultatifs.
- FR-4.3 : version publiée immuable ; pas de modification rétroactive.
- FR-4.4 : moteur générique, aucun référentiel codé en dur (DWWM = pilote).

**M5 — Stages**
- FR-5.1 : plusieurs stages par utilisateur ; un stage par workspace.
- FR-5.2 : participants (stagiaire, maître, tuteur) avec permissions.
- FR-5.3 : statut du stage ; archivage consultable.
- FR-5.4 : référentiel applicable figé sur une version précise.

**M6 — Projets et activités**
- FR-6.1 : un stage contient des projets ; un projet contient des activités.
- FR-6.2 : activité typée (fonctionnalité, correction, étude, conception, recherche, maintenance, amélioration).
- FR-6.3 : activité sans commit autorisée.
- FR-6.4 : objectifs, résultats, difficultés, solutions.

**M7 — Journal quotidien**
- FR-7.1 : saisie par journée (date, heures, durée, objectifs, travaux, difficultés, solutions, résultats, reste à faire).
- FR-7.2 : liaison N-N journées ↔ activités.
- FR-7.3 : mode brouillon / clôturé ; modification après clôture traçable.
- FR-7.4 : notes privées invisibles par défaut pour le maître de stage.

**M8 — Kanban et tâches**
- FR-8.1 : Kanban configurable, statuts initiaux : à faire, en cours, à vérifier, terminé, bloqué.
- FR-8.2 : tâches avec priorité, échéance, critères d'acceptation, preuves, commentaires.
- FR-8.3 : le Kanban Stage Tracker est la source de vérité de son propre suivi.
- FR-8.4 : toute intégration externe (GitHub Projects) est un connecteur séparé, sans double source de vérité non synchronisée.

**M9 — Connecteur Git**
- FR-9.1 : association d'un dépôt à un projet ; première intégration GitHub.
- FR-9.2 : import idempotent (unicité dépôt + SHA).
- FR-9.3 : conservation SHA, auteurs, dates, messages, branches, fichiers, URLs.
- FR-9.4 : association de plusieurs commits à une activité.
- FR-9.5 : historique des synchronisations (`sync_runs`).
- FR-9.6 : identité Git non assimilée automatiquement à celle du stagiaire.
- FR-9.7 : permissions GitHub minimales, lecture seule ; jeton jamais côté frontend.

**M10 — Preuves et livrables**
- FR-10.1 : preuves typées (captures, maquettes, UML, MCD/MLD, rapports de tests, Lighthouse, accessibilité, documents techniques, commits, PR, liens).
- FR-10.2 : titre, type, description, date, propriétaire, emplacement, visibilité, activités liées, ce que la preuve démontre.
- FR-10.3 : une capture ne démontre jamais un test non exécuté.
- FR-10.4 : stockage privé par défaut, abstraction local → distant.

**M11 — Compétences et évaluations**
- FR-11.1 : association activité ↔ compétence avec justification et preuves.
- FR-11.2 : statuts distincts mobilisée / documentée / évaluée.
- FR-11.3 : appréciation, évaluateur, date d'évaluation.
- FR-11.4 : suggestions éventuelles = propositions, jamais validations.

**M12 — Collaboration**
- FR-12.1 : invitations, commentaires, revues, appréciations.
- FR-12.2 : maître de stage limité aux informations autorisées.
- FR-12.3 : le stagiaire maîtrise la visibilité de ses notes personnelles.
- FR-12.4 : commentaires avec auteur et date conservés.

**M13 — Rapports et exports**
- FR-13.1 : synthèses mémoire, dossier professionnel, présentation orale, bilans, suivi hebdomadaire, suivi maître de stage.
- FR-13.2 : exports limités aux données autorisées.
- FR-13.3 : modèles configurables et versionnés, trames officielles non figées.
- FR-13.4 : distinction faits / déclarations personnelles / appréciations de tiers.

### 4.2 Hors périmètre V1

- Portail complet organisme de formation (rôles prévus, parcours non implémentés).
- Webhooks et imports périodiques automatiques.
- Connecteurs GitLab et GitHub Projects.
- IA de suggestion de compétences (hors socle ; jamais une validation).
- Microservices, Kubernetes, architecture distribuée.
- Application mobile native.

## 5. Exigences non fonctionnelles

- NFR-1 **Sécurité** : authentification, hachage des mots de passe, CSRF, XSS, validation serveur, anti-injection, sessions, limitation des tentatives, uploads contrôlés, secrets protégés, journalisation des actions sensibles.
- NFR-2 **Isolation** : contrôle serveur systématique + contraintes SQL ; tests MT-01 à MT-12 bloquants.
- NFR-3 **Performance** : page < 2 s en local sur jeu de données réaliste ; requêtes indexées ; pagination systématique.
- NFR-4 **Accessibilité** : WCAG 2.2 AA sur le périmètre applicable ; navigation clavier, contrastes, libellés.
- NFR-5 **Fiabilité** : migrations versionnées, transactions, sauvegarde/restauration testées.
- NFR-6 **Maintenabilité** : monolithe modulaire par domaine, PSR-12, tests automatisés.
- NFR-7 **Traçabilité** : chaîne de preuves SHA-256 AIDF ; provenance des données importées.

## 6. Critères de succès du pilote COAB

1. Un stage réel est suivi de bout en bout (journal, Kanban, activités).
2. Au moins un dépôt GitHub est importé deux fois sans doublon.
3. Une preuve privée reste invisible pour le maître de stage ; une preuve partagée est consultable.
4. Un export de synthèse ne contient aucune donnée privée non autorisée.
5. Les 12 tests multi-tenant MT-01..MT-12 passent réellement.

## 7. Hypothèses et inconnues

- Les trames officielles de rapports (ADRAR) ne sont pas disponibles : modèles configurables.
- La version applicable du référentiel DWWM doit être vérifiée auprès de la source officielle avant saisie.
- Les exigences RGPD exactes seront documentées en SECURITY (aucune conformité affirmée sans analyse).
