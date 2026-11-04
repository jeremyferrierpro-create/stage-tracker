# Dictionnaire de données

> Complément du MLD : définition métier de chaque champ significatif, règles de
> validation et valeurs autorisées. Les champs techniques (`created_at`,
> `updated_at`) ne sont pas répétés sauf règle particulière.

## 1. Identité

### users
| Champ | Définition | Règles |
|---|---|---|
| name | nom affiché | 2–120 caractères |
| email | identifiant de connexion | unique, format RFC, minuscules |
| password_hash | empreinte du mot de passe | Argon2id/bcrypt ≥ 12 |
| status | état du compte | `active`, `disabled` (aucune suppression d'historique) |
| last_login_at | dernière connexion réussie | informatif, audité |

### workspaces
| Champ | Définition | Règles |
|---|---|---|
| type | nature de l'espace | `personal`, `association`, `professional`, `pedagogical` |
| owner_id | propriétaire | toujours membre `owner` |
| status | état | `active`, `archived` |

### workspace_members
| Champ | Définition | Règles |
|---|---|---|
| role | rôle dans ce workspace | `owner`, `admin`, `trainee`, `supervisor`, `tutor`, `viewer` |
| status | appartenance | `invited`, `active`, `suspended`, `removed` |
| joined_at | date d'acceptation | NULL tant que `invited` |

### invitations
| Champ | Définition | Règles |
|---|---|---|
| email | destinataire | unique par workspace tant que non acceptée |
| role | rôle proposé | doit être ≤ rôle de l'invitant |
| token_hash | empreinte du lien | jamais stocké en clair |
| expires_at | expiration | 7 jours par défaut |

## 2. Organisations et formation

### organizations
| Champ | Définition | Règles |
|---|---|---|
| type | nature | `company`, `association`, `training_center`, `partner` |
| contact_email | contact principal | format e-mail |
| archived_at | archivage | conserve les stages liés |

### training_programs
| Champ | Définition | Règles |
|---|---|---|
| organization_id | organisme de formation | distinct de l'organisation d'accueil |
| certification | certification visée | ex. « DWWM » (texte libre) |

### training_enrollments
| Champ | Définition | Règles |
|---|---|---|
| status | inscription | `active`, `completed`, `withdrawn` |

## 3. Référentiels

### competency_frameworks
| Champ | Définition | Règles |
|---|---|---|
| issuing_authority | autorité émettrice | ex. ministère, entreprise |
| status | état | `draft`, `active`, `archived` |

### framework_versions
| Champ | Définition | Règles |
|---|---|---|
| version | libellé de version | unique par référentiel |
| status | état | `draft`, `published`, `archived` |
| published_at | date de publication | obligatoire si `published` ; déclenche l'immuabilité |

### competency_blocks
| Champ | Définition | Règles |
|---|---|---|
| code | code du bloc | unique dans la version |
| position | ordre d'affichage | entier |

### competencies
| Champ | Définition | Règles |
|---|---|---|
| parent_id | compétence parente | hiérarchie optionnelle |
| code | code | unique dans la version |
| block_id | bloc de rattachement | facultatif |

### competency_criteria
| Champ | Définition | Règles |
|---|---|---|
| label | critère d'évaluation | texte court |
| description | précision | facultatif |

## 4. Stages

### internships
| Champ | Définition | Règles |
|---|---|---|
| trainee_id | stagiaire | doit être membre `trainee` du workspace |
| host_organization_id | organisation d'accueil | ≠ organisme de formation |
| training_program_id | programme | facultatif (stage hors formation) |
| framework_version_id | référentiel applicable | version publiée uniquement |
| status | état | `draft`, `active`, `completed`, `archived` |
| objectives | objectifs du stage | texte |

### internship_participants
| Champ | Définition | Règles |
|---|---|---|
| role | rôle dans le stage | `trainee`, `supervisor`, `tutor` |
| permissions | permissions fines | JSON, valeurs contrôlées |
| status | état | `active`, `removed` (historique conservé) |

## 5. Projets et activités

### projects
| Champ | Définition | Règles |
|---|---|---|
| status | état | `active`, `completed`, `archived` |

### activities
| Champ | Définition | Règles |
|---|---|---|
| type | nature | `feature`, `fix`, `study`, `design`, `research`, `maintenance`, `improvement` |
| status | état Kanban | `todo`, `in_progress`, `to_review`, `done`, `blocked` |
| priority | priorité | `low`, `medium`, `high`, `critical` |
| objectives | objectifs | texte |
| results | résultats obtenus | texte, factuel |
| difficulties | difficultés | texte |
| solutions | solutions apportées | texte |

### tasks
| Champ | Définition | Règles |
|---|---|---|
| activity_id | activité parente | facultatif |
| acceptance_criteria | critères d'acceptation | texte |
| position | ordre dans la colonne | entier |
| due_date | échéance | date future ou nulle |

## 6. Journal

### daily_logs
| Champ | Définition | Règles |
|---|---|---|
| work_date | date de la journée | unique par (stage, auteur) |
| duration_minutes | durée effective | ≥ 0 ; cohérente avec start/end si fournis |
| private_notes | notes personnelles | jamais visibles par le maître de stage par défaut |
| status | état | `draft`, `closed` |
| closed_at | date de clôture | obligatoire si `closed` |

## 7. Git

### repositories
| Champ | Définition | Règles |
|---|---|---|
| provider | fournisseur | `github` en V1 |
| external_id | identifiant externe | unique par provider |
| repository_url | URL du dépôt | HTTPS |

### commits
| Champ | Définition | Règles |
|---|---|---|
| sha | identifiant Git | 40 caractères hexadécimaux |
| author_name / author_email | auteur Git | conservés tels quels |
| github_author | compte GitHub | facultatif |
| committed_at | date du commit | UTC |
| message | message complet | conservé |
| branch | branche observée | facultatif |

### commit_files
| Champ | Définition | Règles |
|---|---|---|
| change_type | type de modification | `added`, `modified`, `removed`, `renamed` |

### sync_runs
| Champ | Définition | Règles |
|---|---|---|
| status | résultat | `running`, `success`, `partial`, `failed` |
| items_imported | éléments importés | entier ≥ 0 |
| error_summary | résumé d'erreur | aucune donnée sensible |

## 8. Preuves

### evidences
| Champ | Définition | Règles |
|---|---|---|
| type | nature de la preuve | énumération contrôlée (voir MLD) |
| demonstrated | ce que la preuve démontre | obligatoire, factuel |
| storage_key | clé de stockage interne | obligatoire si pas d'URL externe |
| external_url | lien externe | commit, PR, URL publique |
| visibility | visibilité | `private`, `stage`, `shared` |
| captured_at | date de capture | facultatif |

## 9. Compétences

### activity_competencies
| Champ | Définition | Règles |
|---|---|---|
| justification | pourquoi cette compétence est mobilisée | obligatoire |
| status | niveau | `mobilized`, `documented`, `evaluated` (jamais automatique) |
| documented_at | date de documentation | si `documented` |

### competency_assessments
| Champ | Définition | Règles |
|---|---|---|
| assessor_id | évaluateur | rôle habilité |
| appreciation | appréciation | texte |
| status | résultat | `pending`, `validated`, `rejected` |

## 10. Collaboration

### comments
| Champ | Définition | Règles |
|---|---|---|
| content | message | non vide, échappé à l'affichage |
| visibility | visibilité | `private`, `stage`, `shared` |

### reviews
| Champ | Définition | Règles |
|---|---|---|
| status | état de revue | `requested`, `in_progress`, `approved`, `changes_requested` |
| feedback | retour | texte |

## 11. Rapports et audit

### report_templates / versions
| Champ | Définition | Règles |
|---|---|---|
| type | type de rapport | énumération (voir MLD) |
| schema_json | structure du modèle | JSON versionné |
| published_at | publication | version publiée immuable |

### reports
| Champ | Définition | Règles |
|---|---|---|
| status | état | `queued`, `generated`, `failed` |
| file_path | emplacement | hors webroot |

### audit_logs
| Champ | Définition | Règles |
|---|---|---|
| action | action effectuée | nomenclature stable |
| metadata | contexte | aucune donnée secrète |
| created_at | horodatage | append-only |
