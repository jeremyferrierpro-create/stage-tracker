# MILESTONES — Ordonnancement et chemin critique

## 1. Principe

Aucune tâche ne démarre avant que ses dépendances soient **PASS** avec preuve
enregistrée. L'ordre suit strictement le prompt maître §9 phase 4.

## 2. Graphe de dépendances (résumé)

```
T-001 Squelette
  └─ T-002 PostgreSQL
       └─ T-003 Qualité/tests
            └─ T-004 Auth
                 ├─ T-005 Workspaces ─┬─ T-006 Policies ─┬─ T-007 Invitations
                 │                    │                  └─ T-012 Campagne 1
                 │                    ├─ T-008 Organisations
                 │                    └─ T-009 Audit
                 ├─ T-010 Factories/seeders
                 └─ T-011 Layout

T-012 ─┬─ T-020 Formations
       ├─ T-021 Référentiels ── T-022 Publication immuable
       └─ T-023 Stages (dépend T-020, T-022, T-008)
              └─ T-024 Projets ── T-025 Activités ─┬─ T-026 Kanban
                                                   ├─ T-027 Journal ── T-028 Clôture
                                                   └─ T-029 Dashboard
T-030 Campagne 2

T-030 ─┬─ T-040 Stockage ── T-041 Preuves ── T-042 Liens
       ├─ T-043 Dépôts ── T-044 Import ── T-045 Fichiers/associations
       └─ T-050 Commentaires ── T-051 Revues ── T-052 Permissions ─┬─ T-053 Révocation
                                                                   └─ T-054 Vue maître
T-046 Campagne 3 (Playwright)

T-052 ─┬─ T-060 Compétences ── T-061 Évaluations
       └─ T-062 Modèles ── T-063 Génération ── T-064 Exports
T-065 Sauvegarde/restauration
T-066 Accessibilité/E2E
T-070 Sécurité
T-071 Release
```

## 3. Jalons

| Jalon | Tâches | Preuve de sortie | Gate AIDF |
|---|---|---|---|
| **J1 — Socle opérationnel** | T-001 → T-003 | `docker compose ps` healthy + `artisan migrate` + suite vide verte | implementation-entry |
| **J2 — Identité et isolation** | T-004 → T-012 | MT-01, MT-02, MT-03, MT-10 PASS | — |
| **J3 — Stage pilote** | T-020 → T-030 | stage COAB saisi, journal + Kanban + activité | — |
| **J4 — Traçabilité** | T-040 → T-046 | MT-06, MT-07, MT-08 PASS + double import | — |
| **J5 — Collaboration** | T-050 → T-054 | MT-03 complet, MT-09 PASS | — |
| **J6 — Pédagogie/exports** | T-060 → T-064 | MT-05, MT-11 PASS | — |
| **J7 — Fiabilité** | T-065 → T-066 | MT-12 PASS + rapport accessibilité | testing-exit |
| **J8 — Sécurité** | T-070 | rapport de sécurité, 0 vulnérabilité bloquante | security-exit |
| **J9 — Release** | T-071 | rapport de release + approbations | release-exit |

## 4. Chemin critique

`T-001 → T-002 → T-003 → T-004 → T-005 → T-006 → T-012 → T-023 → T-025 →
T-027 → T-041 → T-044 → T-052 → T-063 → T-070 → T-071`

Toute tâche du chemin critique retarde la release. Les tâches hors chemin
(seeders, layout, dashboard) peuvent être parallélisées sans risque.

## 5. Lots de livraison

| Lot | Contenu | Valeur démontrable |
|---|---|---|
| L1 | T-001 → T-012 | plusieurs comptes, espaces isolés |
| L2 | T-020 → T-030 | un stage complet suivi sans Git |
| L3 | T-040 → T-046 | activité documentée par des preuves réelles |
| L4 | T-050 → T-054 | maître de stage et stagiaire collaborent |
| L5 | T-060 → T-066 | exploitation pédagogique et exports |
| L6 | T-070 → T-071 | release prouvée |

## 6. Estimation relative (sans engagement de durée)

| Lot | Complexité | Justification |
|---|---|---|
| L1 | Élevée | isolation, auth, policies, tests |
| L2 | Élevée | modèle métier dense, journal, Kanban |
| L3 | Moyenne-élevée | intégration externe, uploads, idempotence |
| L4 | Moyenne | policies croisées, UI limitée |
| L5 | Élevée | référentiels, évaluations, exports |
| L6 | Moyenne | revue et documentation |

Aucune durée calendaire n'est affirmée sans mesure réelle (règle AIDF).

## 7. Risques d'ordonnancement

| Risque | Impact | Mesure |
|---|---|---|
| PHP 8.5 et dépendances tierces | blocage T-001/T-064 | vérifier chaque paquet avant ajout |
| Playwright non installé | T-046/T-066 NOT_PROVEN | installer et prouver, sinon documenter |
| Modèle de compétences trop abstrait | retards L5 | valider avec 2 référentiels de test |
| Fuite de visibilité | bloquant | MT-07/MT-08/MT-11 avant toute release |
| Volume de l'import GitHub | lenteur | pagination + jobs |

## 8. Règles de progression

1. Une tâche n'est `done` que si ses tests sont **exécutés** et sa preuve enregistrée.
2. Un échec de test bloque la tâche suivante du même lot.
3. Toute déviation de plan est consignée dans `DECISIONS.md` et approuvée.
4. Les campagnes de tests (T-012, T-030, T-046) sont des points d'arrêt obligatoires.
