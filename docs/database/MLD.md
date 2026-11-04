# MLD — Modèle logique de données (PostgreSQL 16)

> Conventions : `bigint`/`bigserial` pour les clés internes, `uuid` pour les
> entités exposées, `timestamptz` pour les horodatages, `snake_case`.
> Toutes les tables métier portent `workspace_id` (directement ou via FK
> composite). Suppression logique par `archived_at` lorsque l'historique doit
> être conservé.

## 1. Patron d'isolation (appliqué partout)

```sql
-- Chaque table tenant-scopée expose une clé candidate (id, workspace_id)
-- pour permettre les FK composites.
UNIQUE (id, workspace_id);
-- Les tables filles référencent (parent_id, workspace_id)
FOREIGN KEY (parent_id, workspace_id) REFERENCES parent (id, workspace_id);
```

Conséquence : une ligne dont le `workspace_id` diffère de celui de son parent
est **rejetée par PostgreSQL**, même en cas de bug applicatif (INV-11).

## 2. Domaine Identité

### users
| Colonne | Type | Contraintes |
|---|---|---|
| id | bigserial | PK |
| name | varchar(120) | NOT NULL |
| email | varchar(190) | NOT NULL, UNIQUE, lower-case |
| password_hash | varchar(255) | NOT NULL |
| status | varchar(20) | NOT NULL DEFAULT 'active', CHECK IN ('active','disabled') |
| email_verified_at | timestamptz | NULL |
| last_login_at | timestamptz | NULL |
| created_at / updated_at | timestamptz | NOT NULL |

### workspaces
| Colonne | Type | Contraintes |
|---|---|---|
| id | uuid | PK DEFAULT gen_random_uuid() |
| name | varchar(120) | NOT NULL |
| type | varchar(20) | NOT NULL, CHECK IN ('personal','association','professional','pedagogical') |
| owner_id | bigint | NOT NULL, FK users(id) ON DELETE RESTRICT |
| status | varchar(20) | NOT NULL DEFAULT 'active', CHECK IN ('active','archived') |
| created_at / updated_at | timestamptz | NOT NULL |

### workspace_members
| Colonne | Type | Contraintes |
|---|---|---|
| id | bigserial | PK |
| workspace_id | uuid | NOT NULL, FK workspaces(id) |
| user_id | bigint | NOT NULL, FK users(id) |
| role | varchar(20) | NOT NULL, CHECK IN ('owner','admin','trainee','supervisor','tutor','viewer') |
| status | varchar(20) | NOT NULL DEFAULT 'active', CHECK IN ('invited','active','suspended','removed') |
| joined_at | timestamptz | NULL |
| UNIQUE (workspace_id, user_id) | | |

### invitations
| Colonne | Type | Contraintes |
|---|---|---|
| id | uuid | PK |
| workspace_id | uuid | NOT NULL, FK workspaces(id) |
| email | varchar(190) | NOT NULL |
| role | varchar(20) | NOT NULL (mêmes valeurs que workspace_members.role) |
| token_hash | varchar(255) | NOT NULL, UNIQUE |
| expires_at | timestamptz | NOT NULL |
| accepted_at | timestamptz | NULL |
| created_by | bigint | NOT NULL, FK users(id) |
| UNIQUE (workspace_id, email) WHERE accepted_at IS NULL | | index partiel |

## 3. Domaine Organisations

### organizations
| Colonne | Type | Contraintes |
|---|---|---|
| id | uuid | PK |
| workspace_id | uuid | NOT NULL, FK workspaces(id) |
| name | varchar(160) | NOT NULL |
| type | varchar(20) | NOT NULL, CHECK IN ('company','association','training_center','partner') |
| contact_email | varchar(190) | NULL |
| contact_phone | varchar(40) | NULL |
| address | text | NULL |
| archived_at | timestamptz | NULL |
| UNIQUE (id, workspace_id) | | patron d'isolation |

### organization_members
| Colonne | Type | Contraintes |
|---|---|---|
| id | bigserial | PK |
| organization_id | uuid | NOT NULL |
| workspace_id | uuid | NOT NULL |
| user_id | bigint | NOT NULL, FK users(id) |
| role | varchar(20) | NOT NULL DEFAULT 'member' |
| FK (organization_id, workspace_id) → organizations(id, workspace_id) | | |
| UNIQUE (organization_id, user_id) | | |

## 4. Domaine Formation

### training_programs
| Colonne | Type | Contraintes |
|---|---|---|
| id | uuid | PK |
| workspace_id | uuid | NOT NULL, FK workspaces(id) |
| organization_id | uuid | NOT NULL (organisme de formation) |
| name | varchar(160) | NOT NULL |
| certification | varchar(160) | NULL |
| start_date / end_date | date | NULL |
| FK (organization_id, workspace_id) → organizations(id, workspace_id) | | |
| UNIQUE (id, workspace_id) | | |

### training_enrollments
| Colonne | Type | Contraintes |
|---|---|---|
| id | bigserial | PK |
| user_id | bigint | NOT NULL, FK users(id) |
| training_program_id | uuid | NOT NULL |
| workspace_id | uuid | NOT NULL |
| start_date / end_date | date | NOT NULL |
| status | varchar(20) | NOT NULL DEFAULT 'active' |
| FK (training_program_id, workspace_id) → training_programs(id, workspace_id) | | |
| UNIQUE (user_id, training_program_id) | | |

## 5. Domaine Référentiels

### competency_frameworks
| Colonne | Type | Contraintes |
|---|---|---|
| id | uuid | PK |
| name | varchar(160) | NOT NULL |
| issuing_authority | varchar(160) | NULL |
| description | text | NULL |
| status | varchar(20) | NOT NULL DEFAULT 'draft', CHECK IN ('draft','active','archived') |
| UNIQUE (name) | | |

### framework_versions
| Colonne | Type | Contraintes |
|---|---|---|
| id | uuid | PK |
| framework_id | uuid | NOT NULL, FK competency_frameworks(id) |
| version | varchar(40) | NOT NULL |
| effective_date | date | NULL |
| status | varchar(20) | NOT NULL DEFAULT 'draft', CHECK IN ('draft','published','archived') |
| published_at | timestamptz | NULL |
| UNIQUE (framework_id, version) | | |
| CHECK (status <> 'published' OR published_at IS NOT NULL) | | |

### competency_blocks
| Colonne | Type | Contraintes |
|---|---|---|
| id | uuid | PK |
| framework_version_id | uuid | NOT NULL, FK framework_versions(id) |
| code | varchar(40) | NOT NULL |
| title | varchar(200) | NOT NULL |
| description | text | NULL |
| position | smallint | NOT NULL DEFAULT 0 |
| UNIQUE (framework_version_id, code) | | |

### competencies
| Colonne | Type | Contraintes |
|---|---|---|
| id | uuid | PK |
| framework_version_id | uuid | NOT NULL, FK framework_versions(id) |
| block_id | uuid | NULL, FK competency_blocks(id) |
| parent_id | uuid | NULL, FK competencies(id) |
| code | varchar(40) | NOT NULL |
| title | varchar(200) | NOT NULL |
| description | text | NULL |
| position | smallint | NOT NULL DEFAULT 0 |
| UNIQUE (framework_version_id, code) | | |

### competency_criteria
| Colonne | Type | Contraintes |
|---|---|---|
| id | uuid | PK |
| competency_id | uuid | NOT NULL, FK competencies(id) ON DELETE CASCADE |
| label | varchar(200) | NOT NULL |
| description | text | NULL |
| position | smallint | NOT NULL DEFAULT 0 |

**Immuabilité** : trigger `BEFORE UPDATE OR DELETE` sur `framework_versions`
(statut `published`), `competency_blocks`, `competencies`, `competency_criteria`
→ exception si la version parente est publiée (INV-08).

## 6. Domaine Stages

### internships
| Colonne | Type | Contraintes |
|---|---|---|
| id | uuid | PK |
| workspace_id | uuid | NOT NULL, FK workspaces(id) |
| trainee_id | bigint | NOT NULL, FK users(id) |
| host_organization_id | uuid | NOT NULL |
| training_program_id | uuid | NULL |
| framework_version_id | uuid | NOT NULL, FK framework_versions(id) |
| title | varchar(160) | NOT NULL |
| start_date / end_date | date | NOT NULL, CHECK (end_date >= start_date) |
| objectives | text | NULL |
| status | varchar(20) | NOT NULL DEFAULT 'draft', CHECK IN ('draft','active','completed','archived') |
| FK (host_organization_id, workspace_id) → organizations(id, workspace_id) | | |
| FK (training_program_id, workspace_id) → training_programs(id, workspace_id) | | |
| UNIQUE (id, workspace_id) | | |

### internship_participants
| Colonne | Type | Contraintes |
|---|---|---|
| id | bigserial | PK |
| internship_id | uuid | NOT NULL |
| workspace_id | uuid | NOT NULL |
| user_id | bigint | NOT NULL, FK users(id) |
| role | varchar(20) | NOT NULL, CHECK IN ('trainee','supervisor','tutor') |
| permissions | jsonb | NOT NULL DEFAULT '{}' |
| status | varchar(20) | NOT NULL DEFAULT 'active' |
| FK (internship_id, workspace_id) → internships(id, workspace_id) | | |
| UNIQUE (internship_id, user_id, role) | | |

## 7. Domaine Projets et activités

### projects
| Colonne | Type | Contraintes |
|---|---|---|
| id | uuid | PK |
| internship_id | uuid | NOT NULL |
| workspace_id | uuid | NOT NULL |
| name | varchar(160) | NOT NULL |
| description | text | NULL |
| status | varchar(20) | NOT NULL DEFAULT 'active' |
| start_date / end_date | date | NULL |
| FK (internship_id, workspace_id) → internships(id, workspace_id) | | |
| UNIQUE (id, workspace_id) | | |

### activities
| Colonne | Type | Contraintes |
|---|---|---|
| id | uuid | PK |
| project_id | uuid | NOT NULL |
| workspace_id | uuid | NOT NULL |
| title | varchar(200) | NOT NULL |
| description | text | NULL |
| type | varchar(30) | NOT NULL, CHECK IN ('feature','fix','study','design','research','maintenance','improvement') |
| status | varchar(20) | NOT NULL DEFAULT 'todo', CHECK IN ('todo','in_progress','to_review','done','blocked') |
| priority | varchar(10) | NOT NULL DEFAULT 'medium', CHECK IN ('low','medium','high','critical') |
| start_date / end_date | date | NULL |
| objectives / results / difficulties / solutions | text | NULL |
| archived_at | timestamptz | NULL |
| FK (project_id, workspace_id) → projects(id, workspace_id) | | |
| UNIQUE (id, workspace_id) | | |

### tasks
| Colonne | Type | Contraintes |
|---|---|---|
| id | uuid | PK |
| activity_id | uuid | NULL |
| workspace_id | uuid | NOT NULL |
| title | varchar(200) | NOT NULL |
| description | text | NULL |
| priority | varchar(10) | NOT NULL DEFAULT 'medium' |
| status | varchar(20) | NOT NULL DEFAULT 'todo', CHECK IN ('todo','in_progress','to_review','done','blocked') |
| due_date | date | NULL |
| acceptance_criteria | text | NULL |
| position | integer | NOT NULL DEFAULT 0 |
| FK (activity_id, workspace_id) → activities(id, workspace_id) | | |
| INDEX (workspace_id, status, position) | | |

## 8. Domaine Journal

### daily_logs
| Colonne | Type | Contraintes |
|---|---|---|
| id | uuid | PK |
| internship_id | uuid | NOT NULL |
| workspace_id | uuid | NOT NULL |
| author_id | bigint | NOT NULL, FK users(id) |
| work_date | date | NOT NULL |
| start_time / end_time | time | NULL |
| duration_minutes | smallint | NULL, CHECK (duration_minutes >= 0) |
| objectives / work_done / difficulties / solutions / results / remaining | text | NULL |
| private_notes | text | NULL |
| status | varchar(20) | NOT NULL DEFAULT 'draft', CHECK IN ('draft','closed') |
| closed_at | timestamptz | NULL |
| FK (internship_id, workspace_id) → internships(id, workspace_id) | | |
| UNIQUE (internship_id, author_id, work_date) | | |

### daily_log_activities
| Colonne | Type | Contraintes |
|---|---|---|
| id | bigserial | PK |
| daily_log_id | uuid | NOT NULL |
| activity_id | uuid | NOT NULL |
| workspace_id | uuid | NOT NULL |
| contribution | text | NULL |
| FK (daily_log_id, workspace_id) → daily_logs(id, workspace_id) | | |
| FK (activity_id, workspace_id) → activities(id, workspace_id) | | |
| UNIQUE (daily_log_id, activity_id) | | |

## 9. Domaine Git

### repositories
| Colonne | Type | Contraintes |
|---|---|---|
| id | uuid | PK |
| project_id | uuid | NOT NULL |
| workspace_id | uuid | NOT NULL |
| provider | varchar(20) | NOT NULL DEFAULT 'github' |
| external_id | varchar(120) | NOT NULL |
| name | varchar(160) | NOT NULL |
| repository_url | varchar(500) | NOT NULL |
| default_branch | varchar(120) | NULL |
| last_synced_at | timestamptz | NULL |
| FK (project_id, workspace_id) → projects(id, workspace_id) | | |
| UNIQUE (provider, external_id) | | |

### commits
| Colonne | Type | Contraintes |
|---|---|---|
| id | uuid | PK |
| repository_id | uuid | NOT NULL, FK repositories(id) ON DELETE CASCADE |
| workspace_id | uuid | NOT NULL |
| sha | char(40) | NOT NULL |
| author_name | varchar(160) | NULL |
| author_email | varchar(190) | NULL |
| github_author | varchar(120) | NULL |
| committed_at | timestamptz | NOT NULL |
| message | text | NOT NULL |
| branch | varchar(200) | NULL |
| url | varchar(500) | NULL |
| imported_at | timestamptz | NOT NULL DEFAULT now() |
| UNIQUE (repository_id, sha) | | **INV-05** |

### commit_files
| Colonne | Type | Contraintes |
|---|---|---|
| id | bigserial | PK |
| commit_id | uuid | NOT NULL, FK commits(id) ON DELETE CASCADE |
| path | varchar(500) | NOT NULL |
| change_type | varchar(20) | NOT NULL, CHECK IN ('added','modified','removed','renamed') |
| additions / deletions | integer | NULL |
| UNIQUE (commit_id, path) | | |

### activity_commits
| Colonne | Type | Contraintes |
|---|---|---|
| id | bigserial | PK |
| activity_id | uuid | NOT NULL |
| commit_id | uuid | NOT NULL |
| workspace_id | uuid | NOT NULL |
| linked_by | bigint | NOT NULL, FK users(id) |
| linked_at | timestamptz | NOT NULL DEFAULT now() |
| FK (activity_id, workspace_id) → activities(id, workspace_id) | | |
| FK (commit_id, workspace_id) → commits(id, workspace_id) | | |
| UNIQUE (activity_id, commit_id) | | |

### sync_runs
| Colonne | Type | Contraintes |
|---|---|---|
| id | uuid | PK |
| repository_id | uuid | NOT NULL, FK repositories(id) |
| provider | varchar(20) | NOT NULL |
| started_at / completed_at | timestamptz | |
| status | varchar(20) | NOT NULL, CHECK IN ('running','success','partial','failed') |
| items_imported | integer | NOT NULL DEFAULT 0 |
| error_summary | text | NULL |

## 10. Domaine Preuves

### evidences
| Colonne | Type | Contraintes |
|---|---|---|
| id | uuid | PK |
| workspace_id | uuid | NOT NULL, FK workspaces(id) |
| owner_id | bigint | NOT NULL, FK users(id) |
| title | varchar(200) | NOT NULL |
| type | varchar(30) | NOT NULL, CHECK IN ('screenshot','mockup','uml','mcd','mld','test_report','lighthouse','accessibility','technical_doc','commit','pull_request','external_link') |
| description | text | NULL |
| demonstrated | text | NOT NULL (ce que la preuve démontre) |
| storage_key | varchar(500) | NULL |
| external_url | varchar(500) | NULL |
| visibility | varchar(10) | NOT NULL DEFAULT 'private', CHECK IN ('private','stage','shared') |
| captured_at | timestamptz | NULL |
| archived_at | timestamptz | NULL |
| CHECK (storage_key IS NOT NULL OR external_url IS NOT NULL) | | |

### activity_evidences
| Colonne | Type | Contraintes |
|---|---|---|
| id | bigserial | PK |
| activity_id | uuid | NOT NULL |
| evidence_id | uuid | NOT NULL |
| workspace_id | uuid | NOT NULL |
| note | text | NULL |
| FK (activity_id, workspace_id) → activities(id, workspace_id) | | |
| FK (evidence_id, workspace_id) → evidences(id, workspace_id) | | |
| UNIQUE (activity_id, evidence_id) | | |

## 11. Domaine Évaluation

### activity_competencies
| Colonne | Type | Contraintes |
|---|---|---|
| id | uuid | PK |
| activity_id | uuid | NOT NULL |
| competency_id | uuid | NOT NULL, FK competencies(id) |
| workspace_id | uuid | NOT NULL |
| justification | text | NOT NULL |
| status | varchar(20) | NOT NULL DEFAULT 'mobilized', CHECK IN ('mobilized','documented','evaluated') |
| documented_at | timestamptz | NULL |
| FK (activity_id, workspace_id) → activities(id, workspace_id) | | |
| UNIQUE (activity_id, competency_id) | | |

### competency_assessments
| Colonne | Type | Contraintes |
|---|---|---|
| id | uuid | PK |
| activity_competency_id | uuid | NOT NULL, FK activity_competencies(id) ON DELETE CASCADE |
| assessor_id | bigint | NOT NULL, FK users(id) |
| appreciation | text | NOT NULL |
| status | varchar(20) | NOT NULL, CHECK IN ('pending','validated','rejected') |
| assessed_at | timestamptz | NOT NULL DEFAULT now() |

## 12. Domaine Collaboration

### comments
| Colonne | Type | Contraintes |
|---|---|---|
| id | uuid | PK |
| activity_id | uuid | NOT NULL |
| workspace_id | uuid | NOT NULL |
| author_id | bigint | NOT NULL, FK users(id) |
| content | text | NOT NULL |
| visibility | varchar(10) | NOT NULL DEFAULT 'stage', CHECK IN ('private','stage','shared') |
| created_at | timestamptz | NOT NULL DEFAULT now() |
| FK (activity_id, workspace_id) → activities(id, workspace_id) | | |

### reviews
| Colonne | Type | Contraintes |
|---|---|---|
| id | uuid | PK |
| activity_id | uuid | NOT NULL |
| workspace_id | uuid | NOT NULL |
| reviewer_id | bigint | NOT NULL, FK users(id) |
| status | varchar(20) | NOT NULL, CHECK IN ('requested','in_progress','approved','changes_requested') |
| feedback | text | NULL |
| created_at | timestamptz | NOT NULL DEFAULT now() |
| FK (activity_id, workspace_id) → activities(id, workspace_id) | | |

## 13. Domaine Rapports

### report_templates
| Colonne | Type | Contraintes |
|---|---|---|
| id | uuid | PK |
| workspace_id | uuid | NOT NULL, FK workspaces(id) |
| name | varchar(160) | NOT NULL |
| type | varchar(30) | NOT NULL, CHECK IN ('memoir','professional_file','oral_presentation','personal_review','technical_review','weekly_followup','supervisor_followup') |
| description | text | NULL |
| status | varchar(20) | NOT NULL DEFAULT 'draft' |

### report_template_versions
| Colonne | Type | Contraintes |
|---|---|---|
| id | uuid | PK |
| template_id | uuid | NOT NULL, FK report_templates(id) |
| version | varchar(40) | NOT NULL |
| schema_json | jsonb | NOT NULL |
| published_at | timestamptz | NULL |
| UNIQUE (template_id, version) | | |

### reports
| Colonne | Type | Contraintes |
|---|---|---|
| id | uuid | PK |
| internship_id | uuid | NOT NULL |
| workspace_id | uuid | NOT NULL |
| template_version_id | uuid | NOT NULL, FK report_template_versions(id) |
| generated_by | bigint | NOT NULL, FK users(id) |
| generated_at | timestamptz | NOT NULL DEFAULT now() |
| file_path | varchar(500) | NULL |
| status | varchar(20) | NOT NULL DEFAULT 'queued', CHECK IN ('queued','generated','failed') |
| FK (internship_id, workspace_id) → internships(id, workspace_id) | | |

## 14. Domaine Audit

### audit_logs
| Colonne | Type | Contraintes |
|---|---|---|
| id | bigserial | PK |
| workspace_id | uuid | NULL |
| actor_id | bigint | NULL, FK users(id) |
| action | varchar(80) | NOT NULL |
| subject_type | varchar(120) | NULL |
| subject_id | varchar(120) | NULL |
| metadata | jsonb | NOT NULL DEFAULT '{}' |
| ip | inet | NULL |
| user_agent | varchar(500) | NULL |
| created_at | timestamptz | NOT NULL DEFAULT now() |
| INDEX (workspace_id, created_at) | | |

**Append-only** : aucun `UPDATE`/`DELETE` applicatif ; révocation des droits
d'écriture après insertion (rôle applicatif dédié).

## 15. Index transverses

```sql
CREATE INDEX ix_activities_workspace_status ON activities (workspace_id, status);
CREATE INDEX ix_tasks_workspace_status ON tasks (workspace_id, status);
CREATE INDEX ix_daily_logs_workspace_date ON daily_logs (workspace_id, work_date DESC);
CREATE INDEX ix_commits_workspace_date ON commits (workspace_id, committed_at DESC);
CREATE INDEX ix_evidences_workspace_visibility ON evidences (workspace_id, visibility);
CREATE INDEX ix_audit_workspace_created ON audit_logs (workspace_id, created_at DESC);
```

## 16. Contraintes de conservation

- `archived_at` plutôt que suppression physique pour stages, activités, preuves,
  organisations.
- `users.status='disabled'` conserve toutes les lignes liées (INV-13).
- Les référentiels publiés ne sont jamais modifiés (INV-08) ; une nouvelle
  version est créée.
