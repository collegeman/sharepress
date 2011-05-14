<?php
/*
Plugin Name: Sharepress Pro
Plugin URI: http://getwpapps.com/plugins/sharepress
Description: This is my plugin. There are others like it, but this one is mine.
Author: Aaron Collegeman
Author URI: http://aaroncollegeman.com
Version: 1.0.20110513051121
License: GPL2
*/

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

// load the core dependency
require('core.php');
// load the plugin updater client
require('update-client.php');

// support for setting individual images in options
require('postimage.php');

if (!defined('ABSPATH')) exit;

/**
 * This PHP class is a namespace for the pro version of your plugin. 
 */
class /*@PLUGIN_PRO_CLASS@*/ SharepressPro {
  
  const OPTION_LICENSE_KEY = 'sharepress-pro-license-key';
  
  // holds the singleton instance of your plugin's core
  static $instance;
  
  /**
   * Get the singleton instance of this plugin's core, creating it if it does
   * not already exist.
   */
  static function load() {
    if (!self::$instance) {
      self::$instance = new /*@PLUGIN_PRO_CLASS@*/ SharepressPro();
    }
    return self::$instance;
  }
  
  private function __construct() {
    
    add_action('init', array($this, 'init'), 10, 1);
    
    #
    # Discover this file's path
    #
    $parts = explode(DIRECTORY_SEPARATOR, __FILE__);
    $fn = array_pop($parts);
    $fd = array_pop($parts);
    $file = $fd != 'plugins' ? "{$fd}/{$fn}" : $fn;
    
    #
    # Setup the update client to be able to receive updates from getwpapps.com
    #
    PluginUpdateClient::init(array(
      'path' => __FILE__,
      'plugin' => /*@PLUGIN_PRO_SLUG@*/ 'sharepress', 
      'file' => $file
    ));
  }
  
  function init() {
    // attach a reference to the pro version onto the lite version
    /*@PLUGIN_LITE_CLASS@*/ Sharepress::$pro = $this;
    
    // enhancement #1: post thumbnails are used in messages posted to facebook
    add_theme_support('post-thumbnails');
    // enhancement #2: ability to publish to pages
    add_filter('sharepress_pages', array($this, 'pages'));
    add_action('sharepress_post', array($this, 'post'), 10, 2);
    // enhancement #3: configure the content of each post individually
    add_filter('sharepress_meta_box', array($this, 'meta_box'), 10, 3);
    add_action('wp_ajax_sharepress_get_excerpt', array($this, 'ajax_get_excerpt'));
  }
  
  function ajax_get_excerpt() {
    
  }
  
  function post($meta, $post) {
    if (SHAREPRESS_DEBUG) {
      Sharepress::log(sprintf('SharepressPro::post(%s, %s)', $meta['message'], is_object($post) ? $post->post_title : $post));
      Sharepress::log(sprintf('SharepressPro::post => count(SharepressPro::pages()) = %s', count(self::pages())));
      Sharepress::log(sprintf('SharperessPro::post => $meta["targets"] = %s', serialize($meta['targets'])));
    }
    
    // loop over authorized pages
    foreach(self::pages() as $page) {
      if (in_array($page['id'], $meta['targets'])) {        
        $result = Sharepress::api($page['id'].'/feed', 'POST', array(
          'access_token' => $page['access_token'],
          'name' => $meta['name'],
          'message' => $meta['message'],
          'description' => $meta['description'],
          'picture' => $meta['picture'],
          'link' => $meta['link']
        ));
        
        Sharepress::log(sprintf("posted to the page(%s): %s", $page['name'], serialize($result)));
        
        // store the ID for queuing 
        add_post_meta($post->ID, Sharepress::META_RESULT, $result);
      }
    }
  }
  
  function meta_box($meta_box, $post, $meta) {
    ob_start();
    require('pro-meta-box.php');
    return ob_get_clean();
  }
  
  static function sort_by_name($a, $b) {
    return strcmp($a['name'], $b['name']);
  }
  
  function pages($default = array()) {
    $result = Sharepress::api(Sharepress::me('id').'/accounts', 'GET', array(), '1 hour');
    if ($result) {
      $data = $result['data'];
      
      // we only care about pages...
      $pages = array();
      foreach($data as $d) {
        if (isset($d['name'])) {
          $pages[] = $d;
        }
      }
      
      // sort by page name, for sanity's sake
      usort($pages, array('SharepressPro', 'sort_by_name'));
      
      return $default + $pages;
    } else {
      throw new Exception("Failed to load pages from Facebook.");
    }
  }
  
}

/*@PLUGIN_PRO_CLASS@*/ SharepressPro::load();