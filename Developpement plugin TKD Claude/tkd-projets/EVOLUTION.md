# Évolution — TKD Projets

Pistes connues, datées. Plan complet : `RefontePlugin\tkd-projets-ANALYSE.md` (décisions de l'utilisateur, 18/11/2026).

## Plan de la « Gestion de projets » (18/11/2026)

1. ~~Correctif urgent de la 1.6.0~~ : inutile, la 1.6.0 n'était que sur le site de test, avec des données factices.
2. **2.0.0 : réécriture**. Droits par projet, équipes, types Gala / Fêtes / Compétition avec tâches modèles, correctifs de sécurité. Faite le 18/11/2026.
3. ~~**SP Compta**~~ : fait le 18/11/2026 (SP Compta 0.3.0, `dcecae8`). La porte d'entrée est `SpCompta\Integration\ProjetsApi`, documentée dans son `.md`. Ce qui était prévu :
   - une « porte d'entrée » documentée pour TKD Projets (créer ou mettre à jour le projet compta, lire le bilan et les mouvements) ;
   - des **lignes de budget** par catégorie (nouvelle version de la base 1.5.0, ajout seul).

   La colonne `compta_projet_id` existe déjà dans `…tkdp_projects`.
4. ~~**Onglet Budget**~~ : fait le 18/11/2026 (TKD Projets 2.1.0, SP Compta 0.4.0). Ce qui était prévu : visible du **bureau seulement** (`sp_compta_manager`) : prévu, réalisé et écart par catégorie, calculés par `ProjetBilan` de SP Compta.
   - **Seul le président** propose et valide une dépense de projet. Une fois validée, elle entre **directement** dans SP Compta, rattachée au projet. Il doit donc figurer dans « Accès bureau ».
   - Nature SP Compta d'après le type : `Types::nature()`.
5. **Branchement de TKD Gala** :
   - un projet « Gala » relié à l'édition, avec les chiffres de Gala (clubs, démonstrateurs, repas) ;
   - les recettes de repas prévues ;
   - Gala lui-même change à peine.
6. **Après le gala 2027 et la purge de ses données** : décider si le code de Gala entre dans TKD Projets comme module.

## Autres pistes

- **Leaflet copié dans l'extension**, au lieu de `unpkg.com`. Il faut récupérer les fichiers de Leaflet 1.9.4 (accord de l'utilisateur pour le téléchargement).
- **Autres types**, plus tard : Stage, Voyage. Ils s'ajoutent dans `Types::TYPES`.
- **Carte en lecture seule pour les membres** : aujourd'hui, seuls les responsables ouvrent la carte, et les membres reçoivent leur propre lien.
- **`assets/app.js` à découper** en modules : 1 300 lignes environ.
- **Alerte budget** : un mail au responsable si le réalisé dépasse le prévu (après l'étape 4).

## Comment tenir ce fichier à jour

Ajouter une entrée datée dès qu'une idée apparaît, et barrer ce qui est fait en renvoyant à `JOURNAL.md`.
