<?php

class Nodelist_Activator {
    public static function activate() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        $table_name = $wpdb->prefix . 'nodelist';
        $sql = "CREATE TABLE $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            nodecall varchar(10) NOT NULL,
            qth varchar(25) NOT NULL,
            locator varchar(10) NOT NULL,
            sysop varchar(20) NOT NULL,
            name varchar(20) NOT NULL,
            sysopemail varchar(40) NOT NULL,
            hf tinyint(1) DEFAULT 0 NOT NULL,
            hfportnr int(11) DEFAULT 0 NOT NULL,
            telnet tinyint(1) DEFAULT 0 NOT NULL,
            telneturl varchar(25) NOT NULL,
            telnetport int(11) DEFAULT 0 NOT NULL,
            ax25udp tinyint(1) DEFAULT 0 NOT NULL,
            ax25udpurl varchar(25) NOT NULL,
            ax25udpport int(11) DEFAULT 0 NOT NULL,
            bemerkung varchar(120) NOT NULL,
            regdate TIMESTAMP DEFAULT NULL,
            lastupdate TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        dbDelta( $sql );

        $pending_table_name = $wpdb->prefix . 'nodelist_pending';
        $sql_pending = "CREATE TABLE $pending_table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            nodecall varchar(10) NOT NULL,
            qth varchar(25) NOT NULL,
            locator varchar(10) NOT NULL,
            sysop varchar(20) NOT NULL,
            name varchar(20) NOT NULL,
            sysopemail varchar(40) NOT NULL,
            hf tinyint(1) DEFAULT 0 NOT NULL,
            hfportnr int(11) DEFAULT 0 NOT NULL,
            telnet tinyint(1) DEFAULT 0 NOT NULL,
            telneturl varchar(25) NOT NULL,
            telnetport int(11) DEFAULT 0 NOT NULL,
            ax25udp tinyint(1) DEFAULT 0 NOT NULL,
            ax25udpurl varchar(25) NOT NULL,
            ax25udpport int(11) DEFAULT 0 NOT NULL,
            bemerkung varchar(120) NOT NULL,
            regdate TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        dbDelta( $sql_pending );
    }
}