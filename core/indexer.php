<?php

class HolmesIndexer {

    public function __construct() {
        $this->NUM_INDEXED_PER_REQUEST = 100;
    }

    private function stem_terms($post, $fields) {
        $stemmed_terms = array();

        foreach ($fields as $field => $attributes) {
            $value = '';
            if ($field === 'title')
                $value = $post->post_title;
            else if ($field === 'content')
                $value = $post->post_content;
            else
                $value = get_post_meta($post->ID, $field, true);

            // Fix issue with empty string causing indexing to fail.
            if (!$value)
                $value = " ";

            $stemmed_terms = array_merge($stemmed_terms, HolmesHelpers::stem_terms($value));
        }

        return $this->stem_terms_and_count($stemmed_terms);
    }

    private function stem_terms_and_count($stemmed_terms) {
        $stemmed_terms_with_count = array();

        foreach ($stemmed_terms as $term) {
            if (isset($stemmed_terms_with_count[$term]))
                $stemmed_terms_with_count[$term] += 1;
            else
                $stemmed_terms_with_count[$term] = 1;
        }

        return $stemmed_terms_with_count;
    }

    private function check_progress($num_documents_looped_through, $total_posts_count, $num_index_upper_limit, $term_list) {
        if ($num_documents_looped_through >= $total_posts_count) {
            $result = $this->dump_to_db($term_list);
            if ($result === false)
                return array('result' => 'error', 'message' => 'Error adding to index.');
            else
                return array('result' => 'complete', 'looped_through' => $num_documents_looped_through, 'total' => $total_posts_count);
        }
        else if ($num_documents_looped_through >= $num_index_upper_limit) {
            $result = $this->dump_to_db($term_list);
            if ($result === false)
                return array('result' => 'error', 'message' => 'Error adding to index.');
            else
                return array('result' => 'more', 'looped_through' => $num_documents_looped_through, 'total' => $total_posts_count);
        }
        else {
            return false;
        }
    }

    private function get_total_posts_count($searchable_post_types) {
        $total_posts_count = 0;
        foreach ($searchable_post_types as $post_type => $fields) {
            $post_count = wp_count_posts($post_type);
            if (isset($post_count) && isset($post_count->publish)) 
                $total_posts_count += $post_count->publish;
        }

        return $total_posts_count;
    }

    public function index($index_offset = 0) {
        global $wpdb;

        $num_indexed_per_request = 200;
        $num_documents_looped_through = 0;
        $num_index_upper_limit = $index_offset + $num_indexed_per_request;
        $term_list = array();
       
        $searchable_post_types = $this->get_searchable_post_types();
        $total_posts_count = $this->get_total_posts_count($searchable_post_types);
        
        if ($num_index_upper_limit > $total_posts_count)
            $num_index_upper_limit = $total_posts_count;

        foreach ($searchable_post_types as $post_type => $fields) {
            $posts = get_posts(array('post_type' => $post_type, 'numberposts' => -1, 'orderby' => 'ID', 'order' => 'ASC'));

            foreach ($posts as $post) {
                $num_documents_looped_through += 1;

                if ($num_documents_looped_through > $index_offset) {
                    $stemmed_terms_with_count = $this->stem_terms($post, $fields);

                    foreach ($stemmed_terms_with_count as $term => $count) {
                        $term_list[$term][] = array('doc_id' => $post->ID, 'count' => $count);
                    }

                    $result = $this->check_progress($num_documents_looped_through, $total_posts_count, $num_index_upper_limit, $term_list);
                    if ($result !== false)
                        return $result;
                }
            }
            
        }

        return array('result' => 'error');
    }

    private function update_terms_index($term_list) {
        global $wpdb;

        $terms_to_ids = array();

        $terms = $wpdb->get_results(
            $wpdb->prepare("SELECT term, id FROM " . $wpdb->prefix . "holmes_term_index"), 
            ARRAY_A
        );

        foreach ($terms as $term)
            $terms_to_ids[$term['term']] = $term['id'];

        foreach ($term_list as $term => $documents) {
            if (!array_key_exists($term, $terms_to_ids)) {
                $result = $wpdb->insert(
                    $wpdb->prefix . "holmes_term_index",
                    array('term' => $term)
                );
               
                if ($result !== false)
                    $terms_to_ids[$term] = $wpdb->insert_id;
            }
        }

        return $terms_to_ids;
    }

    private function dump_to_db($term_list) {
        global $wpdb;

        $terms_to_ids = $this->update_terms_index($term_list);
        
        return $this->add_to_document_index($term_list, $terms_to_ids);
    }

    private function add_to_document_index($term_list, $terms_to_ids) {
        global $wpdb;

        $sql = "INSERT INTO " . $wpdb->prefix . "holmes_document_index (term_id, document_id, count) VALUES ";
        $values = array();
        $place_holders = array();

        foreach ($term_list as $term => $documents) {
            foreach ($documents as $document) {
                array_push($values, $terms_to_ids[$term], $document['doc_id'], $document['count']);
                $place_holders[] = "('%d', '%d' ,'%d')";
            }
        }

        $sql .= implode(', ', $place_holders);

        return $wpdb->query($wpdb->prepare("$sql ", $values));
    }

    private function get_searchable_post_types() {
        $searchable_post_types = get_option('holmes_searchable_post_types');
        $searchable_fields = get_option('holmes_searchable_fields');
        $intersection = array_intersect_key($searchable_fields, $searchable_post_types);

        $searchable_fields = array();
        foreach ($intersection as $post_type => $fields) {
            foreach ($fields as $field => $setting) {
                if ($setting['enabled'] === '1')
                    $searchable_fields[$post_type][$field] = $setting;
            }
        } 

        return $searchable_fields;
    }

    public function ajax_run_indexer() {
        $index_offset = get_option('holmes_indexer_offset');
        $index_offset = (isset($index_offset) && $index_offset) ? $index_offset : 0;
        update_option('holmes_indexer_offset', $index_offset + 200);

        $indexer = new HolmesIndexer;
        $result = $indexer->index($index_offset);

        echo json_encode($result);
        exit();
    }

    public function ajax_start_indexer() {
        global $wpdb;

        $wpdb->query($wpdb->prepare("TRUNCATE TABLE " . $wpdb->prefix . "holmes_term_index"));
        $wpdb->query($wpdb->prepare("TRUNCATE TABLE " . $wpdb->prefix . "holmes_document_index"));

        update_option('holmes_indexer_offset', 200);

        $indexer = new HolmesIndexer;
        $result = $indexer->index();

        echo json_encode($result);
        exit();
    }

    public function ajax_initiate_indexer() {
        update_option('holmes_indexer_progress', 0);
        exit();
    }
}

add_action('wp_ajax_holmes_start_indexer', array('HolmesIndexer', 'ajax_start_indexer'));
add_action('wp_ajax_nopriv_holmes_start_indexer', array('HolmesIndexer', 'ajax_start_indexer'));

add_action('wp_ajax_holmes_run_indexer', array('HolmesIndexer', 'ajax_run_indexer'));
add_action('wp_ajax_nopriv_holmes_run_indexer', array('HolmesIndexer', 'ajax_run_indexer'));

add_action('wp_ajax_holmes_initiate_indexer', array('HolmesIndexer', 'ajax_initiate_indexer'));
add_action('wp_ajax_nopriv_holmes_initiate_indexer', array('HolmesIndexer', 'ajax_initiate_indexer'));