---
doc: APPFLOW
status: VALIDATED
version: 1.0.0
source: stage-tracker-specification
updated: 2026-11-04
validated_by: Jeremy Ferrier
---

# APPFLOW — Parcours applicatifs

## 1. Navigation principale

```
Connexion
  └─ Sélection du workspace (/w/{workspace})
       ├─ Tableau de bord
       ├─ Mes stages
       │    └─ Stage → Projets → Activités → Tâches
       ├─ Journal
       ├─ Kanban
       ├─ Git (dépôts, commits, synchronisations)
       ├─ Preuves
       ├─ Compétences (référentiel du stage)
       ├─ Rapports
       └─ Paramètres
            ├─ Workspace (membres, rôles, invitations)
            ├─ Organisations
            ├─ Formations / référentiels
            └─ Profil / sécurité
```

Toute route métier est résolue dans un workspace. Aucune ressource n'est
accessible par identifiant sans vérification d'appartenance.

## 2. Parcours — Stagiaire

### 2.1 Créer un stage
1. Tableau de bord → « Nouveau stage ».
2. Saisie : organisation d'accueil, organisme de formation, dates, objectifs,
   référentiel + version applicable.
3. Invitation du maître de stage et du tuteur (e-mail ou lien).
4. Statut initial : `draft` → `active` après validation des dates.
5. **Erreurs** : dates incohérentes, référentiel non publié, organisation hors workspace.
6. **Preuve attendue** : stage persisté, participants liés, événement d'audit.

### 2.2 Saisir une journée de journal
1. Journal → « Nouvelle journée » (date du jour par défaut).
2. Saisie des heures, objectifs, travaux, difficultés, solutions, résultats,
   reste à faire, notes privées.
3. Association d'une ou plusieurs activités (N-N).
4. Enregistrement en `draft` ; clôture explicite → `closed`.
5. Après clôture : modification possible mais versionnée (`revision` + audit).
6. **Visibilité** : les notes privées ne sont jamais incluses dans les vues
   maître de stage ni dans les exports non autorisés.

### 2.3 Documenter une activité
1. Projet → « Nouvelle activité » : titre, type, description, priorité, dates.
2. Rattachement de tâches Kanban, de journées, de commits, de preuves.
3. Rédaction des objectifs / résultats / difficultés / solutions.
4. Association de compétences avec justification et preuves.

### 2.4 Importer des commits GitHub
1. Git → « Associer un dépôt » (URL, provider GitHub).
2. Vérification du jeton côté serveur (lecture seule).
3. « Synchroniser » → `sync_runs` : started_at, status, items_imported.
4. Import idempotent : un commit déjà présent (même SHA, même dépôt) n'est pas dupliqué.
5. Association manuelle commit(s) → activité.
6. **Erreurs** : jeton invalide (message clair, aucune fuite), dépôt inaccessible,
   limite d'API (reprise possible sans doublon).

### 2.5 Déposer une preuve
1. Preuves → « Nouvelle preuve » : titre, type, description, date, ce que la
   preuve démontre, visibilité (`private` | `stage` | `shared`).
2. Téléversement (contrôle MIME/taille) ou lien externe (commit, PR, URL).
3. Association à une ou plusieurs activités.
4. **Règle** : une capture d'écran ne peut pas être déclarée comme preuve d'un
   test non exécuté ; le champ « démontre » doit rester factuel.

### 2.6 Associer une compétence
1. Activité → « Compétences » → liste issue de la version de référentiel du stage.
2. Sélection d'une ou plusieurs compétences.
3. Justification obligatoire + preuves rattachées.
4. Statut initial : `mobilized` ; passage à `documented` puis `evaluated`
   uniquement par une action explicite (évaluation par un tiers habilité).
5. **Interdit** : passage automatique à `evaluated` sur simple clôture d'activité.

### 2.7 Générer une synthèse
1. Rapports → choix du modèle (mémoire, DP, oral, bilan, suivi hebdo).
2. Choix du stage et de la période ; aperçu des données incluses.
3. Génération en file d'attente ; export PDF/DOCX.
4. Le document distingue : faits, déclarations personnelles, appréciations de tiers.
5. **Contrôle** : les éléments privés sont exclus ; un avertissement liste les
   exclusions.

## 3. Parcours — Maître de stage

1. Réception d'une invitation → création de compte si nécessaire.
2. Accès limité aux stages attribués.
3. Consultation : activités partagées, journées partagées, preuves `stage`/`shared`.
4. Commentaire et appréciation (auteur + date conservés).
5. Revue : demande de précision, validation d'une preuve partagée.
6. **Interdit** : accès aux notes privées, aux brouillons non partagés, aux
   autres stages, aux exports non autorisés.

## 4. Parcours — Tuteur pédagogique

1. Accès aux stagiaires autorisés et à leurs parcours.
2. Consultation des compétences mobilisées/documentées.
3. Évaluation : appréciation, statut, date, évaluateur.
4. Export du suivi pédagogique (données autorisées uniquement).

## 5. Parcours — Administrateur

1. Gestion des utilisateurs (création, désactivation — sans destruction d'historique).
2. Gestion des référentiels : création, version, publication (immuable après publication).
3. Gestion des organisations et formations.
4. Journal d'audit consultable.

## 6. Flux transverses

### 6.1 Invitation
```
Admin/Stagiaire → invitation (email + rôle + portée)
   → lien signé à durée limitée
   → acceptation → appartenance workspace (status=active)
   → refus/expiration → invitation expirée (aucun accès)
```

### 6.2 Visibilité
| Niveau | Visible par |
|---|---|
| `private` | auteur uniquement |
| `stage` | participants du stage autorisés |
| `shared` | personnes explicitement autorisées |

Le niveau est vérifié côté serveur à chaque lecture, téléchargement et export.

### 6.3 Erreurs et états
- 403 : ressource hors workspace ou rôle insuffisant (message neutre, pas de fuite d'existence).
- 404 : ressource inexistante ou non visible (indistinguable du 403 pour un non-membre).
- 422 : validation ; messages en français, champ par champ.
- 429 : limitation de tentatives ; délai affiché.
- États de chargement sur chaque action asynchrone (Livewire) ; jamais d'écran figé.

### 6.4 Suppression / archivage
- Suppression logique par défaut (`archived_at`), restauration possible.
- Suppression physique réservée à l'administrateur, avec confirmation et audit.
- Un compte désactivé conserve l'historique ; transfert de propriété documenté.
