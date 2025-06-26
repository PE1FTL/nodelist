<?php
/*
Plugin Name: Nodelist
Description: Verwaltet eine Knotenliste mit Admin-Freigabe und öffentlicher Anzeige
Version: 0.0.2
Author: Dein Name
*/

// Direkten Zugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

class Nodelist_Plugin {
    private $version = '0.0.2';
    private $db_version = '0.0.1';

    public function __construct() {
        register_activation_hook(__FILE__, [$this, 'activate']);
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_shortcode('nodelist', [$this, 'display_nodelist']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_ajax_nodelist_save', [$this, 'ajax_save']);
        add_action('wp_ajax_nopriv_nodelist_submit', [$this, 'ajax_submit']);
        add_action('wp_ajax_nodelist_get', [$this, 'ajax_get']);
        add_action('wp_ajax_nodelist_delete', [$this, 'ajax_delete']);
    }

    public function activate() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Haupttabelle erstellen
        $sql = "CREATE TABLE {$wpdb->prefix}nodelist (
            id int(11) NOT NULL AUTO_INCREMENT,
            nodecall varchar(10) NOT NULL,
            qth varchar(25) NOT NULL,
            locator varchar(10) NOT NULL,
            sysop varchar(20) NOT NULL,
            name varchar(20) NOT NULL,
            sysopemail varchar(40) NOT NULL,
            hf boolean DEFAULT 0,
            hfportnr int(11) NOT NULL,
            telnet boolean DEFAULT 0,
            telneturl varchar(25) NOT NULL,
            telnetport int(11) NOT NULL,
            ax25udp boolean DEFAULT 0,
            ax25udpurl varchar(25) NOT NULL,
            ax25udpport int(11) NOT NULL,
            bemerkung varchar(120) NOT NULL,
            regdate TIMESTAMP DEFAULT NULL,
            lastupdate TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        // Temporäre Tabelle für ausstehende Einträge erstellen
        $sql_pending = "CREATE TABLE {$wpdb->prefix}nodelist_pending (
            id int(11) NOT NULL AUTO_INCREMENT,
            nodecall varchar(10) NOT NULL,
            qth varchar(25) NOT NULL,
            locator varchar(10) NOT NULL,
            sysop varchar(20) NOT NULL,
            name varchar(20) NOT NULL,
            sysopemail varchar(40) NOT NULL,
            hf boolean DEFAULT 0,
            hfportnr int(11) NOT NULL,
            telnet boolean DEFAULT 0,
            telneturl varchar(25) NOT NULL,
            telnetport int(11) NOT NULL,
            ax25udp boolean DEFAULT 0,
            ax25udpurl varchar(25) NOT NULL,
            ax25udpport int(11) NOT NULL,
            bemerkung varchar(120) NOT NULL,
            submit_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        dbDelta($sql_pending);

        update_option('nodelist_db_version', $this->db_version);
    }

    public function enqueue_scripts() {
        wp_enqueue_style('bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css');
        wp_enqueue_script('bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js', ['jquery'], null, true);
        wp_enqueue_script('nodelist-js', plugin_dir_url(__FILE__) . 'assets/nodelist.js', ['jquery'], $this->version, true);
        wp_enqueue_style('nodelist-css', plugin_dir_url(__FILE__) . 'assets/nodelist.css', [], $this->version);
        
        wp_localize_script('nodelist-js', 'nodelist_vars', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('nodelist_nonce'),
            'is_admin' => current_user_can('manage_options')
        ]);
    }

    public function add_admin_menu() {
        add_menu_page(
            'Nodelist Verwaltung',
            'Nodelist',
            'manage_options',
            'nodelist',
            [$this, 'admin_page'],
            'dashicons-networking'
        );
    }

    public function admin_page() {
        global $wpdb;
        $pending = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}nodelist_pending");
        ?>
        <div class="wrap">
            <h1>Nodelist Verwaltung</h1>
            <h2>Ausstehende Einträge</h2>
            <table class="table table-sm table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nodecall</th>
                        <th>Sysop Email</th>
                        <th>Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending as $entry) : ?>
                        <tr>
                            <td><?php echo esc_html($entry->id); ?></td>
                            <td><?php echo esc_html($entry->nodecall); ?></td>
                            <td><?php echo esc_html($entry->sysopemail); ?></td>
                            <td>
                                <button class="btn btn-sm btn-primary approve-entry" data-id="<?php echo esc_attr($entry->id); ?>">Freigeben</button>
                                <button class="btn btn-sm btn-danger delete-entry" data-id="<?php echo esc_attr($entry->id); ?>">Löschen</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <h2>Hauptliste</h2>
            <?php $this->display_nodelist(['admin' => true]); ?>
        </div>
        <?php
    }

    public function display_nodelist($atts) {
        global $wpdb;
        $per_page = 10;
        $page = isset($_GET['nodelist_page']) ? absint($_GET['nodelist_page']) : 1;
        $offset = ($page - 1) * $per_page;

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}nodelist LIMIT %d OFFSET %d",
            $per_page,
            $offset
        ));

        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}nodelist");
        $max_pages = ceil($total / $per_page);

        // Einfaches CAPTCHA generieren
        $num1 = rand(1, 10);
        $num2 = rand(1, 10);
        $captcha_answer = $num1 + $num2;
        $captcha_question = "$num1 + $num2 = ?";

        ob_start();
        ?>
        <div class="nodelist-container">
            <table class="table table-sm table-striped">
                <thead>
                    <tr>
                        <th>Nodecall</th>
                        <th>QTH</th>
                        <th>Locator</th>
                        <th>Sysop</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>HF</th>
                        <th>Telnet</th>
                        <th>AX25UDP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results as $row) : ?>
                        <tr class="nodelist-row" data-id="<?php echo esc_attr($row->id); ?>">
                            <td><?php echo esc_html($row->nodecall); ?></td>
                            <td><?php echo esc_html($row->qth); ?></td>
                            <td><?php echo esc_html($row->locator); ?></td>
                            <td><?php echo esc_html($row->sysop); ?></td>
                            <td><?php echo esc_html($row->name); ?></td>
                            <td><?php echo esc_html($row->sysopemail); ?></td>
                            <td><input type="checkbox" disabled <?php checked($row->hf); ?>></td>
                            <td><input type="checkbox" disabled <?php checked($row->telnet); ?>></td>
                            <td><input type="checkbox" disabled <?php checked($row->ax25udp); ?>></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <nav>
                <ul class="pagination">
                    <li class="page-item"><a class="page-link" href="?nodelist_page=1">Anfang</a></li>
                    <?php for ($i = max(1, $page - 2); $i <= min($max_pages, $page + 2); $i++) : ?>
                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?nodelist_page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item"><a class="page-link" href="?nodelist_page=<?php echo $max_pages; ?>">Ende</a></li>
                </ul>
            </nav>

            <!-- Neuer Eintrag Button -->
            <?php if (!is_admin()) : ?>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#nodelistModal">Neuer Eintrag</button>
            <?php endif; ?>
        </div>

        <!-- Modal für Details/Bearbeitung/Neueingabe -->
        <div class="modal fade" id="nodelistModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Node Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form id="nodelist-form">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Nodecall*</label>
                                        <input type="text" class="form-control" name="nodecall" required maxlength="10">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">QTH</label>
                                        <input type="text" class="form-control" name="qth" maxlength="25">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Locator</label>
                                        <input type="text" class="form-control" name="locator" maxlength="10">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Sysop</label>
                                        <input type="text" class="form-control" name="sysop" maxlength="20">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Name</label>
                                        <input type="text" class="form-control" name="name" maxlength="20">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Sysop Email*</label>
                                        <input type="email" class="form-control" name="sysopemail" required maxlength="40">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-check-label">HF</label>
                                        <input type="checkbox" class="form-check-input" name="hf">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">HF Port</label>
                                        <input type="number" class="form-control" name="hfportnr">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-check-label">Telnet</label>
                                        <input type="checkbox" class="form-check-input" name="telnet">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Telnet URL</label>
                                        <input type="text" class="form-control" name="telneturl" maxlength="25">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Telnet Port</label>
                                        <input type="number" class="form-control" name="telnetport">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-check-label">AX25UDP</label>
                                        <input type="checkbox" class="form-check-input" name="ax25udp">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">AX25UDP URL</label>
                                        <input type="text" class="form-control" name="ax25udpurl" maxlength="25">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">AX25UDP Port</label>
                                        <input type="number" class="form-control" name="ax25udpport">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Bemerkung</label>
                                        <textarea class="form-control" name="bemerkung" maxlength="120"></textarea>
                                    </div>
                                    <?php if (!is_admin()) : ?>
                                        <div class="mb-3">
                                            <label class="form-label">CAPTCHA: <?php echo esc_html($captcha_question); ?></label>
                                            <input type="number" class="form-control" name="captcha_answer" required>
                                            <input type="hidden" name="captcha_correct" value="<?php echo esc_attr($captcha_answer); ?>">
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <input type="hidden" name="id">
                            <input type="hidden" name="action" value="<?php echo is_admin() ? 'nodelist_save' : 'nodelist_submit'; ?>">
                            <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('nodelist_nonce'); ?>">
                        </form>
                    </div>
                    <div class="modal-footer">
                        <?php if (is_admin()) : ?>
                            <button type="button" class="btn btn-primary" id="save-node">Speichern</button>
                        <?php else : ?>
                            <button type="button" class="btn btn-primary" id="submit-node">Absenden</button>
                        <?php endif; ?>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Schließen</button>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function ajax_get() {
        check_ajax_referer('nodelist_nonce', 'nonce');
        global $wpdb;
        $id = absint($_POST['id']);
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}nodelist WHERE id = %d", $id), ARRAY_A);
        if ($row) {
            wp_send_json_success($row);
        } else {
            wp_send_json_error('Eintrag nicht gefunden');
        }
    }

    public function ajax_submit() {
        check_ajax_referer('nodelist_nonce', 'nonce');
        
        // Einfaches CAPTCHA validieren
        $captcha_answer = intval($_POST['captcha_answer']);
        $captcha_correct = intval($_POST['captcha_correct']);
        
        if ($captcha_answer !== $captcha_correct) {
            wp_send_json_error('Falsche CAPTCHA-Antwort');
        }

        global $wpdb;
        $data = $this->sanitize_input($_POST);
        
        if (empty($data['nodecall']) || empty($data['sysopemail'])) {
            wp_send_json_error('Pflichtfelder fehlen');
        }

        $result = $wpdb->insert("{$wpdb->prefix}nodelist_pending", $data);
        
        if ($result) {
            $this->send_notification_emails($data);
            wp_send_json_success('Eintrag eingereicht');
        } else {
            wp_send_json_error('Fehler beim Einreichen');
        }
    }

    public function ajax_save() {
        check_ajax_referer('nodelist_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Keine Berechtigung');
        }

        global $wpdb;
        $data = $this->sanitize_input($_POST);
        $id = absint($_POST['id']);

        if ($id) {
            // Update existing
            $wpdb->update("{$wpdb->prefix}nodelist", $data, ['id' => $id]);
        } else {
            // Approve from pending
            $pending_id = absint($_POST['pending_id']);
            if ($pending_id) {
                $pending = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}nodelist_pending WHERE id = %d", $pending_id));
                if ($pending) {
                    $wpdb->insert("{$wpdb->prefix}nodelist", (array)$pending);
                    $wpdb->delete("{$wpdb->prefix}nodelist_pending", ['id' => $pending_id]);
                }
            }
        }
        
        wp_send_json_success('Gespeichert');
    }

    public function ajax_delete() {
        check_ajax_referer('nodelist_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Keine Berechtigung');
        }

        global $wpdb;
        $id = absint($_POST['id']);
        $wpdb->delete("{$wpdb->prefix}nodelist_pending", ['id' => $id]);
        wp_send_json_success('Gelöscht');
    }

    private function sanitize_input($data) {
        return [
 'nodecall' => sanitize_text_field($data['nodecall']),
            'qth' => sanitize_text_field($data['qth']),
            'locator' => sanitize_text_field($data['locator']),
            'sysop' => sanitize_text_field($data['sysop']),
            'name' => sanitize_text_field($data['name']),
            'sysopemail' => sanitize_email($data['sysopemail']),
            'hf' => isset($data['hf']) ? 1 : 0,
            'hfportnr' => absint($data['hfportnr']),
            'telnet' => isset($data['telnet']) ? 1 : 0,
            'telneturl' => sanitize_text_field($data['telneturl']),
            'telnetport' => absint($data['telnetport']),
            'ax25udp' => isset($data['ax25udp']) ? 1 : 0,
            'ax25udpurl' => sanitize_text_field($data['ax25udpurl']),
            'ax25udpport' => absint($data['ax25udpport']),
            'bemerkung' => sanitize_textarea_field($data['bemerkung'])
        ];
    }

    private function send_notification_emails($data) {
        $admin_email = get_option('admin_email');
        $subject = 'Neuer Nodelist Eintrag eingereicht';
        $message = "Ein neuer Eintrag wurde eingereicht:\n\n";
        foreach ($data as $key => $value) {
            $message .= ucfirst($key) . ": $value\n";
        }
        $message .= "\nBitte im Admin-Bereich freigeben.";

        wp_mail($admin_email, $subject, $message);
        wp_mail($data['sysopemail'], $subject, "Ihr Eintrag wurde eingereicht und wartet auf Freigabe.");
    }
}
new Nodelist_Plugin();