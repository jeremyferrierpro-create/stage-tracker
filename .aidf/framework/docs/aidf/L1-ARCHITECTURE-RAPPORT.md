# L1-A — ARCHITECTURE RAPPORT

- Date: 2026-11-20
- AIDF version: 0.1.0 → 0.2.0 (incremental, backward compatible)
- Commit: 85f187a

## Résumé

L1-A propose d'introduire un modèle de données structuré pour AIDF, permettant de :

- Représenter les exigences, décisions, architecture, tests et preuves
- Établir une chaîne de traçabilité vérifiable (exigence → design → implémentation → test → preuve)
- Calculer la couverture de façon déterministe (pas de faux PASS par déclaration)
- Préserver les documents Markdown pour la lisibilité humaine

L1 est **additif** : jamais de modifications destructives, jamais de suppression de fichiers existants.

## État initial

### Commit

- Hash: `85f187a` (initial commit 0.1.0 baseline)
- Branche: `master`
- État Git: clean, aucun secret détecté

### Tests

- Total: 44 tests
- Passants: 44/44
- Échouants: 0/44
- Suite complète exécutée

### Structure

- 18 verbes CLI
- 8 phases, 16 gates, 6 types de checks
- 11 documents Markdown (.aidf/docs/)
- 13 skills builtin
- Evidence engine opérationnel
- CLI autonome, sans dépendance runtime inutile

## Architecture retenue

### Modèle de données

**Canonicité:**

| Couche | Format | Rôle | Auteur | Vérifiable |
|---|---|---|---|---|
| **Canonicale** | JSON | Validation, liens, traçabilité | CLI-only writes | Schema + link resolution |
| **Documentaire** | Markdown | Lisibilité humaine | Read-only (dériver) | Hash comparison |
| **Générée** | Markdown | Affichage IDE/CLI | Read-only (dériver) | Hash comparison |

**Artefacts (L1):**

```text
.aidf/
  artefacts/
    discovery.json          # Fait du projet, contraintes, ressources
    constraints.json        # Limites non-négociables
    requirements.json       # Registre des exigences
    architecture.json       # Décisions architecturales
    test-plan.json          # Stratégie de test et cartographie
    decisions/
      <decision-id>.json    # ADR (Architectural Decision Records)
    security/
      threat-model.json     # Modèle de menace STRIDE
```

### Enveloppe commune

```json
{
  "id": "stable-identifier",
  "schemaVersion": "1.0.0",
  "createdAt": "2026-11-20T...",
  "updatedAt": "2026-11-20T...",
  "status": "DRAFT"|"VALIDATED"|"BLOCKED"|"OUTDATED",
  "source": {
    "type": "human"|"generated"|"derived",
    "author": "Jeremy"|"aidf",
    "createdAt": "2026-11-20T...",
    "generator": null|"schema-name@1.0.0"
  },
  "links": [
    {
      "targetId": "REQ-F-001",
      "targetType": "requirement",
      "type": "implements"|"designed-by"|"implemented-by"|"verified-by"|"evidenced-by"
    }
  ],
  "validation": {
    "schemaVersion": "1.0.0",
    "timestamp": "2026-11-20T...",
    "valid": true,
    "errors": [],
    "warnings": []
  }
}
```

### Identifiants

- **FUNCTIONAL**: `REQ-F-XXX`
- **NON_FUNCTIONAL**: `REQ-NF-XXX`
- **CRITERION**: `REQ-F-XXX-ACn`
- **DECISION**: `ADR-XXX`
- **DESIGN**: `DES-XXX`
- **IMPLEMENTATION**: `BACKEND-XXX`
- **TEST**: `TEST-XXX`
- **EVIDENCE**: `EVID-XXX`

Identifiants stables, jamais réutilisés.

### Liens

| Type | Source | Cible | Signification |
|---|---|---|---|
| `implements` | exigence | exigence/décision | Implémente un objectif |
| `designed-by` | exigence/design | architecture/design | A une conception |
| `implemented-by` | design | implémentation | Conception implémentée |
| `verified-by` | exigence/test | test | Vérifié par un test |
| `evidenced-by` | test | preuve | Test exécuté avec preuve |

### Couverture (calculée, jamais déclarée)

Une exigence est **COVERED** seulement si :

1. **Tous ses critères d'acceptation sont TESTÉS**
   - Chaque critère a au moins une vérification
   - Chaque vérification référence un fichier de test existant
   - La commande de test a été exécutée (preuve existe)

2. **Résultat de test est PASS**
   - Preuve existe pour l'exécution du test
   - Preuve a le statut `PASS`
   - Preuve référence le chemin/commande du test

3. **Preuve est récente**
   - Horodatage dans la période de rétention
   - Commit de preuve correspond à la branche courante (si git)

### Commandes CLI (proposées)

| Commande | Objectif |
|---|---|
| `artefacts list` | Lister les artefacts |
| `artefacts show <id>` | Afficher un artefact |
| `artefacts validate <path>` | Valider un artefact |
| `artefacts validate-all` | Valider tous les artefacts |
| `requirements list` | Lister les exigences |
| `requirements show <id>` | Afficher une exigence |
| `requirements add` | Créer une exigence |
| `requirements update <id>` | Modifier une exigence |
| `requirements delete <id>` | Supprimer une exigence (marquer BLOCKED) |
| `requirements link <id>` | Relier à des tests |
| `requirements coverage` | Calculer la couverture |
| `traceability report` | Rapport de chaîne de traçabilité |
| `traceability missing --type <x>` | Trouver les liens manquants |
| `traceability verify <id>` | Vérifier la chaîne pour un artefact |
| `docs generate <doc>` | Générer JSON depuis Markdown |
| `docs check` | Détecter les divergences |
| `docs regenerate <doc>` | Régénérer Markdown |
| `docs reconcile <doc>` | Réconcilier un document frozen |

### Schema versioning

- Chaque artefact a `schemaVersion` indépendant de la version AIDF
- Schémas stockés dans `.aidf/framework/schemas/`
- Anciennes versions maintenues (backward compatible)
- Migration explicite (`aidf artefacts migrate --to 2.0.0`)

## ADR créés

| ADR | Titre | Statut |
|---|---|---|
| ADR-001-artefacts-architectur.md | Architecture des artefacts structurés | **proposed** |
| ADR-002-requirements-registry.md | Registre des exigences | **proposed** |
| ADR-003-traceability-chain.md | Chaîne de traçabilité | **proposed** |
| ADR-004-markdown-generation.md | Génération Markdown et détection de divergences | **proposed** |
| ADR-005-validation-and-tests.md | Validation et tests | **proposed** |
| ADR-006-cli-commands.md | Commandes CLI | **proposed** |
| ADR-007-compatibility-and-migration.md | Compatibilité et migration | **proposed** |
| ADR-008-schema-versioning.md | Versioning des schémas | **proposed** |
| ADR-009-artefacts-scope.md | Scope des artefacts (L1 vs L2+) | **proposed** |

## Stratégie d'implémentation

### L1-A (actuel)

- Analyse de l'existant
- Définition du modèle commun
- Définition des schémas versionnés
- Définition des relations
- Définition du registre des exigences
- Définition des statuts et règles de couverture
- Définition du modèle documentaire JSON → Markdown
- Définition des tests et critères d'acceptation

**Arrêt obligatoire après L1-A.**

### L1-B (à venir, aprés validation humaine)

- Implémenter l'enveloppe commune
- Implémenter les schémas JSON (Draft 2020-12)
- Implémenter le registre des exigences
- Implémenter les validations (schema, liens, couverture)
- Implémenter les tests (schema, liens, couverture, génération)

### L1-C (à venir)

- Implémenter les relations entre artefacts
- Connecter les exigences aux éléments de conception
- Connecter les éléments de conception à l'implémentation
- Connecter les tests et leurs résultats
- Réutiliser l'Evidence Engine existant
- Implémenter le calcul déterministe de couverture
- Produire les rapports de traçabilité

### L1-D (à venir)

- Implémenter le rendu Markdown
- Implémenter la détection des divergences
- Implémenter la protection des documents frozen
- Implémenter la réconciliation explicite
- Tester les scénarios de conflit

### L1-E (à venir)

- Intégrer les fonctionnalités au CLI existant
- Ajouter les tests d'intégration
- Vérifier la compatibilité
- Vérifier le packaging
- Exécuter la suite complète
- Produire le rapport final

## Risques

| ID | Risque | Impact | Probabilité | Mitigation |
|---|---|---|---|---|
| R-L1-01 | Nouveau code introduit des bugs | BLOCKED | LOW | Tests exhaustifs, review, rollback Git |
| R-L1-02 | Complexité accrue pour les nouveaux utilisateurs | LOW | MEDIUM | Documentation claire, CLI simple, exemples |
| R-L1-03 | Migration de 0.1.0 vers 0.2.0 déstabilise | BLOCKED | LOW | Aadditive-only, pas de breaking changes, Git baseline |
| R-L1-04 | Performance dégradée par validation/json | LOW | LOW | Validation rapide, cache, lazy loading |

## Limites fonctionnelles actuelles

| Fonctionnalité | État |
|---|---|
| Canonical JSON artefacts | Non implémenté (design terminé) |
| Registre des exigences | Non implémenté (design terminé) |
| Chaîne de traçabilité | Non implémentée (design terminé) |
| Couverture déterministe | Non implémentée (design terminé) |
| Génération Markdown | Non implémentée (design terminé) |
| Divergence detection | Non implémentée (design terminé) |
| Protection frozen | Non implémentée (design terminé) |
| CLI commands (artefacts, requirements, traceability) | Non implémentées (design terminé) |

## Statut par critère de sortie L1

| Critère | Condition | État |
|---|---|---|
| Schémas versionnés | Schémas présents et validés par tests | DESIGN ONLY |
| Enveloppe commune | Tous les artefacts respectent le contrat | DESIGN ONLY |
| Requirements Registry | Identifiants stables et critères testables | DESIGN ONLY |
| Références | Liens résolus et contrôlés | DESIGN ONLY |
| Traçabilité | Chaîne requirement → evidence vérifiable | DESIGN ONLY |
| Couverture | Calcul déterministe, aucun faux PASS | DESIGN ONLY |
| Tests | Existence, exécution et résultat distingués | DESIGN ONLY |
| Markdown | Génération reproductible | DESIGN ONLY |
| Divergences | Détectées sans écrasement silencieux | DESIGN ONLY |
| Frozen | Protection démontrée par tests | DESIGN ONLY |
| Compatibilité | CLI et fonctionnalités existantes préservés | PROVEN |
| Migration | Dry-run et réversibilité démontrés | DESIGN ONLY |
| Tests | Suite complète exécutée et passante | PROVEN (44/44) |
| Packaging | Distribution vérifiée | PROVEN |
| Documentation | Contrats et limites documentés | DESIGN ONLY |

**Statut global: PARTIAL** (design terminé, implémentation non commencée)

## Compatibilité avec AIDF 0.1.0

| Élément | Impact |
|---|---|
| 18 verbes CLI | Aucun changement |
| 8 phases | Aucun changement |
| 44 tests existants | Aucun supprimé, tous passants |
| 11 documents Markdown | Non modifiés |
| Evidence Engine | Réutilisé, non modifié |
| Workflow gates | Non modifiés |
| CLI exit codes (0/1/2) | Non modifiés |
| `--json` support | Maintenu |
| `--human --by <name>` | Maintenu |

**Aucun breaking change. Aucune suppression.**

## Preuves

### Commit Git

- `85f187a` — Initial commit 0.1.0 baseline
- `HEAD` — master branch, working tree clean

### Tests

- 44/44 tests passants
- Aucun test supprimé
- Nouveaux tests: 0 (L1-B seulement)

### Fichiers modifiés

- Aucun fichier existant modifié
- 9 ADR créés (docs/aidf/ADR-00*.md)
- 1 rapport L1-A (docs/aidf/L1-ARCHITECTURE-RAPPORT.md)

### Artéfacts créés

- docs/aidf/ADR-001-artefacts-architectur.md
- docs/aidf/ADR-002-requirements-registry.md
- docs/aidf/ADR-003-traceability-chain.md
- docs/aidf/ADR-004-markdown-generation.md
- docs/aidf/ADR-005-validation-and-tests.md
- docs/aidf/ADR-006-cli-commands.md
- docs/aidf/ADR-007-compatibility-and-migration.md
- docs/aidf/ADR-008-schema-versioning.md
- docs/aidf/ADR-009-artefacts-scope.md
- docs/aidf/L1-ARCHITECTURE-RAPPORT.md

## Décision suivante

### Option A: Valider L1-A et passer à L1-B

- Accepter l'architecture proposée
- Valider les ADR 001-009
- Passer à l'implémentation (L1-B)

### Option B: Demander des modifications

- Commentaires sur l'architecture
- Modifications des ADR
- Retour à L1-A

### Option C: Arrêter L1

- Conserver le modèle Markdown-only
- Réévaluer les besoins
- Reprendre ultérieurement

---

**Attend validation humaine avant d'implémenter L1-B.**

Aucun fichier n'a été modifié ou supprimé. Aucun fichier existant n'a été affecté. Tout est réversible par `git reset --hard 85f187a`.