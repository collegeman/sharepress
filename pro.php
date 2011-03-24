<?php
/*
Plugin Name: Plugin Name Pro
Description: The pro version of your plugin, to be licensed on getwpapps.com, or directly from you!
Author: Plugin Author
Author URI: 
Version: 0.1
*/

/*
Plugin Name Pro
Copyright (C)2011  Plugin Author

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

define('PLUGIN_NAME_PRO_VERSION', '@VERSION@');

// load the core dependency
require('core.php');
// load the plugin updater client
require('plugin-update-client.php');

if (!defined('ABSPATH')) exit;

/**
 * This PHP class is a namespace for the free version of your plugin. Bear in
 * mind that what you program here (and/or include here) is not only the basis
 * for the free application, but is also the basis for the pro version. 
 */
class PluginNameCorePro {
  
  // holds the singleton instance of your plugin's core
  static $instance;
  
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
   * Create a new instance of this plugin's core. This should only be called
   * once for plugin, which is why we use the singleton approach for accessing
   * its functions.
   *
   * You should begin here to hook your plugin into WordPress's action and
   * filter APIs.
   */
  private function __construct() {
    
    # 
    # The best practice is to use this object's namespace for the plugin's
    # functions. The second parameter to add_action below is a callback that
    # references the function "init" on this instance of your plugin core.
    #
    add_action('init', array($this, 'init'), 10, 1);
    
    #
    # Setup the plugin publisher client.
    PluginPublisherClient::init(array(
      'name' => 'Plugin Name Pro',
      'plugin' => 'pro',
      'version' => PLUGIN_NAME_PRO_VERSION,
      'file' => 'wpapp/pro.php',
      'server' => 'http://getwpapps.com'
    ));
  }
  
  function init() {
    
  }
  
  
}

PluginNameCore::load();