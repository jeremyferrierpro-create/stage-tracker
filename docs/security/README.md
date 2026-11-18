# Rapport de revue de sécurité — T-070

*Statut : `DISPONIBLE` — revue exécutée le 2026-11-18, avant release 1.6.0.*

## 1. Périmètre et méthode

- Entrées : `.aidf/docs/SECURITY.md` (exigences §3, tests obligatoires §6,
  points ouverts §7) et extrait OWASP ASVS (SECURITY §5).
- Méthode : revue croisée **exigence → contrôle → preuve automatisée** ;
  lecture du code pour les contrôles sans test HTTP dédié ; exécution réelle
  des suites concernées (sorties consignées §4).
- Gravités : convention OWASP (critique / majeure / mineure / informationnelle).

## 2. Les 8 tests de sécurité obligatoires (SECURITY §6)

| # | Test exigé | Preuve automatisée | Statut |
|---|---|---|---|
| S-01 | Accès direct par ID à une ressource d'un autre espace → 403/404 | `MultiTenantCampaignTest` (MT-01, MT-02, MT-10), `WorkspaceIsolationTest` (403 membre d'un autre espace, 404 espace inconnu/mal formé), `WorkspacePolicyTest` | PASS |
| S-02 | `workspace_id` fourni en entrée → ignoré | `SecurityCampaignTest::test_s02_workspace_id_in_input_is_ignored` (T-070) : POST stage avec `workspace_id` forgé → la ressource reste dans le contexte courant | PASS |
| S-03 | Upload d'un type interdit → rejeté | `EvidenceTest::test_forbidden_file_type_is_rejected`, `test_oversized_file_is_rejected`, `test_upload_stores_file_outside_webroot_with_random_name` | PASS |
| S-04 | Jeton GitHub jamais dans une réponse HTML/JSON | `RepositoryTest::test_token_is_never_exposed_in_responses_or_serialization` | PASS |
| S-05 | Export sans note privée ni preuve `private` | `ReportTest::test_mt11_generated_report_excludes_private_data_and_lists_exclusions`, `ReportExportTest` (PDF/DOCX, accès tiers refusé) | PASS |
| S-06 | Session expirée → redirection connexion | `WorkspaceIsolationTest::test_guest_is_redirected_to_login`, `LoginTest::test_user_can_logout` (middleware `auth` + garde de session Laravel) | PASS |
| S-07 | Force brute → 429 après seuil | `LoginTest::test_throttling_returns_429_after_five_failures` | PASS |
| S-08 | XSS stocké dans un commentaire → échappé | `CommentTest::test_xss_content_is_escaped_in_html` (Blade `{{ }}` échappe partout) | PASS |

## 3. Checklist des contrôles (SECURITY §3)

| Contrôle | État | Détail / preuve |
|---|---|---|
| Mots de passe Argon2id | PASS | `LoginTest::test_passwords_are_hashed_with_argon2id` |
| Message d'échec neutre (pas d'énumération de comptes) | PASS | `test_invalid_password_is_rejected_with_a_neutral_message`, `test_unknown_email_returns_the_same_neutral_message` |
| Compte désactivé non connectable | PASS | `test_disabled_account_cannot_login_and_history_is_kept` |
| Throttling connexion (429) | PASS | voir S-07 |
| CSRF | PASS | middleware `web` de Laravel actif sur toutes les routes POST/PUT/DELETE ; aucun formulaire ne contourne `@csrf` |
| Isolation multi-tenant (scope global + policies) | PASS | trait `BelongsToWorkspace` (scope global sur toutes les lectures), policies par ressource, S-01 |
| Validation en allowlist | PASS | tous les contrôleurs utilisent `$request->validate([...])` avec règles explicites ; `workspace_id` n'est jamais une entrée acceptée (S-02) ; `$workspace` provient du binding de route |
| Uploads : types et taille filtrés, stockage hors webroot, nom aléatoire | PASS | voir S-03 ; DEC-ST-007 (stockage abstrait) |
| Secrets (jeton GitHub) chiffrés au repos, jamais exposés | PASS | voir S-04 ; jamais journalisé (AuditLogger) |
| Journal d'audit en écriture seule, allowlist d'événements | PASS | `AuditLogTest` ; événements sensibles tracés (invitations, rôles, exports, échecs de génération) |
| Sauvegardes/restauration vérifiées | PASS | T-065 : `BackupRestoreTest` (MT-12) — empreintes SHA-256 comparées ligne à ligne |
| Permissions effectives et révocation immédiate | PASS | `EffectivePermissionsTest`, `RevocationTest`, MT-10 |

## 4. Preuve d'exécution (2026-11-18)

Vérifications de phase `security` (gate `security-exit`) :

| Vérification | Résultat réel |
|---|---|
| Statut du document SECURITY | `VALIDATED` (gate : PASS) |
| `composer audit --no-dev` | **0 avis de vulnérabilité** |
| `npm audit --omit=dev` | **0 vulnérabilité** |
| Réexécution des 9 suites de sécurité | **55 tests PASS / 251 assertions** (11,9 s) |
| Commande `securityAudit` dédiée | non configurée → check `NOT_APPLICABLE` (non bloquant) |
| Approbation humaine de la revue | **APPROUVÉE le 2026-11-18 par Jeremy Ferrier** (preuve `security-review` avec approbation humaine, demandée à l'issue de la présentation du dossier) |

Suites de sécurité exécutées (`php artisan test`), toutes PASS :

| Suite | Couverture |
|---|---|
| `tests/Feature/Tenant/MultiTenantCampaignTest.php` | S-01 (MT-01, MT-02, MT-03, MT-10) |
| `tests/Feature/Tenant/WorkspaceIsolationTest.php` | S-01, S-06 |
| `tests/Feature/Security/SecurityCampaignTest.php` | S-02 (nouveau, T-070) |
| `tests/Feature/Evidence/EvidenceTest.php` | S-03, MT-07, MT-08 |
| `tests/Feature/Git/RepositoryTest.php` | S-04 |
| `tests/Feature/Reporting/ReportTest.php` + `ReportExportTest.php` | S-05 (MT-11), INV-14 |
| `tests/Feature/Auth/LoginTest.php` | S-07 + Argon2id |
| `tests/Feature/Collaboration/CommentTest.php` | S-08 (MT-09) |

Résultat global de la suite complète : **176 tests PASS / 697 assertions**
(175 + 1 nouveau S-02), `vendor/bin/pint` PASS.

## 5. Constats et corrections au fil de la revue

| Constat | Gravité | Traitement |
|---|---|---|
| `workspace_id` présent dans `$fillable` de plusieurs modèles, sans test HTTP dédié sur l'injection d'entrée | mineure | test S-02 ajouté (T-070) ; l'attaque est déjà neutralisée par la validation en allowlist et le binding de route, le test verrouille la régression |
| Erreur 500 sur la liste des rapports (import `View` erroné dans `ReportController`) | mineure (robustesse, pas de fuite de données) | corrigé en T-066 (import `Illuminate\View\View`), couvert par l'audit axe (page 200) |
| Aucune autre vulnérabilité ouverte | — | — |

**Conclusion : aucune vulnérabilité bloquante ouverte.**

## 6. Limites documentées (reprises de SECURITY §7, non bloquantes)

| Point | Statut | Justification |
|---|---|---|
| Analyse RGPD juridique complète | NOT_PROVEN | hors compétence du projet ; registre et export/suppression des données couverts fonctionnellement (§10) |
| Antivirus sur les uploads | NOT_PROVEN | filtrage MIME + taille + stockage hors webroot ; l'analyse AV nécessiterait un binaire externe |
| Chiffrement des sauvegardes | NOT_PROVEN | les archives sont servies via le disque privé (aucune URL publique) ; chiffrement au repos à traiter avant un déploiement multi-organismes réel |
| Politique de conservation validée par l'organisme | NOT_PROVEN | décision de gouvernance AIDF |
| Test d'intrusion externe | NOT_PROVEN | hors périmètre V1 |
