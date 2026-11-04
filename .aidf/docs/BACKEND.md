---
doc: BACKEND
status: VALIDATED
version: 1.0.0
source: stage-tracker-specification
updated: 2026-11-04
validated_by: Jeremy Ferrier
---

# BACKEND — Architecture logique et règles serveur

## 1. Organisation par domaine

| Domaine | Responsabilité | Entités principales |
|---|---|---|
| Identity | comptes, workspaces, appartenances, invitations | users, workspaces, workspace_members, invitations |
| Organization | entreprises, associations, organismes | organizations, organization_members |
| Training | programmes et inscriptions | training_programs, training_enrollments |
| Framework | référentiels versionnés | competency_frameworks, framework_versions, competency_blocks, competencies, competency_criteria |
| Internship | stages et participants | internships, internship_participants |
| Project | projets, activités, tâches | projects, activities, tasks |
| Journal | journées et liens | daily_logs, daily_log_activities |
| Evidence | preuves et liens | evidences, activity_evidences |
| Evaluation | compétences et évaluations | activity_competencies, competency_assessments, reviews |
| Collaboration | commentaires, invitations | comments, invitations |
| Reporting | modèles et rapports | report_templates, report_template_versions, reports |
| Audit | journal d'audit | audit_logs |
| Git (intégration) | dépôts, commits, synchronisations | repositories, commits, commit_files, activity_commits, sync_runs |

## 2. Règles de couche

- Contrôleurs : validation (`FormRequest`) + appel de service + réponse.
- Services de domaine : règles métier, transactions.
- Modèles Eloquent : relations, casts, scopes ; pas de logique métier lourde.
- Policies : autorisation par ressource (workspace + rôle + visibilité).
- Aucun accès base hors Eloquent/Query Builder préparé.

## 3. Résolution du workspace

1. Le workspace courant est résolu par route (`/w/{workspace}`) ou session.
2. Middleware `EnsureWorkspaceMember` : refuse (403) si non-membre actif.
3. `WorkspaceScope` global : toutes les requêtes métier sont filtrées par
   `workspace_id`.
4. Toute création renseigne `workspace_id` depuis le contexte serveur, jamais
   depuis l'entrée utilisateur.
5. Les contraintes SQL composites garantissent l'impossibilité d'une relation
   inter-workspace même en cas d'erreur applicative.

## 4. Autorisation

- Rôles de workspace : `owner`, `admin`, `trainee`, `supervisor`, `tutor`, `viewer`.
- Rôles de stage : `trainee`, `supervisor`, `tutor`.
- Permissions vérifiées par `Policy` sur chaque action (view, create, update,
  delete, share, export, evaluate).
- Principe du moindre privilège ; un rôle de stage ne dépasse jamais le rôle
  de workspace.
- La visibilité des ressources est évaluée **après** l'appartenance au workspace.

## 5. Règles métier structurantes

1. Une activité appartient à un projet, lui-même rattaché à un stage et à un
   workspace (INV-02).
2. Un journal possède un auteur et un stage (INV-03).
3. Un commit est unique par dépôt et SHA (INV-05) ; import idempotent.
4. Une compétence appartient à une version de référentiel (INV-07).
5. Une version de référentiel publiée est immuable (INV-08).
6. Une activité terminée ne valide aucune compétence (INV-09).
7. Toute donnée importée conserve sa provenance (INV-10).
8. Une relation inter-workspace non autorisée est rejetée (INV-11).
9. Les commentaires conservent auteur et date (INV-12).
10. La désactivation d'un compte ne détruit pas l'historique (INV-13).
11. Un export ne contient que les données autorisées (INV-14).

## 6. Transactions et intégrité

- Création d'un stage + participants + audit : transaction unique.
- Import GitHub : transaction par lot ; `sync_runs` mis à jour même en échec partiel.
- Évaluation d'une compétence : transaction + audit + horodatage.
- Suppression : logique par défaut ; physique uniquement sur action admin explicite.

## 7. Journal d'audit

Événements journalisés (append-only) :
- connexion réussie/échouée, changement de mot de passe ;
- création/modification/suppression de ressource sensible ;
- changement de visibilité d'une preuve ;
- invitation, acceptation, révocation ;
- import Git (début, fin, statut, compteurs) ;
- export de rapport (qui, quoi, quelles exclusions).

Champs : `id`, `workspace_id`, `actor_id`, `action`, `subject_type`,
`subject_id`, `metadata` (JSON), `ip`, `user_agent`, `created_at`.
Aucune donnée secrète dans `metadata`.

## 8. Files d'attente et tâches planifiées

- File `database` en V1 ; jobs : import Git volumineux, génération d'export,
  envoi d'invitations.
- Planification : sauvegarde (quotidienne), purge des invitations expirées,
  nettoyage des sessions.
- Idempotence des jobs (clé unique par opération).

## 9. Erreurs et réponses

- Format JSON normalisé : `{ "message": "...", "errors": { "champ": ["..."] } }`.
- Codes : 200/201/204, 400, 401, 403, 404, 409, 422, 429, 500.
- Aucune trace technique exposée en production.
- 403 et 404 indistinguables pour les ressources hors périmètre.

## 10. Conventions de code backend

- PSR-12 + Laravel Pint.
- Noms de tables `snake_case` au pluriel ; modèles au singulier.
- Clés étrangères `<singulier>_id` ; contraintes nommées explicitement.
- Enums PHP pour les statuts (activité, tâche, compétence, visibilité).
- Value Objects pour les types métier (durée, période, visibilité).
- Tests Feature pour chaque endpoint, Unit pour chaque règle métier.
