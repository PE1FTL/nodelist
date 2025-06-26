<?php
if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class Nodelist_Pending_Table extends WP_List_Table {

    function get_columns() {
        return [
            'cb'         => '<input type="checkbox" />',
            'nodecall'   => 'Nodecall',
            'sysop'      => 'SysOp',
            'sysopemail' => 'SysOp E-Mail',
            'qth'        => 'QTH',
            'regdate'    => 'Eingegangen am'
        ];
    }

    function prepare_items() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'nodelist_pending';
        $this->_column_headers = array($this->get_columns(), array(), array());
        $this->items = $wpdb->get_results( "SELECT * FROM $table_name ORDER BY regdate DESC", ARRAY_A );
    }
    
    function column_default( $item, $column_name ) {
        return esc_html( $item[ $column_name ] );
    }

    function column_cb($item) {
        return sprintf('<input type="checkbox" name="entry[]" value="%s" />', $item['id']);
    }

    function column_nodecall($item) {
        $actions = array(
            'approve' => sprintf('<a href="?page=%s&action=%s&id=%s&_wpnonce=%s">Genehmigen</a>', $_REQUEST['page'], 'approve', $item['id'], wp_create_nonce('nodelist_approve_nonce')),
            'reject'  => sprintf('<a href="?page=%s&action=%s&id=%s&_wpnonce=%s" style="color:#a00;">Ablehnen</a>', $_REQUEST['page'], 'reject', $item['id'], wp_create_nonce('nodelist_reject_nonce')),
        );
        return sprintf('%1$s %2$s', $item['nodecall'], $this->row_actions($actions) );
    }
}

class Nodelist_Admin {
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'handle_actions' ) );
    }

    public function add_admin_menu() {
        global $wpdb;
        $pending_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}nodelist_pending");
        $bubble = $pending_count > 0 ? " <span class='awaiting-mod'>$pending_count</span>" : "";

        add_menu_page('Nodelist', 'Nodelist' . $bubble, 'manage_options', 'nodelist_main', null, 'dashicons-list-view', 25);
        add_submenu_page('nodelist_main', 'Ausstehende Einträge', 'Ausstehende Einträge' . $bubble, 'manage_options', 'nodelist_pending', array( $this, 'render_pending_page' ));
    }

    public function render_pending_page() {
        $list_table = new Nodelist_Pending_Table();
        $list_table->prepare_items();
        ?>
        <div class="wrap">
            <h1>Ausstehende Nodelist-Einträge</h1>
            <?php if (isset($_GET['message'])): ?>
            <div id="message" class="updated notice is-dismissible"><p><?php echo esc_html($_GET['message']); ?></p></div>
            <?php endif; ?>
            <form method="post"><?php $list_table->display(); ?></form>
        </div>
        <?php
    }

    public function handle_actions() {
        global $wpdb;
        $main_table = $wpdb->prefix . 'nodelist';
        $pending_table = $wpdb->prefix . 'nodelist_pending';

        if ( isset( $_GET['action'], $_GET['id'], $_GET['_wpnonce'] ) && $_GET['action'] === 'approve' ) {
            if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'nodelist_approve_nonce' ) ) {
                wp_die('Sicherheitsüberprüfung fehlgeschlagen.');
            }
            $id = intval($_GET['id']);
            $pending_entry = $wpdb->get_row("SELECT * FROM $pending_table WHERE id = $id", ARRAY_A);
            if ($pending_entry) {
                unset($pending_entry['id'], $pending_entry['regdate']);
                $pending_entry['regdate'] = current_time('mysql');
                $wpdb->insert($main_table, $pending_entry);
                $wpdb->delete($pending_table, ['id' => $id]);
                wp_redirect(admin_url('admin.php?page=nodelist_pending&message=Eintrag genehmigt und übernommen.'));
                exit;
            }
        }

        if ( isset( $_GET['action'], $_GET['id'], $_GET['_wpnonce'] ) && $_GET['action'] === 'reject' ) {
             if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'nodelist_reject_nonce' ) ) {
                wp_die('Sicherheitsüberprüfung fehlgeschlagen.');
            }
            $id = intval($_GET['id']);
            $wpdb->delete($pending_table, ['id' => $id]);
            wp_redirect(admin_url('admin.php?page=nodelist_pending&message=Eintrag abgelehnt und gelöscht.'));
            exit;
        }
    }
}