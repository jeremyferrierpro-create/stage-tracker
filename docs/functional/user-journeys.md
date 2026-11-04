# Parcours utilisateurs détaillés

> Complète `APPFLOW.md` avec les préconditions, étapes, résultats et erreurs.

## J1 — Première utilisation (stagiaire)

**Acteur** : Jeremy, stagiaire COAB.
**Préconditions** : compte créé, workspace personnel créé.

1. Connexion → tableau de bord vide avec actions guidées.
2. Création du workspace « Stage COAB » (type `association`).
3. Création de l'organisation d'accueil COAB et de l'organisme de formation.
4. Création du stage : dates, objectifs, version du référentiel DWWM publiée.
5. Invitation du maître de stage par e-mail.
6. Création du projet « Site web COAB ».
7. Saisie de la première journée de journal.

**Résultat** : stage actif, participants liés, première journée en brouillon.
**Erreurs** : référentiel non publié → blocage avec message ; dates incohérentes → 422.

## J2 — Journée type

1. Ouverture du journal du jour.
2. Saisie des heures et du travail réalisé.
3. Association de 2 activités.
4. Notes privées (non partagées).
5. Enregistrement brouillon, puis clôture en fin de journée.

**Résultat** : journée clôturée, visible par le maître pour les parties `stage`.
**Vérification** : les notes privées restent invisibles.

## J3 — Documentation d'une activité

1. Ouverture de l'activité « Intégration du formulaire de contact ».
2. Rédaction objectifs/résultats/difficultés/solutions.
3. Association de 3 commits importés.
4. Dépôt d'une preuve `shared` (capture + description factuelle).
5. Association de 2 compétences avec justification.

**Résultat** : activité documentée, preuves et compétences liées.
**Interdit** : marquer une compétence `evaluated` sans évaluation.

## J4 — Import GitHub

1. Git → « Associer un dépôt » (URL + jeton serveur).
2. Synchronisation → `sync_runs` en cours.
3. Vérification des commits importés.
4. Association des commits aux activités.
5. Seconde synchronisation → aucun doublon.

**Erreurs** : jeton invalide → message neutre, aucun secret affiché ; limite API
→ reprise possible.

## J5 — Consultation par le maître de stage

1. Réception de l'invitation → connexion.
2. Accès au stage attribué uniquement.
3. Lecture des journées `stage` et des activités partagées.
4. Commentaire sur une activité.
5. Demande de précision (revue).

**Interdits vérifiés** : notes privées, preuves `private`, autres stages,
exports non autorisés.

## J6 — Évaluation pédagogique

1. Le tuteur consulte les compétences `documented`.
2. Il saisit une appréciation et valide ou rejette.
3. Une `competency_assessment` datée et nominative est créée.
4. Le stagiaire voit le résultat sans pouvoir le modifier.

**Règle** : la validation est humaine, jamais automatique.

## J7 — Génération d'une synthèse

1. Rapports → choix du modèle « Bilan de stage ».
2. Sélection du stage et de la période.
3. Aperçu des données incluses et des exclusions.
4. Génération asynchrone → notification.
5. Téléchargement contrôlé.

**Vérification** : aucune donnée privée ; distinction faits/déclarations/appréciations.

## J8 — Fin de stage et archivage

1. Le stagiaire marque le stage `completed`.
2. Archivage : le stage reste consultable.
3. Le compte du maître de stage peut être révoqué sans perte d'historique.
4. Un export final est généré et archivé.

**Règle** : aucune suppression destructive ; conservation documentée.

## J9 — Incident de sécurité (scénario de test)

1. Un utilisateur tente d'accéder à une ressource d'un autre workspace par ID.
2. Le serveur renvoie 403/404 sans divulguer l'existence.
3. L'événement est journalisé (audit) sans données sensibles.

## J10 — Restauration

1. Sauvegarde base + preuves.
2. Restauration sur environnement vierge.
3. Contrôle d'intégrité par empreintes.
4. Reprise des parcours (connexion, lecture d'un stage, téléchargement d'une preuve).
