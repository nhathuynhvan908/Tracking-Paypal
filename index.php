<?php
/*
Plugin Name: Tracking Paypal
Plugin URI: https://nhathuynhvan.com/
Description: Plugin tracking paypal WooCommerce.
Version: 1.0
Author: Huỳnh Văn Nhật
Author URI: 
*/

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}
if ( ! defined( 'DEVVPST_VERSION' ) ) {
    define( 'DEVVPST_VERSION', '1.0' );
}

if ( ! defined( 'DEVVPST_PATH' ) ) {
    define( 'DEVVPST_PATH', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'DEVVPST_URL' ) ) {
    define( 'DEVVPST_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'DEVVPST_END_POINT' ) ) {
    define( 'DEVVPST_END_POINT', 'https://devvp.com/' );
}

if ( ! defined( 'DEVVPST_CACHE_KEY' ) ) {
    define( 'DEVVPST_CACHE_KEY', 'DEVVPST_cache' );
}

if ( ! class_exists( 'DEVVPST_IMPLEMENT' ) ) {

    class DEVVPST_IMPLEMENT {

        public function __construct() {
            $this->init();
            $this->hooks();
        }
        
        private function init(){
  
            $includes = array(
                'helper',
                'settings',
                'implement',
            );

            foreach( $includes as $files ){
                require_once( DEVVPST_PATH . "{$files}.php" );
            }

            register_activation_hook(__FILE__, array($this, 'DEVVPST_install'));
            register_deactivation_hook(__FILE__, array($this, 'DEVVPST_uninstall'));
        }

        public function DEVVPST_install() {
            update_option( 'enabled_devvpst_api_paypal', 'yes' );
        }
    
        public function DEVVPST_uninstall() {

        }

        private function hooks(){
            add_action( 'plugins_loaded', array($this, 'load_plugin_textdomain'));
            add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
        }

        public function load_plugin_textdomain() {
            load_plugin_textdomain('devvp', FALSE, basename(DEVVPST_PATH) . '/languages/');
        }

        public function admin_enqueue_scripts() {
            wp_enqueue_style( 'devvpst-style', DEVVPST_URL . 'assets/css/style.css' );
            wp_enqueue_script( 'devvpst-js', DEVVPST_URL . 'assets/js/admin.js', array( 'jquery' ) );
            wp_localize_script( 'devvpst-js', 'devvpst', array( 
                'ajax_url' => admin_url( 'admin-ajax.php' ),
            ));
        }
    }
    $DEVVPST_IMPLEMENT = new DEVVPST_IMPLEMENT();
}