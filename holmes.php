<?php
/**
 * Plugin Name: Holmes
 * Author: Norex
 * Author URI: http://ryannielson.ca/
 * Version: 1.0.0
 * Description: Improved Wordpress search.
 */


require_once('core/admin.php');

class Holmes {

    public function __construct() {
       new HolmesAdmin;
    }

}

//register_activation_hook(__FILE__, array('Holmes', 'on_activate'));

new Holmes;
