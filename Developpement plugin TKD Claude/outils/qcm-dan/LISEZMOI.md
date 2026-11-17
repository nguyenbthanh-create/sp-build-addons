# Mise à jour des questions du module « QCM Dan »

Le fichier `claira-tkd-parcours/data/qcm-dan.js` est extrait des 32 quiz « Module B » publiés par la FFTDA
(fftda.fr > Réglementation > Module B). Dans ces PDF, la bonne réponse n'est signalée que par du **texte vert** :
l'extraction lit la couleur du texte, pas seulement son contenu.

Pour régénérer le fichier quand la FFTDA met ses quiz à jour :

1. Télécharger les PDF dans un dossier (ex. `Cahier révision\Quiz module B`).
2. Dans ce dossier `outils/qcm-dan` : `composer install` (une fois).
3. `php extraire-quiz.php "<dossier des PDF>" quiz-brut.json` : affiche le nombre de questions par quiz et
   signale (`pb:`) toute question sans bonne réponse unique ou avec une cellule vide.
4. `php construire-json.php quiz-brut.json ../../claira-tkd-parcours/data/qcm-dan.js`
   (un nouveau quiz doit d'abord être ajouté à la liste `THEMES` : libellé court et nom du PDF sur fftda.fr).

Au 17/11/2026 : 32 quiz, 724 questions, toutes avec une seule bonne réponse.
