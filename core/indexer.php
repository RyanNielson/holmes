<?php

class HolmesIndexer {

    public function __construct() {
        $this->get_posts();
    }

    public function index() {

    }

    private function get_posts() {
        // echo '<pre>';
        // print_r(get_option('holmes_searchable_fields'));
        // print_r(get_option('holmes_searchable_post_types'));
        // echo '</pre>';

        $searchable_post_types = get_option('holmes_searchable_post_types');
        $searchable_fields = get_option('holmes_searchable_fields');

        echo '<pre>';
        // print_r($searchable_post_types);
        // print_r($searchable_fields);
        print_r(array_intersect_key($searchable_fields, $searchable_post_types));
        echo '</pre>';
        //print_r(array_intersect_key($searchable_post_types, $searchable_fields));


        // $filtered_searchable_fields = array();
        // foreach ($searchable_post_types as $post_type) {

        // }

    }

    private function filter_searchable_types($val) {
        return in_array($val, get_option('holmes_searchable_fields'));
    }

}