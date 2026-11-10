# TKD Gala

Inscriptions des clubs invités au gala des arts martiaux, **sans compte WordPress**. Extension indépendante de sp_build (aucune table ni classe partagée). Créée le 10/11/2026.

## Mise en route

1. Envoyer le dossier `tkd-gala` dans `wp-content/plugins/` (ou téléverser `tkd-gala.zip` dans *Extensions → Ajouter*), puis l'activer. Les 3 tables `…tkd_gala_*` se créent à l'activation.
2. Créer une page (ex. « Inscription au gala ») contenant le shortcode `[tkd_gala_inscription]`. C'est son adresse qui part dans les mails.
3. Menu **Gala → Réglages** : édition, date du gala, **date limite**, durée maximale, planches maximum, prix du repas, mail de l'organisation, couleurs proposées (à voir avec l'éclairage), puis cocher **Inscriptions ouvertes**.

## Fonctionnement

- **Lien personnel** : à l'envoi, le représentant reçoit un mail récapitulatif avec un lien `?gala=<jeton>` qui rouvre le formulaire prérempli. **Tout** reste modifiable jusqu'à la date limite incluse ; ensuite la page n'affiche qu'un récapitulatif. « Recevoir à nouveau mon lien » renvoie le lien **uniquement à l'adresse enregistrée** (même réponse si l'adresse est inconnue).
- **Fermer les inscriptions** (case décochée) bloque les nouveaux clubs mais laisse les clubs inscrits modifier jusqu'à la date limite.
- Un **administrateur connecté** peut toujours modifier une inscription, même après la date limite (bouton « Modifier avec le formulaire du club » dans le détail).
- **Démonstrateurs** : tableau ligne par ligne, ou « Coller depuis un tableur » (colonnes Nom, Prénom, Date de naissance, Repas oui/non ; l'en-tête « Nom… » est ignoré ; dates JJ/MM/AAAA converties). Repas réservés aux démonstrateurs, payés sur place.
- **Démonstration** : une seule par club, sans nom (depuis le 10/11/2026). Durée ≤ maximum, 2 couleurs différentes de la liste, planches ≤ maximum (une planche par participant), piste audio facultative à l'envoi (MP3, M4A, AAC, WAV).
- **Règlement** : case obligatoire « J'ai lu et j'accepte le règlement des démonstrations (PDF) », lien réglable dans Réglages (vide = pas de case). Date d'acceptation enregistrée (détail admin, mail récapitulatif).
- **Pistes audio** : rangées dans `wp-content/uploads/tkd-gala/<édition>/` sous un nom aléatoire, accès direct interdit par `.htaccess`, lues uniquement via l'extension (jeton du club ou administrateur). Remplacer une piste supprime l'ancienne.
- **Mails** : au représentant (récapitulatif + lien) et à l'organisation, à chaque enregistrement.

## Administration (menu Gala)

- **Inscriptions** : totaux (clubs, démonstrateurs, repas et montant, démonstrations, durée cumulée, planches, pistes manquantes), liste des clubs, détail (participants, écoute des pistes, case « Repas payés », note interne, suppression).
- **Candidatures** (depuis la 1.1.0, 10/11/2026) : envois du formulaire Elementor des autres disciplines (nom réglable : Réglages → « Formulaire des candidatures »), **lus sans être modifiés** dans les tables d'Elementor (`…e_submissions`, `…e_submissions_values`). Case « Retenue » par candidature ; dépôt de la piste audio d'une candidature retenue (reçue par mail) par l'organisation. Sélection, ordre et piste rangés dans `…tkd_gala_externes` (une ligne par envoi Elementor). Écoute réservée aux administrateurs (`?tkd_gala_audio_ext=`).
- **Conducteur** : toutes les démonstrations de la soirée, 🥋 clubs de taekwondo et 🌐 candidatures retenues, avec **un seul** numéro d'ordre de passage ; heures calculées (début + durées + battement), durée totale et heure de fin, écoute, impression.
- **Exports** : CSV des démonstrateurs (pour le traiteur), CSV des démonstrations (clubs et candidatures retenues, avec discipline et heure), ZIP de toutes les pistes nommées dans l'ordre de passage.
- **Après le gala** : Réglages → « Supprimer les données de l'édition » (inscriptions et pistes audio, irréversible).

## Sécurité

- Pas de nonce WordPress sur le formulaire public (page potentiellement en cache) : la modification exige le jeton secret de 64 caractères, envoyé dans le formulaire. Anti-robots : champ piège + 10 inscriptions / 5 demandes de lien par heure et par IP.
- Toutes les actions d'administration : `manage_options` + nonce.
- Envoi plus lourd que `post_max_size` détecté (PHP vide alors tout le formulaire) avec un message clair.

## En cas de bug

- **Le lien du mail mène à l'accueil** : aucune page publiée ne contient `[tkd_gala_inscription]` (avertissement en tête du menu Gala).
- **Piste audio refusée « trop lourd »** : la limite réelle est la plus petite entre le réglage et `upload_max_filesize`/`post_max_size` du serveur (affichée dans Réglages).
- **Piste audio introuvable à l'écoute** : vérifier le dossier `uploads/tkd-gala/<édition>/` ; changer d'édition dans les réglages ne déplace pas les fichiers.
- **Lecture impossible sur iPhone** : la lecture passe par `Tkd_Gala_Audio::envoyer_fichier()`, qui gère les requêtes « Range » exigées par Safari.
- **Tables absentes** : désactiver puis réactiver l'extension ; message `[TKD Gala] tables absentes` dans le journal d'erreurs sinon.
- **Onglet Candidatures vide** : vérifier que le nom du formulaire Elementor correspond exactement au réglage « Formulaire des candidatures », et que l'action « Collecter les envois » est active sur ce formulaire. Une candidature mise à la corbeille dans Elementor disparaît de l'onglet (et du Conducteur).
- **Une candidature retenue n'apparaît pas au Conducteur** : elle est retenue pour une autre édition (la ligne garde l'édition du moment où elle a été cochée).
