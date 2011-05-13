<?php 
/*
Copyright (C)2011

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

if (!defined('ABSPATH')) exit;

// don't allow the plugin to be loaded twice - hedges against double installations
if (class_exists(/*@PLUGIN_LITE_CLASS@*/ 'PluginName')) return;

/**
 * This PHP class is a namespace for the free version of your plugin. Bear in
 * mind that what you program here (and/or include here) is not only the 
 * the free plugin itself, but is also the basis for the pro version - the core
 * functionality, if you will.
 */
class /*@PLUGIN_LITE_CLASS@*/ PluginName {
  
  // holds the singleton instance of your plugin's core
  static $instance;
  // holds a reference to the pro version of the plugin
  static $pro;
  // the path to this plugin
  static $dir_path;
  
  /**
   * Get the singleton instance of this plugin's core, creating it if it does
   * not already exist.
   */
  static function load() {
    if (!self::$instance) {
      self::$instance = new /*@PLUGIN_LITE_CLASS*/ PluginName();
      
      #
      # Establish the run-time path for this plugin.
      #
      $dir_path = explode(DIRECTORY_SEPARATOR, __FILE__);
      array_pop($dir_path);
      self::$dir_path = implode(DIRECTORY_SEPARATOR, $dir_path);
    }
    return self::$instance;
  }
  
  /**
   * Create a new instance of this plugin's core. There should only ever
   * be one instance of a plugin, so we make the constructor private, and
   * instead ask all other parts of WordPress to call ::load().
   */
  private function __construct() {
    
    #
    # All plugins tend to need these basic actions.
    #
    add_action('init', array($this, 'init'), 11, 1);
    add_action("{self::$dir_path}/lite.php_activate", array($this, 'activate'));
    add_action("{self::$dir_path}/lite.php_deactivate", array($this, 'deactivate'));
    
    # 
    # Add actions and filters here that should be called before the "init" action
    # Note that self::$pro will be null until the "init" action is called
    #
    // add_action($action_name, array($this, $action_name), $priority = 10, $num_args_supported = 1);
    // add_filter($filter_name, array($this, $filter_name), $priority = 10, $num_args_supported = 1);
  }
  
  function init() {
    # 
    # Add your own actions and filters here
    #
    // add_action($action_name, array($this, $action_name), $priority = 10, $num_args_supported = 1);
    // add_filter($filter_name, array($this, $filter_name), $priority = 10, $num_args_supported = 1);
    
    #
    # self::$pro will be defined here and will be a reference to your pro component
    # iff the pro plugin is installed and activated
    #
  }
  
  function activate() {
    global $wpdb;
    
    # 
    # Upgrade database tables here, and create any default data.
    #
    
  }
  
  function deactivate() {
    global $wpdb;
    
    # 
    # Cleanup stuff that shouldn't be left behind.
    #
    
  }
  
  
}

/*@PLUGIN_LITE_CLASS@*/ PluginName::load();