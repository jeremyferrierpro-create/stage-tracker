---
doc: UI-UX
status: VALIDATED
version: 1.0.0
source: stage-tracker-specification
updated: 2026-11-04
validated_by: Jeremy Ferrier
---

# UI-UX — Interface et expérience utilisateur

## 1. Principes

1. **Lisibilité avant effets** : interface sobre, professionnelle, dense mais respirée.
2. **Cohérence** : mêmes composants, mêmes libellés, mêmes emplacements d'action.
3. **Traçabilité visible** : chaque élément affiche son statut, son auteur et sa date.
4. **Français** : toutes les chaînes utilisateur sont en français (i18n prête).
5. **Accessibilité** : WCAG 2.2 AA sur le périmètre applicable.

## 2. Structure de page

```
┌────────────────────────────────────────────────────────┐
│ Barre supérieure : logo · workspace actif · recherche  │
│                    notifications · compte                │
├───────────┬────────────────────────────────────────────┤
│ Navigation│ Contenu principal                          │
│ latérale  │ ┌ Fil d'Ariane                              │
│ (fixe)    │ ├ Titre + actions primaires                 │
│           │ ├ Contenu (liste / détail / formulaire)     │
│           │ └ Pagination / actions secondaires          │
└───────────┴────────────────────────────────────────────┘
```

- Navigation latérale : Tableau de bord, Mes stages, Journal, Activités,
  Kanban, Git, Preuves, Compétences, Rapports, Paramètres.
- Le workspace actif est toujours visible (sélecteur).
- Fil d'Ariane obligatoire dès le 2e niveau.

## 3. Composants du design system

| Composant | Usage | États |
|---|---|---|
| Bouton | action primaire/secondaire/danger | normal, hover, focus, disabled, loading |
| Carte | stage, activité, preuve, commit | défaut, sélectionnée, archivée |
| Tableau | listes paginées | tri, filtre, vide, chargement |
| Badge de statut | PASS/BLOCKED/FAIL/NOT_PROVEN, statuts métier | couleurs + texte (jamais couleur seule) |
| Onglets | détail stage (activités, journal, preuves…) | actif, désactivé |
| Modale | confirmation destructive, aperçu | focus piégé, fermeture Échap |
| Toast | résultat d'action | succès, erreur, information |
| Formulaire | création/édition | validation en ligne, erreurs, aide |
| Kanban | tâches par statut | colonnes, glisser-déposer, compteur |
| Timeline | journal, synchronisations | groupement par date |
| Indicateur de visibilité | private / stage / shared | icône + libellé |

## 4. Formulaires

- Libellés explicites, jamais de placeholder seul.
- Validation serveur systématique ; messages en français, associés au champ.
- Champs obligatoires marqués ; aide contextuelle sur les champs ambigus
  (visibilité, référentiel, ce que démontre une preuve).
- Actions : « Enregistrer » (primaire), « Enregistrer le brouillon »
  (secondaire), « Annuler » (lien).
- Protection contre la double soumission (bouton désactivé + indicateur).
- Brouillons restaurés en cas de perte de connexion (Livewire).

## 5. États et feedback

- **Chargement** : squelette ou spinner sur chaque zone asynchrone.
- **Vide** : message explicatif + action de création.
- **Erreur** : message clair, action de reprise, aucune donnée perdue.
- **Succès** : toast + mise à jour immédiate de la vue.
- **Permission refusée** : message neutre, pas d'indication d'existence de la ressource.

## 6. Accessibilité (WCAG 2.2 AA)

- Contraste texte ≥ 4.5:1 ; éléments non textuels ≥ 3:1.
- Navigation clavier complète ; ordre de focus logique ; focus visible.
- Attributs ARIA sur les composants dynamiques (Livewire, modales, onglets).
- Formulaires : `<label>` associés, `aria-describedby` pour les erreurs.
- Tableaux : en-têtes `<th scope>` ; pagination annoncée.
- Aucune information transmise uniquement par la couleur.
- Langue déclarée (`lang="fr"`), titres hiérarchisés.
- Cibles tactiles ≥ 24×24 px.
- Tests : audit automatique (Lighthouse/axe) + vérification manuelle clavier.

## 7. Responsive

- Points de rupture : mobile < 768 px, tablette 768–1199 px, bureau ≥ 1200 px.
- Navigation latérale repliable en mobile (menu).
- Kanban : défilement horizontal en mobile, colonnes empilées en option.
- Tableaux : transformation en cartes en mobile pour les listes clés.

## 8. Contenus et ton

- Phrases courtes, verbes d'action, pas de jargon inutile.
- Dates au format `jj/mm/aaaa`, heures `HH:MM`, fuseau local.
- Statuts métier explicites : « À faire », « En cours », « À vérifier », « Terminé », « Bloqué ».
- Avertissements de visibilité avant partage ou export.
- Aucune formulation promettant une acquisition automatique de compétence.

## 9. Écrans prioritaires (V1)

1. Connexion / invitation.
2. Tableau de bord (prochaines échéances, activité récente, alertes).
3. Mes stages (liste + détail).
4. Journal (liste + saisie).
5. Activités (liste + détail + associations).
6. Kanban.
7. Git (dépôts, commits, synchronisations).
8. Preuves (galerie + détail + visibilité).
9. Compétences (matrice activité × compétence).
10. Rapports (modèles + génération + historique).
11. Paramètres (membres, rôles, invitations, organisations, référentiels).
