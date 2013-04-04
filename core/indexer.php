<?php

class HolmesIndexer {

    public function __construct() {
        $this->NUM_INDEXED_PER_REQUEST = 100;
    }

    private function stem_terms($post, $fields) {
        $stemmed_terms = array();

        foreach ($fields as $field => $attributes) {
           
            $value = '';
            if ($field === 'title') {
                $value = $post->post_title;

            }
            else if ($field === 'content') {
                $value = $post->post_content;
            }
            else {
                $value = get_post_meta($post->ID, $field, true);
            }

            $weight = $attributes['weight'];

            // Fix issue with empty string causing indexing to fail.
            if (!$value)
                $value = " ";
            if (!$weight)
                $weight = 50;

            $value = strip_tags($value);

            $terms = HolmesHelpers::stem_terms($value);
            foreach ($terms as $term) {
                $stemmed_terms[] = array('term' => $term, 'weight' => $weight, 'field' => $field);
            }
        }

        return $this->stem_terms_and_count($stemmed_terms);
    }

    private function stem_terms_and_count($stemmed_terms) {
        $stemmed_terms_with_count = array();

        foreach ($stemmed_terms as $term_data) {
            $exists = false;
            foreach ($stemmed_terms_with_count as &$term_and_count_data) {
                if ($term_data['term'] === $term_and_count_data['term'] && $term_data['field'] === $term_and_count_data['field']) {
                    $exists = true;
                    $term_and_count_data['count'] += 1;
                    break;
                }
            }

            if (!$exists)
                $stemmed_terms_with_count[] = array_merge($term_data, array('count' => 1));
        }

        return $stemmed_terms_with_count;
    }

    private function check_progress($num_documents_looped_through, $total_posts_count, $num_index_upper_limit, $stemmed_terms_with_count, $term_list) {

        if ($num_documents_looped_through >= $total_posts_count) {
            $result = $this->dump_to_db($stemmed_terms_with_count, $term_list);
            if ($result === false)
                return array('result' => 'error', 'message' => 'Error adding to index 1.');
            else
                return array('result' => 'complete', 'looped_through' => $num_documents_looped_through, 'total' => $total_posts_count);
        }
        else if ($num_documents_looped_through >= $num_index_upper_limit) {
            $result = $this->dump_to_db($stemmed_terms_with_count, $term_list);
            if ($result === false)
                return array('result' => 'error', 'message' => 'Error adding to index 2.');
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
        $stemmed_terms_with_count = array();
        $stemmed_terms_with_count_and_doc = array();

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

                    foreach ($stemmed_terms_with_count as $term_data) {
                        $stemmed_terms_with_count_and_doc[] = array_merge($term_data, array('doc_id' => $post->ID)); // $term_data['doc_id'] = $post->ID;
                        $term_list[$term_data['term']] = $term_data['term'];
                    }

                    $result = $this->check_progress($num_documents_looped_through, $total_posts_count, $num_index_upper_limit, $stemmed_terms_with_count_and_doc, $term_list);
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

    private function dump_to_db($stemmed_terms_with_count, $term_list) {
        global $wpdb;

        $terms_to_ids = $this->update_terms_index($term_list);
        
        return $this->add_to_document_index($stemmed_terms_with_count, $terms_to_ids);
    }

    private function add_to_document_index($stemmed_terms_with_count, $terms_to_ids) {
        global $wpdb;

        $sql = "INSERT INTO " . $wpdb->prefix . "holmes_document_index (term_id, document_id, count, weight) VALUES ";
        $values = array();
        $place_holders = array();

        foreach ($stemmed_terms_with_count as $term_data) {
            array_push($values, $terms_to_ids[$term_data['term']], $term_data['doc_id'], $term_data['count'], $term_data['weight']);
            $place_holders[] = "('%d', '%d', '%d', '%d')";
        }

        if (!empty($values)) {
            $sql .= implode(', ', $place_holders);
            return $wpdb->query($wpdb->prepare("$sql ", $values));
        }

        return true;
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
        if (current_user_can('manage_options')) {
            $index_offset = get_option('holmes_indexer_offset');
            $index_offset = (isset($index_offset) && $index_offset) ? $index_offset : 0;
            update_option('holmes_indexer_offset', $index_offset + 200);

            $indexer = new HolmesIndexer;
            $result = $indexer->index($index_offset);

            echo json_encode($result);
            exit();
        }
    }

    public function ajax_start_indexer() {
        if (current_user_can('manage_options')) {
            global $wpdb;

            $wpdb->query($wpdb->prepare("TRUNCATE TABLE " . $wpdb->prefix . "holmes_term_index"));
            $wpdb->query($wpdb->prepare("TRUNCATE TABLE " . $wpdb->prefix . "holmes_document_index"));

            update_option('holmes_indexer_offset', 200);

            $indexer = new HolmesIndexer;
            $result = $indexer->index();

            echo json_encode($result);
            exit();
        }
    }
}

add_action('wp_ajax_holmes_start_indexer', array('HolmesIndexer', 'ajax_start_indexer'));
add_action('wp_ajax_holmes_run_indexer', array('HolmesIndexer', 'ajax_run_indexer'));