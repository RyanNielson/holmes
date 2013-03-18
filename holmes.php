<?php
/**
 * Plugin Name: Holmes
 * Author: Norex
 * Author URI: http://ryannielson.ca/
 * Version: 1.0.0
 * Description: Improved Wordpress search.
 */

require_once('core/searcher.php');
require_once('core/indexer.php');
require_once('core/admin.php');

class Holmes {

    public function __construct() {
       new HolmesAdmin;
       new HolmesIndexer;
    }

    public function on_activate() {
        global $wpdb;

        $table_name = $wpdb->prefix . "holmes_index";

        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            term text NOT NULL,
            data text NOT NULL,
            UNIQUE KEY id (id)
        );";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta($sql);
    }
}

register_activation_hook(__FILE__, array('Holmes', 'on_activate'));

new Holmes;
