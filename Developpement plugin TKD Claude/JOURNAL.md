# Journal général — Développement plugins TKD Claude

Ce fichier est le journal de **contexte et d'environnement** du projet, à la différence des fichiers `REALISATION.md`/`EVOLUTION.md` de chaque plugin (qui parlent du code). Il répond à la question « où en est-on, sur quelle machine, avec quel Git, et que faut-il faire ensuite ? ». À tenir à jour à chaque changement de machine ou d'environnement.

## 1. Le projet en une phrase

Trois plugins WordPress développés pour le club **TKD Claira** (taekwondo), avec l'aide de Claude Code, hébergés sur OVH (un site de test + un site de production) :

| Plugin | Rôle | Suivi documentaire |
|---|---|---|
| [sp_build](sp_build/) | Gestion complète du club : adhésions, pointage/QR, planning, jury/examens, compétitions, PWA membres, API publique | `CLAUDE.md` + Git local (36 commits) + [REALISATION.md](sp_build/REALISATION.md)/[EVOLUTION.md](sp_build/EVOLUTION.md) créés le 11/09/2026 |
| [sp-compta](sp-compta/) | Trésorerie associative (dépenses, recettes, devis, factures, sponsors) | `CLAUDE.md` très détaillé + `correctif.md` + [REALISATION.md](sp-compta/REALISATION.md)/[EVOLUTION.md](sp-compta/EVOLUTION.md) créés le 11/09/2026 |
| [tkd-cotisations](tkd-cotisations/) | Cotisations, paiements et reçus PDF | Aucun suivi historique retrouvé — [REALISATION.md](tkd-cotisations/REALISATION.md)/[EVOLUTION.md](tkd-cotisations/EVOLUTION.md) créés le 11/09/2026, à alimenter à partir de maintenant |

## 2. Environnement technique

- **Aucun environnement WordPress/PHP local** : d'après les `CLAUDE.md` de `sp_build` et `sp-compta`, il n'y a ni binaire PHP ni WordPress installé dans l'environnement de développement (sandbox Claude Code). Tout se vérifie par relecture attentive du code, puis déploiement réel sur le **site de test OVH**.
- **Déploiement = copie de fichiers (FTP), pas de build** : les trois plugins fonctionnent « sans outillage » — pas de `npm install`, pas de compilation. `sp-compta` a un `composer.json`, mais uniquement pour les tests PHPUnit en développement ; `vendor/` n'est jamais copié sur le site.
- **Deux environnements OVH** : un site de test (utilisé pour valider avant tout déploiement en production) et le site de production du club — confirmé dans `sp_build/CLAUDE.md`.
- **Machine actuelle** (celle où ce journal est écrit, 11/09/2026) : Windows 11, dossier `Developpement plugin TKD Claude`. Aucune configuration Git globale n'existe sur cette machine (`git config --global user.name/user.email` vides) et l'outil `gh` (GitHub CLI) n'est pas installé.

## 3. État Git au 11/09/2026 — le cœur du problème

Voici exactement ce qui a été constaté en explorant ce dossier aujourd'hui :

- **`sp_build/`** a **son propre dépôt Git local**, avec un historique réel et propre : 36 commits, du 01/09/2026 au 10/09/2026, branche `master`, arbre de travail propre (rien en attente). **Mais aucun `remote` n'est configuré** — c'est-à-dire qu'il n'a jamais existé de destination (GitHub, GitLab...) vers laquelle pousser ce dépôt. Ce n'est donc pas un push qui a échoué techniquement : c'est qu'il n'y a jamais eu de dépôt distant créé.
- **`sp-compta/`** et **`tkd-cotisations/`** : **aucun dépôt Git**, ce sont de simples dossiers de fichiers.
- **Le dossier racine** (`Developpement plugin TKD Claude/`) a un dépôt Git tout juste initialisé (aucun commit pour l'instant), qui pour l'instant ne contient rien.
- **Fichiers de documentation manquants** : `sp_build/CLAUDE.md` et `sp-compta/CLAUDE.md` font référence à un dossier `../md/` (juste à côté des plugins, donc à la racine de ce projet) contenant des fichiers importants : `doleances.md`, `01-fonctionnalites.md`, `02-etat-des-lieux.md`, `03-proposition-refonte.md`, `04-journal-modifications.md`, `05-cartographie-css.md`, `06-renouvellement-saison.md`, `07-refonte-init.md`, et `spec-fonctionnelle-module-compta.md`. **Ce dossier `md/` n'existe pas ici.** Il a très probablement été créé et utilisé sur l'autre ordinateur, et n'a jamais été copié/transféré sur celui-ci.

**Conclusion** : le point bloquant n'est pas un push qui a raté, c'est qu'**aucun dépôt distant (GitHub ou autre) n'a jamais été créé pour ce projet**, et qu'**un dossier de documentation (`md/`) existe peut-être uniquement sur l'autre ordinateur**. Tant que ce dossier `md/` n'est pas retrouvé et copié ici, il est possible qu'il y ait des demandes en attente (`doleances.md`) ou des décisions d'architecture (`03-proposition-refonte.md`) invisibles depuis cette machine.

## 4. Plan d'action pour lundi (retour au bureau)

### Étape 0 — avant de toucher à Git : sécuriser les fichiers manquants

Sur l'**autre ordinateur** (celui où le projet a démarré), avant toute manipulation Git, faire une copie de sécurité complète du dossier `Developpement plugin TKD Claude` (ou au moins du dossier `md/` s'il n'existe qu'à côté) sur une clé USB, un espace cloud (OneDrive/Google Drive) ou par email. **Ne rien supprimer sur l'autre ordinateur tant que tout n'est pas confirmé retrouvé ici.**

### Étape 1 — créer un compte GitHub (si pas déjà fait)

GitHub est gratuit, y compris pour des dépôts privés. Créer un compte sur [github.com](https://github.com) si ce n'est pas déjà fait. *(Si un compte existe déjà, passer à l'étape 2.)*

Pour un débutant complet en Git, une alternative plus visuelle à la ligne de commande : **[GitHub Desktop](https://desktop.github.com/)** — une application avec des boutons (« Publish », « Commit », « Push ») plutôt que des commandes à taper. Tout ce qui suit peut aussi se faire avec cette application ; les commandes ci-dessous sont l'équivalent « ligne de commande ».

### Étape 2 — configurer l'identité Git (une seule fois, sur chaque ordinateur)

```bash
git config --global user.name "Votre Nom"
git config --global user.email "votre.email@example.com"
```

### Étape 3 — le piège à éviter : ne pas imbriquer les dépôts Git

`sp_build` a déjà son propre `.git`. Si l'on fait un `git add` depuis le dossier racine sans faire attention, Git essaiera de traiter `sp_build` comme un sous-module cassé au lieu de suivre ses fichiers normalement — source de confusion classique pour un débutant. **La solution la plus simple : un dépôt GitHub séparé par plugin**, plutôt qu'un seul grand dépôt pour tout.

Structure recommandée : **3 dépôts GitHub indépendants**, un par plugin (`sp_build`, `sp-compta`, `tkd-cotisations`). Le dossier racine et ce `JOURNAL.md` peuvent rester simplement en local (pas besoin de Git pour un seul fichier de notes), ou être ajoutés à l'un des trois dépôts si préféré.

### Étape 4 — pousser `sp_build` (le plus simple : il a déjà son historique)

1. Sur github.com, créer un nouveau dépôt vide nommé par exemple `sp_build` (**ne pas** cocher « Add a README », le dépôt local en a déjà un historique).
2. Dans un terminal, se placer dans le dossier `sp_build` puis :

```bash
git remote add origin https://github.com/VOTRE-COMPTE/sp_build.git
```

```bash
git branch -M main
```

```bash
git push -u origin main
```

Les 36 commits et tout l'historique partiront d'un coup — rien à recréer.

### Étape 5 — créer un dépôt et pousser `sp-compta`

1. Sur github.com, créer un nouveau dépôt vide `sp-compta`.
2. Dans le dossier `sp-compta` :

```bash
git init
```

```bash
git add .
```

```bash
git commit -m "Premier commit sp-compta"
```

```bash
git branch -M main
```

```bash
git remote add origin https://github.com/VOTRE-COMPTE/sp-compta.git
```

```bash
git push -u origin main
```

### Étape 6 — même chose pour `tkd-cotisations`

Répéter exactement les commandes de l'étape 5 en remplaçant `sp-compta` par `tkd-cotisations` partout (nom du dépôt GitHub et dossier local).

### Étape 7 — retrouver le dossier `md/` manquant

Une fois le dossier `md/` de l'autre ordinateur récupéré (étape 0), le copier à la racine de `Developpement plugin TKD Claude/` (à côté de `sp_build`, `sp-compta`, `tkd-cotisations`). Vérifier ensuite s'il contient des demandes non traitées (`doleances.md`) à reporter dans les fichiers `EVOLUTION.md` correspondants.

### Ensuite, pour chaque nouvelle session de travail

```bash
git add .
```

```bash
git commit -m "Description courte de ce qui a changé"
```

```bash
git push
```

## 5. Repères pour la suite

- Mettre à jour ce journal à chaque fois que l'environnement change (nouvelle machine, nouveau compte GitHub, dossier retrouvé ou déplacé).
- Mettre à jour `REALISATION.md` de chaque plugin à la fin de chaque session (une ligne datée suffit) — ne pas attendre d'avoir tout oublié.
- Mettre à jour `EVOLUTION.md` dès qu'une idée ou une demande apparaît, même non urgente.

---

*Créé le 11/09/2026.*
