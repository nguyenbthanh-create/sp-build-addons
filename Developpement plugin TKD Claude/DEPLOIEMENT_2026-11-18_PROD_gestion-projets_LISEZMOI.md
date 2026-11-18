# Mise en prod de la Gestion de projets

Préparé le 18/11/2026 à partir du commit `805fa30`. **Rien n'est encore envoyé en prod.**

## Ce qui part en prod

| Extension | Avant | Après | Dossier |
|---|---|---|---|
| SP Compta | 0.2.0 (vérifiée identique à git le 18/11) | **0.4.0** : 5 fichiers remplacés, 3 ajoutés | `DEPLOIEMENT_2026-11-18_PROD_sp-compta-0.4.0_805fa30` |
| TKD Projets | absente | **2.2.0** : nouvelle extension, 37 fichiers | `DEPLOIEMENT_2026-11-18_PROD_tkd-projets-2.2.0_805fa30` |
| TKD Gala | — | **inchangée** | — |

Les fichiers sont exactement ceux du site de test : les tailles ont été contrôlées le 18/11.

**Effets en prod** :
- SP Compta crée une table vide (`…sp_compta_budget_ligne`, schéma 1.5.0) au premier affichage d'une page de la trésorerie. Rien d'autre ne change pour la trésorière, tant qu'aucun budget n'est saisi dans TKD Projets.
- TKD Projets n'est visible que des administrateurs, tant que personne n'est coché comme gestionnaire ou ajouté à une équipe.
- **Attention : en prod, les e-mails partent vraiment**, contrairement au site de test. Ils partent à l'attribution d'une tâche et à l'envoi d'un lien de carte. Pour un essai, décocher « Prévenir par e-mail ».

## Marche à suivre (depuis `outils`, PC maison)

1. **SP Compta** :
   ```
   .\deployer.ps1 -Cible prod -Prod -Extension sp-compta -Dossier ..\DEPLOIEMENT_2026-11-18_PROD_sp-compta-0.4.0_805fa30 -Session 'tkdclad@ftp.cluster121.hosting.ovh.net'
   ```
   La sauvegarde automatique des 5 fichiers remplacés va dans `…\sauvegarde-prod-<date>`.
2. **Ouvrir *Trésorerie → Projets* en prod**, ce qui crée la table. Puis vérifier, sans erreur PHP : Dépenses, Recettes, Projets (fiche d'un projet), Solde, Rapport.
3. **TKD Projets** :
   ```
   .\deployer.ps1 -Cible prod -Prod -Extension tkd-projets -Dossier ..\DEPLOIEMENT_2026-11-18_PROD_tkd-projets-2.2.0_805fa30 -Session 'tkdclad@ftp.cluster121.hosting.ovh.net'
   ```
4. **Activer « TKD Projets »** dans *Extensions*. Cela crée les 5 tables `…tkdp_*` et donne le droit « gestionnaire » aux administrateurs.
5. **Réglages**, par l'utilisateur :
   - *Projets → Réglages* : cocher les gestionnaires et choisir le président ;
   - *Trésorerie → Paramètres → Accès bureau* : cocher le président.
6. **Contrôles en ligne** :
   - page Projets sans erreur ;
   - créer un projet « Gala » **sans e-mail** : l'onglet Gala montre l'édition 2027 et ses vrais totaux ;
   - l'onglet Budget apparaît pour le bureau.

   Supprimer ensuite le projet d'essai. Son projet dans la trésorerie, s'il a été créé, se supprime dans *Trésorerie → Projets*.
7. **Refaire `-Verifier`** pour les deux extensions quelques minutes plus tard, puis compléter `JOURNAL.md`.

## Retour arrière

- **TKD Projets** : la **désactiver** dans *Extensions*. Les tables restent, sans effet. Ne pas cliquer « Supprimer », qui efface ses tables.
- **SP Compta** : remettre les 5 fichiers 0.2.0.
  ```
  .\deployer.ps1 -Cible prod -Prod -Extension sp-compta -Restaurer <dossier sauvegarde-prod-…>
  ```
  - Les 3 nouveaux fichiers (`BudgetLigne`, `BudgetLigneRepository`, `ProjetsApi`) peuvent rester : la 0.2.0 ne les charge jamais.
  - La table `…sp_compta_budget_ligne` reste, vide ou non, sans effet.
  - Au premier wp-admin, la 0.2.0 rejoue une fois sa propre vérification du schéma : c'est sans risque, elle n'ajoute que ce qui manque et ne supprime rien.
