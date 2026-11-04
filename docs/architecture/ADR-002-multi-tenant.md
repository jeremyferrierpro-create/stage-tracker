# ADR-002 — Isolation multi-tenant et autorisations

- Statut : ACCEPTED (2026-11-04)
- Décideurs : Jeremy Ferrier (propriétaire), agent de développement
- Référence : DEC-ST-002, DEC-ST-005, INV-01, INV-11

## Contexte

Plusieurs utilisateurs, organisations et stagiaires partagent la même
application. Un utilisateur peut être stagiaire dans un workspace et maître de
stage dans un autre. Une erreur d'isolation exposerait des données personnelles
et des preuves confidentielles. Le contrôle d'accès ne peut pas reposer sur
l'interface.

## Décision

### Modèle de tenancy

- Entité centrale : `workspaces` (personnel, associatif, professionnel, pédagogique).
- Appartenance : `workspace_members(workspace_id, user_id, role, status)`.
- Toute table métier porte `workspace_id` (directement ou via relation vérifiable).

### Défense en profondeur (4 couches)

1. **Routage** : workspace courant résolu (`/w/{workspace}`) ; middleware
   `EnsureWorkspaceMember` (403 si non-membre actif).
2. **Applicatif** : `WorkspaceScope` global Eloquent ; toute requête filtrée par
   `workspace_id` issu du contexte serveur (jamais de l'entrée utilisateur).
3. **Autorisation** : `Policy` par ressource (view/create/update/delete/share/
   export/evaluate), combinant rôle workspace + rôle stage + visibilité.
4. **Base de données** : clés étrangères composites incluant `workspace_id` ;
   une relation inter-workspace est **impossible** au niveau SQL.

### Rôles

- Workspace : `owner`, `admin`, `trainee`, `supervisor`, `tutor`, `viewer`.
- Stage : `trainee`, `supervisor`, `tutor`.
- Un rôle de stage ne peut jamais dépasser le rôle de workspace.
- Les rôles sont attribués dans un contexte ; aucun rôle global au compte.

### Visibilité

`private` (auteur), `stage` (participants autorisés), `shared` (personnes
explicitement autorisées). Vérification serveur à chaque lecture,
téléchargement et export.

## Justification

- L'isolation applicative seule est contournable par un bug ; les contraintes
  SQL rendent la fuite inter-workspace structurellement impossible.
- Les rôles contextualisés reflètent la réalité (un utilisateur peut cumuler
  des rôles différents selon l'espace).
- Les trois niveaux de visibilité protègent les notes personnelles du stagiaire.

## Conséquences

- Toutes les migrations métier incluent `workspace_id` et les FK composites.
- Toute nouvelle table doit documenter sa stratégie d'isolation.
- Tests bloquants : MT-01 (deux workspaces), MT-02 (non-membre), MT-03 (rôles),
  MT-07/MT-08 (preuves privée/partagée), MT-11 (export).
- Complexité accrue des requêtes et des seeders de test.

## Alternatives écartées

| Alternative | Motif du rejet |
|---|---|
| Filtrage uniquement dans les contrôleurs | contournable, non vérifiable en base |
| Rôle global au compte | incompatible avec le multi-contexte |
| Une base par workspace | complexité opérationnelle excessive en V1 |
| Row Level Security PostgreSQL seule | utile en complément, insuffisante sans policies applicatives |
