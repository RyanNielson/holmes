<?php

require_once(dirname(__FILE__) . '/../stemmer/porter_stemmer.php');

class HolmesIndexer {

    public function __construct() {
        $this->NUM_INDEXED_PER_REQUEST = 100;
        $this->stemmer = new Stemmer;
    }

    public function index($index_offset = 0) {
        global $wpdb;

        $num_indexed_per_request = $this->NUM_INDEXED_PER_REQUEST;
        $num_documents_looped_through = 0;
        $current_document_num = 0;
        $num_index_upper_limit = $index_offset + $num_indexed_per_request;
        $term_list = array();
       
        $searchable_post_types = $this->get_searchable_post_types();

        $total_posts_count = 0;
        foreach ($searchable_post_types as $post_type => $fields) {
            $post_count = wp_count_posts($post_type);
            if (isset($post_count) && isset($post_count->publish)) 
                $total_posts_count += $post_count->publish;
        }

        $docs_looped = array();

        if ($num_index_upper_limit > $total_posts_count)
            $num_index_upper_limit = $total_posts_count;

        foreach ($searchable_post_types as $post_type => $fields) {
            $posts = get_posts(array('post_type' => $post_type, 'numberposts' => -1, 'orderby' => 'ID', 'order' => 'ASC'));

            foreach ($posts as $post) {
                $num_documents_looped_through += 1;

                if ($num_documents_looped_through > $index_offset) {
                    $stemmed_terms = array();
                    $stemmed_terms_with_count = array();

                    foreach ($fields as $field => $attributes) {
                        if ($field === 'title') {
                            $value = $post->post_title;
                        }   
                        else if ($field === 'content') {
                            $value = $post->post_content;
                        }
                        else {

                        }

                        $value = str_replace("'", "", $value);  // Remove apostrophes to fix stemming and stop word replacement.
                        $stemmed_terms = array_merge($stemmed_terms, $this->stemmer->stem_list($this->replace_stopwords($value)));
                    }

                    foreach ($stemmed_terms as $term) {
                        if (isset($stemmed_terms_with_count[$term]))
                            $stemmed_terms_with_count[$term] += 1;
                        else
                            $stemmed_terms_with_count[$term] = 1;
                    }

                    foreach ($stemmed_terms_with_count as $term => $count) {
                        $term_list[$term][] = array('doc_id' => $post->ID, 'count' => $count);
                    }

                    if ($num_documents_looped_through >= $total_posts_count) {
                        $result = $this->dump_to_db($term_list);
                        if ($result === false)
                            return array('result' => 'error', 'message' => 'Error adding to index.');
                        else
                            return array('result' => 'complete', 'looped_through' => $num_documents_looped_through, 'total' => $total_posts_count, 'upper_limit' => $num_index_upper_limit);
                    }
                    else if ($num_documents_looped_through >= $num_index_upper_limit) {
                        $result = $this->dump_to_db($term_list);
                        if ($result === false)
                            return array('result' => 'error', 'message' => 'Error adding to index.');
                        else
                            return array('result' => 'more', 'looped_through' => $num_documents_looped_through, 'total' => $total_posts_count, 'upper_limit' => $num_index_upper_limit);
                    }
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

    private function replace_stopwords($input) {
        $stop_words = array("a", "about", "above", "above", "across", "after", "afterwards", "again", "against", "all", "almost", "alone", "along", "already", "also", "although", "always", "am", "among", "amongst", "amoungst", "amount",  "an", "and", "another", "any","anyhow","anyone","anything","anyway", "anywhere", "are", "around", "as",  "at", "back","be","became", "because","become","becomes", "becoming", "been", "before", "beforehand", "behind", "being", "below", "beside", "besides", "between", "beyond", "bill", "both", "bottom", "but", "by", "call", "can", "cannot", "cant", "co", "con", "could", "couldnt", "cry", "de", "describe", "detail", "do", "done", "down", "due", "during", "each", "eg", "eight", "either", "eleven","else", "elsewhere", "empty", "enough", "etc", "even", "ever", "every", "everyone", "everything", "everywhere", "except", "few", "fifteen", "fify", "fill", "find", "fire", "first", "five", "for", "former", "formerly", "forty", "found", "four", "from", "front", "full", "further", "get", "give", "go", "had", "has", "hasnt", "have", "he", "hence", "her", "here", "hereafter", "hereby", "herein", "hereupon", "hers", "herself", "him", "himself", "his", "how", "however", "hundred", "ie", "if", "in", "inc", "indeed", "interest", "into", "is", "it", "its", "itself", "keep", "last", "latter", "latterly", "least", "less", "ltd", "made", "many", "may", "me", "meanwhile", "might", "mill", "mine", "more", "moreover", "most", "mostly", "move", "much", "must", "my", "myself", "name", "namely", "neither", "never", "nevertheless", "next", "nine", "no", "nobody", "none", "noone", "nor", "not", "nothing", "now", "nowhere", "of", "off", "often", "on", "once", "one", "only", "onto", "or", "other", "others", "otherwise", "our", "ours", "ourselves", "out", "over", "own","part", "per", "perhaps", "please", "put", "rather", "re", "same", "see", "seem", "seemed", "seeming", "seems", "serious", "several", "she", "should", "show", "side", "since", "sincere", "six", "sixty", "so", "some", "somehow", "someone", "something", "sometime", "sometimes", "somewhere", "still", "such", "system", "take", "ten", "than", "that", "the", "their", "them", "themselves", "then", "thence", "there", "thereafter", "thereby", "therefore", "therein", "thereupon", "these", "they", "thickv", "thin", "third", "this", "those", "though", "three", "through", "throughout", "thru", "thus", "to", "together", "too", "top", "toward", "towards", "twelve", "twenty", "two", "un", "under", "until", "up", "upon", "us", "very", "via", "was", "we", "well", "were", "what", "whatever", "when", "whence", "whenever", "where", "whereafter", "whereas", "whereby", "wherein", "whereupon", "wherever", "whether", "which", "while", "whither", "who", "whoever", "whole", "whom", "whose", "why", "will", "with", "within", "without", "would", "yet", "you", "your", "yours", "yourself", "yourselves", "the");

        return preg_replace('/\b(' . implode('|', $stop_words).')\b/i', '', $input);
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
        update_option('holmes_indexer_offset', $index_offset + 100);

        $indexer = new HolmesIndexer;
        $result = $indexer->index($index_offset);

        echo json_encode($result);
        exit();
    }

    public function ajax_start_indexer() {
        global $wpdb;

        $wpdb->query($wpdb->prepare("TRUNCATE TABLE " . $wpdb->prefix . "holmes_term_index"));
        $wpdb->query($wpdb->prepare("TRUNCATE TABLE " . $wpdb->prefix . "holmes_document_index"));

        update_option('holmes_indexer_offset', 100);

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