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
        
        $ranked_documents = array();
        foreach ($document_vectors as $document_id => $terms) {
            $document_vector_score = array();
            $document_vector_sum = 0;
            foreach ($terms as $term => $tfidf) {
                $document_vector_sum += $query_vector[$term] * $tfidf;
            }

            $ranked_documents[$document_id] = $document_vector_sum;
        }

        echo '<br/><br/><h1>Document Vectors</h1></br/>';
        print_r($ranked_documents);
        echo '</pre>';

        return $ranked_documents;
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
            $query_vector[$term] = $count; // / count($query_terms); Readd later if necessary
        }

        // Toss in normalization function.
        $normalization_sum = 0;
        foreach ($query_vector as $term => $tfidf) {
            $normalization_sum += pow($tfidf, 2);
        }
        $normalization_value = sqrt($normalization_sum);

         foreach ($query_vector as $term => $tfidf) {
            $query_vector[$term] = $tfidf / $normalization_value;
        }

        return $query_vector;
    }

    private function generate_document_vectors($query_terms) {
        global $wpdb;
        $occurances = $this->get_term_occurances($query_terms);
        $term_document_counts = array();

        $num_total_documents = $wpdb->get_var("SELECT COUNT(DISTINCT(document_id)) FROM wp_holmes_document_index");

        $documents = array();
        foreach ($occurances as $occurance) {
            $documents[$occurance['document_id']][$occurance['term']] = $occurance['count'];
        }

        // // Default term counts to 0. Clean up, HACK
        // foreach ($query_terms as $term) {
        //     foreach ($documents as &$document) {
        //         if (!array_key_exists($term, $document)) {
        //             $document[$term] = 0;
        //         }
        //     }
        // }

        $term_to_documents = array();
        foreach ($occurances as $occurance) {
            $term_to_documents[$occurance['term']][] = $occurance['document_id'];
        }

        // Default term counts to 0. Clean up, HACK
        foreach ($query_terms as $term) {
            foreach ($documents as &$document) {
                if (!array_key_exists($term, $document)) {
                    $document[$term] = 0;
                }
            }

            if (!array_key_exists($term, $term_to_documents)) {
                $term_to_documents[$term] = array();
            }
        }

        $document_vectors = array();
        foreach ($documents as $document_id => $term_list) {
            $document_vector = array();
            foreach ($term_list as $term => $count) {
                $tf = $count;
                $idf = log($num_total_documents / (1 + count($term_to_documents[$term])));

                $document_vector[$term] = $tf * $idf;
            }

            $normalization_sum = 0;
            foreach ($document_vector as $term => $tfidf) {
                $normalization_sum += pow($tfidf, 2);
            }
            $normalization_value = sqrt($normalization_sum);

            foreach ($document_vector as $term => $tfidf) {
                $document_vector[$term] = $tfidf; // / $normalization_value; IF causes issues
            }

            

            $document_vectors[$document_id] = $document_vector;
        }

        return $document_vectors;
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