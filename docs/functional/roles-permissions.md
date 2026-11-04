# Matrice des rôles et permissions

## 1. Rôles de workspace

| Rôle | Portée | Description |
|---|---|---|
| `owner` | workspace | propriétaire ; tous les droits, transfert de propriété |
| `admin` | workspace | administration des membres, organisations, référentiels |
| `trainee` | workspace | crée et documente ses stages, activités, journaux, preuves |
| `supervisor` | stage attribué | consulte et commente les éléments partagés |
| `tutor` | stagiaires autorisés | suit et évalue les compétences |
| `viewer` | workspace | lecture seule des éléments partagés |

## 2. Rôles de stage

| Rôle | Attribué à | Effet |
|---|---|---|
| `trainee` | stagiaire | propriétaire du journal et des activités de son stage |
| `supervisor` | maître de stage | accès aux éléments `stage`/`shared` du stage |
| `tutor` | tuteur pédagogique | accès pédagogique + évaluation |

**Règle** : un rôle de stage ne peut jamais dépasser le rôle de workspace
correspondant. Un `viewer` ne peut pas être `supervisor` d'un stage.

## 3. Matrice actions × rôles

Légende : ✅ autorisé · 🔶 conditionnel (propriétaire/partagé) · ❌ interdit

| Action | owner | admin | trainee | supervisor | tutor | viewer |
|---|---|---|---|---|---|---|
| Consulter les stages autorisés | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Créer un stage | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| Modifier un stage | ✅ | ✅ | 🔶 auteur | ❌ | ❌ | ❌ |
| Inviter un participant | ✅ | ✅ | 🔶 son stage | ❌ | ❌ | ❌ |
| Créer un journal | ✅ | ❌ | ✅ | ❌ | ❌ | ❌ |
| Modifier son journal | ✅ | ❌ | ✅ | ❌ | ❌ | ❌ |
| Lire un journal `stage` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Lire `private_notes` | ❌ | ❌ | 🔶 auteur | ❌ | ❌ | ❌ |
| Créer une activité | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| Commenter une activité | ✅ | ✅ | ✅ | ✅ | ✅ | 🔶 partagé |
| Déposer une preuve | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| Consulter une preuve `private` | ❌ | ❌ | 🔶 propriétaire | ❌ | ❌ | ❌ |
| Consulter une preuve `stage` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Consulter une preuve `shared` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Associer une compétence | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| Émettre une appréciation | ❌ | ❌ | ❌ | ✅ | ✅ | ❌ |
| Gérer un référentiel | ✅ | ✅ | ❌ | ❌ | 🔶 permission | ❌ |
| Gérer les utilisateurs | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| Générer un export | ✅ | ✅ | ✅ | 🔶 autorisé | 🔶 autorisé | ❌ |
| Consulter l'audit | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |

## 4. Permissions fines (stage)

`internship_participants.permissions` (JSON) peut restreindre :
- `view_journal`, `view_activities`, `view_evidences`, `comment`, `review`,
  `export`.
Valeurs par défaut selon le rôle ; jamais d'élargissement au-delà du rôle de
workspace.

## 5. Règles de composition

1. Les permissions effectives = intersection (rôle workspace ∩ rôle stage ∩
   visibilité ressource).
2. Un utilisateur non membre du workspace n'a **aucun** accès, même avec un
   rôle de stage résiduel (révocation immédiate).
3. La révocation d'un participant conserve l'historique de ses contributions.
4. Un compte `disabled` perd l'accès mais son historique reste lisible par les
   ayants droit.

## 6. Tests associés

| Test | Vérifie |
|---|---|
| MT-02 | non-membre → 403 sur toute ressource |
| MT-03 | stagiaire vs maître : droits différents |
| MT-07 | preuve privée invisible au maître |
| MT-08 | preuve partagée consultable |
| MT-11 | export sans données privées |
