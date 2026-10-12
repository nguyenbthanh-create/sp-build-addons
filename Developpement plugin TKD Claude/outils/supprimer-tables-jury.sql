-- ════════════════════════════════════════════════════════════════════════════════════════
-- Suppression des anciennes tables du module jury de sp_build (12/10/2026).
--
-- Le module jury a été retiré le 03/10/2026 (remplacé par « Passages de grade »). Ses tables
-- étaient restées parce que sp_build les recréait à chaque vérification de la base. Depuis la
-- version du 12/10/2026 (class-db.php), il ne les crée plus : elles peuvent être supprimées.
--
-- AVANT : 1) la version du 12/10/2026 de sp_build doit être en place sur le site concerné
--            (sinon les tables sont recréées, vides, à la prochaine vérification) ;
--         2) site de test d'abord (base tkdcladst), puis la prod (base tkdclad766) APRÈS une
--            sauvegarde OVH faite juste avant.
-- La suppression est DÉFINITIVE (retour arrière = restauration de la sauvegarde).
--
-- NE PAS TOUCHER (toujours utilisées) :
--   mod237_sp_cal_exam_passages            historique des examens (écrit à chaque validation)
--   mod237_sp_cal_exam_grade_progression   enchaînement des grades si TKD Parcours est inactif
--   mod237_sp_cal_passage*                 module « Passages de grade » en service
-- ════════════════════════════════════════════════════════════════════════════════════════

-- Contrôle avant suppression : nombre de lignes de chaque table (pour mémoire).
SELECT table_name AS `table`, table_rows AS `lignes (environ)`
  FROM information_schema.tables
 WHERE table_schema = DATABASE()
   AND table_name IN (
       'mod237_sp_cal_exam_sessions', 'mod237_sp_cal_exam_tables', 'mod237_sp_cal_exam_juges',
       'mod237_sp_cal_exam_affectations', 'mod237_sp_cal_exam_epreuves', 'mod237_sp_cal_exam_grade_contenu',
       'mod237_sp_cal_exam_zemita_seuils', 'mod237_sp_cal_exam_zemita_mapping',
       'mod237_sp_cal_jury_notes', 'mod237_sp_jury_notes' );

DROP TABLE IF EXISTS
    mod237_sp_cal_exam_sessions,
    mod237_sp_cal_exam_tables,
    mod237_sp_cal_exam_juges,
    mod237_sp_cal_exam_affectations,
    mod237_sp_cal_exam_epreuves,
    mod237_sp_cal_exam_grade_contenu,
    mod237_sp_cal_exam_zemita_seuils,
    mod237_sp_cal_exam_zemita_mapping,
    mod237_sp_cal_jury_notes,
    mod237_sp_jury_notes;

-- Contrôle après : il ne doit rester que les deux tables d'examen gardées.
SHOW TABLES LIKE 'mod237_sp_cal_exam%';
