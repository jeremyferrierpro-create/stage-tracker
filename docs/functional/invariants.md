# Invariants métier — INV-01 à INV-14

> Chaque invariant est une règle **non négociable**, associée à un mécanisme de
> contrôle et à au moins un test automatisé. Un invariant violé bloque la
> release.

| ID | Invariant | Contrôle | Test |
|---|---|---|---|
| INV-01 | Un utilisateur ne consulte que les workspaces autorisés. | middleware + policy + scope | MT-01, MT-02 |
| INV-02 | Une activité appartient à un projet et à un stage identifié. | FK composites | MT-04 |
| INV-03 | Un journal possède un auteur et un stage. | NOT NULL + FK | Feature journal |
| INV-04 | Un commit appartient à un dépôt identifié. | FK `repository_id` | MT-06 |
| INV-05 | Un commit est unique par dépôt et SHA. | `UNIQUE (repository_id, sha)` | MT-06 |
| INV-06 | Une preuve possède un propriétaire et une visibilité. | NOT NULL + CHECK | MT-07, MT-08 |
| INV-07 | Une compétence appartient à une version de référentiel. | FK `framework_version_id` | MT-05 |
| INV-08 | Un référentiel publié est immuable. | trigger + policy | MT-05 |
| INV-09 | Une activité terminée n'est pas automatiquement validée. | absence de transition automatique | Feature évaluation |
| INV-10 | Une donnée importée conserve sa provenance. | champs `imported_at`, `provider`, `external_id` | MT-06 |
| INV-11 | Une relation inter-workspace non autorisée est rejetée. | FK composites + scope | MT-01, MT-02 |
| INV-12 | Les commentaires conservent auteur et date. | NOT NULL, pas d'UPDATE de `created_at` | Feature commentaires |
| INV-13 | La désactivation d'un compte ne détruit pas l'historique. | `status=disabled`, pas de cascade | MT-10 |
| INV-14 | Un export ne contient que les données autorisées. | filtrage serveur + audit | MT-11 |

## Détail des mécanismes

### INV-01 / INV-11 — Isolation
- Middleware `EnsureWorkspaceMember` sur toutes les routes `/w/{workspace}`.
- `WorkspaceScope` global sur chaque modèle métier.
- FK composites `(parent_id, workspace_id)` : PostgreSQL refuse toute ligne
  dont le workspace diffère de celui du parent.
- Test d'accès direct par identifiant (IDOR) obligatoire.

### INV-02 / INV-03 / INV-04 / INV-07 — Rattachament
- `activities.project_id` NOT NULL ; `projects.internship_id` NOT NULL ;
  `internships.workspace_id` NOT NULL.
- `daily_logs.internship_id` et `author_id` NOT NULL.
- `commits.repository_id` NOT NULL.
- `competencies.framework_version_id` NOT NULL.

### INV-05 — Unicité des commits
- Contrainte `UNIQUE (repository_id, sha)` ; import par `upsert` ; un second
  import ne crée aucun doublon.

### INV-06 — Preuves
- `owner_id` NOT NULL, `visibility` CHECK (`private`,`stage`,`shared`).
- `storage_key` ou `external_url` obligatoire.

### INV-08 — Immuabilité des référentiels
- Trigger PostgreSQL `BEFORE UPDATE OR DELETE` sur les tables de contenu d'une
  version publiée → exception.
- Une évolution crée une nouvelle version.

### INV-09 — Pas de validation automatique
- Aucun code ne passe `activity_competencies.status` à `evaluated` sans
  `competency_assessments` explicite par un évaluateur habilité.
- Test : clôturer une activité ne change aucun statut de compétence.

### INV-10 — Provenance
- `repositories.provider`, `repositories.external_id`, `commits.imported_at`,
  `sync_runs` conservés ; l'auteur Git n'est jamais assimilé au stagiaire.

### INV-12 — Commentaires
- `author_id` et `created_at` NOT NULL ; aucune mise à jour autorisée de ces
  champs (policy + trigger).

### INV-13 — Désactivation
- `users.status='disabled'` ; aucune suppression en cascade ; transfert de
  propriété documenté.

### INV-14 — Exports
- Le service d'export applique la visibilité avant sérialisation ; les
  exclusions sont listées dans le rapport généré et auditées.

## Règle d'application

Un invariant violé = **FAIL** de la phase TESTING ; la release est **BLOCKED**
jusqu'à correction et nouvelle preuve d'exécution.
