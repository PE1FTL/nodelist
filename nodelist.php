<?php
/**
 * Plugin Name: Nodelist
 * Description: Zeigt eine Nodeliste aus einer Datenbank an. Bietet eine Leseansicht für Gäste und Bearbeitungs-/Genehmigungsfunktionen für Admins.
 * Version: 0.0.1
 * Author: KI-Assistent
 * Text Domain: nodelist
 */

// Sicherheitsabfrage: Direkten Zugriff verhindern
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Konstanten definieren
define( 'NODELIST_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'NODELIST_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Session starten, falls noch nicht geschehen (für CAPTCHA)
function nodelist_start_session() {
    if ( ! session_id() && ! headers_sent() ) {
        session_start();
    }
}
add_action( 'init', 'nodelist_start_session', 1 );

// Aktivierungs-Hook
function nodelist_activate_plugin() {
    require_once NODELIST_PLUGIN_PATH . 'includes/class-nodelist-activator.php';
    Nodelist_Activator::activate();
}
register_activation_hook( __FILE__, 'nodelist_activate_plugin' );

// Inkludieren der benötigten Klassen
require_once NODELIST_PLUGIN_PATH . 'includes/class-nodelist-shortcode.php';
require_once NODELIST_PLUGIN_PATH . 'includes/class-nodelist-ajax.php';
require_once NODELIST_PLUGIN_PATH . 'includes/class-nodelist-admin.php';

// Initialisierung der Klassen
if ( class_exists( 'Nodelist_Shortcode' ) ) {
    new Nodelist_Shortcode();
}

if ( class_exists( 'Nodelist_Ajax' ) ) {
    new Nodelist_Ajax();
}

if ( class_exists( 'Nodelist_Admin' ) ) {
    new Nodelist_Admin();
}

// Skripte und Stile einbinden
function nodelist_enqueue_scripts() {
    wp_enqueue_style( 'nodelist-style', NODELIST_PLUGIN_URL . 'assets/css/nodelist-style.css' );
    wp_enqueue_script( 'nodelist-main-js', NODELIST_PLUGIN_URL . 'assets/js/nodelist-main.js', array( 'jquery' ), '1.0', true );
    wp_localize_script( 'nodelist-main-js', 'nodelist_ajax_object', array(
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'nodelist_nonce' ),
        'is_admin' => current_user_can('manage_options')
    ) );
}
add_action( 'wp_enqueue_scripts', 'nodelist_enqueue_scripts' );


/**************************************************/
/* GITHUB PLUGIN UPDATER
/**************************************************/

class Nodelist_GitHub_Plugin_Updater {

    private $file;
    private $plugin_data;
    private $github_repo_owner;
    private $github_repo_name;
    private $github_api_url;

    public function __construct( $plugin_file ) {
        $this->file = $plugin_file;
        $this->github_repo_owner = 'PE1FTL'; // <-- HIER ANPASSEN
        $this->github_repo_name = 'nodelist';   // <-- HIER ANPASSEN

        $this->plugin_data = get_plugin_data( $this->file );
        $this->github_api_url = "https://api.github.com/repos/{$this->github_repo_owner}/{$this->github_repo_name}/releases/latest";

        add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_update' ) );
        add_filter( 'plugins_api', array( $this, 'plugin_api_info' ), 10, 3 );
    }

    public function check_for_update( $transient ) {
        if ( empty( $transient->checked ) ) {
            return $transient;
        }

        $latest_release = $this->get_latest_release_info();
        if ( ! $latest_release ) {
            return $transient;
        }

        $current_version = $this->plugin_data['Version'];
        $latest_version = ltrim($latest_release->tag_name, 'v');

        if ( version_compare( $latest_version, $current_version, '>' ) ) {
            $plugin_slug = plugin_basename( $this->file );
            $transient->response[$plugin_slug] = (object) array(
                'slug'        => $plugin_slug,
                'new_version' => $latest_version,
                'url'         => "https://github.com/{$this->github_repo_owner}/{$this->github_repo_name}",
                'package'     => $this->get_package_url($latest_release),
            );
        }

        return $transient;
    }

    public function plugin_api_info( $res, $action, $args ) {
        if ( 'plugin_information' !== $action || plugin_basename( $this->file ) !== $args->slug ) {
            return $res;
        }

        $latest_release = $this->get_latest_release_info();
        if ( ! $latest_release ) {
            return $res;
        }
        
        $res = new stdClass();
        $res->name = $this->plugin_data['Name'];
        $res->slug = $args->slug;
        $res->version = ltrim($latest_release->tag_name, 'v');
        $res->author = $this->plugin_data['Author'];
        $res->homepage = $this->plugin_data['PluginURI'];
        $res->requires = '5.0';
        $res->tested = '6.8';
        $res->download_link = $this->get_package_url($latest_release);
        $res->sections = array(
            'description' => $this->plugin_data['Description'],
            'changelog'   => nl2br(esc_html($latest_release->body)),
        );

        return $res;
    }

    private function get_latest_release_info() {
        $response = wp_remote_get( $this->github_api_url );

        if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
            return false;
        }

        return json_decode( wp_remote_retrieve_body( $response ) );
    }

    private function get_package_url($release_info) {
        foreach ($release_info->assets as $asset) {
            if ($asset->name === $this->github_repo_name . '.zip') {
                return $asset->browser_download_url;
            }
        }
        return $release_info->zipball_url;
    }
}

new Nodelist_GitHub_Plugin_Updater( __FILE__ );