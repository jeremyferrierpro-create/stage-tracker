# Critères d'acceptation

> Format Given/When/Then. Chaque critère est vérifiable par un test automatisé
> (Feature, Unit ou Playwright) et conditionne la Definition of Done.

## M1 — Identité et authentification

**AC-1.1 Connexion valide**
- Given un compte `active` avec mot de passe valide
- When l'utilisateur se connecte
- Then une session est créée, le mot de passe est vérifié par hash, et la
  dernière connexion est journalisée.

**AC-1.2 Tentatives répétées**
- Given 5 échecs consécutifs en 15 minutes
- When une 6e tentative survient
- Then la réponse est 429 avec délai, sans révéler l'existence du compte.

**AC-1.3 Compte désactivé**
- Given un compte `disabled`
- When il tente de se connecter
- Then l'accès est refusé et l'historique reste intact (INV-13).

## M2 — Workspaces

**AC-2.1 Isolation**
- Given deux workspaces A et B sans membre commun
- When un membre de A accède à une ressource de B par son identifiant
- Then la réponse est 403/404 et aucune donnée de B n'est renvoyée.

**AC-2.2 Rôles contextualisés**
- Given un utilisateur `trainee` dans A et `supervisor` dans B
- When il agit dans A
- Then ses droits sont ceux de `trainee`, jamais ceux de `supervisor`.

## M3 — Organisations

**AC-3.1 Distinction**
- Given un stage
- When l'organisation d'accueil et l'organisme de formation sont saisis
- Then les deux entités restent distinctes et aucune ne remplace l'autre.

## M4 — Référentiels

**AC-4.1 Immuabilité**
- Given une version publiée
- When on tente de modifier une compétence
- Then l'opération est rejetée (trigger + policy) et une nouvelle version est
  requise pour toute évolution.

**AC-4.2 Généricité**
- Given deux référentiels différents
- When les deux sont utilisés par des stages distincts
- Then aucun code spécifique à un référentiel n'est nécessaire.

## M5 — Stages

**AC-5.1 Création**
- Given un workspace avec un stagiaire et une organisation
- When un stage est créé avec une version de référentiel publiée
- Then le stage est persisté, les participants sont liés, un audit est écrit.

**AC-5.2 Archivage**
- Given un stage terminé
- When il est archivé
- Then il reste consultable et ses données ne sont pas supprimées.

## M6 — Projets et activités

**AC-6.1 Rattachement**
- Given un projet d'un stage
- When une activité est créée
- Then elle appartient au projet, au stage et au workspace (INV-02).

**AC-6.2 Activité sans commit**
- Given une activité sans dépôt
- When elle est clôturée
- Then elle reste valide et documentable par d'autres preuves.

## M7 — Journal

**AC-7.1 Notes privées**
- Given une journée avec `private_notes`
- When le maître de stage consulte la journée partagée
- Then les notes privées ne sont pas présentes dans la réponse.

**AC-7.2 Clôture**
- Given une journée `draft`
- When elle est clôturée
- Then `closed_at` est renseigné et toute modification ultérieure est auditée.

## M8 — Kanban

**AC-8.1 Statuts**
- Given une tâche
- When son statut change
- Then la colonne correspondante est mise à jour et l'historique est conservé.

**AC-8.2 Source de vérité**
- Given une intégration externe éventuelle
- When une tâche est modifiée dans Stage Tracker
- Then Stage Tracker reste la source de vérité (aucune double écriture non
  synchronisée).

## M9 — Git

**AC-9.1 Idempotence**
- Given un dépôt déjà synchronisé
- When une nouvelle synchronisation est lancée
- Then aucun commit existant n'est dupliqué (unicité dépôt + SHA).

**AC-9.2 Provenance**
- Given un commit importé
- When il est affiché
- Then auteur Git, date, message, branche et URL sont conservés ; l'auteur
  n'est pas assimilé au stagiaire.

## M10 — Preuves

**AC-10.1 Visibilité**
- Given une preuve `private`
- When le maître de stage tente d'y accéder
- Then la réponse est 403/404, y compris via une URL directe.

**AC-10.2 Véracité**
- Given une capture d'écran
- When elle est enregistrée comme preuve
- Then le champ « démontre » est obligatoire et aucun test non exécuté ne peut
  être déclaré.

## M11 — Compétences

**AC-11.1 Pas de validation automatique**
- Given une activité clôturée
- When aucun évaluateur n'est intervenu
- Then aucune compétence n'est au statut `evaluated`.

**AC-11.2 Évaluation nominative**
- Given un tuteur habilité
- When il évalue une compétence
- Then une `competency_assessment` est créée avec auteur, date et appréciation.

## M12 — Collaboration

**AC-12.1 Commentaires**
- Given un commentaire
- When il est affiché après modification d'autres données
- Then auteur et date d'origine sont conservés.

## M13 — Rapports

**AC-13.1 Exclusions**
- Given un stage avec notes privées et preuves `private`
- When un export est généré
- Then ces éléments sont absents et les exclusions sont listées.

**AC-13.2 Distinction des contenus**
- Given un rapport généré
- When il est lu
- Then faits, déclarations personnelles et appréciations de tiers sont
  distingués.

## Critères transverses

**AC-T.1 Aucune régression d'isolation**
- Toute suite de tests exécute MT-01 et MT-02 ; un échec bloque la release.

**AC-T.2 Accessibilité**
- Given une page clé
- When elle est auditée (Lighthouse/axe) et parcourue au clavier
- Then les critères WCAG 2.2 AA applicables sont respectés.

**AC-T.3 Traçabilité**
- Given une action sensible
- When elle est effectuée
- Then une entrée d'audit est créée (acteur, action, cible, date).
