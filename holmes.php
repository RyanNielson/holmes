<?php
/**
 * Plugin Name: Holmes
 * Author: Norex
 * Author URI: http://ryannielson.ca/
 * Version: 1.0.0
 * Description: Improved Wordpress search.
 */

class Holmes {

    public function __construct() {
        add_action('admin_init', array($this, 'admin_init'));
        add_action('admin_menu', array($this, 'admin_menu'));

        // add_action( 'admin_menu', array( $this, 'add_options_page' ) );
        // add_action( 'plugins_loaded', array( $this, 'add_filters' ) );
        // add_action( 'admin_init', array( $this, 'init' ) );
        // register_uninstall_hook( __FILE__, array( $this, 'delete_options' ) );
    }

    public function admin_menu() {
        add_menu_page('Holmes Settings', 'Holmes Settings', 'manage_options', 'holmes_settings', array($this, 'settings_page'));
    }

    public function admin_init() {
        add_settings_section('holmes_settings_searchable_post_types_section', 'Searchable Types', '',  'holmes_settings');
        add_settings_field('holmes_searchable_post_types', 'Searchable Post Types', array($this, 'settings_searchable_post_types_callback'), 'holmes_settings', 'holmes_settings_searchable_post_types_section');
        register_setting('holmes_settings_searchable_post_types_section', 'holmes_searchable_post_types');


        add_settings_section('holmes_settings_searchable_fields_section', 'Searchable Fields', '',  'holmes_settings');

        $post_types = get_post_types(array(), 'objects');
        $searchable_types = array_keys(get_option('holmes_searchable_post_types'));
        foreach ($post_types as $post_type) {
            if (in_array($post_type->name, $searchable_types)) {
                add_settings_field('holmes_searchable_fields_' . $post_type->name, $post_type->label, array($this, 'settings_searchable_fields_callback'), 'holmes_settings', 'holmes_settings_searchable_fields_section', array($post_type->name, $post_type->label));
                register_setting('holmes_settings_searchable_fields_section', 'holmes_searchable_fields');
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
        $default_fields = array('title' => array('name' => 'title', 'label' => 'Title'), 'content' => array('name' => 'content', 'label' => 'Content'));

        $html = '';
        foreach ($default_fields as $field) {
            $html .= '<input name="holmes_searchable_fields[' . $args[0] . '][' . $field['name'] . ']" id="holmes_searchable_fields_' . $args[0] . '_' . $field['name'] . '" type="checkbox" value="1" ' . (isset($options) && isset($options[$args[0]]) ? checked(1, $options[$args[0]][$field['name']] , false) : '') . ' />';
            $html .= '<label for="holmes_searchable_fields_' . $args[0] . '_' . $field['name'] . '">' . $field['label'] . '</label>'; 
            $html .= '<input type="text" /><br/>'; 
        }

        echo $html;
    }


    public function settings_page() {
        ?>

        <div class="wrap">
            <h2>Holmes Settings</h2>

            <form method="post" action="options.php"> 
                <?php settings_fields('holmes_settings_searchable_post_types_section'); ?>
                <?php settings_fields('holmes_settings_searchable_fields_section'); ?>
                <?php do_settings_sections('holmes_settings'); ?>
                <?php submit_button(); ?>
            </form>

            <?php
            // if (get_option('twitterpated_consumer_key') && get_option('twitterpated_consumer_secret')) {
            //     $handler = new WPTwitterHandler();
            //     $handler->authorize();
            // }
            ?>

        </div>

        <?php
    }

    

    


    public function on_activate() {

    }

}

register_activation_hook(__FILE__, array('Holmes', 'on_activate'));

new Holmes;
