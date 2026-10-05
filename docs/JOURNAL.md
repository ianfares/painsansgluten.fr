# JOURNAL.md — Historique des sessions IA

> Ce fichier est le point d'entrée unique pour reprendre le contexte d'une session à l'autre. Plusieurs IA (plusieurs comptes Claude Code + ChatGPT) travaillent **en séquentiel** sur ce projet.
>
> **Règle (voir CLAUDE.md §7)** : toute IA qui intervient lit ce fichier en entier au démarrage, et y ajoute une entrée à la fin de sa tâche — sans jamais modifier ou supprimer les entrées précédentes. C'est un complément au compte rendu donné à l'humain et au commit Git, pas un remplacement.

## Format d'une entrée

**Le corps de l'entrée est le compte rendu complet au format `QUALITE.md` §10** (RÉSUMÉ, FICHIERS CRÉÉS/MODIFIÉS, ANALYSE D'IMPACT, SÉCURITÉ, PERFORMANCE, TESTS, QUALITÉ, DOCUMENTATION, À RENSEIGNER/QUESTIONS OUVERTES, POINTS À RELIRE PAR UN HUMAIN, AJOUTÉ AU BACKLOG), précédé de cet en-tête et suivi d'une dernière ligne :
```
## [AAAA-MM-JJ HH:MM] — [IA] — TXX — [titre]
STATUT : terminée / bloquée / partielle
(... compte rendu QUALITE.md §10 ...)
PROCHAINE TÂCHE SUGGÉRÉE : ...
```
Ne jamais utiliser un format allégé — voir `QUALITE.md` §10 pour le détail de chaque champ.

---

## Entrées

## [2026-10-05 22:00] — Claude Sonnet 5 — (cadrage) — Analyse des documents reçus, maquettes, mise en place du dépôt
STATUT : terminée
FAIT :
- Dépôt Git local initialisé (branche `main`).
- Analyse complète de `PLAN.md` (29 sections) et des 26 fiches de tâches `tasks/T00-T25.md` (synthèses déléguées à 2 agents de lecture).
- 3 maquettes interactives (front-office, espace client, back-office) publiées en artifact Design : https://claude.ai/artifact/Q9T5jXU1e8GT87n5euZ2Py — inspirées du vrai site https://painsansgluten.fr (couleurs, typographie) et d'un comparatif rapide de https://www.le-petit-minotier.fr.
- Mise en place du système d'historique multi-IA : `docs/JOURNAL.md` (ce fichier) + ajout de la section 7 dans `CLAUDE.md`.
- Copie de `CLAUDE.md`, `PLAN.md`, `CHECKLIST-PROD.md`, `tasks/` à la racine du dépôt (le dossier `documents/` original est conservé tel quel comme archive brute reçue du client).
FICHIERS : CLAUDE.md (racine), PLAN.md (racine), CHECKLIST-PROD.md (racine), tasks/ (racine), docs/BACKLOG.md, docs/JOURNAL.md
TESTS : sans objet (pas de code applicatif à ce stade)
À RENSEIGNER / QUESTIONS OUVERTES :
- Les 17 paramètres critiques listés dans PLAN.md §27 (TVA, textes légaux, identifiants Chronopost/Stripe/Brevo, coordonnées, assets HD, etc.) restent à fournir par la cliente / Ian / le comptable. Voir PLAN.md §27 pour le détail.
RISQUES / À RELIRE PAR UN HUMAIN :
- Planning : 1 semaine est optimiste pour une V1 "code complet en préprod" ; cible réaliste estimée à ~10 jours ouvrés avec 2 Claude en parallèle. Décision client : on vise quand même la fin de semaine, en acceptant de pousser si besoin.
AJOUTÉ AU BACKLOG :
- QR code de traçabilité produit (page publique par token, allergènes/composition/date de fabrication) — voir docs/BACKLOG.md.
PROCHAINE TÂCHE SUGGÉRÉE : T00 — Initialisation du projet (aucune dépendance aux 17 éléments "À RENSEIGNER" ; environnement local vérifié OK : PHP 8.3.6, Composer, Node, MariaDB 10.11 actifs).
