# TEST-MATRIX — Traçabilité tâches ↔ tests ↔ invariants

## 1. Matrice principale

| Test / invariant | Tâche(s) | Niveau | Statut attendu |
|---|---|---|---|
| MT-01 deux workspaces | T-005, T-006, T-012 | Feature | PASS |
| MT-02 non-membre | T-005, T-006, T-012 | Feature | PASS |
| MT-03 rôles stagiaire/maître | T-006, T-052 | Feature | PASS |
| MT-04 deux stages | T-023, T-030 | Feature | PASS |
| MT-05 versions de référentiel | T-022, T-030 | Feature | PASS |
| MT-06 import répété | T-044, T-046 | Feature | PASS |
| MT-07 preuve privée | T-041, T-046 | Feature | PASS |
| MT-08 preuve partagée | T-041, T-046 | Feature | PASS |
| MT-09 commentaire | T-050, T-030 | Feature | PASS |
| MT-10 révocation | T-012, T-053 | Feature | PASS |
| MT-11 export filtré | T-063 | Feature | PASS |
| MT-12 sauvegarde/restauration | T-065 | Script | PASS |
| INV-01 accès workspaces | T-005, T-006 | Feature | PASS |
| INV-02 rattachement activité | T-023, T-025 | Feature | PASS |
| INV-03 journal auteur/stage | T-027 | Feature | PASS |
| INV-04 commit → dépôt | T-044 | Feature | PASS |
| INV-05 unicité commit | T-044 | Feature | PASS |
| INV-06 preuve propriétaire/visibilité | T-041 | Feature | PASS |
| INV-07 compétence versionnée | T-021, T-022 | Feature | PASS |
| INV-08 référentiel immuable | T-022 | Feature | PASS |
| INV-09 pas de validation auto | T-060, T-061 | Feature | PASS |
| INV-10 provenance importée | T-044 | Feature | PASS |
| INV-11 relation inter-workspace | T-005, T-006 | Feature | PASS |
| INV-12 commentaire auteur/date | T-050 | Feature | PASS |
| INV-13 désactivation conserve historique | T-053 | Feature | PASS |
| INV-14 export autorisé | T-063 | Feature | PASS |

## 2. Tests de sécurité

| Test | Tâche | Niveau |
|---|---|---|
| IDOR inter-workspace | T-006, T-052 | Feature |
| `workspace_id` forgé | T-005 | Feature |
| Upload interdit | T-040 | Feature |
| Jeton GitHub absent des réponses | T-043 | Feature |
| Session expirée | T-004 | Feature |
| Force brute (429) | T-004 | Feature |
| XSS stocké | T-050 | Feature |
| CSRF | T-011 | Feature |

## 3. Parcours Playwright

| Parcours | Tâche | Dépendances |
|---|---|---|
| Connexion → dashboard | T-046 | T-004, T-029 |
| Création stage + invitation | T-046 | T-023, T-007 |
| Journée + clôture | T-046 | T-027, T-028 |
| Activité + preuve partagée | T-046 | T-025, T-041 |
| Import GitHub + association | T-046 | T-044, T-045 |
| Consultation maître | T-054, T-066 | T-052 |
| Export + exclusions | T-066 | T-063 |
| Navigation clavier | T-066 | T-011 |

## 4. Règles de couverture

1. Chaque invariant a au moins un test automatisé identifié ci-dessus.
2. Chaque critère d'acceptation (AC-*) est couvert par au moins un test.
3. Un test ne peut être marqué `skipped` que si la dépendance est
   **NOT_PROVEN** et documentée (ex. Playwright non installé).
4. Les résultats sont enregistrés comme preuves AIDF à chaque campagne.
