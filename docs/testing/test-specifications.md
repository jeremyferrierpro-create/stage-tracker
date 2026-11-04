# Spécifications de tests

## 1. Stratégie

| Niveau | Outil | Portée | Exécution |
|---|---|---|---|
| Unitaire | PHPUnit | règles métier, value objects, services | `php artisan test --testsuite=Unit` |
| Fonctionnel | PHPUnit Feature | endpoints, policies, validation, persistance | `php artisan test --testsuite=Feature` |
| API | PHPUnit Feature | contrats, codes, sérialisation | idem |
| Multi-tenant | PHPUnit Feature | isolation, IDOR, visibilité | suite dédiée `Tenant` |
| Navigateur | Playwright | parcours critiques, accessibilité | `npx playwright test` |
| Migration | PHPUnit | `migrate:fresh` + rollback | CI |
| Restauration | script | sauvegarde/restauration réelle | manuel + preuve |

**Règles** :
- Base de test PostgreSQL dédiée (`stage_tracker_test`), jamais la base pilote.
- Chaque test crée ses données via factories ; aucun jeu partagé mutable.
- Aucun test `skipped` sans justification.
- Les résultats (sortie réelle) sont enregistrés comme preuves AIDF.

## 2. Tests bloquants MT-01 à MT-12

### MT-01 — Deux workspaces distincts
- **Given** deux workspaces A et B avec des données similaires
- **When** un membre de A liste et accède aux ressources de B (par ID direct)
- **Then** aucune donnée de B n'est visible ; 403/404.
- **Vérifie** : INV-01, INV-11.

### MT-02 — Utilisateur non membre
- **Given** un utilisateur authentifié non membre de B
- **When** il appelle `/w/{B}/activities` et `/w/{B}/activities/{id}`
- **Then** 403 sur toutes les routes ; aucune fuite d'existence.
- **Vérifie** : INV-01.

### MT-03 — Stagiaire et maître de stage
- **Given** un stagiaire et un maître dans le même stage
- **When** chacun tente créer/modifier/commenter/évaluer
- **Then** les droits diffèrent conformément à la matrice ; le maître ne peut
  pas créer de journal ni modifier une activité.
- **Vérifie** : rôles contextualisés.

### MT-04 — Un stagiaire possède deux stages
- **Given** deux stages du même stagiaire dans le même workspace
- **When** il consulte journaux, activités et preuves
- **Then** les historiques restent séparés par stage ; aucune fuite croisée.
- **Vérifie** : INV-02.

### MT-05 — Deux versions d'un référentiel
- **Given** une version v1 publiée puis une version v2
- **When** v2 est publiée
- **Then** les compétences v1 restent intactes ; un stage lié à v1 n'est pas
  modifié ; toute écriture sur v1 est rejetée.
- **Vérifie** : INV-07, INV-08.

### MT-06 — Import GitHub répété
- **Given** un dépôt déjà synchronisé
- **When** une seconde synchronisation est lancée
- **Then** aucun doublon (`UNIQUE(repository_id, sha)`) ; `sync_runs` trace les
  deux exécutions ; provenance conservée.
- **Vérifie** : INV-04, INV-05, INV-10.

### MT-07 — Preuve privée
- **Given** une preuve `private` du stagiaire
- **When** le maître de stage y accède (liste, détail, téléchargement direct)
- **Then** 403/404 ; aucune métadonnée sensible exposée.
- **Vérifie** : INV-06.

### MT-08 — Preuve partagée
- **Given** une preuve `shared`
- **When** un participant autorisé y accède
- **Then** accès autorisé et téléchargement contrôlé.
- **Vérifie** : INV-06.

### MT-09 — Commentaire
- **Given** un commentaire créé
- **When** d'autres données sont modifiées puis le commentaire relu
- **Then** auteur, date et contenu d'origine sont conservés.
- **Vérifie** : INV-12.

### MT-10 — Suppression d'un membre
- **Given** un membre ayant créé des activités
- **When** son appartenance est révoquée ou son compte désactivé
- **Then** l'historique reste consultable par les ayants droit ; aucune
  cascade destructive.
- **Vérifie** : INV-13.

### MT-11 — Export d'un stage
- **Given** un stage avec notes privées et preuves privées
- **When** un export est généré
- **Then** aucune donnée privée n'est incluse ; les exclusions sont listées et
  auditées.
- **Vérifie** : INV-14.

### MT-12 — Sauvegarde et restauration
- **Given** une sauvegarde de la base et des fichiers de preuves
- **When** une restauration est effectuée sur un environnement vierge
- **Then** données et pièces jointes sont récupérées à l'identique (contrôle
  par empreintes).
- **Vérifie** : procédure de restauration.

## 3. Tests de sécurité complémentaires

1. Accès direct par ID inter-workspace (IDOR) — 403/404.
2. `workspace_id` forgé en entrée → ignoré.
3. Upload d'exécutable → rejeté (MIME + extension).
4. Jeton GitHub absent de toute réponse HTTP.
5. Session expirée → redirection, aucune action.
6. Force brute → 429 après seuil.
7. XSS stocké dans un commentaire → échappé.
8. CSRF sur formulaire sans jeton → 419.

## 4. Tests de non-régression métier

- Clôturer une activité ne change aucun statut de compétence (INV-09).
- Une version publiée ne peut être modifiée ni supprimée (INV-08).
- Un import répété ne duplique aucun commit (INV-05).
- Un export ne contient aucune note privée (INV-14).

## 5. Parcours Playwright prioritaires

1. Connexion → tableau de bord.
2. Création d'un stage + invitation du maître.
3. Saisie d'une journée + clôture.
4. Création d'une activité + association d'une preuve partagée.
5. Import GitHub (mock serveur contrôlé) + association commit ↔ activité.
6. Consultation par le maître de stage (éléments partagés uniquement).
7. Génération d'un export et vérification des exclusions.
8. Navigation clavier sur les formulaires clés.

## 6. Preuves attendues

- Sortie console des suites exécutées (avec code de sortie 0).
- Rapport Playwright (HTML ou JSON) archivé.
- Captures d'écran uniquement en complément, jamais en substitut.
- Chaque campagne est enregistrée via `aidf evidence add`.
