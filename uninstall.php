<?php
// If uninstall is not called from WordPress, exit
if (! defined('WP_UNINSTALL_PLUGIN') ) {
    exit;
}

//卸载后删除对应配置项
$option_name = 'team_one_hfcm_db_version';
delete_option('hfcm_redis_host');
delete_option('hfcm_redis_port');
delete_option('hfcm_redis_password');
delete_option('hfcm_debug_log');
delete_option('team_one_hfcm_activation_date');
delete_option('team_one_hfcm_db_version');

// Drop a custom db table
global $wpdb;
$table_name = $wpdb->prefix . 'team_one_hfcm_scripts';

$wpdb->query("DROP TABLE IF EXISTS $table_name");
