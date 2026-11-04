---
doc: SECURITY
status: VALIDATED
version: 1.0.0
source: stage-tracker-specification
updated: 2026-11-04
validated_by: Jeremy Ferrier
---

# SECURITY — Exigences et analyse de sécurité

> Ce document est une **spécification** de sécurité. Aucun contrôle n'est
> déclaré opérationnel avant vérification réelle en phase SECURITY.

## 1. Actifs à protéger

1. Données personnelles (identité, parcours, évaluations).
2. Notes privées et brouillons du stagiaire.
3. Preuves techniques et documents confidentiels.
4. Jetons d'accès GitHub.
5. Journal d'audit et historique des décisions.
6. Intégrité des référentiels publiés.
7. Données du pilote COAB (confidentialité de l'association).

## 2. Modèle de menaces (résumé STRIDE)

| Menace | Scénario | Contre-mesures |
|---|---|---|
| Spoofing | vol de session, force brute | sessions sécurisées, rate limiting, hash Argon2id/bcrypt |
| Tampering | modification d'un ID pour changer de ressource | policies + scopes workspace + FK composites |
| Repudiation | contestation d'une action | audit_logs append-only, horodatage |
| Information disclosure | accès à une preuve privée, fuite d'export | visibilité serveur, filtrage export, 403/404 neutres |
| Denial of service | upload massif, import répété | limites taille/rate, jobs, idempotence |
| Elevation of privilege | rôle de stage > rôle workspace | règles de composition des rôles |
| Injection | SQL, XSS, upload piégé | requêtes préparées, échappement Blade, validation MIME |

## 3. Contrôles exigés

### 3.1 Authentification et sessions
- Hachage `argon2id` (ou `bcrypt` cost ≥ 12).
- Mot de passe : longueur minimale 12, vérification contre les fuites connues (option).
- Session régénérée à la connexion ; cookie `HttpOnly`, `SameSite=Lax`, `Secure` en production.
- Déconnexion invalide la session ; expiration après 120 min d'inactivité.
- Limitation : 5 tentatives / 15 min par IP + compte ; message neutre.

### 3.2 Autorisation et isolation
- Vérification serveur sur **chaque** action (Policy + middleware workspace).
- Global scope `workspace_id` sur toutes les tables métier.
- Contraintes FK composites incluant `workspace_id`.
- Tests IDOR : accès direct par identifiant d'une ressource d'un autre workspace.
- Rôles de stage ne pouvant jamais dépasser le rôle de workspace.

### 3.3 Données et injections
- Eloquent/Query Builder uniquement ; aucun SQL concaténé.
- Validation serveur systématique (FormRequest) ; listes blanches d'énumérations.
- XSS : échappement Blade ; aucun `{!! !!}` non justifié ; CSP en production.
- Mass assignment contrôlé (`$fillable` explicite).

### 3.4 Uploads et preuves
- Types autorisés : images (png/jpg/webp), pdf, docx, md, zip contrôlé.
- Taille maximale configurable (défaut 20 Mo) ; vérification MIME réelle.
- Stockage hors webroot ; noms aléatoires ; pas d'exécution possible.
- Téléchargement via contrôleur autorisé ; jamais d'URL publique directe.
- Antivirus : option future ; documenté comme NOT_PROVEN tant que non installé.

### 3.5 Secrets et connecteurs
- `.env` jamais versionné ; `.env.example` sans secret.
- Jeton GitHub chiffré (`encrypted`), scope lecture seule, jamais renvoyé au frontend.
- Rotation documentée ; révocation possible sans perte de données.
- Aucun secret dans les logs, l'audit ou les messages d'erreur.

### 3.6 Journalisation et audit
- Actions sensibles journalisées (connexion, partage, export, suppression, import).
- Audit append-only ; accès restreint aux administrateurs.
- Conservation de l'audit ≥ durée de vie du projet ; purge documentée.

### 3.7 Sauvegardes et restauration
- Sauvegarde quotidienne : base + fichiers de preuves.
- Chiffrement des sauvegardes (option future documentée).
- Test de restauration obligatoire (MT-12) avant release.
- Procédure documentée dans `docs/security/backup-restore.md` (à produire en phase SECURITY).

## 4. RGPD / protection des données

Analyse à ce stade (aucune conformité affirmée) :
- **Base légale** : à déterminer par le responsable de traitement (exécution du
  contrat de stage / intérêt légitime pédagogique).
- **Minimisation** : ne collecter que les données nécessaires au suivi.
- **Droits** : accès, rectification, effacement (sous réserve de conservation
  légale), portabilité — procédures à documenter.
- **Conservation** : durée du stage + durée d'archivage à définir avec
  l'organisme de formation.
- **Sous-traitants** : hébergement futur, GitHub (API) — à encadrer.
- **Sécurité** : mesures techniques décrites ci-dessus.
- **Registre des traitements** : à produire avant mise en production.
- **DPO / information** : à définir avec l'organisme.

Statut : **NOT_PROVEN** pour toute affirmation de conformité. L'analyse
juridique complète est requise avant ouverture à des utilisateurs réels autres
que le pilote.

## 5. Correspondance OWASP ASVS (extrait)

| Exigence ASVS | Contrôle prévu | Statut |
|---|---|---|
| V2 Authentification | Argon2id, rate limiting, sessions | spécifié, non testé |
| V3 Gestion de session | cookie sécurisé, expiration, régénération | spécifié, non testé |
| V4 Contrôle d'accès | policies + workspace scope | spécifié, non testé |
| V5 Validation | FormRequest, listes blanches | spécifié, non testé |
| V6 Cryptographie | secrets chiffrés, HTTPS | spécifié, non testé |
| V7 Erreurs/logs | messages neutres, audit | spécifié, non testé |
| V8 Protection données | minimisation, exports filtrés | spécifié, non testé |
| V12 Fichiers | MIME, stockage privé, taille | spécifié, non testé |

## 6. Tests de sécurité obligatoires

1. Accès direct par ID à une ressource d'un autre workspace → 403/404.
2. Modification d'un `workspace_id` en entrée → ignorée.
3. Upload d'un fichier interdit (exécutable) → rejeté.
4. Jeton GitHub jamais présent dans une réponse HTML/JSON.
5. Export d'un stage sans note privée ni preuve `private`.
6. Session expirée → redirection connexion, aucune action possible.
7. Force brute → 429 après seuil.
8. XSS stocké dans un commentaire → échappé à l'affichage.

## 7. Points ouverts (NOT_PROVEN)

- Analyse RGPD juridique complète.
- Antivirus sur uploads.
- Chiffrement des sauvegardes.
- Politique de conservation validée par l'organisme.
- Test d'intrusion externe.
