# Contrats API — REST v1

> Base : `/api/v1`. Authentification : session Sanctum (web) ou jeton personnel
> (intégrations). Toutes les routes métier sont scopées par workspace :
> `/api/v1/w/{workspace}/...`. Réponses JSON, erreurs normalisées.

## 1. Conventions

- En-têtes : `Accept: application/json`, `Content-Type: application/json`.
- Pagination : `?page=1&per_page=25` (max 100) ; réponse `{ data, meta, links }`.
- Filtres : `?status=...&from=...&to=...`.
- Tri : `?sort=-created_at`.
- Erreurs : `{ "message": "...", "errors": { "champ": ["..."] } }`.
- Codes : 200, 201, 204, 400, 401, 403, 404, 409, 422, 429, 500.
- Idempotence : `Idempotency-Key` sur les imports et générations d'export.
- Aucune donnée privée dans une réponse non autorisée (INV-14).

## 2. Authentification

| Méthode | Route | Description |
|---|---|---|
| POST | `/auth/login` | connexion (email, password) → session |
| POST | `/auth/logout` | déconnexion |
| GET | `/auth/me` | profil + workspaces accessibles |
| POST | `/auth/password/forgot` | demande de réinitialisation |
| POST | `/auth/password/reset` | réinitialisation par jeton |

Exemple de réponse `GET /auth/me` :
```json
{
  "data": {
    "id": 12,
    "name": "Jeremy",
    "email": "jeremy@example.test",
    "workspaces": [
      { "id": "0b6e...", "name": "Stage COAB", "role": "trainee" }
    ]
  }
}
```

## 3. Workspaces et membres

| Méthode | Route | Rôle requis |
|---|---|---|
| GET | `/w/{workspace}` | membre |
| PATCH | `/w/{workspace}` | admin/owner |
| GET | `/w/{workspace}/members` | admin/owner |
| POST | `/w/{workspace}/invitations` | admin/owner |
| POST | `/invitations/{token}/accept` | utilisateur invité |
| DELETE | `/w/{workspace}/members/{user}` | owner |

## 4. Organisations

| Méthode | Route | Rôle |
|---|---|---|
| GET/POST | `/w/{workspace}/organizations` | admin/owner (POST), membre (GET) |
| GET/PATCH/DELETE | `/w/{workspace}/organizations/{id}` | admin/owner |

## 5. Formations et référentiels

| Méthode | Route | Rôle |
|---|---|---|
| GET/POST | `/w/{workspace}/training-programs` | admin/owner |
| GET | `/frameworks` | authentifié |
| GET | `/frameworks/{framework}/versions` | authentifié |
| POST | `/frameworks/{framework}/versions` | admin |
| POST | `/framework-versions/{version}/publish` | admin |
| GET | `/framework-versions/{version}/competencies` | authentifié |

`POST /framework-versions/{version}/publish` :
```json
{ "published_at": "2026-11-04T10:00:00Z" }
```
→ 200 `{ "data": { "status": "published", "immutable": true } }`
→ 409 si déjà publiée ou si des compétences sont incomplètes.

## 6. Stages et participants

| Méthode | Route | Rôle |
|---|---|---|
| GET/POST | `/w/{workspace}/internships` | trainee+ |
| GET/PATCH | `/w/{workspace}/internships/{id}` | participant autorisé |
| POST | `/w/{workspace}/internships/{id}/participants` | trainee/admin |
| DELETE | `/w/{workspace}/internships/{id}/participants/{user}` | trainee/admin |

## 7. Projets, activités, tâches

| Méthode | Route | Rôle |
|---|---|---|
| GET/POST | `/w/{workspace}/projects` | trainee+ |
| GET/POST | `/w/{workspace}/activities` | trainee+ |
| GET/PATCH/DELETE | `/w/{workspace}/activities/{id}` | auteur/trainee (DELETE admin) |
| GET/POST | `/w/{workspace}/tasks` | trainee+ |
| PATCH | `/w/{workspace}/tasks/{id}` | trainee+ |

## 8. Journal

| Méthode | Route | Rôle |
|---|---|---|
| GET/POST | `/w/{workspace}/daily-logs` | trainee (auteur) |
| GET/PATCH | `/w/{workspace}/daily-logs/{id}` | auteur ; lecture `stage` pour participants |
| POST | `/w/{workspace}/daily-logs/{id}/close` | auteur |

**Règle** : `private_notes` n'est jamais sérialisé pour un utilisateur autre
que l'auteur, même avec le rôle superviseur.

## 9. Git

| Méthode | Route | Rôle |
|---|---|---|
| GET/POST | `/w/{workspace}/repositories` | trainee/admin |
| POST | `/w/{workspace}/repositories/{id}/sync` | trainee/admin |
| GET | `/w/{workspace}/repositories/{id}/commits` | membre autorisé |
| POST | `/w/{workspace}/activities/{id}/commits` | trainee (association) |
| GET | `/w/{workspace}/repositories/{id}/sync-runs` | membre autorisé |

`POST .../sync` → 202 :
```json
{ "data": { "sync_run_id": "...", "status": "running" } }
```
Un second import du même dépôt ne crée aucun doublon (contrainte
`UNIQUE (repository_id, sha)`).

## 10. Preuves

| Méthode | Route | Rôle |
|---|---|---|
| GET/POST | `/w/{workspace}/evidences` | trainee+ |
| GET | `/w/{workspace}/evidences/{id}` | selon visibilité |
| PATCH | `/w/{workspace}/evidences/{id}` | propriétaire/admin |
| GET | `/w/{workspace}/evidences/{id}/download` | selon visibilité |
| POST | `/w/{workspace}/evidences/{id}/link` | propriétaire |

Upload : `multipart/form-data`, champ `file` ; types contrôlés ; 20 Mo max.

## 11. Compétences et évaluations

| Méthode | Route | Rôle |
|---|---|---|
| GET | `/w/{workspace}/activities/{id}/competencies` | participant autorisé |
| POST | `/w/{workspace}/activities/{id}/competencies` | trainee |
| POST | `/w/{workspace}/activity-competencies/{id}/assess` | supervisor/tutor |
| PATCH | `/w/{workspace}/activity-competencies/{id}` | trainee (justification) |

`POST .../assess` :
```json
{ "appreciation": "Compétence documentée par des preuves vérifiables.", "status": "validated" }
```
→ crée une `competency_assessment` datée et nominative ; ne modifie jamais
automatiquement le statut sans action explicite.

## 12. Collaboration

| Méthode | Route | Rôle |
|---|---|---|
| GET/POST | `/w/{workspace}/activities/{id}/comments` | participant autorisé |
| POST | `/w/{workspace}/activities/{id}/reviews` | supervisor/tutor |

## 13. Rapports

| Méthode | Route | Rôle |
|---|---|---|
| GET/POST | `/w/{workspace}/report-templates` | admin |
| POST | `/w/{workspace}/internships/{id}/reports` | trainee/admin |
| GET | `/w/{workspace}/reports/{id}` | générateur/participant autorisé |
| GET | `/w/{workspace}/reports/{id}/download` | selon autorisation |

La génération est asynchrone ; la réponse contient les exclusions appliquées
(données privées, preuves non partagées).

## 14. Audit

| Méthode | Route | Rôle |
|---|---|---|
| GET | `/w/{workspace}/audit-logs` | admin/owner |

## 15. Exemples d'erreurs

```json
// 403 (membre d'un autre workspace)
{ "message": "Accès refusé." }

// 422
{
  "message": "Les données fournies sont invalides.",
  "errors": { "work_date": ["La date est déjà utilisée pour ce stage."] }
}

// 429
{ "message": "Trop de tentatives. Réessayez dans 15 minutes." }
```
