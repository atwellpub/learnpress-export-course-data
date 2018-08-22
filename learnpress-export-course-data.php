<?php
/*
Plugin Name: LearnPress - AthleteAlly Extensions
Plugin URI: https://codeable.io/developers/hudson-atwell/?ref=85Fnp
Description: Extensions for the LearnPress: Single Line Quiz Type, Paragraph Quiz Type, Satisfaction Quiz Type.
Author: Hudson Atwell
Version: 1.0.1
Author URI: https://codeable.io/developers/hudson-atwell/?ref=85Fnp
Text Domain: learnpress-athleteally
*/

if ( !class_exists('LearnPress_Export_Course_Data')	) {
    /**
     * Class LearnPress_Export_Course_Data
     */
    final class LearnPress_Export_Course_Data {

        /**
         * Main LearnPress_Export_Course_Data Instance
         */
        public function __construct() {
            self::define_constants();
            self::load_hooks();
            self::load_classes();
        }

        /*
        * Setup plugin constants
        *
        */
        private static function define_constants() {

            define('LEARNPRESS_EXPORTCOURSEDATACURRENT_VERSION', '1.0.1' );
            define('LEARNPRESS_EXPORTCOURSEDATAURLPATH', plugin_dir_url( __FILE__ ));
            define('LEARNPRESS_EXPORTCOURSEDATAPATH', plugin_dir_path( __FILE__ ) );
            define('LEARNPRESS_EXPORTCOURSEDATASLUG', plugin_basename( dirname( __FILE__ ) ) );
            define('LEARNPRESS_EXPORTCOURSEDATAFILE', __FILE__ );

        }

        /**
         *
         */
        private static function load_classes() {

            /* Frontend & Admin */
            include_once( LEARNPRESS_EXPORTCOURSEDATAPATH . 'classes/class.course-export.php');

        }

        /**
         * Load hooks and filters
         */
        public static function load_hooks() {

            /**
             * Load admin side js/css
             */
            add_action('admin_enqueue_scripts' , array( __CLASS__ , 'enqueue_admin_scripts' ));

            /**
             * Register menu item
             */
            add_action('admin_menu', array(__CLASS__, 'register_menu') , 100 );

        }

        public static function enqueue_admin_scripts() {

            if (!isset($_GET['page']) || $_GET['page'] != 'export-course-csv' ) {
                return;
            }

            wp_enqueue_style( 'learnpress-athleteally-course-export' , LEARNPRESS_EXPORTCOURSEDATAURLPATH . 'assets/css/course-export.css');
            wp_enqueue_script( 'learnpress-athleteally-course-export' , LEARNPRESS_EXPORTCOURSEDATAURLPATH . 'assets/js/course-export.js');
            wp_localize_script( 'learnpress-athleteally-course-export' , 'learnpressExport',  array( 'ajaxurl' => admin_url( 'admin-ajax.php' ) ));
        }

        /**
         * Registers the wp-admin option page
         **/
        public static function register_menu(){
            add_submenu_page( 'learn_press', 'Export CSV', 'Export CSV', 'manage_options', 'export-course-csv', array( 'LearnPress_Export_Course_Data_Settings_Page' , 'display')  );
        }
    }

    new LearnPress_Export_Course_Data;

}
