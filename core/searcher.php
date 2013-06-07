<?php

class HolmesSearch {
    public function search($query = '', $page = '1', $per_page = '10') {
        $ranked_documents = $this->search_and_rank($query);

        return array(
            'results' => $this->paginate_documents($ranked_documents, $page, $per_page),
            'max_num_pages' => $this->get_max_num_pages($ranked_documents, $per_page)
        );
    }

    public function search_and_group($query = '', $page = '1', $per_page = '10') {
        $ranked_documents = $this->search_and_rank($query);

        $grouped_posts = array('all_post_types' => $ranked_documents);
        foreach ($ranked_documents as $post) {
            $grouped_posts[$post->post_type][] = $post;
        }

        return array(
            'results' => $this->paginate_grouped_documents($grouped_posts, $page, $per_page),
            'max_num_pages' => $this->get_max_num_pages($ranked_documents, $per_page) // May need to change.
        );
    }

    public function get_max_num_pages($documents, $per_page = '10') {
         return ceil(count($documents) / $per_page);
    }

    public function search_and_rank($query = '') {
        $query_terms = HolmesHelpers::stem_terms($query);

        $query_vector = $this->generate_query_vector($query_terms);
        $document_vectors = $this->generate_document_vectors($query_terms);
        $ranked_documents = $this->rank_documents($query_vector, $document_vectors);

        $posts = array();
        foreach ($ranked_documents as $post_id => $score) {
            $post = get_post($post_id);
            $post->search_score = $score;
            $posts[] = $post;
        }

        return $posts;
    }

    private function paginate_documents($documents, $page, $per_page) {
        return array_slice($documents, ($page - 1) * $per_page, $per_page, false); // Switched to true to fix issues with keys.
    }

    private function paginate_grouped_documents($documents, $page, $per_page) {
        $paginated_results = array();
        foreach ($documents as $post_type => $posts) {
            $paginated_results[$post_type] = array_slice($posts, ($page - 1) * $per_page, $per_page, false);
        }

        return $paginated_results;
    }

    private function rank_documents($query_vector, $document_vectors) {
        $ranked_documents = array();
        foreach ($document_vectors as $document_id => $terms) {
            $document_vector_sum = 0;
            foreach ($terms as $term => $tfidf)
                $document_vector_sum += $query_vector[$term] * $tfidf;

            $ranked_documents[$document_id] = $document_vector_sum;
        }

        arsort($ranked_documents);

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

        foreach ($query_term_counts as $term => $count)
            $query_vector[$term] = $count; // / count($query_terms); Read later if necessary

        $query_vector = $this->normalize_vector($query_vector);

        return $query_vector;
    }

    private function normalize_vector($vector) {
        $normalized_vector = array();
        $normalization_sum = 0;

        foreach ($vector as $term => $tfidf)
            $normalization_sum += pow($tfidf, 2);

        $normalization_value = sqrt($normalization_sum);

        foreach ($vector as $term => $tfidf)
            $normalized_vector[$term] = $tfidf; //Remove normalization / $normalization_value;

        return $normalized_vector;
    }

    private function generate_document_vectors($query_terms) {
        $occurances = $this->get_term_occurances($query_terms);
        
        $documents = array();
        $term_to_documents = array();
        foreach ($occurances as $occurance) {
            $documents[$occurance['document_id']][] = $occurance;
            if (!$term_to_documents[$occurance['term']] || !in_array($occurance['document_id'], $term_to_documents[$occurance['term']]))
                $term_to_documents[$occurance['term']][] = $occurance['document_id'];
        }

        $improved_documents = array();
        foreach ($documents as $doc_id => $document) {
            $combined_terms = array();
            foreach ($document as $term_data) {
                $term = $term_data['term'];
                $count = $term_data['count'];
                $weight = $term_data['weight'];
                $document_id = $term_data['document_id'];

                if (array_key_exists($term, $combined_terms)) {
                    $combined_terms[$term] = $combined_terms[$term] + ($weight / 50 * $count);
                }
                else {
                    $combined_terms[$term] = $weight / 50 * $count;
                }
            }

            $improved_documents[$doc_id] = $combined_terms;
        }

        foreach ($query_terms as $term) {
            foreach ($improved_documents as &$improved_document) {
                if (!array_key_exists($term, $improved_document))
                    $improved_document[$term] = 0;
            }

            if (!array_key_exists($term, $term_to_documents))
                $term_to_documents[$term] = array();
        }

        return $this->calculate_document_vectors($improved_documents, $term_to_documents);
    }

    private function calculate_document_vectors($documents, $term_to_documents) {
        global $wpdb;
        $num_total_documents = $wpdb->get_var("SELECT COUNT(DISTINCT(document_id)) FROM wp_holmes_document_index");

        $document_vectors = array();

        // echo '<pre>';
        // print_r($documents);
        // echo '</pre>';

        foreach ($documents as $document_id => $term_list) {
            $document_vector = array();
            foreach ($term_list as $term => $count)
                $document_vector[$term] = $this->calculate_tdidf($count, $num_total_documents, count($term_to_documents[$term]));

            $document_vectors[$document_id] = $this->normalize_vector($document_vector);
        }

        return $document_vectors;
    }

    private function calculate_tdidf($num_in_document, $num_documents, $documents_containing_term) {
        $tf = $num_in_document; // Add weight here.
        $idf = log($num_documents / (1 + $documents_containing_term));

        return $tf * $idf;
    }

    private function get_term_occurances($query_terms) {
        global $wpdb;

        $query_conditions = array();
        foreach ($query_terms as $terms)
            $query_conditions[] = "t.term = '%s'";

        $sql = "SELECT t.term, d.document_id, d.count, d.weight FROM wp_holmes_term_index t
                LEFT JOIN wp_holmes_document_index d
                ON t.id = d.term_id
                WHERE " . implode(' OR ', $query_conditions);

        return $wpdb->get_results($wpdb->prepare($sql, $query_terms), ARRAY_A);
    }



    // OLD METHOD
    // private function generate_document_vectors($query_terms) {
    //     $occurances = $this->get_term_occurances($query_terms);
        
    //     $documents = array();
    //     $term_to_documents = array();
    //     foreach ($occurances as $occurance) { // Change for multiple counts.
    //         $documents[$occurance['document_id']][] = $occurance;
    //         if (!in_array($occurance['document_id'], $term_to_documents[$occurance['term']]))
    //             $term_to_documents[$occurance['term']][] = $occurance['document_id'];
    //         // $documents[$occurance['document_id']][$occurance['term']] = $occurance['count'];
    //         // $term_to_documents[$occurance['term']][] = $occurance['document_id'];
    //     }

    //     // echo '<pre>';
    //     // print_r($occurances);
    //     // print_r($documents);
        
    //     //echo '</pre>';

    //     // New additions for weighting.

    //     $improved_documents = array();
    //     foreach ($documents as $doc_id => $document) {
    //         $combined_terms = array();
    //         foreach ($document as $term_data) {
    //             $term = $term_data['term'];
    //             $count = $term_data['count'];
    //             $weight = $term_data['weight'];
    //             $document_id = $term_data['document_id'];

    //             if (array_key_exists($term, $combined_terms)) {
    //                 //$combined_terms[$term]['count'] = $combined_terms[$term]['count'] + ($weight / 50 * $count);
    //                 $combined_terms[$term] = $combined_terms[$term] + ($weight / 50 * $count);
    //             }
    //             else {
    //                 $combined_terms[$term] = $weight / 50 * $count;
    //                 // $combined_terms[$term] = array(
    //                 //     'term' => $term,
    //                 //     'document_id' => $document_id,
    //                 //     'count' => $weight / 50 * $count
    //                 // );
    //             }
    //         }

    //         $improved_documents[$doc_id] = $combined_terms;
    //     }

       

    //     // // Default term counts to 0. Clean up, HACK
    //     // foreach ($query_terms as $term) {
    //     //     foreach ($documents as &$document) {
    //     //         if (!array_key_exists($term, $document))
    //     //             $document[$term] = 0;
    //     //     }

    //     //     if (!array_key_exists($term, $term_to_documents))
    //     //         $term_to_documents[$term] = array();
    //     // }

    //     foreach ($query_terms as $term) {
    //         foreach ($improved_documents as &$improved_document) {
    //             if (!array_key_exists($term, $improved_document))
    //                 $improved_document[$term] = 0;
    //         }

    //         if (!array_key_exists($term, $term_to_documents))
    //             $term_to_documents[$term] = array();
    //     }

    //     // print_r($term_to_documents);
    //     // print_r($improved_documents);
    //     // echo '</pre>';

    //     // FIX ISSUES HERE to weight properly.
       
    //     // END FIX

    //     return $this->calculate_document_vectors($improved_documents, $term_to_documents);
    //     // return $this->calculate_document_vectors($documents, $term_to_documents);
    // }

}