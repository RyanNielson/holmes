<?php

class HolmesAdmin {

    public function __construct() {
        add_action('admin_init', array($this, 'admin_init'));
        add_action('admin_menu', array($this, 'admin_menu'));

        add_action('admin_enqueue_scripts', array($this, 'enqueue_javascripts'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_stylesheets'));
    }

    public function enqueue_javascripts() {
        wp_enqueue_script('jquery');
        wp_enqueue_script('holmes_admin_script', plugins_url('holmes') . '/javascripts/admin.js', array('jquery'), '1.0.0');
    }

    public function enqueue_stylesheets() {
        wp_enqueue_style('holmes_admin_stylesheet', plugins_url('holmes') . '/stylesheets/admin.css', array(), '1.0.0');
    }

    public function admin_menu() {
        add_menu_page('Holmes Settings', 'Holmes Settings', 'manage_options', 'holmes_settings', array($this, 'settings_page'));
    }

    public function admin_init() {
        add_settings_section('holmes_settings_searchable_post_types_section', 'Searchable Types', '',  'holmes_settings');
        add_settings_field('holmes_searchable_post_types', 'Searchable Post Types', array($this, 'settings_searchable_post_types_callback'), 'holmes_settings', 'holmes_settings_searchable_post_types_section');
        register_setting('holmes_search_settings_options_group', 'holmes_searchable_post_types');

        add_settings_section('holmes_settings_searchable_fields_section', 'Searchable Fields', '',  'holmes_settings');

        $post_types = get_post_types(array(), 'objects');
        $banned_post_types = array('attachment', 'revision', 'nav_menu_item', 'acf');
        $searchable_types = array_keys(get_option('holmes_searchable_post_types'));
        foreach ($post_types as $post_type) {
            if(!in_array($post_type->name, $banned_post_types)) {
                add_settings_field('holmes_searchable_fields_' . $post_type->name, $post_type->label, array($this, 'settings_searchable_fields_callback'), 'holmes_settings', 'holmes_settings_searchable_fields_section', array($post_type->name, $post_type->label, !in_array($post_type->name, $searchable_types)));
                register_setting('holmes_search_settings_options_group', 'holmes_searchable_fields');
            }
        }
    }

    function settings_searchable_post_types_callback($args) {
        $post_types = get_post_types(array(), 'objects');
        $options = get_option('holmes_searchable_post_types');
        $banned_post_types = array('attachment', 'revision', 'nav_menu_item', 'acf');

        $html = '';
        foreach ($post_types as $post_type ) {
            if(!in_array($post_type->name, $banned_post_types)) {
                $html .= '<input name="holmes_searchable_post_types[' . $post_type->name . ']" id="holmes_searchable_post_types_' . $post_type->name . '" type="checkbox" value="1" ' . checked(1, $options[$post_type->name], false ) . ' />';
                $html .= '<label for="holmes_searchable_post_types_' . $post_type->name .'"> '  . $post_type->label . '</label><br/>'; 
            }
        }

        echo $html;
    }

    function settings_searchable_fields_callback($args) {
        $options = get_option('holmes_searchable_fields');
        $fields = array('title' => array('name' => 'title', 'label' => 'Title'), 'content' => array('name' => 'content', 'label' => 'Content'));

        $posts = get_posts(array('post_type' => $args[0], 'numberposts' => -1));

        foreach ($posts as $post) {
            
            $custom_fields = array_keys(get_post_custom($post->ID));
            $filtered_custom_fields = array_filter($custom_fields, array($this, 'filter_private_post_meta'));
            
            foreach ($filtered_custom_fields as $field) {
                $fields[$field] = array('name' => $field, 'label' => ucwords(str_replace('_', ' ', $field)));
            }
        }

        $html = '';
        foreach ($fields as $field) {
            $html .= $args[2] ? '<span class="hide-fields">' : '<span>';
            $html .= '<input name="holmes_searchable_fields[' . $args[0] . '][' . $field['name'] . '][enabled]" id="holmes_searchable_fields_' . $args[0] . '_' . $field['name'] . '_enabled" type="checkbox" value="1" ' . (isset($options) && isset($options[$args[0]]) ? checked(1, $options[$args[0]][$field['name']]['enabled'] , false) : '') . ' />';
            $html .= '<label for="holmes_searchable_fields_' . $args[0] . '_' . $field['name'] . '">' . $field['label'] . '</label>'; 
            $html .= '<input type="text" name="holmes_searchable_fields[' . $args[0] . '][' . $field['name'] . '][value]" id="holmes_searchable_fields_' . $args[0] . '_' . $field['name'] . '_value" value="' . (isset($options) && isset($options[$args[0]]) ? $options[$args[0]][$field['name']]['value'] : '') . '"/><br/>'; 
            $html .= '</span>';
        }

        echo $html;
    }

    private function filter_private_post_meta($val) {
        if (strpos($val, '_') === 0)
            return false;
        else 
            return true;
    }

    public function settings_page() {
        ?>

        <div class="wrap">
            <h2>Holmes Settings</h2>

            <form method="post" action="options.php"> 
                <?php settings_fields('holmes_search_settings_options_group'); ?>
                <?php //settings_fields('holmes_settings_searchable_fields_section'); ?>
                <?php do_settings_sections('holmes_settings'); ?>
                <?php submit_button(); ?>
            </form>
            <div class="indexer-container">
                <div id="indexer-progress-bar" class="progress progress-striped active">
                    <div class="bar"></div>
                </div>
                <a href="#" id="run-indexer" class="button button-primary button-indexer">Index</a>
            </div>

        </div>

        <?php
    }

    public function on_activate() {

    }

}