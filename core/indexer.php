<?php

require_once(dirname(__FILE__) . '/../stemmer/porter_stemmer.php');

class HolmesIndexer {

    public function __construct() {
        $this->index();
    }

    public function index() {
        $stemmer = new Stemmer;
        $searchable_post_types = $this->get_searchable_post_types();

        $term_list = array();
        foreach ($searchable_post_types as $post_type => $fields) {
            $posts = get_posts(array('post_type' => $post_type, 'numberposts' => -1));

            foreach ($posts as $post) {
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
                    $stemmed_terms = array_merge($stemmed_terms, $stemmer->stem_list($this->replace_stopwords($value)));
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
            }
           

            // echo '<pre>';
            // print_r($stemmed_words);
            // echo '</pre>';
        }

        global $wpdb;
        $wpdb->query($wpdb->prepare("TRUNCATE TABLE " . $wpdb->prefix . "holmes_index"));
        foreach ($term_list as $term => $locations) {
            $wpdb->insert(
                $wpdb->prefix . "holmes_index",
                array(
                    'term' => $term,
                    'data' => serialize($locations)
                )
            );

        }

        // echo '<pre>';
        // print_r($term_list);
        // echo '</pre>';

        // echo '<pre>';
        // print_r($searchable_post_types);
        // echo '</pre>';
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

     // private function filter_searchable_types($val) {
    //     return in_array($val, get_option('holmes_searchable_fields'));
    // }

}