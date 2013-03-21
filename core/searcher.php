<?php

class HolmesSearch {

    public function __construct() {

    }

    public function search($query = '') {
        echo '<pre>';
        $query = str_replace("'", "", $query);
        $query_terms = split("[ ,;\.\n\r\t]+", trim($query));
        
        $query_vector = $this->generate_query_vector($query_terms);
        $document_vectors = $this->generate_document_vectors($query_terms);
        
        echo '<br/><br/><h1>Document Vectors</h1></br/>';
        print_r($document_vectors);
        echo '</pre>';

        return $results;
    }

    private function generate_query_vector($query_terms) {
        $query_vector = array();
        $query_term_counts = array();

        foreach ($query_terms as $term) {
            if (array_key_exists($term, $query_term_counts))
                $query_term_counts[$term] += 1;
            else 
                $query_term_counts[$term] = 1;
        }

        foreach ($query_term_counts as $term => $count) {
            $query_vector[$term] = $count / count($query_terms);
        }

        return $query_vector;
    }

    private function generate_document_vectors($query_terms) {
        $occurances = $this->get_term_occurances($query_terms);

        return $occurances;
    }

    private function get_term_occurances($query_terms) {
        global $wpdb;

        $query_conditions = array();
        foreach ($query_terms as $terms) {
            $query_conditions[] = "t.term = '%s'";
        }

        $sql = "SELECT t.term, d.document_id, d.count FROM wp_holmes_term_index t
                LEFT JOIN wp_holmes_document_index d
                ON t.id = d.term_id
                WHERE " . implode(' OR ', $query_conditions);

        return $wpdb->get_results($wpdb->prepare($sql, $query_terms), ARRAY_A);
    }

}