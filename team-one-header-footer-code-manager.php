<?php
/**
 * Plugin Name: Team One Header Footer Code Manager
 * Plugin URI: 
 * Description: Header Footer Code Manager by Team One is a quick and simple way for you to add tracking code snippets, conversion pixels, or other scripts required by third party services for analytics, tracking, marketing, or chat functions. For detailed documentation, please visit the plugin's <a href="https://www.teamonetech.cn/"> official page</a>.
 * Version: 1.0.5
 * Requires at least: 4.9
 * Requires PHP: 5.6.20
 * Author: Team-one
 * Author URI: https://www.teamonetech.cn/
 * Disclaimer: Use at your own risk. No warranty expressed or implied is provided.
 * Text Domain: team-one-header-footer-code-manager
 * Domain Path: /languages
 */

use MailPoetVendor\Doctrine\ORM\Query\Expr\Func;

/*
 * If this file is called directly, abort.
 */
if ( !defined( 'WPINC' ) ) {
    die;
}

//redis配置
define("CH_HFCM_REDIS_SHORTCODE_KEY", 'teamone-shortcode-key');//短代码

register_activation_hook( __FILE__, array( 'Team_One_NNR_HFCM', 'hfcm_options_install' ) );
add_action( 'plugins_loaded', array( 'Team_One_NNR_HFCM', 'hfcm_db_update_check' ) );
add_action( 'admin_enqueue_scripts', array( 'Team_One_NNR_HFCM', 'hfcm_enqueue_assets' ) );
add_action( 'plugins_loaded', array( 'Team_One_NNR_HFCM', 'hfcm_load_translation_files' ) );
add_action( 'admin_menu', array( 'Team_One_NNR_HFCM', 'hfcm_modifymenu' ) );
add_filter(
    'plugin_action_links_' . plugin_basename( __FILE__ ), array(
        'Team_One_NNR_HFCM',
        'hfcm_add_plugin_page_settings_link'
    )
);
add_action( 'admin_init', array( 'Team_One_NNR_HFCM', 'hfcm_init' ) );
add_shortcode( 'teamone_hfcm', array( 'Team_One_NNR_HFCM', 'hfcm_shortcode' ) );
add_action( 'wp_head', array( 'Team_One_NNR_HFCM', 'hfcm_header_scripts' ) );
add_action( 'wp_footer', array( 'Team_One_NNR_HFCM', 'hfcm_footer_scripts' ) );
add_action( 'the_content', array( 'Team_One_NNR_HFCM', 'hfcm_content_scripts' ) );
add_action( 'wp_ajax_team-one-hfcm-request', array( 'Team_One_NNR_HFCM', 'hfcm_request_handler' ) );

//redis ajax
add_action( 'wp_ajax_team-one-hfcm-set-request', array( 'Team_One_NNR_HFCM', 'hfcm_set_request' ) );

// Files containing submenu functions
require_once plugin_dir_path( __FILE__ ) . 'includes/class-hfcm-snippets-list.php';

//redis
require_once plugin_dir_path( __FILE__ ) . 'includes/class-hfcm-redis.php';

//file_cache
require_once plugin_dir_path( __FILE__ ) . 'includes/class-hfcm-cache-file.php';


if ( !class_exists( 'Team_One_NNR_HFCM' ) ) :

    class Team_One_NNR_HFCM
    {
        public static $nnr_hfcm_db_version = "1.6";
        public static $nnr_hfcm_table = "team_one_hfcm_scripts";
        public static $hfcm_settable = "team_one_hfcm_scripts_set";
        public static $timeout = 60*60*24*7;//redis过期时间设置为一周
        const TEAMONEHFCMVERSION = 'th_team_one_hfcm_version';

        /*
         * hfcm init function
         */
        public static function hfcm_init()
        {
            self::hfcm_check_installation_date();
            self::hfcm_plugin_notice_dismissed();
            self::team_one_hfcm_import_snippets();
            self::team_one_hfcm_export_snippets();
            self::update_sql();
            
        }

        /*
         * function to create the DB / Options / Defaults
         */
        public static function hfcm_options_install()
        {
            $hfcm_now = strtotime( "now" );
            add_option( 'team_one_hfcm_activation_date', $hfcm_now );
            update_option( 'team_one_hfcm_activation_date', $hfcm_now );

            global $wpdb;

            $table_name      = $wpdb->prefix . self::$nnr_hfcm_table;
            $charset_collate = $wpdb->get_charset_collate();
            $sql             = "CREATE TABLE `{$table_name}` (
                    `script_id` int(10) NOT NULL AUTO_INCREMENT,
                    `name` varchar(100) DEFAULT NULL,
                    `snippet` LONGTEXT,
                    `snippet_type` enum('html', 'js', 'css') DEFAULT 'html',
                    `device_type` enum('mobile','desktop', 'both') DEFAULT 'both',
                    `location` varchar(100) NOT NULL,
                    `display_on` enum('All','s_pages', 's_posts','s_categories','s_custom_posts','s_tags', 's_is_home', 's_is_search', 's_is_archive','latest_posts','manual') NOT NULL DEFAULT 'All',
                    `lp_count` int(10) DEFAULT NULL,
                    `s_pages` MEDIUMTEXT DEFAULT NULL,
                    `ex_pages` MEDIUMTEXT DEFAULT NULL,
                    `s_posts` MEDIUMTEXT DEFAULT NULL,
                    `ex_posts` MEDIUMTEXT DEFAULT NULL,
                    `s_custom_posts` varchar(300) DEFAULT NULL,
                    `s_categories` varchar(300) DEFAULT NULL,
                    `s_tags` varchar(300) DEFAULT NULL,
                    `status` enum('active','inactive') NOT NULL DEFAULT 'active',
                    `created_by` varchar(300) DEFAULT NULL,
                    `last_modified_by` varchar(300) DEFAULT NULL,
                    `created` datetime DEFAULT NULL,
                    `last_revision_date` datetime DEFAULT NULL,
                    `snippet_desc` LONGTEXT NULL,
                    PRIMARY KEY (`script_id`)
                )	$charset_collate";

            include_once ABSPATH . 'wp-admin/includes/upgrade.php';
            dbDelta( $sql );
            add_option( 'team_one_hfcm_db_version', self::$nnr_hfcm_db_version );
        }

        /*
         * function to check if plugin is being updated
         */
        public static function hfcm_db_update_check()
        {
            global $wpdb;

            $table_name = $wpdb->prefix . self::$nnr_hfcm_table;
            if ( get_option( 'team_one_hfcm_db_version' ) != self::$nnr_hfcm_db_version ) {
                $wpdb->show_errors();

                if ( !empty( $wpdb->dbname ) ) {
                    // Check for Exclude Pages
                    $nnr_column_ex_pages       = 'ex_pages';
                    $nnr_check_column_ex_pages = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s ",
                            $wpdb->dbname,
                            $table_name,
                            $nnr_column_ex_pages
                        )
                    );
                    if ( empty( $nnr_check_column_ex_pages ) ) {
                        $nnr_alter_sql = "ALTER TABLE `{$table_name}` ADD `ex_pages` varchar(300) DEFAULT 0 AFTER `s_pages`";
                        $wpdb->query( $nnr_alter_sql );
                    }

                    // Check for Exclude Posts
                    $nnr_column_ex_posts       = 'ex_posts';
                    $nnr_check_column_ex_posts = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s ",
                            $wpdb->dbname,
                            $table_name,
                            $nnr_column_ex_posts
                        )
                    );
                    if ( empty( $nnr_check_column_ex_posts ) ) {
                        $nnr_alter_sql = "ALTER TABLE `{$table_name}` ADD `ex_posts` varchar(300) DEFAULT 0 AFTER `s_posts`";
                        $wpdb->query( $nnr_alter_sql );
                    }

                    // Check for Snippet Type
                    $nnr_column_snippet_type       = 'snippet_type';
                    $nnr_check_column_snippet_type = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s ",
                            $wpdb->dbname,
                            $table_name,
                            $nnr_column_snippet_type
                        )
                    );
                    if ( empty( $nnr_check_column_snippet_type ) ) {
                        $nnr_alter_sql = "ALTER TABLE `{$table_name}` ADD `snippet_type` enum('html', 'js', 'css') DEFAULT 'html' AFTER `snippet`";
                        $wpdb->query( $nnr_alter_sql );
                    }


                     // Check for Snippet desc
                     $nnr_column_snippet_desc       = 'snippet_desc';
                     $nnr_check_column_snippet_desc = $wpdb->get_results(
                         $wpdb->prepare(
                             "SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s ",
                             $wpdb->dbname,
                             $table_name,
                             $nnr_column_snippet_desc
                         )
                     );
                     if ( empty( $nnr_check_column_snippet_desc ) ) {
                         $nnr_alter_sql = "ALTER TABLE `{$table_name}` ADD `snippet_desc` LONGTEXT NULL";
                         $wpdb->query( $nnr_alter_sql );
                     }
                
                    $nnr_alter_sql = "ALTER TABLE `{$table_name}` CHANGE `snippet` `snippet` LONGTEXT NULL";
                    $wpdb->query( $nnr_alter_sql );
                 
                    $nnr_alter_sql = "ALTER TABLE `{$table_name}` CHANGE `display_on` `display_on` ENUM('All','s_pages','s_posts','s_categories','s_custom_posts','s_tags','s_is_home','s_is_archive','s_is_search','latest_posts','manual') DEFAULT 'All' NOT NULL";
                    $wpdb->query( $nnr_alter_sql );

                    $nnr_alter_sql = "ALTER TABLE `{$table_name}` CHANGE `s_pages` `s_pages` MEDIUMTEXT NULL, CHANGE `ex_pages` `ex_pages` MEDIUMTEXT NULL, CHANGE `s_posts` `s_posts` MEDIUMTEXT NULL, CHANGE `ex_posts` `ex_posts` MEDIUMTEXT NULL";
                    $wpdb->query( $nnr_alter_sql );
                }
                self::hfcm_options_install();
            }
            update_option( 'team_one_hfcm_db_version', self::$nnr_hfcm_db_version );
        }

        /*
         * Enqueue style-file, if it exists.
         */
        public static function hfcm_enqueue_assets( $hook )
        {
            $allowed_pages = array(
                'toplevel_page_team-one-hfcm-list',
                'teamonehfcm_page_team-one-hfcm-create',
                'admin_page_team-one-hfcm-update',
            );

            wp_register_style( 'team-one-hfcm_general_admin_assets', plugins_url( 'css/style-general-admin.css', __FILE__ ) );
            wp_enqueue_style( 'team-one-hfcm_general_admin_assets' );

            if ( in_array( $hook, $allowed_pages ) ) {
                // Plugin's CSS
                wp_register_style( 'team-one-hfcm_assets', plugins_url( 'css/style-admin.css', __FILE__ ) );
                wp_enqueue_style( 'team-one-hfcm_assets' );
            }

            // Remove hfcm-list from $allowed_pages
            array_shift( $allowed_pages );

            if ( in_array( $hook, $allowed_pages ) ) {
                // selectize.js plugin CSS and JS files
                wp_register_style( 'team-one-selectize-css', plugins_url( 'css/selectize.bootstrap3.css', __FILE__ ) );
                wp_enqueue_style( 'team-one-selectize-css' );

                wp_register_script( 'team-one-selectize-js', plugins_url( 'js/selectize.min.js', __FILE__ ), array( 'jquery' ) );
                wp_enqueue_script( 'team-one-selectize-js' );

                wp_enqueue_code_editor( array( 'type' => 'text/html' ) );
            }
        }

        /*
         * This function loads plugins translation files
         */

        public static function hfcm_load_translation_files()
        {
            load_plugin_textdomain( 'team-one-header-footer-code-manager', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
        }

        /*
         * function to create menu page, and submenu pages.
         */
        public static function hfcm_modifymenu()
        {

            // This is the main item for the menu
            add_menu_page(
               'Team One Header Footer Code Manager',
                'TeamOneHFCM',
                'manage_options',
                'team-one-hfcm-list',
                array( 'Team_One_NNR_HFCM', 'hfcm_list' ),
                'dashicons-hfcm',
                99
            );

            // This is a submenu
            add_submenu_page(
                'team-one-hfcm-list',
                'All Snippets',
                'All Snippets',
                'manage_options',
                'team-one-hfcm-list',
                array( 'Team_One_NNR_HFCM', 'hfcm_list' )
            );

            // This is a submenu
            add_submenu_page(
                'team-one-hfcm-list',
                'Add New Snippet',
                'Add New',
                'manage_options',
                'team-one-hfcm-create',
                array( 'Team_One_NNR_HFCM', 'hfcm_create' )
            );
            // This is a submenu
            add_submenu_page(
                'team-one-hfcm-list',
                'Tools',
                'Tools',
                'manage_options',
                'team-one-hfcm-tools',
                array( 'Team_One_NNR_HFCM', 'hfcm_tools' )
            );

            
            //给redis、日志设置增加权限控制(仅限超级管理员)
            $capability	= is_multisite() ? 'manage_site' : 'manage_options';
            if (current_user_can($capability)) {
                //设置
                add_submenu_page(
                    'team-one-hfcm-list',
                    'Setting',
                    'Setting',
                    'manage_options',
                    'team-one-hfcm-redis-set',
                    array( 'Team_One_NNR_HFCM', 'hfcm_redis_set' )
                );
            }
            
            // This submenu is HIDDEN, however, we need to add it anyways
            add_submenu_page(
                null,
                'Update Script',
                'Update',
                'manage_options',
                'team-one-hfcm-update',
                array( 'Team_One_NNR_HFCM', 'hfcm_update' )
            );

            // This submenu is HIDDEN, however, we need to add it anyways
            add_submenu_page(
                null,
               'Request Handler Script',
                'Request Handler',
                'manage_options',
                'team-one-hfcm-request-handler',
                array( 'Team_One_NNR_HFCM', 'hfcm_request_handler' )
            );

            // hfcm_set_request
            add_submenu_page(
                null,
                'Set Request',
                'Set Request',
                'manage_options',
                'team-one-hfcm-set-request',
                array( 'Team_One_NNR_HFCM', 'hfcm_set_request' )
            );
        }

        /*
         * function to add a settings link for the plugin on the Settings Page
         */
        public static function hfcm_add_plugin_page_settings_link( $links )
        {
            $links = array_merge(
                array( '<a href="' . admin_url( 'admin.php?page=team-one-hfcm-list' ) . '">' . __( 'Settings' ) . '</a>' ),
                $links
            );
            return $links;
        }

        /*
         * function to check the plugins installation date
         */
        public static function hfcm_check_installation_date()
        {
            $install_date = get_option( 'team_one_hfcm_activation_date' );
            $past_date    = strtotime( '-7 days' );

            if ( $past_date >= $install_date ) {
                // add_action( 'admin_notices', array( 'Team_One_NNR_HFCM', 'hfcm_review_push_notice' ) );
            }
            // add_action( 'admin_notices', array( 'Team_One_NNR_HFCM', 'hfcm_static_notices' ) );
        }

        /*
         * function to create the Admin Notice
         */
        public static function hfcm_review_push_notice()
        {
            $allowed_pages_notices = array(
                'toplevel_page_team-one-hfcm-list',
                'hfcm_page_team-one-hfcm-create',
                'admin_page_team-one-hfcm-update',
            );
            $screen  = get_current_screen()->id;

            $user_id = get_current_user_id();
            // Check if current user has already dismissed it
            $install_date = get_option( 'team_one_hfcm_activation_date' );

            if ( !get_user_meta( $user_id, 'hfcm_plugin_notice_dismissed' ) && in_array( $screen, $allowed_pages_notices ) ) {
                ?>
                <!-- <div id="hfcm-message" class="notice notice-success">
                    <a class="hfcm-dismiss-alert notice-dismiss" href="?hfcm-admin-notice-dismissed">Dismiss</a>
                    <p>
                    </p>
                </div> -->
                <?php
            }
        }

        /*
         * function to add the static Admin Notice
         */
        public static function hfcm_static_notices()
        {
            $allowed_pages_notices = array(
                'toplevel_page_team-one-hfcm-list',
                'teamonehfcm_page_team-one-hfcm-create',
                'admin_page_team-one-hfcm-update',
            );

            $screen  = get_current_screen()->id;

            if ( in_array( $screen, $allowed_pages_notices ) ) {
                ?>
                <!-- <div id="hfcm-message" class="notice notice-success">
                    <p>
                        🔥 LIFETIME DEAL ALERT: The PRO version of this plugin is released and and available for a
                        limited time as a one-time, exclusive lifetime deal.
                         Want it? <b><i><a
                                        href="https://www.teamonetech.cn/"
                                        target="_blank">Click here</a> to get Team-One-HFCM Pro for the lowest price ever</i></b>
                    </p>
                </div> -->
                <?php
            }
        }

        /*
         * function to check if current user has already dismissed it
         */
        public static function hfcm_plugin_notice_dismissed()
        {
            $user_id = get_current_user_id();

            // Checking if user clicked on the Dismiss button
            if ( isset( $_GET['hfcm-admin-notice-dismissed'] ) ) {
                add_user_meta( $user_id, 'hfcm_plugin_notice_dismissed', 'true', true );
                // Redirect to original page the user was on
                $current_url = wp_get_referer();
                wp_redirect( $current_url );
                exit;
            }

            // Checking if user clicked on the 'I understand' button
            if ( isset( $_GET['hfcm-file-edit-notice-dismissed'] ) ) {
                add_user_meta( $user_id, 'hfcm_file_edit_plugin_notice_dismissed', 'true', true );
            }
        }

        /*
         * function to render the snippet
         */
        public static function hfcm_render_snippet( $scriptdata )
        {
            $output = "<!-- TeamoneHFCM by Team One - Snippet # " . absint( $scriptdata->script_id ) . ": " . esc_html( $scriptdata->name ) . " -->\n" . html_entity_decode( $scriptdata->snippet ) . "\n<!-- /end TeamoneHFCM by Team One -->\n";
            return $output;
        }

        /*
         * function to implement shortcode
         */
        public static function hfcm_shortcode( $atts )
        {
            global $wpdb;
            $table_name = $wpdb->prefix . self::$nnr_hfcm_table;
            if ( !empty( $atts['id'] ) ) {
                $id          = absint( $atts['id'] );
                $hide_device = wp_is_mobile() ? 'desktop' : 'mobile';

                
                //加密key--短代码标识key
                $rediskey = MD5(sha1(CH_HFCM_REDIS_SHORTCODE_KEY));
                // 实例化redis
                $redis = new Teamone_Hfcm_Redis();

                $file_cache = new Teamone_Hfcm_Cache_File();

                $server_name = $_SERVER['SERVER_NAME'];
                $server_domain = preg_replace('/^(.*?)\.(.*?)\.(.*?)$/', '$2.$3', $server_name);
                // 获取后台配置的域名缓存key
                $hfcm_set_data = $redis::get_hfcm_set();
                $server_name_key = !empty($hfcm_set_data)&& !empty($hfcm_set_data['hfcm_domain_key'])?$hfcm_set_data['hfcm_domain_key']:$server_domain;
                if($redis->open_redis){
                    // 获取redis中存储的数据
                    $cache = $redis->get_redis('hfcm:'.$server_name_key.':'.$wpdb->prefix.':'.$rediskey,self::$timeout);

                }else{

                    $cache = $file_cache->get_json($rediskey);
                }

                if(!empty($cache)){
                    $script_all  = $cache;
                    
                }else{
                  
                    // $script      = $wpdb->get_results(
                    //     $wpdb->prepare(
                    //         "SELECT * FROM `{$table_name}` WHERE status='active' AND device_type!=%s AND script_id=%d",
                    //         $hide_device,
                    //         $id
                    //     )
                    // );
                        // 存储缓存链层
                        $script_all      = $wpdb->get_results("SELECT * FROM `{$table_name}` WHERE status='active' AND display_on = 'manual'");
                        $json_data = json_encode($script_all,true);

                        if(!empty($script_all)){
                            $redis->open_redis ? $redis->set_redis('hfcm:'.$server_name_key.':'.$wpdb->prefix.':'.$rediskey,$json_data,self::$timeout):$file_cache->set_json($rediskey,$json_data);
                        }
                }
                
                // var_dump($hide_device)
                // 定义返回数据
                $script = array();
                // 根据短代码id获取数据
                if($script_all){
                    foreach($script_all as $key=>$scriptdata){
                        if($scriptdata->script_id==$id && $scriptdata->device_type!=$hide_device){
                            $script = $scriptdata;
                        }
                    }
                }
                if ( !empty( $script ) ) {
                    return self::hfcm_render_snippet( $script);
                }
            }
        }

        /*
         * Function to json_decode array and check if empty
         */
        public static function hfcm_not_empty( $scriptdata, $prop_name )
        {
            $data = json_decode( $scriptdata->{$prop_name} );
            if ( empty( $data ) ) {
                return false;
            }
            return true;
        }

        /*
         * function to decide which snippets to show - triggered by hooks
         */
        public static function hfcm_add_snippets( $location = '', $content = '' )
        {   
            // var_dump(esc_url(home_url( '/' )));
            
            global $wpdb;

            $beforecontent = '';
            $aftercontent  = '';
            $table_name    = $wpdb->prefix . self::$nnr_hfcm_table;
            $hide_device   = wp_is_mobile() ? 'desktop' : 'mobile';
            // var_dump($wpdb->prefix);exit();
            $nnr_hfcm_snippet_placeholder_args = array();
            // $nnr_hfcm_snippets_sql             = "SELECT * FROM `{$table_name}` WHERE status='active' AND device_type!=%s";
            // $nnr_hfcm_snippet_placeholder_args = [ $hide_device ];
            $nnr_hfcm_snippets_sql             = "SELECT * FROM `{$table_name}` WHERE status='active'";

            
            // 区分header和footer的key
            $nnr_hfcm_snippets_sql_key = '';
            if ( $location && in_array( $location, array( 'header', 'footer' ) ) ) {
                $nnr_hfcm_snippets_sql               .= " AND location=%s";
                $nnr_hfcm_snippet_placeholder_args[] = $location;

                // 拼接区分的key 
                $nnr_hfcm_snippets_sql_key = $nnr_hfcm_snippets_sql.$location;
            } else {
                $nnr_hfcm_snippets_sql .= " AND location NOT IN ( 'header', 'footer' ) AND display_on !='manual'";
                $nnr_hfcm_snippets_sql_key = $nnr_hfcm_snippets_sql;
            }
            // var_dump($nnr_hfcm_snippet_placeholder_args);
            //加密key
            $rediskey = MD5(sha1($nnr_hfcm_snippets_sql_key));

            // 实例化redis
            $redis = new Teamone_Hfcm_Redis();

            $file_cache = new Teamone_Hfcm_Cache_File();
            // var_dump($_SERVER['HTTP_HOST']);exit;
            $server_name = $_SERVER['SERVER_NAME'];
            $server_domain = preg_replace('/^(.*?)\.(.*?)\.(.*?)$/', '$2.$3', $server_name);
            // 获取后台配置的域名缓存key
            $hfcm_set_data =$redis::get_hfcm_set();
            $server_name_key = !empty($hfcm_set_data)&& !empty($hfcm_set_data['hfcm_domain_key'])?$hfcm_set_data['hfcm_domain_key']:$server_domain;
            if($redis->open_redis){
                // 获取redis中存储的数据
                
                $cache = $redis->get_redis('hfcm:'.$server_name_key.':'.$wpdb->prefix.':'.$rediskey,self::$timeout);
                // var_dump($cache);exit;
            }else{
                //获取文件中存储的数据
                $cache = $file_cache->get_json($rediskey);
            }
            
            if(!empty($cache)){
                $script  = $cache;
            }else{
                if(!empty($nnr_hfcm_snippet_placeholder_args)){
                    $script = $wpdb->get_results(
                        $wpdb->prepare(
                            $nnr_hfcm_snippets_sql,
                            $nnr_hfcm_snippet_placeholder_args
                        )
                    );
                    // var_dump($script);exit;
                    // 存储缓存链层
                    $json_data = json_encode($script,true);
                    // $data = $redis->open_redis ? $redis->set_redis('hfcm:'.DB_NAME.':'.$wpdb->prefix.':'.$rediskey,$json_data): $file_cache->set_json($rediskey,$json_data);
                    if(!empty($script)){
                        $redis->open_redis ? $redis->set_redis('hfcm:'.$server_name_key.':'.$wpdb->prefix.':'.$rediskey,$json_data,self::$timeout): $file_cache->set_json($rediskey,$json_data);
                    }
                }
                
                
            }
            // var_dump($script);exit;
            if ( !empty( $script ) ) {
                // 过滤数据
                foreach($script as $key=> $val){
                    if($val->device_type==$hide_device){
                        unset($script[$key]);
                    }
                }

                foreach ( $script as $key => $scriptdata ) {
                    $out = '';
                    switch ( $scriptdata->display_on ) {
                        case 'All':
                            $is_not_empty_ex_pages = self::hfcm_not_empty( $scriptdata, 'ex_pages' );
                            $is_not_empty_ex_posts = self::hfcm_not_empty( $scriptdata, 'ex_posts' );
                            if ( ($is_not_empty_ex_pages && is_page( json_decode( $scriptdata->ex_pages ) )) || ($is_not_empty_ex_posts && is_single( json_decode( $scriptdata->ex_posts ) )) ) {
                                $out = '';
                            } else {
                                $out = self::hfcm_render_snippet( $scriptdata );
                            }
                            break;
                        case 'latest_posts':
                            if ( is_single() ) {
                                if ( !empty( $scriptdata->lp_count ) ) {
                                    $nnr_hfcm_latest_posts = wp_get_recent_posts(
                                        array(
                                            'numberposts' => absint( $scriptdata->lp_count ),
                                        )
                                    );
                                } else {
                                    $nnr_hfcm_latest_posts = wp_get_recent_posts(
                                        array(
                                            'numberposts' => 5
                                        )
                                    );
                                }

                                foreach ( $nnr_hfcm_latest_posts as $key => $lpostdata ) {
                                    if ( get_the_ID() == $lpostdata['ID'] ) {
                                        $out = self::hfcm_render_snippet( $scriptdata );
                                    }
                                }
                            }
                            break;
                        case 's_categories':
                            $is_not_empty_s_categories = self::hfcm_not_empty( $scriptdata, 's_categories' );
                            if ( $is_not_empty_s_categories && in_category( json_decode( $scriptdata->s_categories ) ) ) {
                                if ( is_category( json_decode( $scriptdata->s_categories ) ) ) {
                                    $out = self::hfcm_render_snippet( $scriptdata );
                                }
                                if ( !is_archive() && !is_home() ) {
                                    $out = self::hfcm_render_snippet( $scriptdata );
                                }
                            }
                            break;
                        case 's_custom_posts':
                            $is_not_empty_s_custom_posts = self::hfcm_not_empty( $scriptdata, 's_custom_posts' );
                            if ( $is_not_empty_s_custom_posts && is_singular( json_decode( $scriptdata->s_custom_posts ) ) ) {
                                $out = self::hfcm_render_snippet( $scriptdata );
                            }
                            break;
                        case 's_posts':
                            $is_not_empty_s_posts = self::hfcm_not_empty( $scriptdata, 's_posts' );
                            if ( $is_not_empty_s_posts && is_single( json_decode( $scriptdata->s_posts ) ) ) {
                                $out = self::hfcm_render_snippet( $scriptdata );
                            }
                            break;
                        case 's_is_home':
                            if ( is_home() || is_front_page() ) {
                              
                                $out = self::hfcm_render_snippet( $scriptdata );
                            }
                            break;
                        case 's_is_archive':
                            if ( is_archive() ) {
                                $out = self::hfcm_render_snippet( $scriptdata );
                            }
                            break;
                        case 's_is_search':
                            if ( is_search() ) {
                                $out = self::hfcm_render_snippet( $scriptdata );
                            }
                            break;
                        case 's_pages':
                            $is_not_empty_s_pages = self::hfcm_not_empty( $scriptdata, 's_pages' );
                            if ( $is_not_empty_s_pages ) {
                                // Gets the page ID of the blog page
                                $blog_page = get_option( 'page_for_posts' );
                                // Checks if the blog page is present in the array of selected pages
                                if ( in_array( $blog_page, json_decode( $scriptdata->s_pages ) ) ) {
                                    if ( is_page( json_decode( $scriptdata->s_pages ) ) || (!is_front_page() && is_home()) ) {
                                        $out = self::hfcm_render_snippet( $scriptdata );
                                    }
                                } elseif ( is_page( json_decode( $scriptdata->s_pages ) ) ) {
                                    $out = self::hfcm_render_snippet( $scriptdata );
                                }
                            }
                            break;
                        case 's_tags':
                            $is_not_empty_s_tags = self::hfcm_not_empty( $scriptdata, 's_tags' );
                            if ( $is_not_empty_s_tags && has_tag( json_decode( $scriptdata->s_tags ) ) ) {
                                if ( is_tag( json_decode( $scriptdata->s_tags ) ) ) {
                                    $out = self::hfcm_render_snippet( $scriptdata );
                                }
                                if ( !is_archive() && !is_home() ) {
                                    $out = self::hfcm_render_snippet( $scriptdata );
                                }
                            }
                    }

                    switch ( $scriptdata->location ) {
                        case 'before_content':
                            $beforecontent .= $out;
                            break;
                        case 'after_content':
                            $aftercontent .= $out;
                            break;
                        default:
                            echo $out;
                    }
                }
            }
            // Return results after the loop finishes
            return $beforecontent . $content . $aftercontent;
        }

        /*
         * function to add snippets in the header
         */
        public static function hfcm_header_scripts()
        {
            if ( !is_feed() ) {
                self::hfcm_add_snippets( 'header' );
            }
        }

        /*
         * function to add snippets in the footer
         */
        public static function hfcm_footer_scripts()
        {
            if ( !is_feed() ) {
                self::hfcm_add_snippets( 'footer' );
            }
        }

        /*
         * function to add snippets before/after the content
         */
        public static function hfcm_content_scripts( $content )
        {
            if ( !is_feed() && !(defined( 'REST_REQUEST' ) && REST_REQUEST) ) {
                return self::hfcm_add_snippets( false, $content );
            } else {
                return $content;
            }
        }

        /*
         * load redirection Javascript code
         */
        public static function hfcm_redirect( $url = '' )
        {
            // Register the script
            wp_register_script( 'hfcm_redirection', plugins_url( 'js/location.js', __FILE__ ) );

            // Localize the script with new data
            $translation_array = array( 'url' => $url );
            wp_localize_script( 'hfcm_redirection', 'hfcm_location', $translation_array );

            // Enqueued script with localized data.
            wp_enqueue_script( 'hfcm_redirection' );
        }

        /*
         * function to sanitize POST data
         */
        public static function hfcm_sanitize_text( $key, $is_not_snippet = true )
        {
            if ( !empty( $_POST['data'][ $key ] ) ) {
                $post_data = stripslashes_deep( $_POST['data'][ $key ] );
                if ( $is_not_snippet ) {
                    $post_data = sanitize_text_field( $post_data );
                } else {
                    $post_data = htmlentities( $post_data );
                }
                return $post_data;
            }

            return '';
        }

        /*
         * function to sanitize strings within POST data arrays
         */
        public static function hfcm_sanitize_array( $key, $type = 'integer' )
        {
            if ( !empty( $_POST['data'][ $key ] ) ) {
                $arr = $_POST['data'][ $key ];

                if ( !is_array( $arr ) ) {
                    return array();
                }

                if ( 'integer' === $type ) {
                    return array_map( 'absint', $arr );
                } else { // strings
                    $new_array = array();
                    foreach ( $arr as $val ) {
                        $new_array[] = sanitize_text_field( $val );
                    }
                }

                return $new_array;
            }

            return array();
        }

        /*
         * function for submenu "Add snippet" page
         */
        public static function hfcm_create()
        {
            // check user capabilities
            $nnr_hfcm_can_edit = current_user_can( 'manage_options' );

            if ( !$nnr_hfcm_can_edit ) {
                echo 'Sorry, you do not have access to this page.';
                return false;
            }

            // prepare variables for includes/hfcm-add-edit.php
            $name             = '';
            $snippet          = '';
            $nnr_snippet_type = 'html';
            $device_type      = '';
            $location         = '';
            $display_on       = '';
            $status           = '';
            $lp_count         = 5; // Default value
            $s_pages          = array();
            $ex_pages         = array();
            $s_posts          = array();
            $ex_posts         = array();
            $s_custom_posts   = array();
            $s_categories     = array();
            $s_tags           = array();
            $snippet_desc     = '';

            // Notify hfcm-add-edit.php NOT to make changes for update
            $update = false;

            include_once plugin_dir_path( __FILE__ ) . 'includes/hfcm-add-edit.php';
        }

        /*
         * function to handle add/update requests
         */
        public static function hfcm_request_handler()
        {
            
            // check user capab ilities
            $nnr_hfcm_can_edit = current_user_can( 'manage_options' );

            if ( !$nnr_hfcm_can_edit ) {
                echo 'Sorry, you do not have access to this page.';
                return false;
            }

            if ( isset( $_POST['insert'] ) ) {
                // Check nonce
                check_admin_referer( 'create-snippet' );
            } else {
                if ( empty( $_REQUEST['id'] ) ) {
                    die( 'Missing ID parameter.' );
                }
                $id = absint( $_REQUEST['id'] );
            }
            if ( isset( $_POST['update'] ) ) {
                // Check nonce
                check_admin_referer( 'update-snippet_' . $id );
            }

            $snippet_obj_del = new Teamone_Hfcm_Snippets_List();

            // Handle AJAX on/off toggle for snippets
            if ( isset( $_REQUEST['toggle'] ) && !empty( $_REQUEST['togvalue'] ) ) {

                // Check nonce
                check_ajax_referer( 'hfcm-toggle-snippet', 'security' );

                if ( 'on' === $_REQUEST['togvalue'] ) {
                    $status = 'active';
                } else {
                    $status = 'inactive';
                }
                
                // Global vars
                global $wpdb;
                $table_name = $wpdb->prefix . self::$nnr_hfcm_table;

                // 获取location值
                $old_location = $snippet_obj_del->get_location_from_data($id);
                //var_dump($old_location);exit();
                $snippet_obj_del->del_rediskey($old_location);

                $wpdb->update(
                    $table_name, //table
                    array( 'status' => $status ), //data
                    array( 'script_id' => $id ), //where
                    array( '%s' ), //data format
                    array( '%s' ) //where format
                );

                
            } elseif ( isset( $_POST['insert'] ) || isset( $_POST['update'] ) ) {

                // Create / update snippet
                
                // Sanitize fields
                $name             = self::hfcm_sanitize_text( 'name' );
                $snippet          = self::hfcm_sanitize_text( 'snippet', false );
                $nnr_snippet_type = self::hfcm_sanitize_text( 'snippet_type' );
                $device_type      = self::hfcm_sanitize_text( 'device_type' );
                $display_on       = self::hfcm_sanitize_text( 'display_on' );
                $location         = self::hfcm_sanitize_text( 'location' );
                $lp_count         = self::hfcm_sanitize_text( 'lp_count' );
                $status           = self::hfcm_sanitize_text( 'status' );
                $s_pages          = self::hfcm_sanitize_array( 's_pages' );
                $ex_pages         = self::hfcm_sanitize_array( 'ex_pages' );
                $s_posts          = self::hfcm_sanitize_array( 's_posts' );
                $ex_posts         = self::hfcm_sanitize_array( 'ex_posts' );
                $s_custom_posts   = self::hfcm_sanitize_array( 's_custom_posts', 'string' );
                $s_categories     = self::hfcm_sanitize_array( 's_categories' );
                $s_tags           = self::hfcm_sanitize_array( 's_tags' );
                $snippet_desc     = self::hfcm_sanitize_text( 'snippet_desc', false );


                if ( 'manual' === $display_on ) {
                    $location = '';
                }   

              

                $lp_count = max( 1, (int) $lp_count );

                // Global vars
                global $wpdb;
                global $current_user;
                $table_name = $wpdb->prefix . self::$nnr_hfcm_table;

                
                // Update snippet
                if ( isset( $id ) ) {

                    // 获取原有的location定位
                    $old_location = $snippet_obj_del->get_location_from_data($id);
                    $snippet_obj_del->del_rediskey($old_location);

                    $wpdb->update(
                        $table_name, //table
                        // Data
                        array(
                            'name'               => $name,
                            'snippet'            => $snippet,
                            'snippet_type'       => $nnr_snippet_type,
                            'device_type'        => $device_type,
                            'location'           => $location,
                            'display_on'         => $display_on,
                            'status'             => $status,
                            'lp_count'           => $lp_count,
                            's_pages'            => wp_json_encode( $s_pages ),
                            'ex_pages'           => wp_json_encode( $ex_pages ),
                            's_posts'            => wp_json_encode( $s_posts ),
                            'ex_posts'           => wp_json_encode( $ex_posts ),
                            's_custom_posts'     => wp_json_encode( $s_custom_posts ),
                            's_categories'       => wp_json_encode( $s_categories ),
                            's_tags'             => wp_json_encode( $s_tags ),
                            'last_revision_date' => current_time( 'Y-m-d H:i:s' ),
                            'last_modified_by'   => sanitize_text_field( $current_user->display_name ),
                            'snippet_desc'       => $snippet_desc,
                        ),
                        // Where
                        array( 'script_id' => $id ),
                        // Data format
                        array(
                            '%s',
                            '%s',
                            '%s',
                            '%s',
                            '%s',
                            '%s',
                            '%s',
                            '%s',
                            '%s',
                            '%s',
                        ),
                        // Where format
                        array( '%s' )
                    );
                    self::hfcm_redirect( admin_url( 'admin.php?page=team-one-hfcm-update&message=1&id=' . $id ) );
                    
                } else {

                    if(empty($location)){
                        $location = CH_HFCM_REDIS_SHORTCODE_KEY;
                    }
                    $snippet_obj_del->del_rediskey($location);
                    // Create new snippet
                    $wpdb->insert(
                        $table_name, //table
                        array(
                            'name'           => $name,
                            'snippet'        => $snippet,
                            'snippet_type'   => $nnr_snippet_type,
                            'device_type'    => $device_type,
                            'location'       => $location,
                            'display_on'     => $display_on,
                            'status'         => $status,
                            'lp_count'       => $lp_count,
                            's_pages'        => wp_json_encode( $s_pages ),
                            'ex_pages'       => wp_json_encode( $ex_pages ),
                            's_posts'        => wp_json_encode( $s_posts ),
                            'ex_posts'       => wp_json_encode( $ex_posts ),
                            's_custom_posts' => wp_json_encode( $s_custom_posts ),
                            's_categories'   => wp_json_encode( $s_categories ),
                            's_tags'         => wp_json_encode( $s_tags ),
                            'created'        => current_time( 'Y-m-d H:i:s' ),
                            'created_by'     => sanitize_text_field( $current_user->display_name ),
                            'snippet_desc'       => $snippet_desc,

                        ), array(
                            '%s',
                            '%s',
                            '%s',
                            '%s',
                            '%s',
                            '%s',
                            '%s',
                            '%d',
                            '%s',
                            '%s',
                            '%s',
                            '%s',
                            '%s',
                            '%s',
                            '%s',
                            '%s',
                            '%s',
                            '%s',
                        )
                    );
                    $lastid = $wpdb->insert_id;
                    self::hfcm_redirect( admin_url( 'admin.php?page=team-one-hfcm-update&message=6&id=' . $lastid ) );
                }
                
            } elseif ( isset( $_POST['get_posts'] ) ) {

                // JSON return posts for AJAX

                // Check nonce
                check_ajax_referer( 'hfcm-get-posts', 'security' );

                // Global vars
                global $wpdb;
                $table_name = $wpdb->prefix . self::$nnr_hfcm_table;
                // Get all selected posts
                if ( -1 === $id ) {
                    $s_posts  = array();
                    $ex_posts = array();
                } else {
                    // Select value to update
                    $script  = $wpdb->get_results(
                        $wpdb->prepare( "SELECT s_posts FROM `{$table_name}` WHERE script_id=%s", $id )
                    );
                    $s_posts = array();
                    if ( !empty( $script ) ) {
                        foreach ( $script as $s ) {
                            $s_posts = json_decode( $s->s_posts );
                            if ( !is_array( $s_posts ) ) {
                                $s_posts = array();
                            }
                        }
                    }

                    $ex_posts  = array();
                    $script_ex = $wpdb->get_results(
                        $wpdb->prepare( "SELECT ex_posts FROM `{$table_name}` WHERE script_id=%s", $id )
                    );
                    if ( !empty( $script_ex ) ) {
                        foreach ( $script_ex as $s ) {
                            $ex_posts = json_decode( $s->ex_posts );
                            if ( !is_array( $ex_posts ) ) {
                                $ex_posts = array();
                            }
                        }
                    }
                }

                // Get all posts
                $args = array(
                    'public'   => true,
                    '_builtin' => false,
                );

                $output   = 'names'; // names or objects, note names is the default
                $operator = 'and'; // 'and' or 'or'

                $c_posttypes = get_post_types( $args, $output, $operator );
                $posttypes   = array( 'post' );
                foreach ( $c_posttypes as $cpdata ) {
                    $posttypes[] = $cpdata;
                }
                $posts = get_posts(
                    array(
                        'post_type'      => $posttypes,
                        'posts_per_page' => -1,
                        'numberposts'    => -1,
                        'orderby'        => 'title',
                        'order'          => 'ASC',
                    )
                );

                $json_output = array(
                    'selected' => array(),
                    'posts'    => array(),
                    'excluded' => array(),
                );

                if ( !empty( $posts ) ) {
                    foreach ( $posts as $pdata ) {
                        $nnr_hfcm_post_title = trim( $pdata->post_title );

                        if ( empty( $nnr_hfcm_post_title ) ) {
                            $nnr_hfcm_post_title = "(no title)";
                        }
                        if ( !empty( $ex_posts ) && in_array( $pdata->ID, $ex_posts ) ) {
                            $json_output['excluded'][] = $pdata->ID;
                        }

                        if ( !empty( $s_posts ) && in_array( $pdata->ID, $s_posts ) ) {
                            $json_output['selected'][] = $pdata->ID;
                        }

                        $json_output['posts'][] = array(
                            'text'  => sanitize_text_field( $nnr_hfcm_post_title ),
                            'value' => $pdata->ID,
                        );
                    }
                }

                echo wp_json_encode( $json_output );
                wp_die();
            }
        }

        /*
         * function for submenu "Update snippet" page
         */
        public static function hfcm_update()
        {

            add_action( 'wp_enqueue_scripts', 'hfcm_selectize_enqueue' );

            // check user capabilities
            $nnr_hfcm_can_edit = current_user_can( 'manage_options' );

            if ( !$nnr_hfcm_can_edit ) {
                echo 'Sorry, you do not have access to this page.';
                return false;
            }

            if ( empty( $_GET['id'] ) ) {
                die( 'Missing ID parameter.' );
            }
            $id = absint( $_GET['id'] );

            global $wpdb;
            $table_name = $wpdb->prefix . self::$nnr_hfcm_table;

            //selecting value to update
            $nnr_hfcm_snippets = $wpdb->get_results(
                $wpdb->prepare( "SELECT * FROM `{$table_name}` WHERE script_id=%s", $id )
            );
            foreach ( $nnr_hfcm_snippets as $s ) {
                $name             = $s->name;
                $snippet          = $s->snippet;
                $nnr_snippet_type = $s->snippet_type;
                $device_type      = $s->device_type;
                $location         = $s->location;
                $display_on       = $s->display_on;
                $status           = $s->status;
                $lp_count         = $s->lp_count;
                $snippet_desc     = $s->snippet_desc;

                if ( empty( $lp_count ) ) {
                    $lp_count = 5;
                }
                $s_pages  = json_decode( $s->s_pages );
                $ex_pages = json_decode( $s->ex_pages );
                $ex_posts = json_decode( $s->ex_posts );

                if ( !is_array( $s_pages ) ) {
                    $s_pages = array();
                }

                if ( !is_array( $ex_pages ) ) {
                    $ex_pages = array();
                }

                $s_posts = json_decode( $s->s_posts );
                if ( !is_array( $s_posts ) ) {
                    $s_posts = array();
                }

                $ex_posts = json_decode( $s->ex_posts );
                if ( !is_array( $ex_posts ) ) {
                    $ex_posts = array();
                }

                $s_custom_posts = json_decode( $s->s_custom_posts );
                if ( !is_array( $s_custom_posts ) ) {
                    $s_custom_posts = array();
                }

                $s_categories = json_decode( $s->s_categories );
                if ( !is_array( $s_categories ) ) {
                    $s_categories = array();
                }

                $s_tags = json_decode( $s->s_tags );
                if ( !is_array( $s_tags ) ) {
                    $s_tags = array();
                }

                $createdby        = esc_html( $s->created_by );
                $lastmodifiedby   = esc_html( $s->last_modified_by );
                $createdon        = esc_html( $s->created );
                $lastrevisiondate = esc_html( $s->last_revision_date );
            }

            // escape for html output
            $name             = esc_textarea( $name );
            $snippet          = esc_textarea( $snippet );
            $nnr_snippet_type = esc_textarea( $nnr_snippet_type );
            $device_type      = esc_html( $device_type );
            $location         = esc_html( $location );
            $display_on       = esc_html( $display_on );
            $status           = esc_html( $status );
            $lp_count         = esc_html( $lp_count );
            $i                = esc_html( $lp_count );
            $snippet_desc     = esc_textarea( $snippet_desc );

            // Notify hfcm-add-edit.php to make necesary changes for update
            $update = true;

            include_once plugin_dir_path( __FILE__ ) . 'includes/hfcm-add-edit.php';
        }

        /*
         * function to get list of all snippets
         */
        public static function hfcm_list()
        {

            global $wpdb;
            $table_name    = $wpdb->prefix . self::$nnr_hfcm_table;
            $activeclass   = '';
            $inactiveclass = '';
            $allclass      = 'current';
            $snippet_obj   = new Teamone_Hfcm_Snippets_List();

            $is_pro_version_active = self::is_hfcm_pro_active();

            if ( $is_pro_version_active ) {
                ?>
                <div class="notice hfcm-warning-notice notice-warning">
                    <?php _e(
                        'Please deactivate the free version of this plugin in order to avoid duplication of the snippets.
                    You can use our tools to import all the snippets from the free version of this plugin.', 'header-footer-code-manager'
                    ); ?>
                </div>
                <?php
            }

            if ( !empty( $_GET['import'] ) ) {
                if ( $_GET['import'] == 2 ) {
                    $message = "Header Footer Code Manager has successfully imported all snippets and set them as INACTIVE. Please review each snippet individually and ACTIVATE those that are needed for this site. Snippet types that are only available in the PRO version are skipped";
                } else {
                    $message = "Header Footer Code Manager has successfully imported all snippets and set them as INACTIVE. Please review each snippet individually and ACTIVATE those that are needed for this site.";
                }
                ?>
                <div id="hfcm-message" class="notice notice-success is-dismissible">
                    <p>
                        <?php _e( $message, 'header-footer-code-manager' ); ?>
                    </p>
                </div>
                <?php
            }
            if ( !empty( $_GET['script_status'] ) && in_array(
                    $_GET['script_status'], array( 'active', 'inactive' )
                )
            ) {
                $allclass = '';
                if ( 'active' === $_GET['script_status'] ) {
                    $activeclass = 'current';
                }
                if ( 'inactive' === $_GET['script_status'] ) {
                    $inactiveclass = 'current';
                }
            }
            ?>
            <div class="wrap">
                <h1><?php esc_html_e( 'Snippets', 'header-footer-code-manager' ) ?>
                    <a href="<?php echo admin_url( 'admin.php?page=team-one-hfcm-create' ) ?>" class="page-title-action">
                        <?php esc_html_e( 'Add New Snippet', 'header-footer-code-manager' ) ?>
                    </a>
                </h1>

                <form method="post">
                    <?php
                    $snippet_obj->prepare_items();
                    $snippet_obj->search_box( __( 'Search Snippets', 'header-footer-code-manager' ), 'search_id' );
                    $snippet_obj->display();
                    ?>
                </form>

            </div>
            <?php

            // Register the script
            wp_register_script( 'hfcm_toggle', plugins_url( 'js/toggle.js', __FILE__ ) );

            // Localize the script with new data
            $translation_array = array(
                'url'      => admin_url( 'admin.php' ),
                'security' => wp_create_nonce( 'hfcm-toggle-snippet' ),
            );
            wp_localize_script( 'hfcm_toggle', 'hfcm_ajax', $translation_array );

            // Enqueued script with localized data.
            wp_enqueue_script( 'hfcm_toggle' );
        }

        /*
         * function to get load tools page
         */
        public static function hfcm_tools()
        {
            global $wpdb;
            $nnr_hfcm_table_name = $wpdb->prefix . self::$nnr_hfcm_table;

            $nnr_hfcm_snippets = $wpdb->get_results( "SELECT * from `{$nnr_hfcm_table_name}`" );

            include_once plugin_dir_path( __FILE__ ) . 'includes/hfcm-tools.php';
        }

        /*
         * function to export snippets
         */
        public static function team_one_hfcm_export_snippets()
        {
            
            global $wpdb;
            $nnr_hfcm_table_name = $wpdb->prefix . self::$nnr_hfcm_table;

            if ( !empty( $_POST['nnr_hfcm_snippets'] ) && !empty( $_POST['action'] ) && ($_POST['action'] == "team_one_download") && check_admin_referer( 'hfcm-nonce' ) ) {
                $nnr_hfcm_snippets_comma_separated = "";
                foreach ( $_POST['nnr_hfcm_snippets'] as $nnr_hfcm_key => $nnr_hfcm_snippet ) {
                    $nnr_hfcm_snippet = str_replace( "snippet_", "", sanitize_text_field( $nnr_hfcm_snippet ) );
                    $nnr_hfcm_snippet = absint( $nnr_hfcm_snippet );
                    if ( !empty( $nnr_hfcm_snippet ) ) {
                        if ( empty( $nnr_hfcm_snippets_comma_separated ) ) {
                            $nnr_hfcm_snippets_comma_separated .= $nnr_hfcm_snippet;
                        } else {
                            $nnr_hfcm_snippets_comma_separated .= "," . $nnr_hfcm_snippet;
                        }
                    }
                }
                if ( !empty( $nnr_hfcm_snippets_comma_separated ) ) {
                    $nnr_hfcm_snippets = $wpdb->get_results(
                        "SELECT * FROM `{$nnr_hfcm_table_name}` WHERE script_id IN (" . $nnr_hfcm_snippets_comma_separated . ")"
                    );

                    if ( !empty( $nnr_hfcm_snippets ) ) {
                        $nnr_hfcm_export_snippets = array( "title" => "Header Footer Code Manager" );

                        foreach ( $nnr_hfcm_snippets as $nnr_hfcm_snippet_key => $nnr_hfcm_snippet_item ) {
                            unset( $nnr_hfcm_snippet_item->script_id );
                            $nnr_hfcm_export_snippets['snippets'][ $nnr_hfcm_snippet_key ] = $nnr_hfcm_snippet_item;
                        }
                        $file_name = 'team-one-hfcm-export-' . date( 'Y-m-d' ) . '.json';
                        header( "Content-Description: File Transfer" );
                        header( "Content-Disposition: attachment; filename={$file_name}" );
                        header( "Content-Type: application/json; charset=utf-8" );
                        echo json_encode( $nnr_hfcm_export_snippets, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
                    }
                }
                die;
            }
        }

        /*
         * function to import snippets
         */
        public static function team_one_hfcm_import_snippets()
        {   
            
            if ( !empty( $_FILES['team_one_nnr_hfcm_import_file']['tmp_name'] ) && check_admin_referer( 'hfcm-nonce' ) ) {
                if ( !empty( $_FILES['team_one_nnr_hfcm_import_file']['type'] ) && $_FILES['team_one_nnr_hfcm_import_file']['type'] != "application/json" ) {
                    ?>
                    <div class="notice hfcm-warning-notice notice-warning">
                        <?php _e( 'Please upload a valid import file', 'header-footer-code-manager' ); ?>
                    </div>
                    <?php
                    return;
                }

                global $wpdb;
                $nnr_hfcm_table_name = $wpdb->prefix . self::$nnr_hfcm_table;

                $nnr_hfcm_snippets_json = file_get_contents( $_FILES['team_one_nnr_hfcm_import_file']['tmp_name'] );
                $nnr_hfcm_snippets      = json_decode( $nnr_hfcm_snippets_json );

                if ( empty( $nnr_hfcm_snippets->title ) || (!empty( $nnr_hfcm_snippets->title ) && $nnr_hfcm_snippets->title != "Header Footer Code Manager") ) {
                    ?>
                    <div class="notice hfcm-warning-notice notice-warning">
                        <?php _e( 'Please upload a valid import file', 'header-footer-code-manager' ); ?>
                    </div>
                    <?php
                    return;
                }

                $nnr_non_script_snippets = 1;
                foreach ( $nnr_hfcm_snippets->snippets as $nnr_hfcm_key => $nnr_hfcm_snippet ) {
                    $nnr_hfcm_snippet = (array) $nnr_hfcm_snippet;
                    if ( !empty( $nnr_hfcm_snippet['snippet_type'] ) && !in_array(
                            $nnr_hfcm_snippet['snippet_type'], array( "html", "css", "js" )
                        )
                    ) {
                        $nnr_non_script_snippets = 2;
                        continue;
                    }
                    if ( !empty( $nnr_hfcm_snippet['location'] ) && !in_array(
                            $nnr_hfcm_snippet['location'], array( 'header', 'before_content', 'after_content',
                                                                  'footer' )
                        )
                    ) {
                        $nnr_non_script_snippets = 2;
                        continue;
                    }
                    $nnr_hfcm_sanitizes_snippet = [];
                    $nnr_hfcm_keys              = array(
                        "name", "snippet", "snippet_type", "device_type", "location",
                        "display_on", "lp_count", "s_pages", "ex_pages", "s_posts",
                        "ex_posts", "s_custom_posts", "s_categories", "s_tags", "status",
                        "created_by", "last_modified_by", "created", "last_revision_date","snippet_desc"
                    );
                    foreach ( $nnr_hfcm_snippet as $nnr_key => $nnr_item ) {
                        $nnr_key = sanitize_text_field( $nnr_key );
                        if ( in_array( $nnr_key, $nnr_hfcm_keys ) ) {
                            if ( $nnr_key == "lp_count" ) {
                                $nnr_item = absint( $nnr_item );
                            } elseif ( $nnr_key != "snippet" && $nnr_key != "snippet_desc") {
                                $nnr_item = sanitize_text_field( $nnr_item );
                            }
                            $nnr_hfcm_sanitizes_snippet[ $nnr_key ] = $nnr_item;
                        }
                    }
                    $nnr_hfcm_sanitizes_snippet['status'] = 'inactive';

                    $wpdb->insert(
                        $nnr_hfcm_table_name, $nnr_hfcm_sanitizes_snippet, array(
                            '%s',
                            '%s',
                            '%s',
                            '%s',
                            '%s',
                            '%s',
                            '%d',
                            '%s',
                            '%s',
                            '%s',
                            '%s',
                            '%s',
                            '%s',
                            '%s',
                            '%s',
                            '%s',
                            '%s',
                            '%s',
                            '%s',
                            '%s'
                        )
                    );
                }

                self::hfcm_redirect( admin_url( 'admin.php?page=team-one-hfcm-list&import=' . $nnr_non_script_snippets ) );
            }
        }

        /**
         * Check if TeamoneHFCM Pro is activated
         *
         * @return bool
         */
        public static function is_hfcm_pro_active()
        {
            if ( is_plugin_active( 'header-footer-code-manager-pro/header-footer-code-manager-pro.php' ) ) {
                return true;
            }

            return false;
        }

        public static function hfcm_get_categories()
        {
            $args       = array(
                'public'       => true,
                'hierarchical' => true
            );
            $output     = 'objects'; // or objects
            $operator   = 'and'; // 'and' or 'or'
            $taxonomies = get_taxonomies( $args, $output, $operator );

            $nnr_hfcm_categories = [];

            foreach ( $taxonomies as $taxonomy ) {
                $nnr_hfcm_taxonomy_categories = get_categories(
                    [
                        'taxonomy'   => $taxonomy->name,
                        'hide_empty' => 0
                    ]
                );
                $nnr_hfcm_taxonomy_categories = [
                    'name'  => $taxonomy->label,
                    'terms' => $nnr_hfcm_taxonomy_categories
                ];
                $nnr_hfcm_categories[]        = $nnr_hfcm_taxonomy_categories;
            }

            return $nnr_hfcm_categories;
        }

        public static function hfcm_get_tags()
        {
            $args       = array( 'hide_empty' => 0 );
            $args       = array(
                'public'       => true,
                'hierarchical' => false
            );
            $output     = 'objects'; // or objects
            $operator   = 'and'; // 'and' or 'or'
            $taxonomies = get_taxonomies( $args, $output, $operator );

            $nnr_hfcm_tags = [];

            foreach ( $taxonomies as $taxonomy ) {
                $nnr_hfcm_taxonomy_tags = get_tags(
                    [
                        'taxonomy'   => $taxonomy->name,
                        'hide_empty' => 0
                    ]
                );
                $nnr_hfcm_taxonomy_tags = [
                    'name'  => $taxonomy->label,
                    'terms' => $nnr_hfcm_taxonomy_tags
                ];
                $nnr_hfcm_tags[]        = $nnr_hfcm_taxonomy_tags;
            }

            return $nnr_hfcm_tags;
        }

        /**
         * Teamone
         * redis and log switch
        */
        public static function hfcm_redis_set(){

            global $wpdb;
            $table_name      = $wpdb->prefix . self::$hfcm_settable;
            $nnr_set_data = $wpdb->get_results(
                "SELECT * FROM `{$table_name}`"
            );
            $id = 0;
            $hfcm_domain_key = '';
            if ( $nnr_set_data ) {
                foreach ( $nnr_set_data as $s ) {
                    $id             = $s->id;
                    $hfcm_domain_key    = $s->hfcm_domain_key;
                }
                $update = true;
            }
            include_once plugin_dir_path( __FILE__ ) . 'includes/hfcm-redis-setting.php';

        }

        public static function hfcm_set_request(){
            
            global $wpdb;
            $table_name      = $wpdb->prefix . self::$hfcm_settable;
            // check user capabilities
            $nnr_hfcm_can_edit = current_user_can( 'manage_options' );

            if ( !$nnr_hfcm_can_edit ) {
                echo 'Sorry, you do not have access to this page.';
                return false;
            }
            
            $host = $_POST['host'];

            $port = $_POST['port'] ? $_POST['port'] : '6379';

            $password = $_POST['password'];

            $cache_key_salt = $_POST['cache_key_salt'] ? $_POST['cache_key_salt'] : 'wp_';

            $log_set = $_POST['debug_log'] ? 1 : 0;
            $id = absint( $_POST['id'] );
            $redis_domain_key = $_POST['redis_domain_key'];
            // var_dump($id);exit;
            if ( isset( $id )  && !empty($id)) {
                $wpdb->update(
                    $table_name, //table
                    // Data
                    array(
                        'hfcm_domain_key'=> $redis_domain_key,
                        'updatetime'     => current_time('timestamp'),
                    ),
                    // Where
                    array( 'id' => $id ),
                    // Data format
                    array(
                        '%s',
                        '%s',
                    ),
                    // Where format
                    array( '%s' )
                );
            } else {
                // Create
                $wpdb->insert(
                    $table_name, //table
                    array(
                        'hfcm_domain_key'=> $redis_domain_key,
                        'createtime'     => current_time('timestamp'),
                    ), array(
                        '%s',
                        '%s',
                    )
                );
                $id = $wpdb->insert_id;
            }

            if(empty($host)){
                
                self::hfcm_check_setting_push_notice(0);
                
            }else{
                update_option('hfcm_redis_host', $host);
                update_option('hfcm_redis_port', $port);
                update_option('hfcm_redis_password', $password);
                update_option('hfcm_redis_cache_key_salt', $cache_key_salt);
                update_option('hfcm_redis_domain_key', $cache_key_salt);

                
                //test connection Redis server
                try{
                    $redis = new Redis();
                    $redis->connect(get_option('hfcm_redis_host'),get_option('hfcm_redis_port'));

                    if(get_option('hfcm_redis_password')){
                        $redis->auth(get_option('hfcm_redis_password'));
                    }

                    self::hfcm_check_setting_push_notice(1);
                }catch(Exception $exception){

                    $log = new Teamone_Hfcm_Cache_File();
                    $log->flie_log($exception,'redis');
                    
                    self::hfcm_check_setting_push_notice(0);
                    
                }
            }
            if($log_set != get_option('hfcm_debug_log')){
                $update_res =  update_option('hfcm_debug_log', $log_set);
                if($update_res){
                    self::hfcm_check_setting_push_notice(1,'log');
                }else{
                    self::hfcm_check_setting_push_notice(0,'log');
                }
            }
            
            $back_bt = '<a href="'.admin_url( 'admin.php?page=team-one-hfcm-redis-set').'&id=' . $id.' "class="button button-primary button-large nnr-btnsave">Back To The Set Interface</a>';
            echo $back_bt;
            // self::hfcm_redirect( admin_url( 'admin.php?page=team-one-hfcm-redis-set'));
        }

        
        //setting message alert
        public static function hfcm_check_setting_push_notice($status_code=0,$type='redis'){
            
            switch($type){
                case 'redis':

                    $msg = "Redis Connection setting";
                    
                    break;

                case 'log':

                    $msg = "Error Log Setting";
                    break;
            }

            switch($status_code){
                case 0:

                    $msg .= " Fail set";
                    $notic =  '
                    <div id="hfcm-message" class="notice hfcm-warning-notice notice-warning">
                        <p>
                            '.$msg.'
                        </p>
                    </div>
                    ';

                    break;

                case 1:

                    $msg .= " Successfully set";
                    $notic =  '
                        <div id="hfcm-message" class="notice notice-success is-dismissible">
                            <p>
                            '.$msg.'
                            </p>
                        </div>
                    ';
                    break;

                default:
                    $msg .= " Not set";
            }
            echo $notic;
        }


         /**
         * Update Table.
         */
        public static function update_sql(){

            if(get_option(self::TEAMONEHFCMVERSION) == '1.0' ){
                return;
            }

            self::update_tables();
        }

        /**
         * update_tables
         */
        public static function update_tables() {
            global $wpdb;

            $wpdb->hide_errors();

            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            $table_name = self::$hfcm_settable;
            // Check if the table exists
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
            // If the table doesn't exist, create it
            if (!$table_exists) {
                $charset_collate = '';
                if ( $wpdb->has_cap( 'collation' ) ) {
                    $charset_collate = $wpdb->get_charset_collate();
                }
                $table_name      = $wpdb->prefix . self::$hfcm_settable;
                //$base_prefix prefix
                $tables = "CREATE TABLE `{$table_name}` (
                    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
                    `hfcm_domain_key` varchar(255) NOT NULL COMMENT '缓存域名',
                    `createtime` bigint(16) DEFAULT NULL COMMENT '创建时间',
                    `updatetime` bigint(16) DEFAULT NULL COMMENT '更新时间',
                    `deletetime` bigint(16) DEFAULT NULL COMMENT '删除时间',
                    PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci COMMENT='重定向插件集合表'";
                dbDelta( $tables );
                update_option( self::TEAMONEHFCMVERSION, '1.0' );
            };
        }

    }   

endif;
