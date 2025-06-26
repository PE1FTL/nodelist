<?php

class Nodelist_Ajax {

    public function __construct() {
        add_action( 'wp_ajax_nodelist_get_details', array( $this, 'get_details' ) );
        add_action( 'wp_ajax_nopriv_nodelist_get_details', array( $this, 'get_details' ) );

        add_action( 'wp_ajax_nodelist_update_entry', array( $this, 'update_entry' ) );
        add_action( 'wp_ajax_nodelist_create_entry', array( $this, 'create_entry' ) );
        add_action( 'wp_ajax_nopriv_nodelist_create_entry', array( $this, 'create_entry' ) );
    }

    private function get_all_post_data() {
        $data = array();
        $fields = ['id', 'nodecall', 'qth', 'locator', 'sysop', 'name', 'sysopemail', 'hf', 'hfportnr', 'telnet', 'telneturl', 'telnetport', 'ax25udp', 'ax25udpurl', 'ax25udpport', 'bemerkung', 'captcha'];
        foreach($fields as $field) {
            if(isset($_POST[$field])) {
                 if(is_string($_POST[$field])) {
                    $data[$field] = sanitize_text_field(stripslashes($_POST[$field]));
                 } else {
                    $data[$field] = $_POST[$field];
                 }
            }
        }
        $data['hf'] = isset($_POST['hf']) && $_POST['hf'] == 'true' ? 1 : 0;
        $data['telnet'] = isset($_POST['telnet']) && $_POST['telnet'] == 'true' ? 1 : 0;
        $data['ax25udp'] = isset($_POST['ax25udp']) && $_POST['ax25udp'] == 'true' ? 1 : 0;
        unset($data['captcha']);
        return $data;
    }

    public function get_details() {
        check_ajax_referer( 'nodelist_nonce', 'nonce' );
        global $wpdb;
        $table_name = $wpdb->prefix . 'nodelist';
        $id = intval( $_POST['id'] );
        $data = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $id ) );
        if ( $data ) {
            wp_send_json_success( $data );
        } else {
            wp_send_json_error( 'Eintrag nicht gefunden.' );
        }
    }

    public function update_entry() {
        check_ajax_referer( 'nodelist_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Keine Berechtigung.' );
        }
        global $wpdb;
        $table_name = $wpdb->prefix . 'nodelist';
        $data = $this->get_all_post_data();
        $id = intval( $data['id'] );
        unset($data['id']);
        $result = $wpdb->update( $table_name, $data, array( 'id' => $id ) );
        if ( $result !== false ) {
            wp_send_json_success( 'Eintrag erfolgreich aktualisiert.' );
        } else {
            wp_send_json_error( 'Fehler beim Aktualisieren des Eintrags.' );
        }
    }

    public function create_entry() {
        check_ajax_referer( 'nodelist_nonce', 'nonce' );
        
        $correct_answer = isset($_SESSION['nodelist_captcha']) ? $_SESSION['nodelist_captcha'] : null;
        $user_answer = isset($_POST['captcha']) ? intval($_POST['captcha']) : null;
        unset($_SESSION['nodelist_captcha']);
        if ($correct_answer === null || $user_answer !== $correct_answer) {
            wp_send_json_error( 'Die Antwort der Sicherheitsfrage ist falsch.' );
            return;
        }
        
        $data = $this->get_all_post_data();
        if ( empty($data['nodecall']) || empty($data['sysopemail']) ) {
            wp_send_json_error( 'Nodecall und SysOp E-Mail sind Pflichtfelder.' );
            return;
        }

        global $wpdb;
        $pending_table_name = $wpdb->prefix . 'nodelist_pending';
        unset($data['id']);
        $result = $wpdb->insert( $pending_table_name, $data );
        if ($result) {
            $this->send_notifications( $data );
            wp_send_json_success('Ihr Eintrag wurde zur Überprüfung eingereicht. Vielen Dank!');
        } else {
            wp_send_json_error('Fehler beim Speichern des Eintrags.');
        }
    }

    private function send_notifications( $data ) {
        $admin_email = get_option( 'admin_email' );
        $sysop_email = $data['sysopemail'];
        $headers = array('Content-Type: text/html; charset=UTF-8');
        $admin_subject = "Neuer Nodelist-Eintrag zur Genehmigung";
        $admin_message = "<html><body><h2>Ein neuer Nodelist-Eintrag wartet auf Ihre Genehmigung.</h2><p><strong>Nodecall:</strong> " . esc_html($data['nodecall']) . "</p><p><strong>SysOp:</strong> " . esc_html($data['sysop']) . "</p><p><strong>SysOp E-Mail:</strong> " . esc_html($data['sysopemail']) . "</p><p>Bitte gehen Sie zum <a href='" . admin_url('admin.php?page=nodelist_pending') . "'>Admin-Bereich</a>, um den Eintrag zu prüfen.</p></body></html>";
        wp_mail( $admin_email, $admin_subject, $admin_message, $headers );
        $sysop_subject = "Ihr Nodelist-Eintrag wurde empfangen";
        $sysop_message = "<html><body><h2>Vielen Dank für Ihren Eintrag in die Nodeliste.</h2><p>Ihre Daten wurden zur Überprüfung an den Administrator weitergeleitet. Sie erhalten eine Benachrichtigung, sobald Ihr Eintrag freigeschaltet wurde.</p><p><strong>Ihre übermittelten Daten:</strong></p><ul>";
        foreach ($data as $key => $value) {
            $sysop_message .= "<li><strong>" . esc_html(ucfirst($key)) . ":</strong> " . esc_html($value) . "</li>";
        }
        $sysop_message .= "</ul></body></html>";
        wp_mail( $sysop_email, $sysop_subject, $sysop_message, $headers );
    }
}