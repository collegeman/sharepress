<?php 
if (!defined('ABSPATH')) exit;

/**
 * This PHP class is a namespace for the free version of your plugin. Bear in
 * mind that what you program here (and/or include here) is not only the basis
 * for the free application, but is also the basis for the pro version. 
 */
class PluginNameCore {
  
  // holds the singleton instance of your plugin's core
  static $instance;
  // holds a reference to the pro version of the plugin
  static $pro;
  
  /**
   * Get the singleton instance of this plugin's core, creating it if it does
   * not already exist.
   */
  static function load() {
    if (!self::$instance) {
      self::$instance = new PluginNameCore();
    }
    return self::$instance;
  }
  
  /**
   * Begin here to hook your plugin into WordPress's action and filter APIs.
   */
  private function __construct() {
    
    #
    # Establish the run-time path for this plugin.
    #
    $dir_path = explode('/', __FILE__);
    array_pop($dir_path);
    $dir_path = implode('/', $dir_path);
    
    # 
    # The best practice is to use this object's namespace for the plugin's
    # functions. The second parameter to add_action below is a callback that
    # references the function "init" on this instance of your plugin core.
    #
    add_action('init', array($this, 'init'), 11, 1);
    
    
    #
    # Need to do something when the plugin was activated - setup default data
    # or create some database tables? Do that in the activate hook.
    #
    // add_action('activate_sharepress/sharepress-lite.php', array($this, 'activate'));
    
  }
  
  function init() {
    PluginNameCore::$pro = $this;
    
  }
  
  function activate() {
    
    
  }
  
  
}

PluginNameCore::load();