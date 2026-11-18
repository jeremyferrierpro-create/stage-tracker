# Rapport d'accessibilité — T-066

*Statut : `DISPONIBLE` — audit exécuté le 2026-11-18.*

## Méthode

- Outil : **axe-core 4.14** (`@axe-core/playwright`), règles activées
  `wcag2a` + `wcag2aa` (WCAG 2.1 niveau A et AA).
- Exécution : **Playwright 1.65** (Chromium headless), suite
  `e2e/accessibility.spec.ts` — exigence : **zéro défaut d'impact
  « serious » ou « critical »** sur chaque page auditée.
- Pages auditées (connectées, comptes de démonstration) :

| Page | Compte |
|---|---|
| `/login` (publique) | — |
| Tableau de bord de l'espace | admin |
| Liste des stages | admin |
| Fiche du stage (avec formulaire « Ajouter un participant ») | admin |
| Liste des projets | admin |
| Fiche du projet | admin |
| Fiche de l'activité (tâches, commentaires, revues, compétences) | admin |
| Compétences de l'espace | admin |
| Rapports du stage | admin |
| Modèles de rapports | admin |
| Membres et invitations | admin |
| Journal d'audit | admin |
| Preuves | admin |
| Liste des stages + journal quotidien | stagiaire |

## Problèmes trouvés et corrigés

| # | Défaut (axe) | Impact | Page | Correction |
|---|---|---|---|---|
| 1 | `select-name` [critical] — 2 `<select>` sans nom accessible | critical | Fiche du stage (ajout de participant) | `<label>` associés via `for`/`id` (`participant-user`, `participant-role`) |
| 2 | `select-name` [critical] — 3 `<select>` sans nom accessible | critical | Fiche de l'activité | `<label>` associés (`task-title`, `task-priority`, `task-status`) + `aria-label` sur les sélecteurs de statut de tâche et de revue |

Ces corrections sont structurelles (labels réellement associés, pas de
solution cosmétique) ; elles bénéficient à tous les profils d'utilisateurs,
pas seulement aux lecteurs d'écran.

## Résultat

- **10/10 parcours Playwright PASS** (dont 3 audits axe-core et le nouveau
  parcours « guide tuteur » : évaluation nominative d'une compétence avec
  capture `docs/screenshots/tutor-assessment.png`).
- Zéro défaut `serious`/`critical` sur l'ensemble des pages auditées.

## Limites connues (documentées, hors exigence T-066)

- L'audit automatique axe-core ne couvre pas tout : l'audit manuel
  (navigation clavier complète, lecteur d'écran, ordre de lecture) reste un
  complément recommandé — hors périmètre de la V1.
- Les contrastes de la mention « à venir » (sections non livrées) et des
  badges d'état sont des éléments décoratifs vérifiés AA par axe ; les
  couleurs restent tributaires du thème (mode clair).
- Parcours testés sur Chromium uniquement (moteur unique) ; les écarts
  WebKit/Firefox ne sont pas couverts en V1.
