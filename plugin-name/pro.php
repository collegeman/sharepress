<?php
/*
Plugin Name: Plugin Name Pro
Description: The pro version of your plugin
Author: Plugin Author
Author URI: 
Version: @VERSION@
*/

/*
Plugin Name
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
class PluginNamePro {
  
  // holds the singleton instance of your plugin's core
  static $instance;
  
  /**
   * Get the singleton instance of this plugin's core, creating it if it does
   * not already exist.
   */
  static function load() {
    if (!self::$instance) {
      self::$instance = new PluginNamePro();
    }
    return self::$instance;
  }
  
  private function __construct() {
    
    add_action('init', array($this, 'init'), 10, 1);
    
    #
    # Setup the update client to be able to receive updates from getwpapps.com
    #
    PluginUpdateClient::init(array(
      'name' => 'Plugin Name Pro',
      'plugin' => 'pro',
      'version' => PLUGIN_NAME_PRO_VERSION,
      'file' => 'wpapp/pro.php',
      'server' => 'http://getwpapps.com'
    ));
  }
  
  function init() {
    // attach a reference to the pro version onto the lite version
    PluginName::$pro = $this;
    
    
    
  }
  
  
}

PluginNameCore::load();