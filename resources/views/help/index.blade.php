@extends('layouts.app')

@section('title', 'Guide d\'utilisation & Préparation DWWM')

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => 'Accueil', 'url' => route('workspaces.index')],
        ['label' => 'Guide d\'utilisation DWWM'],
    ]"/>
@endsection

@section('content')
<div class="help-guide">
    <div class="card card-hero" style="background: linear-gradient(135deg, var(--parme-soft) 0%, #e8e0f4 100%); border: 1px solid var(--border-subtle); padding: 2.2rem; border-radius: 14px; margin-bottom: 2rem;">
        <span class="badge" style="background: var(--parme-primary); color: #fff; font-size: 0.85rem; padding: 0.3rem 0.8rem; margin-bottom: 0.75rem;">
            🎓 Titre Professionnel Développeur Web et Web Mobile (DWWM)
        </span>
        <h1 style="font-size: 2rem; margin: 0.5rem 0 0.75rem 0; font-weight: 800; letter-spacing: -0.02em;">
            Mode d'emploi & Guide de Réussite au Titre DWWM
        </h1>
        <p style="color: var(--text-secondary); font-size: 1.05rem; line-height: 1.6; max-width: 820px; margin: 0;">
            Ce guide a été spécialement conçu pour vous et vos collègues en formation DWWM. Il vous explique pas à pas comment utiliser <strong>Stage Tracker</strong> pour consigner vos réalisations quotidiennes, prouver l'acquisition de vos compétences, inviter votre tuteur, et générer en un clic tous les éléments nécessaires à votre <strong>Dossier Professionnel (DP)</strong> et votre <strong>Soutenance Orale</strong> devant le jury.
        </p>
    </div>

    <!-- Les 4 étapes clés -->
    <h2 style="font-size: 1.4rem; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
        <span>🚀</span> Les 4 étapes clés de votre suivi
    </h2>

    <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem; margin-bottom: 2rem;">
        <div class="card" style="border-top: 4px solid var(--parme-primary);">
            <div style="font-size: 1.8rem; margin-bottom: 0.5rem;">1️⃣</div>
            <h3 style="margin-top: 0; font-size: 1.15rem;">Invitez votre tuteur</h3>
            <p style="color: var(--text-secondary); font-size: 0.95rem; line-height: 1.5;">
                Depuis le tableau de bord de votre espace, générez un <strong>lien magique direct</strong> pour votre maître de stage. En s'inscrivant via ce lien, il est automatiquement rattaché à votre stage sans configuration supplémentaire.
            </p>
        </div>

        <div class="card" style="border-top: 4px solid #7c8dba;">
            <div style="font-size: 1.8rem; margin-bottom: 0.5rem;">2️⃣</div>
            <h3 style="margin-top: 0; font-size: 1.15rem;">Remplissez votre journal</h3>
            <p style="color: var(--text-secondary); font-size: 0.95rem; line-height: 1.5;">
                Chaque fin de journée (ou le vendredi), saisissez vos heures et le résumé de vos activités. Précisez les technologies employées (Laravel, Vue, React, Tailwind, PostgreSQL) et les obstacles franchis.
            </p>
        </div>

        <div class="card" style="border-top: 4px solid #6b9e8a;">
            <div style="font-size: 1.8rem; margin-bottom: 0.5rem;">3️⃣</div>
            <h3 style="margin-top: 0; font-size: 1.15rem;">Associez des preuves</h3>
            <p style="color: var(--text-secondary); font-size: 0.95rem; line-height: 1.5;">
                Ne dites pas seulement ce que vous avez fait : prouvez-le ! Déposez des captures d'écran, diagrammes UML/MCD, liens de commits GitHub ou maquettes Figma, puis associez-les aux <strong>6 compétences DWWM</strong>.
            </p>
        </div>

        <div class="card" style="border-top: 4px solid #9b7ec4;">
            <div style="font-size: 1.8rem; margin-bottom: 0.5rem;">4️⃣</div>
            <h3 style="margin-top: 0; font-size: 1.15rem;">Générez votre rapport</h3>
            <p style="color: var(--text-secondary); font-size: 0.95rem; line-height: 1.5;">
                À la fin du stage, cliquez sur <em>Générer le rapport de stage</em>. L'outil assemble automatiquement la synthèse d'heures, le tableau de couverture des compétences et le catalogue de preuves pour alimenter votre DP !
            </p>
        </div>
    </div>

    <!-- Référentiel DWWM Officiel -->
    <div class="card" style="margin-bottom: 2rem;">
        <h2 style="font-size: 1.35rem; margin-top: 0;">
            📋 Correspondance officielle : Titre DWWM 2023 & Dossier Professionnel
        </h2>
        <p style="color: var(--text-secondary); margin-bottom: 1.25rem;">
            Le Titre Professionnel Développeur Web et Web Mobile est articulé autour de 2 Certificats de Compétences Professionnelles (CCP) et de 6 compétences clés qui doivent être prouvées dans votre Dossier Professionnel (DP) et démontrées lors de la soutenance orale :
        </p>

        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 2px solid var(--border-subtle); text-align: left;">
                        <th style="padding: 0.75rem; color: var(--parme-primary);">Bloc / CCP</th>
                        <th style="padding: 0.75rem;">Code & Intitulé Officiel</th>
                        <th style="padding: 0.75rem;">Livrables & Preuves attendus</th>
                        <th style="padding: 0.75rem;">Section correspondante du DP</th>
                    </tr>
                </thead>
                <tbody>
                    <tr style="border-bottom: 1px solid var(--border-subtle); background: var(--parme-soft);">
                        <td rowspan="3" style="padding: 0.75rem; font-weight: 700; color: var(--parme-primary); vertical-align: top;">
                            CCP 1<br><span style="font-size: 0.8rem; font-weight: 400; color: var(--text-muted);">Développer la présence en ligne d'une organisation (Front-end)</span>
                        </td>
                        <td style="padding: 0.75rem;"><strong>C1</strong> — Maquetter des interfaces utilisateur web ou mobiles</td>
                        <td style="padding: 0.75rem; color: var(--text-secondary); font-size: 0.9rem;">Zoning, wireframes, prototypes interactifs Figma, charte graphique, responsive design mobile/desktop.</td>
                        <td style="padding: 0.75rem; color: var(--text-muted); font-size: 0.9rem;">Pratique professionnelle N°1 (Partie Front-end)</td>
                    </tr>
                    <tr style="border-bottom: 1px solid var(--border-subtle); background: var(--parme-soft);">
                        <td style="padding: 0.75rem;"><strong>C2</strong> — Développer des interfaces statiques et adaptables</td>
                        <td style="padding: 0.75rem; color: var(--text-secondary); font-size: 0.9rem;">HTML5 sémantique, CSS3/Tailwind, conformité RGAA/accessibilité, balisage SEO, respect maquette.</td>
                        <td style="padding: 0.75rem; color: var(--text-muted); font-size: 0.9rem;">Pratique professionnelle N°1 & N°2</td>
                    </tr>
                    <tr style="border-bottom: 2px solid var(--border-subtle); background: var(--parme-soft);">
                        <td style="padding: 0.75rem;"><strong>C3</strong> — Développer des interfaces dynamiques et réactives</td>
                        <td style="padding: 0.75rem; color: var(--text-secondary); font-size: 0.9rem;">JavaScript moderne (ES6+), validation formulaire côté client, requêtes asynchrones Fetch/Axios, gestion d'état, composants UI.</td>
                        <td style="padding: 0.75rem; color: var(--text-muted); font-size: 0.9rem;">Pratique professionnelle N°1 (Interactions)</td>
                    </tr>

                    <tr style="border-bottom: 1px solid var(--border-subtle); background: rgba(124, 141, 186, 0.06);">
                        <td rowspan="3" style="padding: 0.75rem; font-weight: 700; color: #7c8dba; vertical-align: top;">
                            CCP 2<br><span style="font-size: 0.8rem; font-weight: 400; color: var(--text-muted);">Développer une application web complète (Back-end)</span>
                        </td>
                        <td style="padding: 0.75rem;"><strong>C4</strong> — Concevoir une base de données relationnelle</td>
                        <td style="padding: 0.75rem; color: var(--text-secondary); font-size: 0.9rem;">Modèle Conceptuel (MCD/MLD Merise ou diagramme E-R), dictionnaire de données, script SQL de création, intégrité référentielle, index.</td>
                        <td style="padding: 0.75rem; color: var(--text-muted); font-size: 0.9rem;">Pratique professionnelle N°2 (Base de données)</td>
                    </tr>
                    <tr style="border-bottom: 1px solid var(--border-subtle); background: rgba(124, 141, 186, 0.06);">
                        <td style="padding: 0.75rem;"><strong>C5</strong> — Développer des composants d'accès aux données SQL / NoSQL</td>
                        <td style="padding: 0.75rem; color: var(--text-secondary); font-size: 0.9rem;">Requêtes SQL préparées (PDO), ORM (Eloquent), migrations, seeders, transactions, sécurisation contre injections SQL.</td>
                        <td style="padding: 0.75rem; color: var(--text-muted); font-size: 0.9rem;">Pratique professionnelle N°2 (Persistance)</td>
                    </tr>
                    <tr style="background: rgba(124, 141, 186, 0.06);">
                        <td style="padding: 0.75rem;"><strong>C6</strong> — Développer la partie métier côté serveur d'une application web</td>
                        <td style="padding: 0.75rem; color: var(--text-secondary); font-size: 0.9rem;">Architecture MVC/API RESTful, authentification & autorisations RBAC, validation des entrées, gestion des erreurs, tests unitaires et d'intégration.</td>
                        <td style="padding: 0.75rem; color: var(--text-muted); font-size: 0.9rem;">Pratique professionnelle N°2 (Services métier)</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Conseils pour le Dossier Professionnel et l'Oral -->
    <div class="grid" style="grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
        <div class="card">
            <h2 style="font-size: 1.25rem; margin-top: 0; display: flex; align-items: center; gap: 0.5rem;">
                <span>📝</span> Rédaction du Dossier Professionnel (DP)
            </h2>
            <ul style="color: var(--text-secondary); font-size: 0.95rem; line-height: 1.6; padding-left: 1.25rem; margin: 0;">
                <li><strong>Complétez 2 exemples concrets de pratique :</strong> Un projet orienté Front-end (ex: maquettage et intégration d'une application) et un projet orienté Back-end (ex: architecture MVC, API et base de données relationnelle).</li>
                <li><strong>Structurez chaque fiche :</strong> Contexte d'intervention, moyens utilisés, démarche méthodologique, résultats obtenus et auto-évaluation.</li>
                <li><strong>Intégrez les synthèses de Stage Tracker :</strong> Copiez-collez les heures réelles, la liste des technos utilisées et la matrice de couverture des compétences calculée par l'application.</li>
                <li><strong>Annexes :</strong> Joignez les captures d'écran et extraits de code significatifs exportés depuis vos preuves de stage.</li>
            </ul>
        </div>

        <div class="card">
            <h2 style="font-size: 1.25rem; margin-top: 0; display: flex; align-items: center; gap: 0.5rem;">
                <span>🎙️</span> Préparation de la Soutenance Orale
            </h2>
            <ul style="color: var(--text-secondary); font-size: 0.95rem; line-height: 1.6; padding-left: 1.25rem; margin: 0;">
                <li><strong>Durée totale (1h35) :</strong> Présentation du projet (35 min), entretien technique jury (40 min), entretien final de synthèse (20 min).</li>
                <li><strong>Démonstration live fluide :</strong> Montrez votre application en fonctionnement réel (front + back + base de données). Prévoyez toujours une vidéo de secours.</li>
                <li><strong>Expliquez vos choix techniques :</strong> Pourquoi cette modélisation de base de données ? Comment assurez-vous la sécurité (XSS, CSRF, injections SQL) ? Quelle est votre politique de tests ?</li>
                <li><strong>Posture professionnelle :</strong> Utilisez le vocabulaire technique adéquat (HTTP, status codes, ORM, REST, composants, responsive, RGAA).</li>
            </ul>
        </div>
    </div>

    <!-- Foire Aux Questions (FAQ) -->
    <div class="card">
        <h2 style="font-size: 1.25rem; margin-top: 0; display: flex; align-items: center; gap: 0.5rem;">
            <span>💡</span> Foire aux questions (FAQ)
        </h2>
        <div style="display: flex; flex-direction: column; gap: 1rem; margin-top: 1rem;">
            <div>
                <strong style="color: var(--parme-primary);">Mon tuteur n'a pas reçu d'e-mail, comment faire ?</strong>
                <p style="color: var(--text-muted); margin: 0.25rem 0 0 0; font-size: 0.95rem;">
                    Aucun problème ! Stage Tracker génère automatiquement un <em>lien magique direct</em> affiché à l'écran dès la création de l'invitation. Vous pouvez copier ce lien en un clic et le lui transmettre directement par WhatsApp, Slack, Teams ou e-mail personnel.
                </p>
            </div>
            <div>
                <strong style="color: var(--parme-primary);">Est-ce que mes camarades peuvent voir mes journées ou mes preuves ?</strong>
                <p style="color: var(--text-muted); margin: 0.25rem 0 0 0; font-size: 0.95rem;">
                    Non. L'application repose sur une isolation multi-tenant stricte : chaque compte dispose de son propre espace étanche. Seuls vous-même et votre maître de stage / tuteur invité avez accès à vos journées, preuves et rapports.
                </p>
            </div>
            <div>
                <strong style="color: var(--parme-primary);">Puis-je exporter mon rapport de stage en PDF ?</strong>
                <p style="color: var(--text-muted); margin: 0.25rem 0 0 0; font-size: 0.95rem;">
                    Oui ! Rendez-vous dans <em>Rapports</em>, puis cliquez sur <em>Générer un rapport</em>. Vous obtiendrez une version HTML imprimable, un document prêt pour le DP et les synthèses complètes de votre parcours.
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
