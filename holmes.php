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

        // for ($i = 0; $i < 5000; $i++) {
        //     wp_insert_post(
        //         array('post_title' => 'Page ' . $i, 
        //             'post_content' => "Now that we know who you are, I know who I am. I'm not a mistake! It all makes sense! In a comic, you know how you can tell who the arch-villain's going to be? He's the exact opposite of the hero. And most times they're friends, like you and me! I should've known way back when... You know why, David? Because of the kids. They called me Mr Glass.", 
        //             'post_status' => 'publish', 
        //             'post_type' => 'page',
        //             'post_author' => 1));
        // }
    }
}

register_activation_hook(__FILE__, array('Holmes', 'on_activate'));

new Holmes;
