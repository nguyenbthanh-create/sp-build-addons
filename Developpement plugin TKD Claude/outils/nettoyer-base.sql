-- ════════════════════════════════════════════════════════════════════════════════════════
-- Nettoyage de la base WordPress du club (08/10/2026) — restes d'extensions supprimées,
-- anciennes tables de sp_build, révisions de pages en surnombre.
--
-- ORDRE : 1) site de test (base tkdcladst, copie de la prod) + vérification complète ;
--         2) prod (base tkdclad766) APRÈS une sauvegarde OVH faite juste avant.
-- Toutes les suppressions sont DÉFINITIVES (retour arrière = restauration de la sauvegarde).
--
-- Ne touche pas : tables d'Elementor (e_*), de WP Mail SMTP, d'Action Scheduler (utilisé par
-- WP Mail SMTP), de SportsPress (articles sp_*), de sp_build / SP Compta / TKD Cotisations en
-- service, ni sp_cal_exam_passages et sp_cal_exam_grade_progression (historique des examens).
-- Les anciennes tables du jury (sp_cal_exam_sessions, _tables, _juges, …) restent : sp_build les
-- recrée encore (create_jury_tables()) — à retirer d'abord du code.
-- Laissés aussi : les 12 tableaux TablePress (la page « Horaires d'entraînement » en appelle un)
-- et la corbeille WordPress (à vider depuis l'administration si on le souhaite).
-- ════════════════════════════════════════════════════════════════════════════════════════


-- ── LOT 1 — Révisions : on garde les 10 plus récentes de chaque page ───────────────────────
-- (2 038 révisions, ~30 Mo avec leurs copies Elementor). À compléter, dans le wp-config.php,
-- par : define( 'WP_POST_REVISIONS', 10 );
DELETE p FROM mod237_posts p
  JOIN ( SELECT ID FROM ( SELECT ID, ROW_NUMBER() OVER ( PARTITION BY post_parent ORDER BY post_date DESC, ID DESC ) AS rang
                            FROM mod237_posts WHERE post_type = 'revision' ) r
         WHERE r.rang > 10 ) a ON a.ID = p.ID;


-- ── LOT 2 — Tables d'extensions qui ne sont plus installées ────────────────────────────────
-- WP ERP, WooCommerce (+ WooPayments), Uncanny Automator (et ses 4 vues, cause de l'échec du
-- 1er import du 08/10), All in One SEO, WPForms, MonsterInsights, Jetpack, Ultimate Member,
-- Supsystic Pricing Table, Essential Blocks.
DROP VIEW IF EXISTS
  `mod237_uap_action_logs_view`,
  `mod237_uap_api_logs_view`,
  `mod237_uap_recipe_logs_view`,
  `mod237_uap_trigger_logs_view`;
DROP TABLE IF EXISTS
  `mod237_aioseo_ai_insights_keyword_reports`, `mod237_aioseo_cache`, `mod237_aioseo_crawl_cleanup_blocked_args`,
  `mod237_aioseo_crawl_cleanup_logs`, `mod237_aioseo_notifications`, `mod237_aioseo_posts`,
  `mod237_aioseo_seo_analyzer_results`, `mod237_aioseo_writing_assistant_keywords`, `mod237_aioseo_writing_assistant_posts`,
  `mod237_eb_form_settings`,
  `mod237_erp_acct_bill_account_details`, `mod237_erp_acct_bill_details`, `mod237_erp_acct_bills`, `mod237_erp_acct_cash_at_banks`,
  `mod237_erp_acct_chart_of_accounts`, `mod237_erp_acct_currency_info`, `mod237_erp_acct_expense_checks`, `mod237_erp_acct_expense_details`,
  `mod237_erp_acct_expenses`, `mod237_erp_acct_financial_years`, `mod237_erp_acct_invoice_account_details`, `mod237_erp_acct_invoice_details`,
  `mod237_erp_acct_invoice_details_tax`, `mod237_erp_acct_invoice_receipts`, `mod237_erp_acct_invoice_receipts_details`, `mod237_erp_acct_invoices`,
  `mod237_erp_acct_journal_details`, `mod237_erp_acct_journals`, `mod237_erp_acct_ledger_categories`, `mod237_erp_acct_ledger_details`,
  `mod237_erp_acct_ledger_settings`, `mod237_erp_acct_ledgers`, `mod237_erp_acct_opening_balances`, `mod237_erp_acct_pay_bill`,
  `mod237_erp_acct_pay_bill_details`, `mod237_erp_acct_pay_purchase`, `mod237_erp_acct_pay_purchase_details`, `mod237_erp_acct_payment_methods`,
  `mod237_erp_acct_people_account_details`, `mod237_erp_acct_people_trn`, `mod237_erp_acct_people_trn_details`, `mod237_erp_acct_product_categories`,
  `mod237_erp_acct_product_details`, `mod237_erp_acct_product_types`, `mod237_erp_acct_products`, `mod237_erp_acct_purchase`,
  `mod237_erp_acct_purchase_account_details`, `mod237_erp_acct_purchase_details`, `mod237_erp_acct_purchase_details_tax`, `mod237_erp_acct_synced_taxes`,
  `mod237_erp_acct_tax_agencies`, `mod237_erp_acct_tax_agency_details`, `mod237_erp_acct_tax_cat_agency`, `mod237_erp_acct_tax_categories`,
  `mod237_erp_acct_tax_pay`, `mod237_erp_acct_taxes`, `mod237_erp_acct_transfer_voucher`, `mod237_erp_acct_trn_status_types`,
  `mod237_erp_acct_voucher_no`, `mod237_erp_audit_log`, `mod237_erp_company_locations`, `mod237_erp_crm_activities_task`,
  `mod237_erp_crm_contact_group`, `mod237_erp_crm_contact_subscriber`, `mod237_erp_crm_customer_activities`, `mod237_erp_crm_customer_companies`,
  `mod237_erp_crm_save_email_replies`, `mod237_erp_crm_save_search`, `mod237_erp_holidays_indv`, `mod237_erp_hr_announcement`,
  `mod237_erp_hr_dependents`, `mod237_erp_hr_depts`, `mod237_erp_hr_designations`, `mod237_erp_hr_education`,
  `mod237_erp_hr_employee_history`, `mod237_erp_hr_employee_notes`, `mod237_erp_hr_employee_performance`, `mod237_erp_hr_employees`,
  `mod237_erp_hr_financial_years`, `mod237_erp_hr_holiday`, `mod237_erp_hr_leave_approval_status`, `mod237_erp_hr_leave_encashment_requests`,
  `mod237_erp_hr_leave_entitlements`, `mod237_erp_hr_leave_policies`, `mod237_erp_hr_leave_policies_segregation`, `mod237_erp_hr_leave_request_details`,
  `mod237_erp_hr_leave_requests`, `mod237_erp_hr_leaves`, `mod237_erp_hr_leaves_unpaid`, `mod237_erp_hr_work_exp`,
  `mod237_erp_people_type_relations`, `mod237_erp_people_types`, `mod237_erp_peoplemeta`, `mod237_erp_peoples`, `mod237_erp_user_leaves`,
  `mod237_jetpack_sync_queue`, `mod237_monsterinsights_cache`, `mod237_pts_tables`,
  `mod237_uap_action_log`, `mod237_uap_action_log_meta`, `mod237_uap_api_log`, `mod237_uap_api_log_response`,
  `mod237_uap_closure_log`, `mod237_uap_closure_log_meta`, `mod237_uap_loop_entries`, `mod237_uap_loop_entries_items`,
  `mod237_uap_loop_entries_items_data`, `mod237_uap_options`, `mod237_uap_queue`, `mod237_uap_recipe_count`,
  `mod237_uap_recipe_log`, `mod237_uap_recipe_log_meta`, `mod237_uap_recipe_throttle_log`, `mod237_uap_rss_items`,
  `mod237_uap_scheduled_actions`, `mod237_uap_tokens_log`, `mod237_uap_trigger_log`, `mod237_uap_trigger_log_meta`,
  `mod237_um_metadata`,
  `mod237_wc_admin_note_actions`, `mod237_wc_admin_notes`, `mod237_wc_category_lookup`, `mod237_wc_customer_lookup`,
  `mod237_wc_download_log`, `mod237_wc_order_addresses`, `mod237_wc_order_coupon_lookup`, `mod237_wc_order_operational_data`,
  `mod237_wc_order_product_lookup`, `mod237_wc_order_stats`, `mod237_wc_order_tax_lookup`, `mod237_wc_orders`,
  `mod237_wc_orders_meta`, `mod237_wc_product_attributes_lookup`, `mod237_wc_product_download_directories`, `mod237_wc_product_meta_lookup`,
  `mod237_wc_rate_limits`, `mod237_wc_reserved_stock`, `mod237_wc_tax_rate_classes`, `mod237_wc_webhooks`,
  `mod237_woocommerce_api_keys`, `mod237_woocommerce_attribute_taxonomies`, `mod237_woocommerce_downloadable_product_permissions`,
  `mod237_woocommerce_log`, `mod237_woocommerce_order_itemmeta`, `mod237_woocommerce_order_items`, `mod237_woocommerce_payment_tokenmeta`,
  `mod237_woocommerce_payment_tokens`, `mod237_woocommerce_sessions`, `mod237_woocommerce_shipping_zone_locations`,
  `mod237_woocommerce_shipping_zone_methods`, `mod237_woocommerce_shipping_zones`, `mod237_woocommerce_tax_rate_locations`,
  `mod237_woocommerce_tax_rates`,
  `mod237_wpforms_analytics_forms`, `mod237_wpforms_analytics_snapshots`, `mod237_wpforms_logs`, `mod237_wpforms_payment_meta`,
  `mod237_wpforms_payments`, `mod237_wpforms_product_events_queue`, `mod237_wpforms_tasks_meta`;


-- ── LOT 3 — Anciennes tables de sp_build (versions v6 / v7 / test, jamais nettoyées) ─────────
-- Aucune n'est lue par le code actuel des 4 plugins (vérifié le 08/10/2026).
DROP TABLE IF EXISTS
  `mod237_calendrier_notes`, `mod237_sp_cal_categories`, `mod237_sp_cal_grade_correspondance`,
  `mod237_sp_cal_people`, `mod237_sp_cal_presence`,
  `mod237_sp_cal_test_categories`, `mod237_sp_cal_test_fixed_slots`, `mod237_sp_cal_test_people`,
  `mod237_sp_cal_test_presence`, `mod237_sp_cal_test_time_slots`,
  `mod237_sp_cal_v6_categories`, `mod237_sp_cal_v6_people`, `mod237_sp_cal_v6_presence`, `mod237_sp_cal_v6_time_slots`,
  `mod237_sp_cal_v7_categories`, `mod237_sp_cal_v7_events`, `mod237_sp_cal_v7_people`, `mod237_sp_cal_v7_presence`,
  `mod237_sp_cal_v7_time_slots`,
  `mod237_sp_exam_grade_progression`;


-- ── LOT 4 — Réglages, contenus et tâches laissés par les extensions disparues ──────────────
-- 4a. Réglages (options) des extensions supprimées.
DELETE FROM mod237_options
 WHERE option_name REGEXP '^_?(aioseo|automator|uap_|uncanny|ultimatemember|um_|erp_|wperp|monsterinsights|jetpack|jp_|jpsq|woocommerce|wc_|wcpay|woopay|wpforms|eael|essential|wpdeveloper|wpins|eb_|templately|iwc|sib_|edd_sl_)'
    OR option_name IN ( 'stb_options', 'mega-slider', 'premier_license_key', 'wpc_teachers_list',
                        'tkd_master_data', 'tkd_prog_master', 'sp_wa_calendar_data' )
    OR option_name LIKE 'sp\_jury\_snapshot\_%';
-- 4b. Données temporaires (WordPress et les extensions les recréent au besoin).
DELETE FROM mod237_options WHERE option_name LIKE '\_transient\_%' OR option_name LIKE '\_site\_transient\_%';
-- 4c. Contenus d'extensions disparues (recettes Uncanny Automator, formulaires Ultimate Member, tournois).
DELETE FROM mod237_posts WHERE post_type IN ( 'uo-recipe', 'uo-action', 'uo-trigger', 'uo-closure', 'uo-loop', 'uo-loop-filter',
                                             'um_form', 'um_directory', 'stb-tournament' );
-- 4d. Tâches planifiées (Action Scheduler) de WooCommerce, WooPayments, AIOSEO, WPForms, Ultimate Member.
DELETE l FROM mod237_actionscheduler_logs l
  JOIN mod237_actionscheduler_actions a ON a.action_id = l.action_id
  JOIN mod237_actionscheduler_groups g ON g.group_id = a.group_id
 WHERE g.slug IN ( 'woocommerce', 'woocommerce-payments', 'woocommerce_payments', 'wc-admin-data', 'aioseo', 'wpforms', 'ultimate-member' );
DELETE a FROM mod237_actionscheduler_actions a
  JOIN mod237_actionscheduler_groups g ON g.group_id = a.group_id
 WHERE g.slug IN ( 'woocommerce', 'woocommerce-payments', 'woocommerce_payments', 'wc-admin-data', 'aioseo', 'wpforms', 'ultimate-member' );
DELETE FROM mod237_actionscheduler_groups
 WHERE slug IN ( 'woocommerce', 'woocommerce-payments', 'woocommerce_payments', 'wc-admin-data', 'aioseo', 'wpforms', 'ultimate-member' );
-- 4e. Réglages par utilisateur des mêmes extensions.
DELETE FROM mod237_usermeta
 WHERE meta_key REGEXP '^_?(um_|woocommerce|wc_|wcpay|erp_|aioseo|monsterinsights|jetpack|wpforms|uap_|automator)';
-- 4f. Métadonnées devenues orphelines (révisions et contenus supprimés ci-dessus).
DELETE pm FROM mod237_postmeta pm LEFT JOIN mod237_posts p ON p.ID = pm.post_id WHERE p.ID IS NULL;
DELETE tr FROM mod237_term_relationships tr LEFT JOIN mod237_posts p ON p.ID = tr.object_id WHERE p.ID IS NULL
   AND tr.object_id NOT IN ( SELECT link_id FROM mod237_links );


-- ── Après coup (une fois) : récupérer la place sur le disque ───────────────────────────────
OPTIMIZE TABLE mod237_posts, mod237_postmeta, mod237_options, mod237_usermeta,
               mod237_actionscheduler_actions, mod237_actionscheduler_logs;
