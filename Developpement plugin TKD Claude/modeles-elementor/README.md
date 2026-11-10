# Modèles Elementor

Pages Elementor créées par code, à importer dans *wp-admin → Modèles → Modèles enregistrés → Importer des modèles*. Le `.json` est produit par le script `.php` du même nom (`php generer-….php`) : modifier le script puis régénérer, plutôt que d'éditer le JSON à la main.

## `candidature-gala-2027.json` — Candidature au Gala des Arts Martiaux 2027 (10/11/2026)

Formulaire de candidature des structures **hors taekwondo**, d'après le dossier PDF « Dossier_candidature_Gala_Arts_Martiaux_Claira_2027 ». Envoi unique, sans modification ensuite (corrections par mail). Formulaire Elementor Pro en 6 étapes : Conditions → Structure → Contacts → Démonstration → Technique et vidéo → Engagement.

- **Actions après envoi** : enregistrement dans *Elementor → Envois* (export CSV possible), mail à l'organisation (`contact@tkdclaira.fr`, réponse directe au responsable), copie au responsable de la structure.
- **Vidéo** : lien obligatoire (YouTube, Google Drive, Autre), pas d'envoi de fichier (taille, fichiers publics). WeTransfer volontairement absent (liens qui expirent).
- **Signature / « Fait à… le… »** remplacés par une case de certification + nom, prénom, fonction (date d'envoi enregistrée automatiquement).
- **Anti-robots** : champ piège (honeypot).
- **Durée** : 10 à 15 minutes (le PDF dit aussi « 10 à 20 » en page 1 : à trancher, constante `$DUREE_MAX` du script).

### Mise en place

1. Importer le `.json` (Modèles → Importer).
2. *Pages → Ajouter*, titre « Candidature Gala des Arts Martiaux 2027 », **Modifier avec Elementor** → icône dossier → *Mes modèles* → **Insérer** le modèle → Publier.
3. Vérifier dans l'éditeur, widget Formulaire → *Actions après soumission* : adresse de l'organisation (Email) et expéditeur.
4. Faire un envoi d'essai, puis le supprimer dans *Elementor → Envois*.
5. **Après le 15/12/2026** : dépublier la page (Elementor ne ferme pas un formulaire tout seul).

### Noms de réglages

Relevés dans le code d'Elementor Pro 4.3.1 du site (`modules/forms/widgets/form.php`, `actions/email.php`, `email2.php`, `submissions/actions/save-to-database.php`, `fields/step.php`, `acceptance.php`, `number.php`). Une future version d'Elementor peut les renommer : en cas d'import incomplet, vérifier ces fichiers.
