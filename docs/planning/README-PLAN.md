# README-PLAN — Construction du manuel utilisateur

> Décision de référence : DEC-ST-013. Le `README.md` est le mode d'emploi de
> l'outil, destiné à un camarade de formation qui clone le dépôt pour son
> propre stage.

## 1. Règle de progression

À la fin de chaque tâche d'implémentation :

1. Identifier la ou les sections du README concernées (tableau §2).
2. Rédiger ou compléter la section avec :
   - le rôle concerné ;
   - le parcours pas à pas (écrans, actions, résultats attendus) ;
   - les erreurs fréquentes et leur résolution ;
   - les commandes réellement exécutées (si technique).
3. Vérifier que la section ne décrit aucune fonctionnalité non testée.
4. Inclure la mise à jour dans le commit de la tâche.
5. Enregistrer la preuve AIDF (`implementation-change` ou `documentation-update`).

Une tâche dont l'impact utilisateur est nul peut ne pas modifier le README,
mais la justification est consignée dans la revue de tâche.

## 2. Structure du README et responsabilités

| Section README | Contenu | Remplie par |
|---|---|---|
| 1. Présentation | ce que fait l'outil, ce qu'il ne fait pas | T-001 |
| 2. Statut et gouvernance | phase AIDF, documents, preuves | T-001 |
| 3. Prérequis | versions vérifiées (PHP, Composer, Node, Docker, Git) | T-001, T-002 |
| 4. Installation | clone, dépendances, `.env`, Docker, migrations | T-001 → T-003 |
| 5. Premier lancement | création du compte, workspace initial | T-004, T-005, T-011 |
| 6. Concepts clés | workspace, stage, projet, activité, journal, preuve, compétence | T-023 → T-027 |
| 7. Guide stagiaire | stage, journal, Kanban, activités, preuves | T-023 → T-042 |
| 8. Guide maître de stage | invitation, consultation, commentaires, revues | T-007, T-050 → T-054 |
| 9. Guide tuteur | suivi et évaluation des compétences | T-060, T-061 |
| 10. Guide administrateur | membres, organisations, référentiels, audit | T-006 → T-009, T-021, T-062 |
| 11. Connecteur GitHub | jeton, association, import, idempotence | T-043 → T-045 |
| 12. Rapports et exports | modèles, génération, exclusions | T-062 → T-064 |
| 13. Sauvegarde et restauration | procédure vérifiée | T-065 |
| 14. Sécurité et confidentialité | visibilité, notes privées, secrets | T-041, T-052, T-070 |
| 15. Dépannage (FAQ) | erreurs réelles rencontrées et solutions | toutes (au fil de l'eau) |
| 16. Développement et contribution | tests, conventions, AIDF, workflow Git | T-003, T-066 |
| 17. Limites connues | NOT_PROVEN, dette, périmètre V1 | T-071 |

## 3. Règles de rédaction

- Public : stagiaire ou maître de stage non développeur ; ton direct, phrases courtes.
- Toujours indiquer le **rôle** qui peut effectuer l'action.
- Toujours décrire le **résultat attendu** et l'**erreur possible**.
- Les commandes sont copiables telles quelles, testées sur l'environnement réel.
- Les sections non encore disponibles portent : `> À venir — livré par T-xxx`.
- Aucune promesse de fonctionnalité future présentée comme disponible.

## 4. Critère de complétude avant release (T-071)

1. Un camarade peut installer l'outil en suivant uniquement le README.
2. Chaque rôle dispose d'un parcours complet documenté.
3. Aucune section ne décrit une fonctionnalité absente ou non testée.
4. Les procédures de sauvegarde/restauration et de sécurité sont présentes.
5. Les limites connues sont explicites.
