<?php 
/*
Plugin Name: SharePress
Plugin URI: http://aaroncollegeman.com/sharepress
Description: SharePress publishes your content to the social Web (Facebook, Twitter, and soon Google+), and lets you schedule reposts, too!
Author: Fat Panda, LLC
Author URI: http://fatpandadev.com
Version: 3.0
License: GPL2
*/

/*
Copyright (C)2011 Fat Panda, LLC

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

require(dirname(__FILE__).'/lib/oauth.php');
require(dirname(__FILE__).'/lib/base_sharepress.php');
require(dirname(__FILE__).'/lib/facebook/facebook.php');
require(dirname(__FILE__).'/lib/twitter/twitter.php');
require(dirname(__FILE__).'/lib/google/google.php');

// override this in functions.php
@define('SHAREPRESS_DEBUG', false);

class SharePress {

  const OPTION_MIGRATED = "option-migrated";

  private $base;
  private $facebook;
  private $twitter;
  private $google;
  public $pro;
  
  // holds the singleton instance of your plugin's core
  private static $instance;
  static function load() {
    $class = __CLASS__;
    return (self::$instance ? self::$instance : ( self::$instance = new $class() ));
  }

  private function __construct() {
    $this->core = new Core_SharePress();

    if (self::isMigrated()) {
      $this->facebook = new Facebook_SharePress();
      $this->twitter = new Twitter_SharePress();
      $this->google = new Google_SharePress();
    } else {
      add_filter('plugin_action_links_sharepress/sharepress.php', array($this, 'plugin_action_links'), 10, 4);  
    } 
  }

  static function isMigrated() {
    return get_option(self::OPTION_MIGRATED);
  }

  function plugin_action_links($actions, $plugin_file, $plugin_data, $context) {
    $actions['settings'] = '<a href="options-general.php?page=sharepress">Settings</a>';
    if (!$this->$pro) {
      $actions['go-pro'] = '<a href="http://aaroncollegeman.com/sharepress?utm_source=sharepress&utm_medium=in-app-promo&utm_campaign=buy-pro-version">Buy Pro Version</a>';
    }
    return $actions;
  }

  static function is_mu() {
    return defined('MULTISITE') && MULTISITE && defined('SHAREPRESS_MU') && SHAREPRESS_MU;
  }

  static function clear_cache() {
    global $wpdb;
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'sharepress_cache-%'");
  }

  static function unlocked() {
    $license_key = self::$instance->core->setting('license_key');
    if (defined('SHAREPRESS_LICENSE_KEY') && SHAREPRESS_LICENSE_KEY) {
      $license_key = SHAREPRESS_LICENSE_KEY;
    }
    return strlen($license_key) == 32;
  }
  
  static function err($message) {
    self::log($message, 'ERROR');
  }
  
  static function log($message, $level = 'INFO') {
    if (SHAREPRESS_DEBUG) {
      global $thread_id;
      if (is_null($thread_id)) {
        $thread_id = substr(md5(uniqid()), 0, 6);
      }
      $dir = dirname(__FILE__);
      $filename = $dir.'/sharepress-'.gmdate('Ymd').'.log';
      $message = sprintf("%s %s %-5s %s\n", $thread_id, get_date_from_gmt(gmdate('Y-m-d H:i:s'), 'H:i:s'), $level, $message);
      if (!@file_put_contents($filename, $message, FILE_APPEND)) {
        error_log("Failed to access SharePress log file [$filename] for writing: add write permissions to directory [$dir]?");
      }
    }
  }

  static function api($path, $method = 'GET', $params = array(), $cache_for = false) {
    return self::load()->facebook->api($path, $method, $params, $cache_for);  
  }

}

SharePress::load();

#
# Don't be a dick. I have a family.
# http://aaroncollegeman/sharepress/
#
if (Sharepress::unlocked()) require(dirname(__FILE__).'/lib/pro.php');