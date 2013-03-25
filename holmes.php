<?php
/**
 * Plugin Name: Holmes
 * Author: Norex
 * Author URI: http://ryannielson.ca/
 * Version: 1.0.0
 * Description: Improved Wordpress search.
 */

require_once('core/helpers.php');
require_once('core/searcher.php');
require_once('core/indexer.php');
require_once('core/admin.php');

class Holmes {

    public function __construct() {
       new HolmesAdmin;
    }

    public function on_activate() {
        global $wpdb;

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        $terms_index_table_name = $wpdb->prefix . "holmes_term_index";
        $sql = "CREATE TABLE $terms_index_table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            term varchar(255) NOT NULL,

            PRIMARY KEY (`id`),
            UNIQUE (`term`)
        );";
        dbDelta($sql);

        $document_index_table_name = $wpdb->prefix . "holmes_document_index";
        $sql = "CREATE TABLE $document_index_table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            term_id bigint(20) unsigned NOT NULL,
            document_id bigint(20) unsigned NOT NULL,
            count bigint(20) unsigned NOT NULL,

            PRIMARY KEY (`id`),
            FOREIGN KEY (`term_id`) REFERENCES Persons(`id`)
        );";
        dbDelta($sql);
    }
}

register_activation_hook(__FILE__, array('Holmes', 'on_activate'));

new Holmes;

add_filter('the_posts', 'test_the_posts_filter', 10, 2);
function test_the_posts_filter($posts, $query) {
    if (is_search() && $query->is_main_query() && !is_admin()) {
        $search_query = $query->query['s'];
        $search = new HolmesSearch;

        $page = is_paged() ? $query->query['paged'] : 1;
        
        $search_results = $search->search($search_query, $page, get_query_var('posts_per_page'));
        $query->max_num_pages = $search_results['max_num_pages'];

        $posts = $search_results['results'];
    }
    
    return $posts;
}