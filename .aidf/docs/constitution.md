---
doc: constitution
status: VALIDATED
version: 1.0.0
source: stage-tracker-specification
updated: 2026-11-04
validated_by: Jeremy Ferrier
---

# Constitution du projet — Stage Tracker

> Ratifiée par : Jeremy Ferrier (propriétaire)
> Date de ratification : 2026-11-04 (approbation explicite du plan SPECIFICATION)
> Procédure d'amendement : toute modification d'article fait l'objet d'une décision
> enregistrée dans `.aidf/docs/DECISIONS.md`, confirmée par le propriétaire
> (`aidf evidence add --human --by "Jeremy Ferrier"`).

## Préambule

Stage Tracker est une application web multiutilisateur de suivi de stages, de
traçabilité professionnelle et de valorisation des compétences. Elle est
développée sous gouvernance AIDF 0.1.0, selon un mode *evidence-driven* :
aucune affirmation sur le comportement, la qualité, la sécurité ou la
complétude du produit ne vaut sans artefact vérifiable.

## Article I — La preuve prime sur l'affirmation

Toute affirmation doit être adossée à une preuve : commande et sortie, résultat
de test, diff de fichier, enregistrement de revue ou décision humaine. Une
déclaration d'agent n'est pas une preuve.

Statuts autorisés, exclusivement : **PASS**, **BLOCKED**, **FAIL**,
**NOT_PROVEN**. Une fonctionnalité présente dans le code n'est ni testée ni
prouvée par sa seule existence.

## Article II — Aucune simulation déguisée

- Aucun backend fictif, aucun mock présenté comme une intégration réelle.
- Aucun résultat de test inventé, aucune preuve fabriquée.
- Aucune donnée fictive dans les parcours de production.
- Une interface n'est « connectée » que si la persistance réelle est démontrée.

## Article III — Isolation multi-tenant

Toute donnée métier est rattachée à un workspace, directement ou par une
relation vérifiable. L'isolation est garantie côté serveur **et** par des
contraintes de base de données. Une relation entre deux workspaces différents
est interdite et rejetée. Un utilisateur n'accède qu'aux workspaces autorisés.

## Article IV — Référentiels génériques et versionnés

Le moteur de compétences ne code en dur aucun référentiel (pas de condition
`if ($code === 'RNCP...')`). Hiérarchie : Référentiel → Version → Bloc →
Compétence → Critère facultatif. Une version publiée est **immuable** ; une
nouvelle version ne modifie jamais rétroactivement un stage existant.

## Article V — Distinction des niveaux de compétence

Trois notions sont irréductibles : compétence **mobilisée**, **documentée**,
**évaluée**. Une activité terminée n'implique aucune acquisition. Une
suggestion automatique n'est jamais une validation.

## Article VI — Sécurité dès la conception

La sécurité est traitée en SPECIFICATION et vérifiée en phase SECURITY. Aucune
revendication de conformité RGPD ne peut reposer sur la seule présence de
mesures techniques. Les secrets ne sont jamais versionnés. Les jetons GitHub
sont stockés côté serveur, chiffrés, avec permissions minimales en lecture.

## Article VII — Conservation et traçabilité de l'historique

La désactivation d'un compte, la fin d'un stage, le départ d'un participant ou
l'évolution d'un référentiel ne détruisent pas l'historique. Archivage et
suppression suivent une politique documentée. Les commentaires conservent
auteur et date.

## Article VIII — Gouvernance et gates

Le workflow comporte 8 phases et 16 gates. Aucune phase n'est franchie sans
preuve de sortie. Aucune validation humaine n'est supposée : elle est
nominative et enregistrée. Toute contradiction affectant la sécurité, les
données, l'architecture ou le comportement métier bloque la décision jusqu'à
arbitrage humain.

## Article IX — Périmètre produit

Stage Tracker n'est ni un ERP de formation, ni un clone de GitHub Projects, ni
un générateur automatique de mémoire. Sa valeur est la chaîne de traçabilité :
Travail réalisé → Activité → Tâche → Commit → Fichier → Preuve → Compétence →
Évaluation → Synthèse.

## Article X — Données du pilote COAB

Le stage COAB constitue le premier cas pilote. Ses données sont isolées dans un
workspace distinct et ne sont jamais réutilisées dans des jeux de démonstration
ou des exports non autorisés sans consentement explicite.
