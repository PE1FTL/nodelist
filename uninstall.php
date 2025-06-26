<?php
// Sicherheitsabfrage: Nur aus WordPress heraus aufrufen
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;
$table_name = $wpdb->prefix . 'nodelist';
$pending_table_name = $wpdb->prefix . 'nodelist_pending';

// Optional: Tabellen bei Deinstallation löschen.
// $wpdb->query("DROP TABLE IF EXISTS $table_name");
// $wpdb->query("DROP TABLE IF EXISTS $pending_table_name");